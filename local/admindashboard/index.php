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
 * Admin Dashboard — comprehensive platform overview for administrators.
 *
 * @package   local_admindashboard
 * @copyright 2025 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

global $DB, $CFG, $OUTPUT, $PAGE;

$context = context_system::instance();
require_capability('local/admindashboard:view', $context);

$PAGE->set_url(new moodle_url('/local/admindashboard/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('admindashboard', 'local_admindashboard'));
$PAGE->set_heading(get_string('admindashboard', 'local_admindashboard'));
$PAGE->set_pagelayout('standard');
$PAGE->requires->jquery();

// Set active nav node for sidebar highlight.
if ($node = $PAGE->navigation->find('local_admindashboard', navigation_node::TYPE_CUSTOM)) {
    $node->make_active();
}

// CDN includes.
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
echo '<script src="https://code.highcharts.com/highcharts.js"></script>';
echo '<script src="https://code.highcharts.com/modules/exporting.js"></script>';
echo '<script src="https://code.highcharts.com/modules/export-data.js"></script>';
echo '<script src="https://code.highcharts.com/modules/accessibility.js"></script>';

// DataTables (local fallback or CDN).
$PAGE->requires->js(new moodle_url('https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js'), true);

echo $OUTPUT->header();

// ============================================================
// QUERY BLOCK A: Learner Statistics
// ============================================================

// Get teacher/editing-teacher role user IDs to exclude from learner counts.
$teacher_userids = [];
foreach (['teacher', 'editingteacher'] as $shortname) {
    $role = $DB->get_record('role', ['shortname' => $shortname]);
    if ($role) {
        $assignments = $DB->get_records('role_assignments', ['roleid' => $role->id]);
        foreach ($assignments as $ra) {
            $teacher_userids[$ra->userid] = true;
        }
    }
}
$teacher_count_exclude = count($teacher_userids);

$thirty_days_ago = time() - (30 * 86400);

// Total users (id > 2, not deleted).
$total_all = $DB->count_records_sql("SELECT COUNT(*) FROM {user} WHERE id > 2 AND deleted = 0");
$total_learners = max(0, $total_all - $teacher_count_exclude);

// Active learners (last access within 30 days, not suspended, minus teachers).
$active_all = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {user}
     WHERE id > 2 AND deleted = 0 AND suspended = 0
     AND lastaccess >= ?",
    [$thirty_days_ago]
);
$active_learners = max(0, $active_all - $teacher_count_exclude);

// Suspended.
$suspended_learners = $DB->count_records_sql("SELECT COUNT(*) FROM {user} WHERE id > 2 AND deleted = 0 AND suspended = 1");

// Created but never logged in (not suspended).
$never_logged = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {user}
     WHERE id > 2 AND deleted = 0 AND suspended = 0
     AND (lastaccess = 0 OR lastaccess IS NULL)"
);

// Inactive 30+ days (logged in before, but not in last 30 days, not suspended).
$inactive_30 = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {user}
     WHERE id > 2 AND deleted = 0 AND suspended = 0
     AND lastaccess < ? AND lastaccess != 0",
    [$thirty_days_ago]
);

// ============================================================
// QUERY BLOCK B: Tutor Statistics
// ============================================================

$teacher_role = $DB->get_record('role', ['shortname' => 'teacher']);
$tutors_raw = [];
if ($teacher_role) {
    $tutors_raw = $DB->get_records_sql(
        "SELECT DISTINCT ra.userid FROM {role_assignments} ra WHERE ra.roleid = ?",
        [$teacher_role->id]
    );
}
$total_tutors = count($tutors_raw);

$now = time();
$three_days = $now + (3 * 86400);
$sla_threshold = $now - (72 * 3600); // 72 hours ago — marking SLA.

$tutor_data = [];
$total_caseload = 0;

