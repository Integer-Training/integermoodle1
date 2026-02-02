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
 * Learner Dashboard — personalised overview for learners.
 *
 * Shows progression, upcoming due dates, recent messages,
 * KPI cards and monthly hours chart.
 *
 * @package   local_learnerdashboard
 * @copyright 2026 Epearl Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

global $DB, $CFG, $OUTPUT, $PAGE, $USER;

$context = context_system::instance();

$PAGE->set_url(new moodle_url('/local/learnerdashboard/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('mydashboard', 'local_learnerdashboard'));

$PAGE->requires->jquery();

// CDN includes — Bootstrap Icons + Highcharts.
echo '<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';

echo $OUTPUT->header();

// ===== CORE VARIABLES =====
$userid = (int) $USER->id;
$now    = time();
$seven_days = $now + (7 * 86400);

// ===== QUERY 1: ENROLLED COURSES =====
$courses = enrol_get_users_courses($userid);
$enrolled_courses_count = count($courses);
$allcourseids = [];
foreach ($courses as $course) {
    $allcourseids[] = $course->id;
}

// ===== QUERY 2: ASSIGNMENT STATS + PROGRESSION (single batch) =====
$total_passed    = 0;
$total_submitted = 0;
$total_all       = 0;
$course_progression = [];

if (!empty($allcourseids)) {
    list($cid_sql, $cid_params) = $DB->get_in_or_equal($allcourseids, SQL_PARAMS_NAMED, 'cid');
    $cid_params['userid']  = $userid;
    $cid_params['userid2'] = $userid;
    $cid_params['userid3'] = $userid;

    $assign_query = "SELECT CONCAT(a.id, '-', :userid2) AS rowkey,
                            a.course AS courseid,
                            c.fullname AS coursename,
                            a.id AS assignid,
                            a.name AS assignname,
                            CASE
                                WHEN gg.finalgrade IS NOT NULL
                                 AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(sc.scale, ',',
                                     CAST(gg.finalgrade AS UNSIGNED)), ',', -1)) = 'Pass'
                                THEN 'Pass'
                                WHEN gg.finalgrade IS NOT NULL AND gg.finalgrade > 0
                                THEN 'Refer'
                                WHEN sub.id IS NOT NULL AND sub.status = 'submitted'
                                THEN 'Pending Grading'
                                WHEN sub.id IS NOT NULL AND sub.status = 'draft'
                                THEN 'Submitted'
                                ELSE 'Not Submitted'
                            END AS grade_status
                     FROM {assign} a
                     JOIN {course} c ON c.id = a.course AND c.visible = 1
                     JOIN {modules} mdl_m ON mdl_m.name = 'assign'
                     JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course
                         AND cm.module = mdl_m.id AND cm.visible = 1
                     LEFT JOIN {assign_submission} sub ON sub.assignment = a.id
                         AND sub.userid = :userid AND sub.latest = 1
                     LEFT JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
                         AND gi.courseid = a.course
                     LEFT JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = :userid3
                     LEFT JOIN {scale} sc ON sc.id = gi.scaleid
                     WHERE a.course {$cid_sql}
                       AND a.name NOT LIKE '%IAG%'
                       AND a.name NOT LIKE '%ID Proof%'
                       AND a.name NOT LIKE '%Case Stud%'
                     ORDER BY a.course, a.name";

    $results = $DB->get_records_sql($assign_query, $cid_params);

    foreach ($results as $row) {
        $cid = (int) $row->courseid;
        $is_passed    = ($row->grade_status === 'Pass');
        $is_submitted = in_array($row->grade_status, ['Pass', 'Refer', 'Pending Grading']);

        if (!isset($course_progression[$cid])) {
            $course_progression[$cid] = [
                'name'        => $row->coursename,
                'total'       => 0,
                'passed'      => 0,
                'submitted'   => 0,
                'assignments' => [],
            ];
        }

        $cd = &$course_progression[$cid];
        $cd['total']++;
        if ($is_passed) {
            $cd['passed']++;
        }
        if ($is_submitted) {
            $cd['submitted']++;
        }

        $cd['assignments'][] = [
            'name'   => $row->assignname,
            'status' => $row->grade_status,
        ];

        $total_all++;
        if ($is_passed) {
            $total_passed++;
        }
        if ($is_submitted) {
            $total_submitted++;
        }
    }
}

$overall_progress = ($total_all > 0) ? round(($total_passed / $total_all) * 100) : 0;
$due_assignments  = $total_all - $total_submitted;

// ===== QUERY 3: UPCOMING DUE DATES (7 days) =====
$upcoming_due = [];

