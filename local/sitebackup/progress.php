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

/**
 * Backup progress page — polls the log record for live status updates.
 * Also serves as AJAX endpoint when ?ajax=1 is passed.
 *
 * @package   local_sitebackup
 * @copyright 2026 Epearl Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$context = context_system::instance();
require_capability('local/sitebackup:manage', $context);

$logid = required_param('id', PARAM_INT);
$ajax  = optional_param('ajax', 0, PARAM_INT);

$log = $DB->get_record('local_sitebackup_logs', ['id' => $logid]);
if (!$log) {
    if ($ajax) {
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Backup log not found']);
        die();
    }
    redirect(new moodle_url('/local/sitebackup/view.php'),
        'Backup log not found', null, \core\output\notification::NOTIFY_ERROR);
}

// AJAX endpoint — return JSON status.
if ($ajax) {
    header('Content-Type: application/json');
    $elapsed = time() - $log->timecreated;
    echo json_encode([
        'status'        => $log->status,
        'progress_step' => $log->progress_step ?? 'Working...',
        'elapsed'       => $elapsed,
        'elapsed_fmt'   => gmdate('H:i:s', $elapsed),
        'filesize'      => $log->filesize > 0 ? display_size($log->filesize) : '',
        'error_message' => $log->error_message ?? '',
        'filename'      => $log->filename,
    ]);
    die();
}

// Full page — progress UI.
$PAGE->set_url(new moodle_url('/local/sitebackup/progress.php', ['id' => $logid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pluginname', 'local_sitebackup') . ' — Backup in Progress');
$PAGE->set_heading(get_string('pluginname', 'local_sitebackup'));
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();
?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Libre+Baskerville:wght@400;700&display=swap" rel="stylesheet">

<style>
.sbp-shell { max-width:700px; margin:40px auto; font-family:system-ui,-apple-system,sans-serif; }
.sbp-card {
    background:#fff; border-radius:12px; overflow:hidden;
    box-shadow:0 4px 24px rgba(0,0,0,0.08); border:1px solid #e2e8f0;
}
.sbp-header {
    background:linear-gradient(135deg,#1a2238 0%,#2d3a5c 100%);
    padding:28px 32px; color:#fff; text-align:center;
}
.sbp-header h2 { font-family:'Libre Baskerville',serif; margin:0 0 4px; font-size:1.4rem; }
.sbp-header p { margin:0; opacity:0.7; font-size:0.9rem; }
.sbp-body { padding:32px; text-align:center; }
.sbp-spinner {
    width:60px; height:60px; border:4px solid #e2e8f0; border-top-color:#3a5ba0;
    border-radius:50%; animation:sbp-spin 0.8s linear infinite; margin:0 auto 24px;
}
@keyframes sbp-spin { to { transform:rotate(360deg); } }
.sbp-step {
    font-size:1.1rem; font-weight:600; color:#1a2238; margin-bottom:8px;
    min-height:1.6em;
}
.sbp-elapsed { font-size:0.9rem; color:#64748b; margin-bottom:24px; }
.sbp-bar-wrap {
    background:#e2e8f0; border-radius:8px; height:8px; overflow:hidden; margin-bottom:24px;
}
.sbp-bar {
    height:100%; background:linear-gradient(90deg,#3a5ba0,#6ea3c1);
    border-radius:8px; width:0%; transition:width 0.5s ease;
    animation:sbp-pulse 2s ease-in-out infinite;
}
@keyframes sbp-pulse { 0%,100%{opacity:1;} 50%{opacity:0.6;} }
.sbp-note { font-size:0.85rem; color:#94a3b8; }

/* Done states */
.sbp-done { display:none; }
.sbp-done.active { display:block; }
.sbp-running.hidden { display:none; }