foreach ($tutors_raw as $traw) {
    $t = $DB->get_record('user', ['id' => $traw->userid, 'deleted' => 0]);
    if (!$t) {
        continue;
    }

    // Tutor's enrolled visible courses.
    $tutor_courses = $DB->get_records_sql(
        "SELECT c.id, c.fullname
         FROM {user_enrolments} ue
         JOIN {enrol} en ON ue.enrolid = en.id
         JOIN {course} c ON c.id = en.courseid
         WHERE ue.userid = ? AND c.visible = 1",
        [$t->id]
    );

    $learner_count = 0;
    $awaiting = 0;

    if (!empty($tutor_courses)) {
        $cids = array_keys($tutor_courses);
        list($cid_sql, $cid_params) = $DB->get_in_or_equal($cids, SQL_PARAMS_NAMED, 'cid');

        // Caseload via groups (tutor + student in same group, student has 'student' role).
        $case_params = array_merge(['tutorid' => $t->id, 'tutorid2' => $t->id], $cid_params);
        $learner_count = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT s.id)
             FROM {groups_members} gm_teacher
             JOIN {groups} g ON g.id = gm_teacher.groupid
             JOIN {groups_members} gm_students ON gm_students.groupid = g.id
             JOIN {user} s ON s.id = gm_students.userid
             JOIN {role_assignments} ra ON ra.userid = s.id
             JOIN {context} ctx ON ctx.id = ra.contextid
             JOIN {role} r ON r.id = ra.roleid
             WHERE gm_teacher.userid = :tutorid
                 AND ctx.contextlevel = 50
                 AND ctx.instanceid {$cid_sql}
                 AND r.shortname = 'student'
                 AND s.id != :tutorid2
                 AND s.deleted = 0",
            $case_params
        );
        $total_caseload += $learner_count;

        // Awaiting marking — overdue (submitted > 72h ago, needs grading).
        // Includes: first submissions not yet graded + re-uploads after grading
        // (where learner modified submission after receiving a grade).
        $awaiting_overdue = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT CONCAT(sub.userid, '-', sub.assignment))
             FROM {groups_members} gm_t
             JOIN {groups} g ON g.id = gm_t.groupid
             JOIN {groups_members} gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
             JOIN {role_assignments} ra ON ra.userid = gm_s.userid
             JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
             JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
             JOIN {user} u ON u.id = gm_s.userid AND u.suspended = 0 AND u.deleted = 0
             JOIN {assign} a ON a.course = g.courseid
             JOIN {modules} mdl_m ON mdl_m.name = 'assign'
             JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course AND cm.module = mdl_m.id AND cm.visible = 1
             JOIN {assign_submission} sub ON sub.assignment = a.id AND sub.userid = gm_s.userid
                 AND (sub.status = 'submitted'
                     OR (sub.status = 'draft' AND a.name LIKE '%Case Stud%')
                     OR (sub.status = 'draft' AND (
                         EXISTS (SELECT 1 FROM {files} df WHERE df.component = 'assignsubmission_file'
                             AND df.filearea = 'submission_files' AND df.itemid = sub.id AND df.filename <> '.')
                         OR EXISTS (SELECT 1 FROM {assignsubmission_onlinetext} ot
                             WHERE ot.submission = sub.id AND ot.onlinetext IS NOT NULL AND ot.onlinetext <> '')
                     ))) AND sub.latest = 1
             LEFT JOIN {assign_grades} gr ON gr.assignment = a.id AND gr.userid = gm_s.userid AND gr.attemptnumber = sub.attemptnumber
             WHERE gm_t.userid = :tutorid3
                 AND a.name NOT LIKE '%IAG%'
                 AND a.name NOT LIKE '%ID Proof%'
                 AND (
                     (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0)
                     OR
                     (sub.status = 'submitted' AND gr.id IS NOT NULL AND gr.grade IS NOT NULL AND gr.grade >= 0
                      AND EXISTS (
                          SELECT 1 FROM {files} f
                          WHERE f.component = 'assignsubmission_file'
                          AND f.filearea = 'submission_files'
                          AND f.itemid = sub.id
                          AND f.filename <> '.'
                          AND f.timecreated > gr.timemodified
                      ))
                 )
                 AND sub.timemodified <= :sla",
            ['tutorid3' => $t->id, 'sla' => $sla_threshold]
        );

        // Awaiting marking — on time (submitted <= 72h ago, needs grading).
        // Includes: first submissions not yet graded + re-uploads after grading.
        $awaiting_ok = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT CONCAT(sub.userid, '-', sub.assignment))
             FROM {groups_members} gm_t
             JOIN {groups} g ON g.id = gm_t.groupid
             JOIN {groups_members} gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
             JOIN {role_assignments} ra ON ra.userid = gm_s.userid
             JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
             JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
             JOIN {user} u ON u.id = gm_s.userid AND u.suspended = 0 AND u.deleted = 0
             JOIN {assign} a ON a.course = g.courseid
             JOIN {modules} mdl_m ON mdl_m.name = 'assign'
             JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course AND cm.module = mdl_m.id AND cm.visible = 1
             JOIN {assign_submission} sub ON sub.assignment = a.id AND sub.userid = gm_s.userid
                 AND (sub.status = 'submitted'
                     OR (sub.status = 'draft' AND a.name LIKE '%Case Stud%')
                     OR (sub.status = 'draft' AND (
                         EXISTS (SELECT 1 FROM {files} df WHERE df.component = 'assignsubmission_file'
                             AND df.filearea = 'submission_files' AND df.itemid = sub.id AND df.filename <> '.')
                         OR EXISTS (SELECT 1 FROM {assignsubmission_onlinetext} ot
                             WHERE ot.submission = sub.id AND ot.onlinetext IS NOT NULL AND ot.onlinetext <> '')
                     ))) AND sub.latest = 1
             LEFT JOIN {assign_grades} gr ON gr.assignment = a.id AND gr.userid = gm_s.userid AND gr.attemptnumber = sub.attemptnumber
             WHERE gm_t.userid = :tutorid4
                 AND a.name NOT LIKE '%IAG%'
                 AND a.name NOT LIKE '%ID Proof%'
                 AND (
                     (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0)
                     OR
                     (sub.status = 'submitted' AND gr.id IS NOT NULL AND gr.grade IS NOT NULL AND gr.grade >= 0
                      AND EXISTS (
                          SELECT 1 FROM {files} f
                          WHERE f.component = 'assignsubmission_file'
                          AND f.filearea = 'submission_files'
                          AND f.itemid = sub.id
                          AND f.filename <> '.'
                          AND f.timecreated > gr.timemodified
                      ))
                 )
                 AND sub.timemodified > :sla2",
            ['tutorid4' => $t->id, 'sla2' => $sla_threshold]
        );
        $awaiting = $awaiting_overdue + $awaiting_ok;
    }

    // Graded count.
    $graded = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {assign_grades} WHERE grader = ?",
        [$t->id]
    );

    // Average turnaround days.
    $turn_rec = $DB->get_record_sql(
        "SELECT AVG(ag.timemodified - sub.timemodified) / 86400 AS avg_days
         FROM {assign_grades} ag
         JOIN {assign} a ON a.id = ag.assignment
         JOIN {assign_submission} sub ON sub.assignment = ag.assignment AND sub.userid = ag.userid
               AND (sub.status = 'submitted'
                   OR (sub.status = 'draft' AND a.name LIKE '%Case Stud%')
                   OR (sub.status = 'draft' AND (
                       EXISTS (SELECT 1 FROM {files} df WHERE df.component = 'assignsubmission_file'
                           AND df.filearea = 'submission_files' AND df.itemid = sub.id AND df.filename <> '.')
                       OR EXISTS (SELECT 1 FROM {assignsubmission_onlinetext} ot
                           WHERE ot.submission = sub.id AND ot.onlinetext IS NOT NULL AND ot.onlinetext <> '')
                   )))
         WHERE ag.grader = ? AND ag.timemodified > sub.timemodified AND ag.grade IS NOT NULL",
        [$t->id]
    );
    $avg_turn = ($turn_rec && $turn_rec->avg_days !== null) ? round($turn_rec->avg_days, 1) : 'N/A';

    // Pass rate via scale lookup (MySQL SUBSTRING_INDEX — matches existing codebase pattern).
    $pass_rate = 'N/A';
    try {
        $pass_rec = $DB->get_record_sql(
            "SELECT
                COUNT(*) AS total_graded,
                SUM(CASE WHEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(sc.scale, ',', CAST(gg.finalgrade AS UNSIGNED)), ',', -1)) = 'Pass' THEN 1 ELSE 0 END) AS pass_count
             FROM {assign_grades} ag
             JOIN {assign} a ON a.id = ag.assignment
             JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
             JOIN {grade_grades} gg ON gg.itemid = gi.id AND gg.userid = ag.userid
             JOIN {scale} sc ON sc.id = gi.scaleid
             WHERE ag.grader = ? AND gg.finalgrade IS NOT NULL",
            [$t->id]
        );
        if ($pass_rec && $pass_rec->total_graded > 0) {
            $pass_rate = round(($pass_rec->pass_count / $pass_rec->total_graded) * 100) . '%';
        }
    } catch (Exception $e) {
        $pass_rate = 'N/A';
    }

    // Per-course caseload (Approved Programmes).
    $programmes = [];
    if (!empty($tutor_courses)) {
        foreach ($tutor_courses as $tc) {
            $prog_case = $DB->count_records_sql(
                "SELECT COUNT(DISTINCT s.id)
                 FROM {groups_members} gm_t
                 JOIN {groups} g ON g.id = gm_t.groupid AND g.courseid = :cid
                 JOIN {groups_members} gm_s ON gm_s.groupid = g.id
                 JOIN {user} s ON s.id = gm_s.userid
                 JOIN {role_assignments} ra ON ra.userid = s.id
                 JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
                 JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
                 WHERE gm_t.userid = :tid AND s.id != :tid2 AND s.deleted = 0",
                ['cid' => $tc->id, 'tid' => $t->id, 'tid2' => $t->id]
            );
            $programmes[] = ['name' => $tc->fullname, 'caseload' => (int)$prog_case];
        }
    }

    // Overdue assignments for this tutor's students (submitted but ungraded, past due).
    $tutor_overdue = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT CONCAT(sub.userid, '-', sub.assignment))
         FROM {groups_members} gm_t
         JOIN {groups} g ON g.id = gm_t.groupid
         JOIN {groups_members} gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
         JOIN {role_assignments} ra ON ra.userid = gm_s.userid
         JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
         JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
         JOIN {user} u ON u.id = gm_s.userid AND u.suspended = 0 AND u.deleted = 0
         JOIN {assign} a ON a.course = g.courseid
         JOIN {modules} mdl_m ON mdl_m.name = 'assign'
         JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course AND cm.module = mdl_m.id AND cm.visible = 1
         JOIN {assign_submission} sub ON sub.assignment = a.id AND sub.userid = gm_s.userid
             AND (sub.status = 'submitted'
                 OR (sub.status = 'draft' AND a.name LIKE '%Case Stud%')
                 OR (sub.status = 'draft' AND (
                     EXISTS (SELECT 1 FROM {files} df WHERE df.component = 'assignsubmission_file'
                         AND df.filearea = 'submission_files' AND df.itemid = sub.id AND df.filename <> '.')
                     OR EXISTS (SELECT 1 FROM {assignsubmission_onlinetext} ot
                         WHERE ot.submission = sub.id AND ot.onlinetext IS NOT NULL AND ot.onlinetext <> '')
                 ))) AND sub.latest = 1
         LEFT JOIN {assign_grades} gr ON gr.assignment = a.id AND gr.userid = gm_s.userid AND gr.attemptnumber = sub.attemptnumber
         WHERE gm_t.userid = :tid3
           AND a.name NOT LIKE '%IAG%'
           AND a.name NOT LIKE '%ID Proof%'
           AND (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0)
           AND sub.timemodified > 0
           AND sub.timemodified <= :nowts",
        ['tid3' => $t->id, 'nowts' => $now]
    );

    // Imminent assignments (due within 3 days) for this tutor's students.
    $tutor_imminent = 0;
    if (!empty($tutor_courses)) {
        $tcids = array_keys($tutor_courses);
        list($tcid_sql, $tcid_params) = $DB->get_in_or_equal($tcids, SQL_PARAMS_NAMED, 'tcid');
        $imm_params = array_merge(
            ['tid_imm' => $t->id, 'now_imm' => $now, 'three_imm' => $three_days],
            $tcid_params
        );
        $tutor_imminent = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT CONCAT(a.id, '-', gm_s.userid))
             FROM {groups_members} gm_t
             JOIN {groups} g ON g.id = gm_t.groupid
             JOIN {groups_members} gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
             JOIN {user} u ON u.id = gm_s.userid AND u.suspended = 0 AND u.deleted = 0
             JOIN {role_assignments} ra ON ra.userid = u.id
             JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
             JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
             JOIN {assign} a ON a.course = g.courseid
             JOIN {modules} mod_imm ON mod_imm.name = 'assign'
             JOIN {course_modules} cm_imm ON cm_imm.instance = a.id AND cm_imm.course = a.course AND cm_imm.module = mod_imm.id AND cm_imm.visible = 1
             WHERE gm_t.userid = :tid_imm
               AND a.course {$tcid_sql}
               AND a.duedate > 0 AND a.name NOT LIKE '%IAG%'
               AND a.name NOT LIKE '%ID Proof%'
               AND a.duedate >= :now_imm AND a.duedate <= :three_imm",
            $imm_params
        );
    }

    // Inactive learners (30+ days) in this tutor's groups.
    $tutor_inactive = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT s.id)
         FROM {groups_members} gm_t
         JOIN {groups} g ON g.id = gm_t.groupid
         JOIN {groups_members} gm_s ON gm_s.groupid = g.id
         JOIN {user} s ON s.id = gm_s.userid
         JOIN {role_assignments} ra ON ra.userid = s.id
         JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
         JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
         WHERE gm_t.userid = :tid4 AND s.id != :tid5
           AND s.deleted = 0 AND s.suspended = 0
           AND s.lastaccess IS NOT NULL AND s.lastaccess != 0
           AND s.lastaccess < :thirty",
        ['tid4' => $t->id, 'tid5' => $t->id, 'thirty' => $thirty_days_ago]
    );

    // Build detail JSON for expandable child rows.
    $detail = json_encode([
        'programmes' => $programmes,
        'overdue' => (int)$tutor_overdue,
        'imminent' => (int)$tutor_imminent,
        'awaiting' => (int)$awaiting,
        'inactive' => (int)$tutor_inactive,
    ]);

    $tutor_data[] = [
        'fullname'          => fullname($t),
        'email'             => $t->email,
        'userid'            => $t->id,
        'learner_count'     => $learner_count,
        'awaiting_overdue'  => $awaiting_overdue,
        'awaiting_ok'       => $awaiting_ok,
        'has_overdue'       => ($awaiting_overdue > 0),
        'has_ok'            => ($awaiting_ok > 0),
        'inactive_count'    => $tutor_inactive,
        'has_inactive'      => ($tutor_inactive > 0),
        'avg_turnaround'    => $avg_turn,
        'pass_rate'         => $pass_rate,
        'detail_json'       => $detail,
    ];
}