if (!empty($allcourseids)) {
    list($due_cid_sql, $due_cid_params) = $DB->get_in_or_equal($allcourseids, SQL_PARAMS_NAMED, 'dcid');
    $due_cid_params['userid']    = $userid;
    $due_cid_params['now']       = $now;
    $due_cid_params['sevendays'] = $seven_days;

    $due_sql = "SELECT a.id AS assignid, a.name AS assignname, c.fullname AS coursename,
                       a.duedate, cm.id AS cmid
                FROM {assign} a
                JOIN {course} c ON c.id = a.course AND c.visible = 1
                JOIN {modules} mdl_m ON mdl_m.name = 'assign'
                JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course
                    AND cm.module = mdl_m.id AND cm.visible = 1
                JOIN {user_enrolments} ue ON ue.userid = :userid
                JOIN {enrol} en ON en.id = ue.enrolid AND en.courseid = a.course
                LEFT JOIN {assign_submission} sub ON sub.assignment = a.id
                    AND sub.userid = ue.userid AND sub.latest = 1 AND sub.status = 'submitted'
                WHERE a.course {$due_cid_sql}
                  AND a.duedate > :now AND a.duedate <= :sevendays
                  AND a.name NOT LIKE '%IAG%'
                  AND a.name NOT LIKE '%ID Proof%'
                  AND a.name NOT LIKE '%Case Stud%'
                  AND sub.id IS NULL
                ORDER BY a.duedate ASC";

    $due_records = $DB->get_records_sql($due_sql, $due_cid_params);

    foreach ($due_records as $dr) {
        $days_remaining = max(0, ceil(($dr->duedate - $now) / 86400));
        $urgency = 'green';
        if ($days_remaining < 2) {
            $urgency = 'red';
        } else if ($days_remaining < 5) {
            $urgency = 'amber';
        }
        $upcoming_due[] = [
            'assignname'     => $dr->assignname,
            'coursename'     => $dr->coursename,
            'duedate'        => date('d M Y, H:i', $dr->duedate),
            'days_remaining' => $days_remaining,
            'is_red'         => ($urgency === 'red'),
            'is_amber'       => ($urgency === 'amber'),
            'is_green'       => ($urgency === 'green'),
            'assign_link'    => (new moodle_url('/mod/assign/view.php', ['id' => $dr->cmid]))->out(false),
        ];
    }
}
$has_upcoming_due = !empty($upcoming_due);

// ===== QUERY 4: RECENT MESSAGES (local_mail) =====
$recent_messages = [];
$unread_count    = 0;
$has_messages    = false;

$mail_tables_exist = $DB->get_manager()->table_exists('local_mail_messages');