.sbp-result-icon { font-size:48px; margin-bottom:16px; }
.sbp-result-icon.success { color:#16a34a; }
.sbp-result-icon.failed { color:#dc2626; }
.sbp-result-msg { font-size:1.1rem; font-weight:600; margin-bottom:8px; }
.sbp-result-detail { font-size:0.9rem; color:#64748b; margin-bottom:24px; }

.sbp-btn {
    display:inline-block; padding:10px 24px; border-radius:8px; text-decoration:none;
    font-weight:600; font-size:0.9rem; transition:all 0.2s;
}
.sbp-btn--primary { background:#1a2238; color:#fff; }
.sbp-btn--primary:hover { background:#2d3a5c; color:#fff; }
.sbp-btn--success { background:#16a34a; color:#fff; margin-right:8px; }
.sbp-btn--success:hover { background:#15803d; color:#fff; }
</style>

<div class="sbp-shell">
  <div class="sbp-card">
    <div class="sbp-header">
      <h2><i class="fa fa-cloud-upload"></i> Site Backup</h2>
      <p><?php echo s($log->filename); ?></p>
    </div>

    <div class="sbp-body">
      <!-- Running state -->
      <div id="sbp-running" class="sbp-running">
        <div class="sbp-spinner"></div>
        <div id="sbp-step" class="sbp-step">Starting backup...</div>
        <div id="sbp-elapsed" class="sbp-elapsed">Elapsed: 00:00:00</div>
        <div class="sbp-bar-wrap"><div id="sbp-bar" class="sbp-bar"></div></div>
        <div class="sbp-note">
          Backup is running in the background. You can close this page — it will continue.<br>
          Check the <a href="<?php echo (new moodle_url('/local/sitebackup/view.php'))->out(); ?>">backup dashboard</a> for results.
        </div>
      </div>

      <!-- Done state -->
      <div id="sbp-done" class="sbp-done">
        <div id="sbp-result-icon" class="sbp-result-icon"></div>
        <div id="sbp-result-msg" class="sbp-result-msg"></div>
        <div id="sbp-result-detail" class="sbp-result-detail"></div>
        <div id="sbp-result-actions"></div>
      </div>
    </div>
  </div>
</div>

<script>
(function() {
    var logId = <?php echo (int) $logid; ?>;
    var pollUrl = '<?php echo (new moodle_url('/local/sitebackup/progress.php', ['id' => $logid, 'ajax' => 1]))->out(false); ?>';
    var dashUrl = '<?php echo (new moodle_url('/local/sitebackup/view.php'))->out(false); ?>';
    var steps = [
        'Starting backup',
        'Dumping database',
        'Zipping themes',
        'Zipping plugins',
        'Copying config',
        'Zipping uploaded files',
        'Creating master ZIP',
        'Saving backup file',
        'Uploading to Google Drive',
        'Backup complete'
    ];
    var timer = null;

    function getStepIndex(stepText) {
        if (!stepText) return 0;
        var lower = stepText.toLowerCase();
        for (var i = 0; i < steps.length; i++) {
            if (lower.indexOf(steps[i].toLowerCase()) !== -1) return i;
        }
        if (lower.indexOf('fail') !== -1) return -1;
        return 0;
    }

    function poll() {
        fetch(pollUrl)
            .then(function(r) { return r.json(); })
            .then(function(data) {
                // Update step text
                document.getElementById('sbp-step').textContent = data.progress_step || 'Working...';
                document.getElementById('sbp-elapsed').textContent = 'Elapsed: ' + data.elapsed_fmt;

                // Update progress bar
                var idx = getStepIndex(data.progress_step);
                if (idx >= 0) {
                    var pct = Math.min(95, Math.round((idx / (steps.length - 1)) * 100));
                    document.getElementById('sbp-bar').style.width = pct + '%';
                }

                if (data.status === 'success') {
                    showDone(true, data);
                } else if (data.status === 'failed') {
                    showDone(false, data);
                } else {
                    timer = setTimeout(poll, 3000);
                }
            })
            .catch(function() {
                timer = setTimeout(poll, 5000);
            });
    }

    function showDone(success, data) {
        document.getElementById('sbp-running').classList.add('hidden');
        var done = document.getElementById('sbp-done');
        done.classList.add('active');

        var icon = document.getElementById('sbp-result-icon');
        var msg = document.getElementById('sbp-result-msg');
        var detail = document.getElementById('sbp-result-detail');
        var actions = document.getElementById('sbp-result-actions');

        if (success) {
            icon.className = 'sbp-result-icon success';
            icon.innerHTML = '<i class="fa fa-check-circle"></i>';
            msg.textContent = 'Backup completed successfully!';
            detail.textContent = 'Duration: ' + data.elapsed_fmt + (data.filesize ? ' — Size: ' + data.filesize : '');
            actions.innerHTML = '<a href="' + dashUrl + '" class="sbp-btn sbp-btn--primary"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>';
        } else {
            icon.className = 'sbp-result-icon failed';
            icon.innerHTML = '<i class="fa fa-times-circle"></i>';
            msg.textContent = 'Backup failed';
            detail.textContent = data.error_message || 'An unknown error occurred.';
            actions.innerHTML = '<a href="' + dashUrl + '" class="sbp-btn sbp-btn--primary"><i class="fa fa-arrow-left"></i> Back to Dashboard</a>';
        }
    }

    // Start polling immediately.
    poll();
})();
</script>

<?php
echo $OUTPUT->footer();