$avg_caseload = ($total_tutors > 0) ? round($total_caseload / $total_tutors, 1) : 0;

// ============================================================
// QUERY BLOCK C: Course Statistics
// ============================================================

$total_courses = $DB->count_records_sql("SELECT COUNT(*) FROM {course} WHERE id > 1 AND visible = 1");

$courses_raw = $DB->get_records_sql(
    "SELECT c.id, c.fullname
     FROM {course} c
     WHERE c.id > 1 AND c.visible = 1
     ORDER BY c.fullname ASC"
);

$course_data = [];
foreach ($courses_raw as $course) {
    $course_context = context_course::instance($course->id);
    $enrolled = count_enrolled_users($course_context);

    $active_in_course = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid)
         FROM {user_enrolments} ue
         JOIN {enrol} en ON ue.enrolid = en.id
         JOIN {user} u ON u.id = ue.userid
         WHERE en.courseid = ? AND u.suspended = 0 AND u.deleted = 0 AND u.lastaccess >= ?",
        [$course->id, $thirty_days_ago]
    );

    $assign_count = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {assign} WHERE course = ?",
        [$course->id]
    );

    $completions = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {course_completions} WHERE course = ? AND timecompleted IS NOT NULL",
        [$course->id]
    );
    $completion_pct = ($enrolled > 0) ? round(($completions / $enrolled) * 100) : 0;

    $course_data[] = [
        'coursename'      => $course->fullname,
        'enrolled'        => $enrolled,
        'active_learners' => $active_in_course,
        'assignments'     => $assign_count,
        'completion_pct'  => $completion_pct,
        'completion_rate' => $completion_pct . '%',
    ];
}

