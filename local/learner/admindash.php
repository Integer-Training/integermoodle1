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
 * Admin Dashboard - comprehensive platform overview for administrators.
 *
 * @package   local_learner
 * @copyright 2025 Gecko
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

global $DB, $CFG, $OUTPUT, $PAGE;

$context = context_system::instance();
require_capability('local/learner:view', $context);

$PAGE->set_url(new moodle_url('/local/learner/admindash.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('admindashboard', 'local_learner'));
$PAGE->set_heading(get_string('admindashboard', 'local_learner'));
$PAGE->set_pagelayout('standard');
$PAGE->requires->jquery();

// Set active nav node for sidebar highlight.
if ($node = $PAGE->navigation->find('local_learner_admindash', navigation_node::TYPE_CUSTOM)) {
    $node->make_active();
}

// CDN includes.
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
echo '<script src="https://code.highcharts.com/highcharts.js"></script>';
echo '<script src="https://code.highcharts.com/modules/data.js"></script>';
echo '<script src="https://code.highcharts.com/modules/drilldown.js"></script>';
echo '<script src="https://code.highcharts.com/modules/exporting.js"></script>';
echo '<script src="https://code.highcharts.com/modules/export-data.js"></script>';
echo '<script src="https://code.highcharts.com/modules/accessibility.js"></script>';

// DataTables.
$PAGE->requires->js('/local/learner/js/jquery.dataTables.min.js', true);
$PAGE->requires->css('/local/learner/js/jquery.dataTables.min.css', true);

echo $OUTPUT->header();

// ============================================================
// QUERY BLOCK A: Learner Statistics
// ============================================================

// Get teacher role users to exclude from learner counts.
$teacher_role = $DB->get_record('role', ['shortname' => 'teacher']);
$teacher_userids = [];
if ($teacher_role) {
    $teacher_assignments = $DB->get_records('role_assignments', ['roleid' => $teacher_role->id]);
    foreach ($teacher_assignments as $ta) {
        $teacher_userids[$ta->userid] = true;
    }
}
// Also exclude editing teachers.
$eteacher_role = $DB->get_record('role', ['shortname' => 'editingteacher']);
if ($eteacher_role) {
    $eteacher_assignments = $DB->get_records('role_assignments', ['roleid' => $eteacher_role->id]);
    foreach ($eteacher_assignments as $ea) {
        $teacher_userids[$ea->userid] = true;
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

// Suspended learners (manually deactivated by admin).
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

$tutors_raw = [];
if ($teacher_role) {
    $tutors_raw = $DB->get_records_sql(
        "SELECT DISTINCT ra.userid
         FROM {role_assignments} ra
         WHERE ra.roleid = ?",
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

    // Get tutor's enrolled courses.
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

        // Caseload count via groups (same pattern as tutor.php).
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
        "SELECT AVG(ag.timemodified - sub.timemodified) / 86400 as avg_days
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

    // Pass rate via scale lookup (MySQL-specific SUBSTRING_INDEX, consistent with local/tutors/view.php).
    $pass_rate = 'N/A';
    try {
        $pass_rec = $DB->get_record_sql(
            "SELECT
                COUNT(*) as total_graded,
                SUM(CASE WHEN TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(sc.scale, ',', CAST(gg.finalgrade AS UNSIGNED)), ',', -1)) = 'Pass' THEN 1 ELSE 0 END) as pass_count
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
    "SELECT c.id, c.fullname, c.visible
     FROM {course} c
     WHERE c.id > 1 AND c.visible = 1
     ORDER BY c.fullname ASC"
);

$course_data = [];
foreach ($courses_raw as $course) {
    $course_context = context_course::instance($course->id);
    $enrolled = count_enrolled_users($course_context);

    // Active learners in this course (last access within 30 days).
    $active_in_course = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT ue.userid)
         FROM {user_enrolments} ue
         JOIN {enrol} en ON ue.enrolid = en.id
         JOIN {user} u ON u.id = ue.userid
         WHERE en.courseid = ? AND u.suspended = 0 AND u.deleted = 0
         AND u.lastaccess >= ?",
        [$course->id, $thirty_days_ago]
    );

    // Assignments in course.
    $assign_count = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {assign} WHERE course = ?",
        [$course->id]
    );

    // Completion rate.
    $completions = $DB->count_records_sql(
        "SELECT COUNT(*) FROM {course_completions} WHERE course = ? AND timecompleted IS NOT NULL",
        [$course->id]
    );
    $completion_pct = ($enrolled > 0) ? round(($completions / $enrolled) * 100) : 0;
    $completion_rate = $completion_pct . '%';

    $course_data[] = [
        'coursename' => $course->fullname,
        'enrolled' => $enrolled,
        'active_learners' => $active_in_course,
        'assignments' => $assign_count,
        'completion_pct' => $completion_pct,
        'completion_rate' => $completion_rate,
    ];
}

// ============================================================
// QUERY BLOCK D: Assignment / Grading Stats (Global)
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

// Global overdue (submitted but ungraded, past assignment due date).
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

// Global imminent (due within 3 days).
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

$current_year = (int)date('Y');
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
// ASSEMBLE TEMPLATE CONTEXT
// ============================================================

$templatecontext = [];

// KPI cards.
$templatecontext['total_learners'] = number_format($total_learners);
$templatecontext['active_learners'] = number_format($active_learners);
$templatecontext['suspended_learners'] = number_format($suspended_learners);
$templatecontext['total_tutors'] = $total_tutors;
$templatecontext['avg_caseload'] = $avg_caseload;
$templatecontext['total_courses'] = $total_courses;
$templatecontext['global_awaiting'] = number_format($global_awaiting);
$templatecontext['global_overdue'] = number_format($global_overdue);
$templatecontext['global_imminent'] = number_format($global_imminent);

// Pie chart data (raw numbers for Highcharts).
$templatecontext['pie_active'] = $active_learners;
$templatecontext['pie_inactive30'] = $inactive_30;
$templatecontext['pie_suspended'] = $suspended_learners;
$templatecontext['pie_never'] = $never_logged;

// Column chart data.
for ($m = 1; $m <= 12; $m++) {
    $templatecontext['enrol_month_' . $m] = $enrollment_months[$m];
}
$templatecontext['currentyear'] = $current_year;

// Tutor table.
$templatecontext['tutors'] = array_values($tutor_data);
$templatecontext['has_tutors'] = !empty($tutor_data);

// Course table.
$templatecontext['courses'] = array_values($course_data);
$templatecontext['has_courses'] = !empty($course_data);

// Alert links.
$templatecontext['inactive_count'] = number_format($inactive_30);
$templatecontext['inactive_link'] = (new moodle_url('/local/learner/view.php', ['action' => 'inactive']))->out();
$templatecontext['overdue_count'] = number_format($global_overdue);
$templatecontext['overdue_link'] = (new moodle_url('/local/learner/markallocation.php', ['action' => 'overdue']))->out();
$templatecontext['imminent_count'] = number_format($global_imminent);
$templatecontext['imminent_link'] = (new moodle_url('/local/learner/markallocation.php', ['action' => 'imm']))->out();

// Navigation links.
$templatecontext['learners_link'] = (new moodle_url('/local/learner/view.php'))->out();
$templatecontext['tutors_link'] = (new moodle_url('/local/learner/tutor.php'))->out();
$templatecontext['marking_link'] = (new moodle_url('/local/tutors/view.php'))->out();
$templatecontext['wwwroot'] = $CFG->wwwroot;

echo $OUTPUT->render_from_template('local_learner/admindash', $templatecontext);

echo $OUTPUT->footer();
