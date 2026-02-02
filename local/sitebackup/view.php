<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

require_once('../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$context = context_system::instance();
require_capability('local/sitebackup:manage', $context);

// Handle actions.
$action = optional_param('action', '', PARAM_ALPHA);

// Run backup now — queue as ad-hoc task so it runs in the background via cron.
if ($action === 'backup' && confirm_sesskey()) {
    $skipdrive = optional_param('skipdrive', 0, PARAM_INT);

    // Create the log record immediately so we can redirect to progress page.
    $start = time();
    $timestamp = date('Y-m-d_H-i-s');
    $filename = 'sitebackup_' . $timestamp . '.zip';
    $logid = $DB->insert_record('local_sitebackup_logs', (object) [
        'filename'      => $filename,
        'filesize'      => 0,
        'status'        => 'queued',
        'progress_step' => 'Queued — waiting for cron to pick up...',
        'contents'      => '',
        'timecreated'   => $start,
    ]);

    // Queue the ad-hoc task.
    $task = new \local_sitebackup\task\run_backup();
    $task->set_custom_data((object) [
        'skipdrive' => (bool) $skipdrive,
        'logid'     => $logid,
    ]);
    \core\task\manager::queue_adhoc_task($task);

    // Trigger cron in the background so the task starts immediately.
    // This is fire-and-forget — if it fails, cron will pick it up on next scheduled run.
    $cronurl = $CFG->wwwroot . '/admin/cron.php';
    @file_get_contents($cronurl, false, stream_context_create([
        'http' => ['timeout' => 1, 'method' => 'GET'],
    ]));

    // Redirect to progress page immediately — no waiting.
    redirect(new moodle_url('/local/sitebackup/progress.php', ['id' => $logid]));
}

// Disconnect Google Drive.
if ($action === 'disconnect' && confirm_sesskey()) {
    \local_sitebackup\google_drive::disconnect();
    redirect(
        new moodle_url('/local/sitebackup/view.php'),
        get_string('drivedisconnected', 'local_sitebackup'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Download a backup file locally.
if ($action === 'download') {
    $downloadid = required_param('downloadid', PARAM_INT);
    $log = $DB->get_record('local_sitebackup_logs', ['id' => $downloadid]);
    if ($log) {
        $filepath = $CFG->dataroot . '/sitebackup/' . $log->filename;
        if (file_exists($filepath)) {
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . $log->filename . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: no-cache, no-store, must-revalidate');
            readfile($filepath);
            die();
        }
    }
    redirect(
        new moodle_url('/local/sitebackup/view.php'),
        get_string('filenotfound', 'local_sitebackup'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

// Delete a backup log + Drive file + local file.
if ($action === 'delete' && confirm_sesskey()) {
    $deleteid = required_param('deleteid', PARAM_INT);
    $log = $DB->get_record('local_sitebackup_logs', ['id' => $deleteid]);
    if ($log) {
        // Delete from Drive if connected.
        if (!empty($log->drive_file_id) && \local_sitebackup\google_drive::is_connected()) {
            try {
                \local_sitebackup\google_drive::delete_file($log->drive_file_id);
            } catch (\Exception $e) {
                // Silently fail — file may already be deleted.
            }
        }
        // Delete local file.
        $localfile = $CFG->dataroot . '/sitebackup/' . $log->filename;
        if (file_exists($localfile)) {
            unlink($localfile);
        }
        $DB->delete_records('local_sitebackup_logs', ['id' => $deleteid]);
        redirect(
            new moodle_url('/local/sitebackup/view.php'),
            get_string('deleted', 'local_sitebackup'),
            null,
            \core\output\notification::NOTIFY_SUCCESS
        );
    }
}

$PAGE->set_url(new moodle_url('/local/sitebackup/view.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_sitebackup'));
$PAGE->set_heading(get_string('pluginname', 'local_sitebackup'));
$PAGE->set_pagelayout('standard');
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';

// Highlight sidebar nav.
if ($node = $PAGE->navigation->find('local_sitebackup', navigation_node::TYPE_CUSTOM)) {
    $node->make_active();
}

// Gather template data.
$driveconnected = \local_sitebackup\google_drive::is_connected();
$driveconfigured = \local_sitebackup\google_drive::is_configured();

// Auto-fail stale backups that have been "in_progress" or "queued" for over 2 hours.
$staletime = time() - 7200;
$stalebackups = $DB->get_records_select('local_sitebackup_logs',
    "(status = 'in_progress' OR status = 'queued') AND timecreated < ?", [$staletime]);
foreach ($stalebackups as $stale) {
    $DB->update_record('local_sitebackup_logs', (object) [
        'id'            => $stale->id,
        'status'        => 'failed',
        'error_message' => get_string('backuptimedout', 'local_sitebackup'),
        'duration'      => time() - $stale->timecreated,
    ]);
}

// Last backup.
$lastbackup = $DB->get_record_sql(
    'SELECT * FROM {local_sitebackup_logs} ORDER BY timecreated DESC LIMIT 1'
);

$lastbackuptime = get_string('never', 'local_sitebackup');
$lastbackupstatus = '';
$lastbackuppill = '';
if ($lastbackup) {
    $lastbackuptime = userdate($lastbackup->timecreated, get_string('strftimedatetimeshort', 'langconfig'));
    $lastbackupstatus = get_string('status_' . $lastbackup->status, 'local_sitebackup');
    $lastbackuppill = $lastbackup->status === 'success' ? 'success' : ($lastbackup->status === 'failed' ? 'failed' : 'progress');
}

// Next scheduled time.
$task = \core\task\manager::get_scheduled_task('\\local_sitebackup\\task\\scheduled_backup');
$nextscheduled = get_string('na', 'local_sitebackup');
if ($task) {
    $nextrun = $task->get_next_run_time();
    if ($nextrun) {
        $nextscheduled = userdate($nextrun, get_string('strftimedatetimeshort', 'langconfig'));
    }
}

// Backup history.
$logs = $DB->get_records('local_sitebackup_logs', null, 'timecreated DESC', '*', 0, 50);
$logdata = [];
foreach ($logs as $log) {
    $contentsarr = json_decode($log->contents, true) ?: [];
    $contentslabels = [];
    foreach ($contentsarr as $c) {
        $key = 'backupcontents_' . $c;
        $contentslabels[] = get_string($key, 'local_sitebackup');
    }

    $statusclass = 'progress';
    if ($log->status === 'success') {
        $statusclass = 'success';
    } else if ($log->status === 'failed') {
        $statusclass = 'failed';
    } else if ($log->status === 'queued') {
        $statusclass = 'progress';
    }

    $errorshort = '';
    if (!empty($log->error_message)) {
        $errorshort = strlen($log->error_message) > 60
            ? substr($log->error_message, 0, 60) . '...'
            : $log->error_message;
    }

    // Check if local file exists for download.
    $localfile = $CFG->dataroot . '/sitebackup/' . $log->filename;
    $haslocal = file_exists($localfile);

    $logdata[] = [
        'time'            => userdate($log->timecreated, get_string('strftimedatetimeshort', 'langconfig')),
        'filename'        => $log->filename,
        'size_display'    => $log->filesize > 0 ? display_size($log->filesize) : '-',
        'contents_display' => implode(', ', $contentslabels),
        'status_display'  => get_string('status_' . $log->status, 'local_sitebackup'),
        'status_class'    => $statusclass,
        'duration_display' => $log->duration > 0 ? $log->duration . 's' : '-',
        'drive_link'      => $log->drive_link ?: '',
        'has_local_file'  => $haslocal,
        'download_url'    => $haslocal ? (new moodle_url('/local/sitebackup/view.php', [
            'action' => 'download', 'downloadid' => $log->id
        ]))->out(false) : '',
        'error_message'   => $log->error_message ?: '',
        'error_short'     => $errorshort,
        'can_delete'      => true,
        'delete_url'      => (new moodle_url('/local/sitebackup/view.php', [
            'action' => 'delete', 'deleteid' => $log->id, 'sesskey' => sesskey()
        ]))->out(false),
    ];
}

$templatecontext = [
    'last_backup_time'   => $lastbackuptime,
    'last_backup_status' => $lastbackupstatus,
    'last_backup_pill'   => $lastbackuppill,
    'drive_connected'    => $driveconnected,
    'drive_configured'   => $driveconfigured,
    'next_scheduled'     => $nextscheduled,
    'has_logs'           => !empty($logdata),
    'logs'               => $logdata,
    'settings_url'       => (new moodle_url('/admin/settings.php',
        ['section' => 'local_sitebackup']))->out(false),
    'diagnose_url'       => (new moodle_url('/local/sitebackup/diagnose.php'))->out(false),
    'connect_url'        => $driveconfigured ? \local_sitebackup\google_drive::get_auth_url() : '',
    'disconnect_url'     => (new moodle_url('/local/sitebackup/view.php',
        ['action' => 'disconnect', 'sesskey' => sesskey()]))->out(false),
    'backup_local_url'   => (new moodle_url('/local/sitebackup/view.php',
        ['action' => 'backup', 'skipdrive' => 1, 'sesskey' => sesskey()]))->out(false),
    'backup_drive_url'   => (new moodle_url('/local/sitebackup/view.php',
        ['action' => 'backup', 'sesskey' => sesskey()]))->out(false),
    'sesskey'            => sesskey(),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_sitebackup/view', $templatecontext);
echo $OUTPUT->footer();

// Inline JS for backup buttons with progress overlay.
echo '<script>
document.addEventListener("DOMContentLoaded", function() {
    var overlay = document.getElementById("sb-progress-overlay");
    var buttons = document.querySelectorAll(".sb-backup-trigger");
    buttons.forEach(function(btn) {
        btn.addEventListener("click", function() {
            var msg = btn.getAttribute("data-confirm");
            if (confirm(msg)) {
                if (overlay) overlay.classList.add("active");
                window.location.href = btn.getAttribute("data-url");
            }
        });
    });
});
</script>';
