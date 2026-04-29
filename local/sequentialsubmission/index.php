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
 * Admin dashboard for Sequential Submission — list active locks, force-unlock,
 * view audit log.
 *
 * @package   local_sequentialsubmission
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sequentialsubmission:manage', $context);

use local_sequentialsubmission\lock_checker;

$PAGE->set_url(new moodle_url('/local/sequentialsubmission/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('managelocks', 'local_sequentialsubmission'));
$PAGE->set_heading(get_string('managelocks', 'local_sequentialsubmission'));
$PAGE->set_pagelayout('admin');
$PAGE->requires->jquery();

// Handle force-unlock POST.
$action = optional_param('action', '', PARAM_ALPHA);
if ($action === 'force_unlock' && confirm_sesskey()) {
    $learnerid = required_param('userid', PARAM_INT);
    $blockingcmid = optional_param('blocking_cmid', 0, PARAM_INT);
    $reason = trim(required_param('reason', PARAM_TEXT));

    if (empty($reason)) {
        redirect($PAGE->url, get_string('forceunlock_reason_required', 'local_sequentialsubmission'),
            null, \core\output\notification::NOTIFY_ERROR);
    }

    $ttl_hours = (int) get_config('local_sequentialsubmission', 'force_unlock_ttl_hours');
    if ($ttl_hours <= 0) {
        $ttl_hours = 24;
    }

    $expiresat = time() + ($ttl_hours * 3600);
    $rec = (object) [
        'userid' => $learnerid,
        'blocking_cmid' => $blockingcmid ?: null,
        'grantedby' => $USER->id,
        'expiresat' => $expiresat,
        'reason' => $reason,
        'active' => 1,
        'timecreated' => time(),
    ];
    $DB->insert_record('local_ss_bypass', $rec);

    lock_checker::log_event($learnerid, 'force_unlock', [
        'blocking_cmid' => $blockingcmid ?: null,
        'initiatedby' => $USER->id,
        'reason' => 'admin_unlock',
        'notes' => $reason,
    ]);

    // Notify the learner.
    $learner = $DB->get_record('user', ['id' => $learnerid], '*', IGNORE_MISSING);
    if ($learner) {
        $msg = new \core\message\message();
        $msg->component = 'local_sequentialsubmission';
        $msg->name = 'forceunlocked';
        $msg->userfrom = \core_user::get_noreply_user();
        $msg->userto = $learner;
        $a = (object) [
            'firstname' => $learner->firstname,
            'expires' => userdate($expiresat, '%A %e %B, %I:%M %p'),
        ];
        $msg->subject = get_string('msg_forceunlocked_subject', 'local_sequentialsubmission');
        $msg->fullmessage = get_string('msg_forceunlocked_body', 'local_sequentialsubmission', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml = nl2br(htmlspecialchars(
            get_string('msg_forceunlocked_body', 'local_sequentialsubmission', $a)
        ));
        $msg->smallmessage = $msg->subject;
        $msg->notification = 1;
        message_send($msg);
    }

    $successstring = get_string('forceunlock_success', 'local_sequentialsubmission', (object) [
        'learner' => $learner ? fullname($learner) : 'User ' . $learnerid,
        'expires' => userdate($expiresat, '%A %e %B, %I:%M %p'),
    ]);
    redirect($PAGE->url, $successstring, null, \core\output\notification::NOTIFY_SUCCESS);
}

// Also trigger the one-time grandfather task if requested.
if ($action === 'run_grandfather' && confirm_sesskey()) {
    $task = new \local_sequentialsubmission\task\notify_existing();
    \core\task\manager::queue_adhoc_task($task);
    redirect($PAGE->url, 'Grandfather notification task queued. Will run on next cron.',
        null, \core\output\notification::NOTIFY_SUCCESS);
}

echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
echo $OUTPUT->header();

$prefix = $CFG->prefix;
$now = time();
$sla_days = (int) get_config('local_sequentialsubmission', 'sla_days') ?: 7;
$enabled = lock_checker::is_enabled();

// ============================================================
// Gather all active locks (learners with pending submissions).
// ============================================================
$patterns = lock_checker::get_exempt_patterns();
$params = [];
$excl = '';
foreach ($patterns as $i => $p) {
    $k = "ep{$i}";
    $excl .= " AND a.name NOT LIKE :{$k}";
    $params[$k] = '%' . $p . '%';
}

$locks_sql = "SELECT sub.id AS subid, sub.userid, sub.timemodified AS submitted_at,
                     u.firstname, u.lastname,
                     cm.id AS cmid, a.name AS assignname, c.fullname AS coursename, c.id AS courseid,
                     gr.grade AS grade_value, gi.scaleid, sc.scale AS scale_values
              FROM {assign_submission} sub
              JOIN {user} u ON u.id = sub.userid AND u.suspended = 0 AND u.deleted = 0
              JOIN {assign} a ON a.id = sub.assignment
              JOIN {course} c ON c.id = a.course
              JOIN {modules} m ON m.name = 'assign'
              JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = m.id AND cm.visible = 1
              LEFT JOIN {assign_grades} gr ON gr.assignment = a.id AND gr.userid = sub.userid
                  AND gr.attemptnumber = sub.attemptnumber
              LEFT JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
              LEFT JOIN {scale} sc ON sc.id = gi.scaleid
              WHERE sub.latest = 1
                AND sub.status = 'submitted'
                AND (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0 OR gr.grade = 1)
                {$excl}
              ORDER BY sub.timemodified ASC";

$lockrows = $DB->get_records_sql($locks_sql, $params);

// Filter out Pass rows (derive in PHP since scale logic is complex).
$active_locks = [];
$stuck_count = 0;
$sla_cutoff = $now - ($sla_days * 86400);
foreach ($lockrows as $r) {
    $state = lock_checker::derive_grade_state($r->grade_value, $r->scaleid, $r->scale_values);
    if ($state === 'pass') {
        continue;
    }
    $days = max(0, (int) floor(($now - $r->submitted_at) / 86400));
    $is_stuck = ($r->submitted_at < $sla_cutoff);
    if ($is_stuck) {
        $stuck_count++;
    }

    // Assigned tutor via group membership.
    $tutor = $DB->get_record_sql(
        "SELECT DISTINCT u.firstname, u.lastname
         FROM {groups_members} gm_l
         JOIN {groups_members} gm_t ON gm_t.groupid = gm_l.groupid AND gm_t.userid <> gm_l.userid
         JOIN {user} u ON u.id = gm_t.userid
         JOIN {role_assignments} ra ON ra.userid = u.id
         JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
         JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'teacher'
         WHERE gm_l.userid = :userid LIMIT 1",
        ['userid' => $r->userid]
    );
    $tutorname = $tutor ? $tutor->firstname . ' ' . $tutor->lastname : get_string('unassigned_tutor', 'local_sequentialsubmission');

    $active_locks[] = (object) [
        'userid' => (int) $r->userid,
        'fullname' => $r->firstname . ' ' . $r->lastname,
        'coursename' => $r->coursename,
        'courseid' => (int) $r->courseid,
        'assignname' => $r->assignname,
        'cmid' => (int) $r->cmid,
        'days' => $days,
        'is_stuck' => $is_stuck,
        'tutorname' => $tutorname,
        'status_label' => $state === 'refer' ? 'Referred' : 'Awaiting Marking',
        'status_class' => $state === 'refer' ? 'ss-pill-refer' : 'ss-pill-await',
    ];
}

// Counts.
$active_count = count($active_locks);
$recent_blocks = $DB->count_records_select('local_ss_log',
    "action = 'block' AND timecreated > :since", ['since' => $now - (30 * 86400)]);
$recent_bypasses = $DB->count_records_select('local_ss_bypass',
    "timecreated > :since", ['since' => $now - (30 * 86400)]);

// Audit log — recent 50.
$audit_rows = $DB->get_records_sql(
    "SELECT l.*, u.firstname, u.lastname, iu.firstname AS initfirst, iu.lastname AS initlast,
            cm.id AS blocked_cmid_valid, a.name AS blocked_name
     FROM {local_ss_log} l
     LEFT JOIN {user} u ON u.id = l.userid
     LEFT JOIN {user} iu ON iu.id = l.initiatedby
     LEFT JOIN {course_modules} cm ON cm.id = l.blocked_cmid
     LEFT JOIN {assign} a ON a.id = cm.instance
     ORDER BY l.timecreated DESC
     LIMIT 50",
    []
);

$audit_list = [];
foreach ($audit_rows as $a) {
    $audit_list[] = [
        'time' => userdate($a->timecreated, '%d %b %Y, %H:%M'),
        'learner' => $a->firstname ? $a->firstname . ' ' . $a->lastname : '—',
        'action' => ucwords(str_replace('_', ' ', $a->action)),
        'action_raw' => $a->action,
        'details' => $a->notes ?: ($a->reason ?: '—'),
        'initiatedby' => $a->initfirst ? $a->initfirst . ' ' . $a->initlast : '',
    ];
}

$sesskey = sesskey();

// ============================================================
// Render.
// ============================================================
?>
<style>
.ss-shell { max-width: 100%; margin: 0 auto; padding: 16px 24px; font-family: 'Inter', system-ui, sans-serif; color: #1a2238; }
.ss-header { background: linear-gradient(135deg, #1a2238 0%, #3a5ba0 100%); color: #fff; border-radius: 12px; padding: 28px 32px; margin-bottom: 24px; position: relative; overflow: hidden; }
.ss-header::before { content: ''; position: absolute; top: -50%; right: -10%; width: 400px; height: 400px; background: radial-gradient(circle, rgba(247,200,115,0.15), transparent 70%); pointer-events: none; }
.ss-header h1 { font-family: 'Libre Baskerville', Georgia, serif; font-size: 30px; margin: 0 0 8px 0; color: #fff; }
.ss-header p { margin: 0; color: rgba(255,255,255,0.85); font-size: 14px; }
.ss-status-pill { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; margin-left: 12px; }
.ss-status-on { background: #8AD4BA; color: #0a4b3a; }
.ss-status-off { background: #ff7675; color: #4a0e0e; }
.ss-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px; margin-bottom: 24px; }
.ss-card { background: #fff; border-left: 4px solid #3a5ba0; border-radius: 8px; padding: 18px 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.ss-card-label { font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; margin-bottom: 6px; }
.ss-card-value { font-size: 32px; font-weight: 700; color: #1a2238; font-family: 'Libre Baskerville', Georgia, serif; }
.ss-card.ss-warn { border-left-color: #e17055; }
.ss-card.ss-info { border-left-color: #6ea3c1; }
.ss-panel { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 24px; box-shadow: 0 2px 8px rgba(0,0,0,0.04); }
.ss-panel h2 { font-family: 'Libre Baskerville', Georgia, serif; font-size: 20px; margin: 0 0 16px 0; color: #1a2238; }
.ss-table { width: 100%; border-collapse: collapse; }
.ss-table th { background: #f8fafc; font-size: 12px; text-transform: uppercase; letter-spacing: 0.05em; color: #6b7280; text-align: left; padding: 12px; border-bottom: 2px solid #e5e7eb; }
.ss-table td { padding: 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; vertical-align: middle; }
.ss-table tr:hover td { background: #f8fafc; }
.ss-pill { display: inline-block; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.03em; }
.ss-pill-await { background: #fef3c7; color: #92400e; }
.ss-pill-refer { background: #fee2e2; color: #991b1b; }
.ss-pill-stuck { background: #ff7675; color: #fff; margin-left: 6px; }
.ss-btn { display: inline-block; padding: 6px 14px; border-radius: 6px; font-size: 13px; font-weight: 500; border: none; cursor: pointer; text-decoration: none; }
.ss-btn-unlock { background: #3a5ba0; color: #fff; }
.ss-btn-unlock:hover { background: #2d477f; color: #fff; }
.ss-btn-secondary { background: #f8fafc; color: #3a5ba0; border: 1px solid #cbd5e1; }
.ss-btn-secondary:hover { background: #e2e8f0; }
.ss-modal { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.55); z-index: 9999; align-items: center; justify-content: center; }
.ss-modal.open { display: flex; }
.ss-modal-inner { background: #fff; border-radius: 12px; max-width: 460px; width: 90%; padding: 28px; box-shadow: 0 20px 60px rgba(0,0,0,0.3); }
.ss-modal-inner h3 { font-family: 'Libre Baskerville', serif; margin: 0 0 10px 0; }
.ss-modal-inner p { font-size: 14px; color: #475569; margin: 0 0 16px 0; }
.ss-modal-inner label { font-size: 13px; font-weight: 500; color: #334155; display: block; margin-bottom: 6px; }
.ss-modal-inner textarea { width: 100%; border: 1px solid #cbd5e1; border-radius: 6px; padding: 10px; font-family: inherit; font-size: 14px; min-height: 80px; box-sizing: border-box; }
.ss-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 16px; }
.ss-empty { text-align: center; padding: 48px; color: #6b7280; font-size: 14px; }
.ss-empty .bi { font-size: 42px; display: block; margin-bottom: 12px; color: #94a3b8; }
.ss-toolbar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; gap: 12px; flex-wrap: wrap; }
.ss-search { padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 14px; min-width: 240px; }
</style>

<div class="ss-shell">
    <div class="ss-header">
        <h1>
            <i class="bi bi-lock-fill"></i>
            <?php echo s(get_string('managelocks', 'local_sequentialsubmission')); ?>
            <?php if ($enabled): ?>
                <span class="ss-status-pill ss-status-on">Enabled</span>
            <?php else: ?>
                <span class="ss-status-pill ss-status-off">Disabled (kill switch OFF)</span>
            <?php endif; ?>
        </h1>
        <p>One-submission-at-a-time rule enforcement, audit log, and force-unlock controls.</p>
    </div>

    <div class="ss-cards">
        <div class="ss-card">
            <div class="ss-card-label">Active Locks</div>
            <div class="ss-card-value"><?php echo $active_count; ?></div>
        </div>
        <div class="ss-card ss-warn">
            <div class="ss-card-label">Stuck &gt; <?php echo $sla_days; ?> days</div>
            <div class="ss-card-value"><?php echo $stuck_count; ?></div>
        </div>
        <div class="ss-card ss-info">
            <div class="ss-card-label">Blocks (30d)</div>
            <div class="ss-card-value"><?php echo (int) $recent_blocks; ?></div>
        </div>
        <div class="ss-card ss-info">
            <div class="ss-card-label">Force-unlocks (30d)</div>
            <div class="ss-card-value"><?php echo (int) $recent_bypasses; ?></div>
        </div>
    </div>

    <div class="ss-panel">
        <div class="ss-toolbar">
            <h2><?php echo s(get_string('heading_active_locks', 'local_sequentialsubmission')); ?></h2>
            <div>
                <input type="text" id="ssSearch" class="ss-search" placeholder="Search learner / course / assignment…">
                <a class="ss-btn ss-btn-secondary" href="<?php echo (new moodle_url('/local/sequentialsubmission/order_check.php'))->out(false); ?>">
                    <i class="bi bi-list-ol"></i> <?php echo s(get_string('orderpreview', 'local_sequentialsubmission')); ?>
                </a>
                <a class="ss-btn ss-btn-secondary" href="<?php echo (new moodle_url('/admin/settings.php', ['section' => 'local_sequentialsubmission']))->out(false); ?>">
                    <i class="bi bi-gear"></i> Settings
                </a>
            </div>
        </div>

        <?php if ($active_count === 0): ?>
            <div class="ss-empty">
                <i class="bi bi-check-circle"></i>
                No active locks. Every learner is clear to submit their next unit.
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="ss-table" id="ssLockTable">
                    <thead><tr>
                        <th><?php echo s(get_string('col_learner', 'local_sequentialsubmission')); ?></th>
                        <th><?php echo s(get_string('col_tutor', 'local_sequentialsubmission')); ?></th>
                        <th><?php echo s(get_string('col_course', 'local_sequentialsubmission')); ?></th>
                        <th><?php echo s(get_string('col_pending_assignment', 'local_sequentialsubmission')); ?></th>
                        <th><?php echo s(get_string('col_days_waiting', 'local_sequentialsubmission')); ?></th>
                        <th><?php echo s(get_string('col_status', 'local_sequentialsubmission')); ?></th>
                        <th><?php echo s(get_string('col_action', 'local_sequentialsubmission')); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($active_locks as $l): ?>
                        <tr>
                            <td><?php echo s($l->fullname); ?></td>
                            <td><?php echo s($l->tutorname); ?></td>
                            <td><?php echo s($l->coursename); ?></td>
                            <td><?php echo s($l->assignname); ?></td>
                            <td>
                                <?php echo $l->days; ?>d
                                <?php if ($l->is_stuck): ?>
                                    <span class="ss-pill ss-pill-stuck">Stuck</span>
                                <?php endif; ?>
                            </td>
                            <td><span class="ss-pill <?php echo $l->status_class; ?>"><?php echo s($l->status_label); ?></span></td>
                            <td>
                                <button class="ss-btn ss-btn-unlock ss-unlock-trigger"
                                        data-userid="<?php echo $l->userid; ?>"
                                        data-cmid="<?php echo $l->cmid; ?>"
                                        data-fullname="<?php echo s($l->fullname); ?>">
                                    <?php echo s(get_string('forceunlock', 'local_sequentialsubmission')); ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <div class="ss-panel">
        <h2><?php echo s(get_string('heading_audit_log', 'local_sequentialsubmission')); ?></h2>
        <?php if (empty($audit_list)): ?>
            <div class="ss-empty">
                <i class="bi bi-journal-text"></i>
                No activity yet.
            </div>
        <?php else: ?>
            <div style="overflow-x: auto;">
                <table class="ss-table">
                    <thead><tr>
                        <th><?php echo s(get_string('col_time', 'local_sequentialsubmission')); ?></th>
                        <th><?php echo s(get_string('col_event', 'local_sequentialsubmission')); ?></th>
                        <th><?php echo s(get_string('col_learner', 'local_sequentialsubmission')); ?></th>
                        <th><?php echo s(get_string('col_details', 'local_sequentialsubmission')); ?></th>
                        <th>Initiator</th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($audit_list as $a): ?>
                        <tr>
                            <td><?php echo s($a['time']); ?></td>
                            <td><?php echo s($a['action']); ?></td>
                            <td><?php echo s($a['learner']); ?></td>
                            <td><?php echo s($a['details']); ?></td>
                            <td><?php echo s($a['initiatedby']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Force-unlock modal -->
    <div class="ss-modal" id="ssModal">
        <div class="ss-modal-inner">
            <h3 id="ssModalTitle">Force unlock learner</h3>
            <p><?php echo s(get_string('forceunlock_prompt', 'local_sequentialsubmission')); ?></p>
            <form method="POST" action="<?php echo $PAGE->url->out(false); ?>">
                <input type="hidden" name="sesskey" value="<?php echo $sesskey; ?>">
                <input type="hidden" name="action" value="force_unlock">
                <input type="hidden" name="userid" id="ssModalUserid" value="">
                <input type="hidden" name="blocking_cmid" id="ssModalCmid" value="">
                <label for="ssModalReason"><?php echo s(get_string('forceunlock_reason_label', 'local_sequentialsubmission')); ?></label>
                <textarea id="ssModalReason" name="reason" required></textarea>
                <div class="ss-modal-actions">
                    <button type="button" class="ss-btn ss-btn-secondary" id="ssModalCancel">Cancel</button>
                    <button type="submit" class="ss-btn ss-btn-unlock"><?php echo s(get_string('forceunlock_confirm', 'local_sequentialsubmission')); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var modal = document.getElementById('ssModal');
    var modalTitle = document.getElementById('ssModalTitle');
    var modalUserid = document.getElementById('ssModalUserid');
    var modalCmid = document.getElementById('ssModalCmid');
    var modalReason = document.getElementById('ssModalReason');
    var cancelBtn = document.getElementById('ssModalCancel');

    document.querySelectorAll('.ss-unlock-trigger').forEach(function(btn) {
        btn.addEventListener('click', function() {
            modalTitle.textContent = 'Force unlock ' + btn.getAttribute('data-fullname') + '?';
            modalUserid.value = btn.getAttribute('data-userid');
            modalCmid.value = btn.getAttribute('data-cmid');
            modalReason.value = '';
            modal.classList.add('open');
            setTimeout(function() { modalReason.focus(); }, 60);
        });
    });

    cancelBtn.addEventListener('click', function() { modal.classList.remove('open'); });
    modal.addEventListener('click', function(e) {
        if (e.target === modal) { modal.classList.remove('open'); }
    });

    // Simple client-side filter.
    var search = document.getElementById('ssSearch');
    if (search) {
        search.addEventListener('input', function() {
            var q = search.value.toLowerCase();
            document.querySelectorAll('#ssLockTable tbody tr').forEach(function(tr) {
                tr.style.display = tr.textContent.toLowerCase().indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }
})();
</script>

<?php
echo $OUTPUT->footer();