// ============================================================
// QUERY BLOCK D: Assignment / Grading Stats
// ============================================================

// Global awaiting marking (first submissions + re-uploads after grading).
$global_awaiting = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT CONCAT(sub.userid, '-', sub.assignment))
     FROM {assign_submission} sub
     JOIN {assign} a ON a.id = sub.assignment
     JOIN {modules} mdl_m ON mdl_m.name = 'assign'
     JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course AND cm.module = mdl_m.id AND cm.visible = 1
     JOIN {user} u ON u.id = sub.userid AND u.suspended = 0 AND u.deleted = 0
     LEFT JOIN {assign_grades} gr ON gr.assignment = sub.assignment AND gr.userid = sub.userid AND gr.attemptnumber = sub.attemptnumber
     WHERE (sub.status = 'submitted'
         OR (sub.status = 'draft' AND a.name LIKE '%Case Stud%')
         OR (sub.status = 'draft' AND (
             EXISTS (SELECT 1 FROM {files} df WHERE df.component = 'assignsubmission_file'
                 AND df.filearea = 'submission_files' AND df.itemid = sub.id AND df.filename <> '.')
             OR EXISTS (SELECT 1 FROM {assignsubmission_onlinetext} ot
                 WHERE ot.submission = sub.id AND ot.onlinetext IS NOT NULL AND ot.onlinetext <> '')
         ))) AND sub.latest = 1
     AND a.name NOT LIKE '%IAG%'
     AND a.name NOT LIKE '%ID Proof%'
     AND (
         (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0)
         OR
         (sub.status = 'submitted' AND gr.id IS NOT NULL AND gr.grade IS NOT NULL AND gr.grade >= 0
          AND EXISTS (
              SELECT 1 FROM {files} f
              WHERE f.component = 'assignsubmission_file'
              AND f.filearea = 'submission_files'
              AND f.itemid = sub.id
              AND f.filename <> '.'
              AND f.timecreated > gr.timemodified
          ))
     )"
);

