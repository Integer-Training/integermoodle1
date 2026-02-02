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
 * Learner management view — main list with KPI cards, filters, and expandable details.
 *
 * @package   local_learner
 * @copyright 2026 Epearl Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $DB, $CFG, $PAGE, $OUTPUT, $USER;

require_login();
$context = context_system::instance();
require_capability('local/learner:view', $context);

$PAGE->set_url(new moodle_url('/local/learner/view.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('learnermanagement', 'local_learner'));
$PAGE->requires->jquery();
$PAGE->requires->js('/local/learner/js/jquery.dataTables.min.js', true);
$PAGE->requires->css('/local/learner/js/jquery.dataTables.min.css');
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

// ===================================================================
// QUERY 1: Main learner list — all non-staff users.
// ===================================================================

// Get role IDs for staff roles to exclude.
$staff_roles = $DB->get_records_list('role', 'shortname', ['teacher', 'editingteacher', 'manager']);
$staff_roleids = array_keys($staff_roles);

$exclude_sql = '';
$params = [];
if (!empty($staff_roleids)) {
    list($in_sql, $in_params) = $DB->get_in_or_equal($staff_roleids, SQL_PARAMS_NAMED, 'sr');
    $exclude_sql = "AND u.id NOT IN (
        SELECT DISTINCT ra.userid FROM {role_assignments} ra WHERE ra.roleid {$in_sql}
    )";
    $params = $in_params;
}

$learners = $DB->get_records_sql(
    "SELECT u.id, u.firstname, u.lastname, u.email, u.lastaccess, u.firstaccess, u.suspended
     FROM {user} u
     WHERE u.id > 2 AND u.deleted = 0 {$exclude_sql}
     ORDER BY u.lastname, u.firstname",
    $params
);

$learner_ids = array_keys($learners);

// ===================================================================
// QUERY 2: Per-learner course list (batch).
// ===================================================================

$learner_courses = [];  // userid => [['id' => courseid, 'name' => fullname], ...]
$all_course_names = []; // Unique course names for filter dropdown.

if (!empty($learner_ids)) {
    list($uid_sql, $uid_params) = $DB->get_in_or_equal($learner_ids, SQL_PARAMS_NAMED, 'uid');
    $course_rows = $DB->get_records_sql(
        "SELECT CONCAT(ue.userid, '-', c.id) AS rowkey, ue.userid, c.id AS courseid, c.fullname
         FROM {user_enrolments} ue
         JOIN {enrol} en ON en.id = ue.enrolid AND en.enrol = 'manual'
         JOIN {course} c ON c.id = en.courseid AND c.visible = 1
         WHERE ue.userid {$uid_sql}
         ORDER BY ue.userid, c.fullname",
        $uid_params
    );
    foreach ($course_rows as $cr) {
        if (!isset($learner_courses[$cr->userid])) {
            $learner_courses[$cr->userid] = [];
        }
        $learner_courses[$cr->userid][] = ['id' => (int) $cr->courseid, 'name' => $cr->fullname];
        $all_course_names[$cr->fullname] = true;
    }
}

// ===================================================================
// QUERY 3: Time spent (streaming logstore + PHP session calculation).
// ===================================================================

$time_spent = []; // userid => total_seconds

if (!empty($learner_ids)) {
    list($uid2_sql, $uid2_params) = $DB->get_in_or_equal($learner_ids, SQL_PARAMS_NAMED, 'lu');
    $yearstart = mktime(0, 0, 0, 1, 1, (int) date('Y'));
    $uid2_params['yearstart'] = $yearstart;

    $log_events = $DB->get_recordset_sql(
        "SELECT userid, timecreated
         FROM {logstore_standard_log}
         WHERE userid {$uid2_sql}
           AND timecreated >= :yearstart
         ORDER BY userid, timecreated ASC",
        $uid2_params
    );

    $prev_uid = null;
    $prev_time = null;
    $idle_cap = 1800; // 30-minute idle cap.

    foreach ($log_events as $ev) {
        $uid = (int) $ev->userid;
        $tc = (int) $ev->timecreated;

        if ($uid === $prev_uid && $prev_time !== null) {
            $gap = $tc - $prev_time;
            if ($gap > 0 && $gap <= $idle_cap) {
                if (!isset($time_spent[$uid])) {
                    $time_spent[$uid] = 0;
                }
                $time_spent[$uid] += $gap;
            }
        }
        $prev_uid = $uid;
        $prev_time = $tc;
    }
    $log_events->close();
}

// ===================================================================
// QUERY 4: Tutor lookup (group membership).
// ===================================================================

$tutor_map = []; // learner_userid => tutor_fullname

if (!empty($learner_ids)) {
    list($uid3_sql, $uid3_params) = $DB->get_in_or_equal($learner_ids, SQL_PARAMS_NAMED, 'gm');
    $tutor_rows = $DB->get_records_sql(
        "SELECT gm_s.userid AS learner_id,
                MIN(CONCAT(tu.firstname, ' ', tu.lastname)) AS tutor_name
         FROM {groups_members} gm_s
         JOIN {groups_members} gm_t ON gm_t.groupid = gm_s.groupid AND gm_t.userid != gm_s.userid
         JOIN {role_assignments} ra ON ra.userid = gm_t.userid
         JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'teacher'
         JOIN {user} tu ON tu.id = gm_t.userid
         WHERE gm_s.userid {$uid3_sql}
         GROUP BY gm_s.userid",
        $uid3_params
    );
    foreach ($tutor_rows as $tr) {
        $tutor_map[$tr->learner_id] = $tr->tutor_name;
    }
}

// ===================================================================
// Build template context.
// ===================================================================

$thirty_days_ago = time() - (30 * 86400);
$total_count = 0;
$active_count = 0;
$inactive_count = 0;
$created_count = 0;
$suspended_count = 0;
$learner_rows = [];

foreach ($learners as $u) {
    $total_count++;

    // Determine status.
    if ($u->suspended) {
        $status = 'Suspended';
        $suspended_count++;
    } else if (!$u->lastaccess && !$u->firstaccess) {
        $status = 'Created';
        $created_count++;
    } else if ($u->lastaccess && $u->lastaccess >= $thirty_days_ago) {
        $status = 'Active';
        $active_count++;
    } else {
        $status = 'Inactive';
        $inactive_count++;
    }

    // Format time spent.
    $secs = isset($time_spent[$u->id]) ? $time_spent[$u->id] : 0;
    if ($secs >= 3600) {
        $hrs = floor($secs / 3600);
        $mins = floor(($secs % 3600) / 60);
        $time_fmt = $hrs . 'h ' . $mins . 'm';
    } else if ($secs >= 60) {
        $time_fmt = floor($secs / 60) . 'm';
    } else {
        $time_fmt = '0m';
    }

    // Format last login.
    if ($u->lastaccess) {
        $last_login = date('d-m-Y H:i', $u->lastaccess);
        $last_login_ts = $u->lastaccess;
    } else {
        $last_login = 'Never';
        $last_login_ts = 0;
    }

    // Course data.
    $courses = isset($learner_courses[$u->id]) ? $learner_courses[$u->id] : [];
    $course_name_list = [];
    foreach ($courses as $c) {
        $course_name_list[] = $c['name'];
    }

    // Tutor.
    $tutor_name = isset($tutor_map[$u->id]) ? $tutor_map[$u->id] : 'Unassigned';

    // URLs.
    $edit_url = new moodle_url('/local/learner/edit.php', ['id' => $u->id]);
    $loginas_url = new moodle_url('/course/loginas.php', ['id' => 1, 'user' => $u->id, 'sesskey' => sesskey()]);
    $sendlogin_url = new moodle_url('/local/learner/email.php', ['id' => $u->id]);

    $learner_rows[] = [
        'id' => $u->id,
        'firstname' => $u->firstname,
        'lastname' => $u->lastname,
        'email' => $u->email,
        'tutor_name' => $tutor_name,
        'course_count' => count($courses),
        'time_spent' => $time_fmt,
        'time_spent_seconds' => $secs,
        'last_login' => $last_login,
        'last_login_ts' => $last_login_ts,
        'status' => $status,
        'is_suspended' => $u->suspended ? true : false,
        'edit_url' => $edit_url->out(false),
        'loginas_url' => $loginas_url->out(false),
        'sendlogin_url' => $sendlogin_url->out(false),
        'courses_json' => json_encode($courses),
        'course_names' => implode('||', $course_name_list),
    ];
}

// Course list for filter dropdown (sorted).
$course_list = [];
ksort($all_course_names);
foreach ($all_course_names as $cname => $unused) {
    $course_list[] = ['name' => $cname];
}

$templatecontext = [
    'wwwroot' => $CFG->wwwroot,
    'sesskey' => sesskey(),
    'total_count' => $total_count,
    'active_count' => $active_count,
    'inactive_count' => $inactive_count,
    'created_count' => $created_count,
    'suspended_count' => $suspended_count,
    'learners' => $learner_rows,
    'course_list' => $course_list,
    'new_learner_url' => (new moodle_url('/user/editadvanced.php', ['id' => -1]))->out(false),
    'inactive_url' => (new moodle_url('/local/learner/inactiveview.php'))->out(false),
];

// ===================================================================
// Render.
// ===================================================================

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_learner/view', $templatecontext);
echo $OUTPUT->footer();
