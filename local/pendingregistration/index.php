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
 * Pending Registration — main page showing learners awaiting registration.
 *
 * @package   local_pendingregistration
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

$context = context_system::instance();

// Access check: admins, managers, teachers, editing teachers.
$has_access = is_siteadmin() || $DB->record_exists_sql(
    "SELECT 1 FROM {role_assignments} ra
     JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('manager', 'teacher', 'editingteacher')
     WHERE ra.userid = ?", [$USER->id]
);
if (!$has_access) {
    throw new moodle_exception('nopermission', 'error', '', null, 'You do not have permission to view this page.');
}

$PAGE->set_url(new moodle_url('/local/pendingregistration/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pendingregistration', 'local_pendingregistration'));
$PAGE->set_heading(get_string('pendingregistration', 'local_pendingregistration'));
$PAGE->set_pagelayout('standard');

$PAGE->requires->jquery();

echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';

echo $OUTPUT->header();

// Fetch pending records: active, not yet registered.
$records = $DB->get_records_select(
    'local_pendingregistration',
    'registration_check = 0 AND is_active = 1',
    null,
    'learner_name ASC'
);

// Get last sync time.
$last_synced = $DB->get_field_sql(
    "SELECT MAX(last_synced) FROM {local_pendingregistration}"
);

// ===== Lookup Moodle user IDs by email =====
$email_to_uid = [];
$uid_list = [];
if (!empty($records)) {
    $emails = [];
    foreach ($records as $r) {
        $emails[] = strtolower(trim($r->learner_email));
    }
    list($email_sql, $email_params) = $DB->get_in_or_equal($emails, SQL_PARAMS_NAMED, 'em');
    $moodle_users = $DB->get_records_sql(
        "SELECT id, LOWER(email) AS email, timecreated FROM {user} WHERE LOWER(email) {$email_sql} AND deleted = 0",
        $email_params
    );
    $email_to_created = [];
    foreach ($moodle_users as $mu) {
        $email_to_uid[$mu->email] = (int) $mu->id;
        $uid_list[] = (int) $mu->id;
        $email_to_created[$mu->email] = (int) $mu->timecreated;
    }
}

// ===== Tutor lookup via group membership =====
$learner_tutors = []; // uid => tutor name
$tutor_names_set = [];
if (!empty($uid_list)) {
    list($lid_sql, $lid_params) = $DB->get_in_or_equal($uid_list, SQL_PARAMS_NAMED, 'lid');
    $tutor_rows = $DB->get_records_sql(
        "SELECT DISTINCT gm_s.userid AS learnerid,
                CONCAT(tu.firstname, ' ', tu.lastname) AS tutorname
         FROM {groups_members} gm_s
         JOIN {groups} g ON g.id = gm_s.groupid
         JOIN {groups_members} gm_t ON gm_t.groupid = g.id AND gm_t.userid <> gm_s.userid
         JOIN {role_assignments} ra ON ra.userid = gm_t.userid
         JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
         JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'teacher'
         JOIN {user} tu ON tu.id = gm_t.userid AND tu.suspended = 0 AND tu.deleted = 0
         WHERE gm_s.userid {$lid_sql}",
        $lid_params
    );
    foreach ($tutor_rows as $tr) {
        $learner_tutors[(int) $tr->learnerid] = $tr->tutorname;
        $tutor_names_set[$tr->tutorname] = true;
    }
    ksort($tutor_names_set);
}

// ===== Course enrollment lookup =====
$learner_courses = []; // uid => comma-separated course names
$course_names_set = [];
if (!empty($uid_list)) {
    list($uid_sql, $uid_params) = $DB->get_in_or_equal($uid_list, SQL_PARAMS_NAMED, 'uid');
    $course_rows = $DB->get_records_sql(
        "SELECT DISTINCT ue.userid, c.fullname AS coursename
         FROM {user_enrolments} ue
         JOIN {enrol} en ON en.id = ue.enrolid
         JOIN {course} c ON c.id = en.courseid AND c.visible = 1
         WHERE ue.userid {$uid_sql}
         ORDER BY c.fullname ASC",
        $uid_params
    );
    foreach ($course_rows as $cr) {
        $uid = (int) $cr->userid;
        if (!isset($learner_courses[$uid])) {
            $learner_courses[$uid] = [];
        }
        $learner_courses[$uid][] = $cr->coursename;
        $course_names_set[$cr->coursename] = true;
    }
    ksort($course_names_set);
}