$global_overdue = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT CONCAT(sub.userid, '-', sub.assignment))
     FROM {assign_submission} sub
     JOIN {assign} a ON a.id = sub.assignment
     JOIN {modules} mdl_m ON mdl_m.name = 'assign'
     JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course AND cm.module = mdl_m.id AND cm.visible = 1
     JOIN {user} u ON u.id = sub.userid AND u.suspended = 0 AND u.deleted = 0
     LEFT JOIN {assign_grades} gr ON gr.assignment = sub.assignment AND gr.userid = sub.userid AND gr.attemptnumber = sub.attemptnumber
     WHERE (sub.status = 'submitted'
         OR (sub.status = 'draft' AND a.name LIKE '%Case Stud%')
         OR (sub.status = 'draft' AND (
             EXISTS (SELECT 1 FROM {files} df WHERE df.component = 'assignsubmission_file'
                 AND df.filearea = 'submission_files' AND df.itemid = sub.id AND df.filename <> '.')
             OR EXISTS (SELECT 1 FROM {assignsubmission_onlinetext} ot
                 WHERE ot.submission = sub.id AND ot.onlinetext IS NOT NULL AND ot.onlinetext <> '')
         ))) AND sub.latest = 1
     AND a.name NOT LIKE '%IAG%'
     AND a.name NOT LIKE '%ID Proof%'
     AND (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0)
     AND a.duedate > 0 AND a.duedate < ?",
    [$now]
);