if ($mail_tables_exist) {
    $msg_sql = "SELECT m.id, m.subject, m.courseid, m.time,
                       mu_sender.userid AS sender_userid,
                       CONCAT(sender.firstname, ' ', sender.lastname) AS sender_name,
                       c.fullname AS coursename,
                       mu.unread
                FROM {local_mail_message_users} mu
                JOIN {local_mail_messages} m ON m.id = mu.messageid AND m.draft = 0
                JOIN {local_mail_message_users} mu_sender ON mu_sender.messageid = m.id AND mu_sender.role = 1
                JOIN {user} sender ON sender.id = mu_sender.userid
                LEFT JOIN {course} c ON c.id = m.courseid
                WHERE mu.userid = :userid
                  AND mu.role IN (2, 3, 4)
                  AND mu.deleted = 0
                ORDER BY m.time DESC
                LIMIT 5";

    $msg_records = $DB->get_records_sql($msg_sql, ['userid' => $userid]);

    foreach ($msg_records as $mr) {
        $elapsed = $now - $mr->time;
        if ($elapsed < 60) {
            $time_ago = 'Just now';
        } else if ($elapsed < 3600) {
            $mins = floor($elapsed / 60);
            $time_ago = $mins . ' min' . ($mins > 1 ? 's' : '') . ' ago';
        } else if ($elapsed < 86400) {
            $hrs = floor($elapsed / 3600);
            $time_ago = $hrs . ' hour' . ($hrs > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($elapsed / 86400);
            $time_ago = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }

        $recent_messages[] = [
            'id'          => $mr->id,
            'subject'     => $mr->subject,
            'sender_name' => $mr->sender_name,
            'coursename'  => $mr->coursename ?: 'General',
            'time_ago'    => $time_ago,
            'is_unread'   => (bool) $mr->unread,
            'view_link'   => (new moodle_url('/local/mail/view.php', ['m' => $mr->id]))->out(false),
        ];
    }
    $has_messages = !empty($recent_messages);

    $unread_count = $DB->count_records_sql(
        "SELECT COUNT(*)
         FROM {local_mail_message_users} mu
         JOIN {local_mail_messages} m ON m.id = mu.messageid AND m.draft = 0
         WHERE mu.userid = :userid
           AND mu.role IN (2, 3, 4)
           AND mu.unread = 1
           AND mu.deleted = 0",
        ['userid' => $userid]
    );
}

// ===== QUERY 5: HOURS SPENT (Highcharts data) =====
$month_names = [
    1 => 'January', 2 => 'February', 3 => 'March', 4 => 'April',
    5 => 'May', 6 => 'June', 7 => 'July', 8 => 'August',
    9 => 'September', 10 => 'October', 11 => 'November', 12 => 'December',
];
$hours_chart_data = [];

// Fetch all course-related log timestamps for the current year, ordered chronologically.
// Then calculate time spent in PHP with a 30-minute idle cap (standard time-on-site method).
$hours_results_raw = [];
try {
    // Use timestamp range for index-friendly query (performance optimization).
    if (get_config('local_performance', 'enable_logstore_fix')) {
        $yearstart = mktime(0, 0, 0, 1, 1, (int) date('Y'));
        $yearend = mktime(0, 0, 0, 1, 1, (int) date('Y') + 1);
        $log_sql = "SELECT timecreated
                    FROM {logstore_standard_log}
                    WHERE userid = :userid
                      AND courseid > 1
                      AND timecreated >= :yearstart
                      AND timecreated < :yearend
                    ORDER BY timecreated ASC";
        $hours_results_raw = $DB->get_records_sql($log_sql, [
            'userid' => $userid,
            'yearstart' => $yearstart,
            'yearend' => $yearend,
        ]);
    } else {
        $log_sql = "SELECT timecreated
                    FROM {logstore_standard_log}
                    WHERE userid = :userid
                      AND courseid > 1
                      AND YEAR(FROM_UNIXTIME(timecreated)) = YEAR(CURDATE())
                    ORDER BY timecreated ASC";
        $hours_results_raw = $DB->get_records_sql($log_sql, ['userid' => $userid]);
    }
} catch (Exception $e) {
    // Silently handle — chart will show zeros.
}

// Aggregate per-month with 30-minute idle cap between consecutive events.
$month_seconds = array_fill(1, 12, 0);
$max_gap = 1800; // 30 minutes — gaps larger than this are treated as idle/away.
$prev_time = null;
$prev_month = null;

foreach ($hours_results_raw as $row) {
    $ts = (int) $row->timecreated;
    $m  = (int) date('n', $ts);

    if ($prev_time !== null && $m === $prev_month) {
        $gap = $ts - $prev_time;
        if ($gap > 0 && $gap <= $max_gap) {
            $month_seconds[$m] += $gap;
        }
    }

    $prev_time  = $ts;
    $prev_month = $m;
}

foreach ($month_names as $num => $name) {
    $val = ($month_seconds[$num] > 0) ? round($month_seconds[$num] / 3600, 1) : 0;
    $hours_chart_data[] = ['name' => $name, 'y' => $val];
}

// ===== BUILD PROGRESSION DATA =====
$progression_courses = [];
foreach ($course_progression as $cid => $cd) {
    $c_current = ($cd['total'] > 0) ? round(($cd['passed'] / $cd['total']) * 100) : 0;
    $sorted = $cd['assignments'];
    usort($sorted, function ($a, $b) {
        $na = preg_match('/(\d+)/', $a['name'], $ma) ? (int) $ma[1] : PHP_INT_MAX;
        $nb = preg_match('/(\d+)/', $b['name'], $mb) ? (int) $mb[1] : PHP_INT_MAX;
        if ($na !== $nb) {
            return $na - $nb;
        }
        return strnatcasecmp($a['name'], $b['name']);
    });
    $progression_courses[] = [
        'name'             => $cd['name'],
        'current'          => $c_current,
        'passed'           => $cd['passed'],
        'submitted'        => $cd['submitted'],
        'total'            => $cd['total'],
        'assignments_json' => json_encode($sorted),
    ];
}
$has_progression = !empty($progression_courses);

// ===== TEMPLATE CONTEXT =====
$templatecontext = [
    'name'                  => $USER->firstname,
    'fullname'              => fullname($USER),
    'enrolled_courses'      => $enrolled_courses_count,
    'due_assignments'       => max(0, $due_assignments),
    'completed_assignments' => $total_submitted,
    'overall_progress'      => $overall_progress,
    'unread_messages'       => $unread_count,
    'upcoming_due'          => $upcoming_due,
    'has_upcoming_due'      => $has_upcoming_due,
    'upcoming_count'        => count($upcoming_due),
    'recent_messages'       => $recent_messages,
    'has_messages'          => $has_messages,
    'progression_courses'   => $progression_courses,
    'has_progression'       => $has_progression,
    'hours_chart_json'      => json_encode($hours_chart_data),
    'mycourses_link'        => (new moodle_url('/my/courses.php'))->out(false),
    'mail_link'             => (new moodle_url('/local/mail/view.php'))->out(false),
    'contact_link'          => (new moodle_url('/local/learner/contact.php'))->out(false),
    'progression_link'      => (new moodle_url('/local/learnerprogression/index.php'))->out(false),
    'wwwroot'               => $CFG->wwwroot,
];

echo $OUTPUT->render_from_template('local_learnerdashboard/dashboard', $templatecontext);

echo $OUTPUT->footer();