// ===== Current % (passed workbooks / total workbooks) =====
$learner_progress = []; // uid => ['passed' => int, 'total' => int, 'pct' => int]
if (!empty($uid_list)) {
    list($pid_sql, $pid_params) = $DB->get_in_or_equal($uid_list, SQL_PARAMS_NAMED, 'pid');
    $progress_rows = $DB->get_records_sql(
        "SELECT CONCAT(u.id, '-', a.id) AS rowkey,
                u.id AS userid,
                a.name AS assignname,
                CASE
                    WHEN gg.finalgrade IS NOT NULL
                     AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(sc.scale, ',',
                         CAST(gg.finalgrade AS UNSIGNED)), ',', -1)) = 'Pass'
                    THEN 'Pass'
                    WHEN ag.grade IS NOT NULL AND ag.grade >= 0
                     AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(sc.scale, ',',
                         CAST(ag.grade AS UNSIGNED)), ',', -1)) = 'Pass'
                    THEN 'Pass'
                    ELSE 'Other'
                END AS grade_status
         FROM {user} u
         JOIN {user_enrolments} ue ON ue.userid = u.id
         JOIN {enrol} en ON en.id = ue.enrolid
         JOIN {course} c ON c.id = en.courseid AND c.visible = 1
         JOIN {assign} a ON a.course = c.id
         JOIN {modules} mdl_m ON mdl_m.name = 'assign'
         JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course
             AND cm.module = mdl_m.id AND cm.visible = 1
         LEFT JOIN {assign_submission} sub ON sub.assignment = a.id
             AND sub.userid = u.id AND sub.latest = 1
         LEFT JOIN {assign_grades} ag ON ag.assignment = a.id
             AND ag.userid = u.id AND ag.attemptnumber = COALESCE(sub.attemptnumber, 0)
         LEFT JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
             AND gi.courseid = a.course
         LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = u.id
         LEFT JOIN {scale} sc ON sc.id = gi.scaleid
         WHERE u.id {$pid_sql}
           AND a.name NOT LIKE '%IAG%'
           AND a.name NOT LIKE '%ID Proof%'
           AND a.name NOT LIKE '%Case Stud%'
         ORDER BY u.id",
        $pid_params
    );

    foreach ($progress_rows as $pr) {
        $uid = (int) $pr->userid;
        if (!isset($learner_progress[$uid])) {
            $learner_progress[$uid] = ['passed' => 0, 'total' => 0];
        }
        $learner_progress[$uid]['total']++;
        if ($pr->grade_status === 'Pass') {
            $learner_progress[$uid]['passed']++;
        }
    }
    // Calculate percentages.
    foreach ($learner_progress as $uid => &$lp) {
        $lp['pct'] = ($lp['total'] > 0) ? round(($lp['passed'] / $lp['total']) * 100) : 0;
    }
    unset($lp);
}

// ===== Build template data =====
$learners = [];
foreach ($records as $r) {
    $email = strtolower(trim($r->learner_email));
    $uid = $email_to_uid[$email] ?? 0;

    $tutor = $uid ? ($learner_tutors[$uid] ?? 'Unassigned') : 'No Moodle account';
    $courses = $uid ? ($learner_courses[$uid] ?? []) : [];
    $course_str = !empty($courses) ? implode(', ', $courses) : '-';
    $progress = $uid ? ($learner_progress[$uid] ?? null) : null;
    $pct = $progress ? $progress['pct'] : 0;
    $passed = $progress ? $progress['passed'] : 0;
    $total = $progress ? $progress['total'] : 0;

    $created_ts = $email_to_created[$email] ?? 0;
    $moodle_created = $created_ts > 0 ? userdate($created_ts, '%d %b %Y') : '-';

    $learners[] = [
        'id' => (int) $r->id,
        'learner_id' => $r->learner_id,
        'learner_name' => $r->learner_name,
        'learner_email' => $r->learner_email,
        'course' => $course_str,
        'tutor' => $tutor,
        'paid_to_date' => number_format((float) $r->paid_to_date, 2),
        'paid_raw' => (float) $r->paid_to_date,
        'subscription_status' => ucfirst($r->subscription_status ?: '-'),
        'current_pct' => $pct,
        'passed_total' => $total > 0 ? $passed . '/' . $total : '-',
        'moodle_created' => $moodle_created,
        'moodle_created_ts' => $created_ts,
        'registration_check' => (int) $r->registration_check,
        'tutor_check' => (int) $r->tutor_check,
        'admin_check' => (int) $r->admin_check,
        'notes' => $r->notes ?? '',
        'links' => $r->links ?? '',
        'sub_active' => in_array(strtolower($r->subscription_status ?? ''), ['active', 'trialing']),
    ];
}

// Build filter lists.
$tutor_list = [];
foreach ($tutor_names_set as $name => $v) {
    $tutor_list[] = ['name' => $name];
}
$course_list = [];
foreach ($course_names_set as $name => $v) {
    $course_list[] = ['name' => $name];
}

$templatecontext = [
    'learners' => $learners,
    'has_learners' => !empty($learners),
    'total_count' => count($learners),
    'sync_url' => (new moodle_url('/local/pendingregistration/sync.php', ['sesskey' => sesskey()]))->out(false),
    'ajax_url' => (new moodle_url('/local/pendingregistration/ajax.php'))->out(false),
    'sesskey' => sesskey(),
    'last_synced' => $last_synced ? userdate($last_synced, '%d %b %Y, %H:%M') : 'Never',
    'norecords' => get_string('norecords', 'local_pendingregistration'),
    'tutor_list' => $tutor_list,
    'course_list' => $course_list,
];

echo $OUTPUT->render_from_template('local_pendingregistration/pending_table', $templatecontext);

echo $OUTPUT->footer();