$global_imminent = $DB->count_records_sql(
    "SELECT COUNT(DISTINCT CONCAT(a.id, '-', sub.userid))
     FROM {assign} a
     JOIN {modules} mdl_m ON mdl_m.name = 'assign'
     JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course AND cm.module = mdl_m.id AND cm.visible = 1
     JOIN {assign_submission} sub ON sub.assignment = a.id AND sub.status = 'new'
     JOIN {user} u ON u.id = sub.userid AND u.suspended = 0 AND u.deleted = 0
     WHERE a.duedate > 0
     AND a.name NOT LIKE '%IAG%'
     AND a.name NOT LIKE '%ID Proof%'
     AND a.duedate >= ? AND a.duedate <= ?",
    [$now, $three_days]
);

// ============================================================
// QUERY BLOCK E: Monthly Enrollment Trend
// ============================================================

$current_year = (int) date('Y');
$enrollment_months = [];
for ($m = 1; $m <= 12; $m++) {
    $start = mktime(0, 0, 0, $m, 1, $current_year);
    $end = ($m == 12) ? mktime(23, 59, 59, 12, 31, $current_year) : mktime(0, 0, 0, $m + 1, 1, $current_year) - 1;
    $enrollment_months[$m] = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {user_enrolments} WHERE timestart >= ? AND timestart <= ? AND timestart > 0",
        [$start, $end]
    );
}

// ============================================================
// QUERY BLOCK F: Online Users (last 5 minutes)
// ============================================================

$online_threshold = time() - 300; // 5 minutes — standard Moodle "online" threshold.

$online_users_raw = $DB->get_records_sql(
    "SELECT u.id, u.firstname, u.lastname, u.lastaccess, u.email
     FROM {user} u
     WHERE u.lastaccess >= :threshold
       AND u.deleted = 0 AND u.id > 2
     ORDER BY u.lastaccess DESC",
    ['threshold' => $online_threshold]
);

$online_users = [];
$online_learner_count = 0;
$online_tutor_count = 0;
$online_admin_count = 0;

foreach ($online_users_raw as $ou) {
    if (is_siteadmin($ou->id)) {
        $role_label = 'Admin';
        $online_admin_count++;
    } else if (isset($teacher_userids[$ou->id])) {
        $role_label = 'Tutor';
        $online_tutor_count++;
    } else {
        $role_label = 'Learner';
        $online_learner_count++;
    }

    $ago = time() - $ou->lastaccess;
    $time_ago = ($ago < 60) ? 'Just now' : round($ago / 60) . ' min ago';

    $online_users[] = [
        'fullname'   => fullname($ou),
        'email'      => $ou->email,
        'role_label' => $role_label,
        'is_admin'   => ($role_label === 'Admin'),
        'is_tutor'   => ($role_label === 'Tutor'),
        'is_learner' => ($role_label === 'Learner'),
        'time_ago'   => $time_ago,
    ];
}

$online_count = count($online_users_raw);

// ============================================================
// QUERY BLOCK G: AI Detection Checks (GPTZero)
// ============================================================

$ai_checks = [];
$ai_checks_count = 0;
$gptzero_table_exists = $DB->get_manager()->table_exists('plagiarism_gptzero_files');

if ($gptzero_table_exists) {
    // Get recent AI checks (last 50) with learner and assignment info.
    $ai_checks_raw = $DB->get_records_sql(
        "SELECT pf.id, pf.cm, pf.userid, pf.filename, pf.predicted_class, pf.class_probability, pf.timesubmitted,
                u.firstname, u.lastname, u.email,
                a.name AS assignment_name, c.fullname AS course_name,
                sub.id AS submission_id
         FROM {plagiarism_gptzero_files} pf
         JOIN {user} u ON u.id = pf.userid
         JOIN {course_modules} cm ON cm.id = pf.cm
         JOIN {assign} a ON a.id = cm.instance
         JOIN {course} c ON c.id = a.course
         LEFT JOIN {assign_submission} sub ON sub.assignment = a.id AND sub.userid = pf.userid AND sub.latest = 1
         WHERE pf.predicted_class IS NOT NULL AND pf.predicted_class != ''
         ORDER BY pf.timesubmitted DESC
         LIMIT 50"
    );

    $ai_checks_count = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {plagiarism_gptzero_files} WHERE predicted_class IS NOT NULL AND predicted_class != ''"
    );

    foreach ($ai_checks_raw as $check) {
        $cls = strtolower($check->predicted_class);
        $pct = round($check->class_probability * 100);

        $ai_checks[] = [
            'learner_name'    => $check->firstname . ' ' . $check->lastname,
            'assignment_name' => $check->assignment_name,
            'course_name'     => $check->course_name,
            'predicted_class' => ucfirst($cls),
            'probability'     => $pct . '%',
            'scan_date'       => userdate($check->timesubmitted, '%d %b %Y %H:%M'),
            'is_human'        => ($cls === 'human'),
            'is_ai'           => ($cls === 'ai'),
            'is_mixed'        => ($cls === 'mixed'),
            'report_url'      => $check->submission_id
                ? (new moodle_url('/local/learner/aireport.php', ['id' => $check->submission_id]))->out()
                : '',
            'has_report'      => !empty($check->submission_id),
        ];
    }
}

// ============================================================
// QUERY BLOCK H: Draft Feedback Pending (local_draftfeedback plugin)
// ============================================================

$global_draft_feedback = 0;
$draft_feedback_per_tutor = [];
$draftfeedback_table_exists = $DB->get_manager()->table_exists('local_draftfeedback');

if ($draftfeedback_table_exists) {
    // Global count of pending draft feedback requests.
    $global_draft_feedback = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT df.id)
         FROM {local_draftfeedback} df
         JOIN {course_modules} cm ON cm.id = df.cmid
         JOIN {assign} a ON a.id = cm.instance
         JOIN {user} u ON u.id = df.userid AND u.suspended = 0 AND u.deleted = 0
         WHERE df.status = 'pending'
         AND a.name NOT LIKE '%IAG%'
         AND a.name NOT LIKE '%ID Proof%'
         AND a.name NOT LIKE '%Case Stud%'"
    );

    // Per-tutor draft feedback counts using the manager's count method.
    if (class_exists('\local_draftfeedback\manager')) {
        foreach ($tutors_raw as $traw) {
            $count = \local_draftfeedback\manager::count_pending_drafts_for_tutor($traw->userid);
            $draft_feedback_per_tutor[$traw->userid] = $count;
        }
    }
}

// Add draft feedback counts to tutor_data array.
foreach ($tutor_data as &$td) {
    $userid = $td['userid'] ?? 0;
    $td['draft_feedback_count'] = $draft_feedback_per_tutor[$userid] ?? 0;
    $td['has_draft_feedback'] = ($td['draft_feedback_count'] > 0);
}
unset($td); // Break reference

// ============================================================
// ASSEMBLE TEMPLATE CONTEXT
// ============================================================

$templatecontext = [
    // KPI cards.
    'total_learners'     => number_format($total_learners),
    'active_learners'    => number_format($active_learners),
    'suspended_learners' => number_format($suspended_learners),
    'total_tutors'       => $total_tutors,
    'avg_caseload'       => $avg_caseload,
    'total_courses'      => $total_courses,
    'global_awaiting'    => number_format($global_awaiting),
    'global_overdue'     => number_format($global_overdue),
    'global_imminent'    => number_format($global_imminent),

    // Pie chart data.
    'pie_active'     => $active_learners,
    'pie_inactive30' => $inactive_30,
    'pie_suspended'  => $suspended_learners,
    'pie_never'      => $never_logged,

    // Column chart data.
    'currentyear' => $current_year,

    // Tables.
    'tutors'      => array_values($tutor_data),
    'has_tutors'  => !empty($tutor_data),
    'courses'     => array_values($course_data),
    'has_courses' => !empty($course_data),

    // Alert links — these point to local/learner pages if they exist, otherwise generic.
    'inactive_count' => number_format($inactive_30),
    'overdue_count'  => number_format($global_overdue),
    'imminent_count' => number_format($global_imminent),
    'wwwroot'        => $CFG->wwwroot,

    // Online users.
    'online_count'         => $online_count,
    'online_learner_count' => $online_learner_count,
    'online_tutor_count'   => $online_tutor_count,
    'online_admin_count'   => $online_admin_count,
    'online_users'         => $online_users,
    'has_online_users'     => !empty($online_users),

    // GPTZero AI Detection Usage.
    'gptzero_words_used'   => number_format((int)get_config('plagiarism_gptzero', 'words_used')),
    'gptzero_words_limit'  => number_format(300000),
    'gptzero_scans_count'  => number_format((int)get_config('plagiarism_gptzero', 'scans_count')),
    'gptzero_last_scan'    => ((int)get_config('plagiarism_gptzero', 'last_scan_time') > 0)
        ? userdate((int)get_config('plagiarism_gptzero', 'last_scan_time'), '%d %b %Y %H:%M')
        : 'Never',
    'gptzero_words_pct'    => min(100, round(((int)get_config('plagiarism_gptzero', 'words_used') / 300000) * 100, 1)),

    // AI Checks history.
    'ai_checks'            => $ai_checks,
    'has_ai_checks'        => !empty($ai_checks),
    'ai_checks_count'      => number_format($ai_checks_count),
    'gptzero_enabled'      => $gptzero_table_exists,

    // Draft Feedback.
    'global_draft_feedback'     => number_format($global_draft_feedback),
    'global_draft_feedback_raw' => $global_draft_feedback,
    'has_draft_feedback'        => ($global_draft_feedback > 0),
    'draft_feedback_url'        => (new moodle_url('/local/draftfeedback/index.php'))->out(),
    'draftfeedback_enabled'     => $draftfeedback_table_exists,

    // AI Check Tool.
    'ai_checks_url'             => (new moodle_url('/local/learner/aichecks.php'))->out(),
];

// Monthly enrollment data.
for ($m = 1; $m <= 12; $m++) {
    $templatecontext['enrol_month_' . $m] = $enrollment_months[$m];
}

// Alert links — check if local/learner exists for deep links, otherwise use generic.
$learner_plugin_exists = file_exists($CFG->dirroot . '/local/learner/view.php');
if ($learner_plugin_exists) {
    $templatecontext['inactive_link'] = (new moodle_url('/local/learner/view.php', ['action' => 'inactive']))->out();
    $templatecontext['overdue_link']  = (new moodle_url('/local/learner/markallocation.php', ['action' => 'overdue']))->out();
    $templatecontext['imminent_link'] = (new moodle_url('/local/learner/markallocation.php', ['action' => 'imm']))->out();
    $templatecontext['learners_link'] = (new moodle_url('/local/learner/view.php'))->out();
    $templatecontext['tutors_link']   = (new moodle_url('/local/learner/tutor.php'))->out();
} else {
    $templatecontext['inactive_link'] = (new moodle_url('/admin/user.php'))->out();
    $templatecontext['overdue_link']  = (new moodle_url('/mod/assign/index.php'))->out();
    $templatecontext['imminent_link'] = (new moodle_url('/mod/assign/index.php'))->out();
    $templatecontext['learners_link'] = (new moodle_url('/admin/user.php'))->out();
    $templatecontext['tutors_link']   = (new moodle_url('/admin/user.php'))->out();
}

// Course list link.
$templatecontext['courses_link'] = (new moodle_url('/course/index.php'))->out();

// Marking link — outstanding marking for all tutors.
$templatecontext['marking_link'] = (new moodle_url('/local/learner/markallocation.php', ['action' => 'mark']))->out();

echo $OUTPUT->render_from_template('local_admindashboard/admindash', $templatecontext);

echo $OUTPUT->footer();
