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
 * Tutor Dashboard — caseload stats, grading pipeline, learner activity.
 *
 * Shows distinct learner counts (not per-course duplicates), grading
 * pipeline stats, pass rate, and per-course caseload breakdown.
 *
 * @package   local_learner
 * @copyright 2025 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

$context = context_system::instance();

$PAGE->set_url(new moodle_url('/local/learner/tutordash.php'));
$PAGE->set_context($context);
$PAGE->set_title('Tutor Dashboard');

$PAGE->requires->jquery();

echo '<!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
echo '<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>';

echo $OUTPUT->header();

$prefix = $CFG->prefix;
$tutorid = (int) $USER->id;
$now = time();
$thirty_days_ago = $now - (30 * 24 * 60 * 60);
$sla_threshold = $now - (72 * 3600); // 72 hours ago — marking SLA.

// ============================================================
// 1. DISTINCT LEARNER IDS (single query, no double-counting)
// ============================================================
$learner_sql = "SELECT DISTINCT gm_s.userid
    FROM {$prefix}groups_members gm_t
    JOIN {$prefix}groups g ON g.id = gm_t.groupid
    JOIN {$prefix}groups_members gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
    JOIN {$prefix}role_assignments ra ON ra.userid = gm_s.userid
    JOIN {$prefix}context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
    JOIN {$prefix}role r ON r.id = ra.roleid AND r.shortname = 'student'
    JOIN {$prefix}user u ON u.id = gm_s.userid AND u.suspended = 0 AND u.deleted = 0
    WHERE gm_t.userid = {$tutorid}";

$learner_records = $DB->get_records_sql($learner_sql);
$learner_ids = array_keys($learner_records);
$total_learners = count($learner_ids);

// ============================================================
// 1b. SUSPENDED LEARNERS (separate count — not in learner_ids)
// ============================================================
$suspended_sql = "SELECT COUNT(DISTINCT gm_s.userid)
    FROM {$prefix}groups_members gm_t
    JOIN {$prefix}groups g ON g.id = gm_t.groupid
    JOIN {$prefix}groups_members gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
    JOIN {$prefix}role_assignments ra ON ra.userid = gm_s.userid
    JOIN {$prefix}context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
    JOIN {$prefix}role r ON r.id = ra.roleid AND r.shortname = 'student'
    JOIN {$prefix}user u ON u.id = gm_s.userid AND u.suspended = 1 AND u.deleted = 0
    WHERE gm_t.userid = {$tutorid}";
$suspended_count = $DB->count_records_sql($suspended_sql);

// ============================================================
// 2. LEARNER ACTIVITY BREAKDOWN (active / inactive / created)
// ============================================================
$active_count = 0;
$inactive_count = 0;
$created_count = 0;

if (!empty($learner_ids)) {
    list($lid_sql, $lid_params) = $DB->get_in_or_equal($learner_ids, SQL_PARAMS_NAMED, 'uid');
    $user_records = $DB->get_records_sql(
        "SELECT id, lastaccess, lastlogin FROM {user} WHERE id {$lid_sql}",
        $lid_params
    );
    foreach ($user_records as $u) {
        if (empty($u->lastlogin) && empty($u->lastaccess)) {
            $created_count++;
        } else if ($u->lastaccess < $thirty_days_ago) {
            $inactive_count++;
        } else {
            $active_count++;
        }
    }
}

// ============================================================
// 3. COURSES & PER-COURSE CASELOAD (single query, not N+1)
// ============================================================
$caseload_sql = "SELECT g.courseid, c.fullname AS coursename, COUNT(DISTINCT gm_s.userid) AS caseload
    FROM {$prefix}groups_members gm_t
    JOIN {$prefix}groups g ON g.id = gm_t.groupid
    JOIN {$prefix}course c ON c.id = g.courseid AND c.visible = 1
    JOIN {$prefix}groups_members gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
    JOIN {$prefix}role_assignments ra ON ra.userid = gm_s.userid
    JOIN {$prefix}context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
    JOIN {$prefix}role r ON r.id = ra.roleid AND r.shortname = 'student'
    JOIN {$prefix}user u ON u.id = gm_s.userid AND u.suspended = 0 AND u.deleted = 0
    WHERE gm_t.userid = {$tutorid}
    GROUP BY g.courseid, c.fullname
    ORDER BY c.fullname";

$caseload_rows = $DB->get_records_sql($caseload_sql);

$courselist = [];
$allcourses = [];
foreach ($caseload_rows as $row) {
    $courselist[] = [
        'coursename' => $row->coursename,
        'caseload' => (int) $row->caseload,
        'users_link' => new moodle_url('/user/index.php', ['id' => $row->courseid]),
    ];
    $allcourses[] = (int) $row->courseid;
}

$enrolled_courses = count($courselist);

// ============================================================
// 4. TOTAL ELIGIBLE ASSIGNMENTS (with exclusions + visible filter)
// ============================================================
$total_assignments = 0;
if (!empty($allcourses)) {
    list($crs_sql, $crs_params) = $DB->get_in_or_equal($allcourses, SQL_PARAMS_NAMED, 'crs');
    $total_assignments = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT a.id)
         FROM {assign} a
         JOIN {modules} mdl_m ON mdl_m.name = 'assign'
         JOIN {course_modules} cm ON cm.instance = a.id AND cm.course = a.course
             AND cm.module = mdl_m.id AND cm.visible = 1
         WHERE a.course {$crs_sql}
             AND a.name NOT LIKE '%IAG%'
             AND a.name NOT LIKE '%ID Proof%'",
        $crs_params
    );
}

// ============================================================
// 5. AWAITING MARKING (first submissions, attemptnumber = 0)
// ============================================================
$mark_sql = "SELECT COUNT(DISTINCT sub.id)
    FROM {$prefix}groups_members gm_t
    JOIN {$prefix}groups g ON g.id = gm_t.groupid
    JOIN {$prefix}groups_members gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
    JOIN {$prefix}role_assignments ra ON ra.userid = gm_s.userid
    JOIN {$prefix}context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
    JOIN {$prefix}role r ON r.id = ra.roleid AND r.shortname = 'student'
    JOIN {$prefix}user u ON u.id = gm_s.userid AND u.suspended = 0 AND u.deleted = 0
    JOIN {$prefix}assign a ON a.course = g.courseid
    JOIN {$prefix}modules mod_m ON mod_m.name = 'assign'
    JOIN {$prefix}course_modules cm ON cm.instance = a.id AND cm.course = a.course AND cm.module = mod_m.id AND cm.visible = 1
    JOIN {$prefix}assign_submission sub ON sub.assignment = a.id AND sub.userid = gm_s.userid
        AND sub.status IN ('submitted', 'draft') AND sub.latest = 1 AND sub.attemptnumber = 0
    LEFT JOIN {$prefix}assign_grades gr ON gr.assignment = a.id AND gr.userid = gm_s.userid
        AND gr.attemptnumber = sub.attemptnumber
    WHERE gm_t.userid = {$tutorid}
        AND a.name NOT LIKE '%IAG%'
        AND a.name NOT LIKE '%ID Proof%'
        AND (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0)";

$yet_to_grade = $DB->count_records_sql($mark_sql);

// ============================================================
// 6. RESUBMISSIONS AWAITING REVIEW
//    Case 1: Normal resubmission (attemptnumber > 0) not yet graded.
//    Case 2: Re-upload on same attempt — learner uploaded NEW files
//            AFTER receiving a grade. Verified by checking the {files}
//            table for submission files created after the grading time.
//            This catches the common scenario where attemptreopenmethod
//            didn't fire and the learner re-uploaded on attemptnumber = 0.
// ============================================================
$resub_sql = "SELECT COUNT(DISTINCT sub.id)
    FROM {$prefix}groups_members gm_t
    JOIN {$prefix}groups g ON g.id = gm_t.groupid
    JOIN {$prefix}groups_members gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
    JOIN {$prefix}role_assignments ra ON ra.userid = gm_s.userid
    JOIN {$prefix}context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
    JOIN {$prefix}role r ON r.id = ra.roleid AND r.shortname = 'student'
    JOIN {$prefix}user u ON u.id = gm_s.userid AND u.suspended = 0 AND u.deleted = 0
    JOIN {$prefix}assign a ON a.course = g.courseid
    JOIN {$prefix}modules mod_r ON mod_r.name = 'assign'
    JOIN {$prefix}course_modules cm_r ON cm_r.instance = a.id AND cm_r.course = a.course AND cm_r.module = mod_r.id AND cm_r.visible = 1
    JOIN {$prefix}assign_submission sub ON sub.assignment = a.id AND sub.userid = gm_s.userid
        AND sub.status IN ('submitted', 'draft') AND sub.latest = 1
    LEFT JOIN {$prefix}assign_grades gr ON gr.assignment = a.id AND gr.userid = gm_s.userid
        AND gr.attemptnumber = sub.attemptnumber
    WHERE gm_t.userid = {$tutorid}
        AND a.name NOT LIKE '%IAG%'
        AND a.name NOT LIKE '%ID Proof%'
        AND (
            (sub.attemptnumber > 0 AND (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0))
            OR
            (gr.id IS NOT NULL AND gr.grade IS NOT NULL AND gr.grade >= 0
             AND EXISTS (
                 SELECT 1 FROM {$prefix}files f
                 WHERE f.component = 'assignsubmission_file'
                 AND f.filearea = 'submission_files'
                 AND f.itemid = sub.id
                 AND f.filename <> '.'
                 AND f.timecreated > gr.timemodified
             ))
        )";

$resubmissions = $DB->count_records_sql($resub_sql);

// ============================================================
// 7. OVERDUE — ungraded submissions submitted > 72 hours ago (SLA breach)
// ============================================================
$o_sql = "SELECT COUNT(DISTINCT sub.id)
    FROM {$prefix}groups_members gm_t
    JOIN {$prefix}groups g ON g.id = gm_t.groupid
    JOIN {$prefix}groups_members gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
    JOIN {$prefix}user u ON u.id = gm_s.userid AND u.suspended = 0 AND u.deleted = 0
    JOIN {$prefix}role_assignments ra ON ra.userid = u.id
    JOIN {$prefix}context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
    JOIN {$prefix}role r ON r.id = ra.roleid AND r.shortname = 'student'
    JOIN {$prefix}assign a ON a.course = g.courseid
    JOIN {$prefix}modules mod_o ON mod_o.name = 'assign'
    JOIN {$prefix}course_modules cm_o ON cm_o.instance = a.id AND cm_o.course = a.course AND cm_o.module = mod_o.id AND cm_o.visible = 1
    JOIN {$prefix}assign_submission sub ON sub.assignment = a.id AND sub.userid = u.id
        AND sub.status IN ('submitted', 'draft') AND sub.latest = 1
    LEFT JOIN {$prefix}assign_grades gr ON gr.assignment = a.id AND gr.userid = u.id
        AND gr.attemptnumber = sub.attemptnumber
    WHERE gm_t.userid = {$tutorid}
        AND a.name NOT LIKE '%IAG%'
        AND a.name NOT LIKE '%ID Proof%'
        AND (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0)
        AND sub.timemodified <= {$sla_threshold}";

$overdue_assigns = $DB->count_records_sql($o_sql);

// ============================================================
// 8. IMMINENT — assignments due within 3 days (not yet submitted)
// ============================================================
$three_days = strtotime('+3 days');
$m_sql = "SELECT COUNT(DISTINCT CONCAT(a.id, '-', u.id))
    FROM {$prefix}groups_members gm_t
    JOIN {$prefix}groups g ON g.id = gm_t.groupid
    JOIN {$prefix}groups_members gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
    JOIN {$prefix}user u ON u.id = gm_s.userid AND u.suspended = 0 AND u.deleted = 0
    JOIN {$prefix}role_assignments ra ON ra.userid = u.id
    JOIN {$prefix}context ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
    JOIN {$prefix}role r ON r.id = ra.roleid AND r.shortname = 'student'
    JOIN {$prefix}assign a ON a.course = g.courseid
    JOIN {$prefix}modules mod_i ON mod_i.name = 'assign'
    JOIN {$prefix}course_modules cm_i ON cm_i.instance = a.id AND cm_i.course = a.course AND cm_i.module = mod_i.id AND cm_i.visible = 1
    LEFT JOIN {$prefix}assign_submission sub ON sub.assignment = a.id AND sub.userid = u.id AND sub.latest = 1
    WHERE gm_t.userid = {$tutorid}
        AND a.duedate > 0 AND a.duedate >= {$now} AND a.duedate <= {$three_days}
        AND a.name NOT LIKE '%IAG%'
        AND a.name NOT LIKE '%ID Proof%'
        AND (sub.id IS NULL OR sub.status <> 'submitted')";

$imm_assigns = $DB->count_records_sql($m_sql);

// ============================================================
// 9. GRADING STATS — pass rate + graded count (this tutor's grades)
// ============================================================
$graded_count = 0;
$pass_count = 0;
$refer_count = 0;

if (!empty($learner_ids)) {
    list($gl_sql, $gl_params) = $DB->get_in_or_equal($learner_ids, SQL_PARAMS_NAMED, 'gl');
    $gl_params['graderid'] = $tutorid;

    $grade_stats = $DB->get_records_sql(
        "SELECT gg.id, gg.finalgrade, gi.scaleid, sc.scale
         FROM {grade_grades} gg
         JOIN {grade_items} gi ON gi.id = gg.itemid AND gi.itemmodule = 'assign'
         LEFT JOIN {scale} sc ON sc.id = gi.scaleid
         WHERE gg.usermodified = :graderid
             AND gg.userid {$gl_sql}
             AND gg.finalgrade IS NOT NULL AND gg.finalgrade > 0",
        $gl_params
    );

    foreach ($grade_stats as $gs) {
        $graded_count++;
        if (!empty($gs->scale)) {
            $scale_items = explode(',', $gs->scale);
            $idx = (int) $gs->finalgrade - 1;
            if (isset($scale_items[$idx]) && trim($scale_items[$idx]) === 'Pass') {
                $pass_count++;
            } else {
                $refer_count++;
            }
        }
    }
}

$pass_rate = ($graded_count > 0) ? round(($pass_count / $graded_count) * 100) : 0;

// ============================================================
// 10. AVERAGE TURNAROUND (days between submission and grading)
//     Optionally filtered from a configurable reset date.
// ============================================================
$avg_turnaround = 0;
$turnaround_since = '';
$reset_date_raw = get_config('local_learner', 'turnaround_reset_date');
$reset_timestamp = 0;
if (!empty($reset_date_raw)) {
    $parsed = strtotime($reset_date_raw);
    if ($parsed !== false) {
        $reset_timestamp = $parsed;
        $turnaround_since = userdate($reset_timestamp, '%d %b %Y');
    }
}

if (!empty($learner_ids)) {
    list($at_sql, $at_params) = $DB->get_in_or_equal($learner_ids, SQL_PARAMS_NAMED, 'at');
    $at_params['graderid'] = $tutorid;

    $turnaround_where = '';
    if ($reset_timestamp > 0) {
        $turnaround_where = ' AND ag.timemodified >= :resetdate';
        $at_params['resetdate'] = $reset_timestamp;
    }

    $turnaround_result = $DB->get_record_sql(
        "SELECT AVG(ag.timemodified - sub.timemodified) AS avg_seconds
         FROM {assign_grades} ag
         JOIN {assign_submission} sub ON sub.assignment = ag.assignment
             AND sub.userid = ag.userid AND sub.latest = 1
         WHERE ag.grader = :graderid
             AND ag.userid {$at_sql}
             AND ag.grade IS NOT NULL AND ag.grade >= 0
             AND ag.timemodified > sub.timemodified{$turnaround_where}",
        $at_params
    );
    if ($turnaround_result && $turnaround_result->avg_seconds > 0) {
        $avg_turnaround = round($turnaround_result->avg_seconds / 86400, 1); // Convert to days.
    }
}

// ============================================================
// 11. DRAFT FEEDBACK PENDING (from local_draftfeedback plugin)
// ============================================================
$draft_feedback_count = 0;
if ($DB->get_manager()->table_exists('local_draftfeedback')) {
    $draft_feedback_count = \local_draftfeedback\manager::count_pending_drafts_for_tutor($tutorid);
}

// ============================================================
// 12. RECENT NEWS (from local_news plugin)
// ============================================================
$recent_news = [];
$unread_news_count = 0;
$has_news = false;

$news_table_exists = $DB->get_manager()->table_exists('local_news');

if ($news_table_exists) {
    // Get 5 most recent published news items with read status.
    $news_sql = "SELECT n.id, n.title, n.category, n.important, n.requires_acknowledgement,
                        n.publishdate, n.timecreated,
                        nr.id AS read_id, nr.acknowledged
                 FROM {local_news} n
                 LEFT JOIN {local_news_read} nr ON nr.newsid = n.id AND nr.userid = :userid
                 WHERE n.published = 1
                   AND n.publishdate <= :now1
                   AND (n.expirydate IS NULL OR n.expirydate > :now2)
                 ORDER BY n.publishdate DESC
                 LIMIT 5";

    $news_records = $DB->get_records_sql($news_sql, [
        'userid' => $tutorid,
        'now1' => $now,
        'now2' => $now,
    ]);

    // Get category labels.
    $category_labels = [
        'update' => get_string('category_update', 'local_news'),
        'announcement' => get_string('category_announcement', 'local_news'),
        'maintenance' => get_string('category_maintenance', 'local_news'),
        'policy_change' => get_string('category_policy_change', 'local_news'),
    ];

    foreach ($news_records as $nr) {
        $is_unread = empty($nr->read_id);
        if ($is_unread) {
            $unread_news_count++;
        }

        $recent_news[] = [
            'id' => $nr->id,
            'title' => $nr->title,
            'category' => $category_labels[$nr->category] ?? $nr->category,
            'category_class' => 'td-cat-' . str_replace('_', '-', $nr->category),
            'is_important' => (bool) $nr->important,
            'is_unread' => $is_unread,
            'requires_ack' => (bool) $nr->requires_acknowledgement,
            'is_acknowledged' => !empty($nr->acknowledged),
            'formatted_date' => userdate($nr->publishdate, '%d %b %Y'),
            'view_url' => (new moodle_url('/local/news/view.php', ['id' => $nr->id]))->out(false),
        ];
    }
    $has_news = !empty($recent_news);
}

// ============================================================
// BUILD TEMPLATE CONTEXT
// ============================================================
$templatecontext = [
    // KPI cards - top row.
    'your_learners'     => $total_learners,
    'enrolled_courses'  => $enrolled_courses,
    'yet_to_grade'      => $yet_to_grade,
    'resubmissions'     => $resubmissions,
    'total_assignments' => $total_assignments,
    'overdue_assigns'   => $overdue_assigns,
    'imm_assigns'       => $imm_assigns,
    'graded'            => $graded_count,
    'pass_rate'         => $pass_rate,
    'pass_count'        => $pass_count,
    'refer_count'       => $refer_count,
    'avg_turnaround'    => $avg_turnaround,
    'turnaround_since'  => $turnaround_since,
    'has_turnaround_since' => !empty($turnaround_since),

    // Learner activity breakdown.
    'active_count'    => $active_count,
    'inactive_count'  => $inactive_count,
    'created_count'   => $created_count,
    'suspended_count' => $suspended_count,

    // Per-course caseload.
    'courses' => $courselist,

    // URLs.
    'yet_to_grade_url'      => new moodle_url('/local/learner/markallocation.php?action=mark'),
    'resubmissions_url'     => new moodle_url('/local/learner/markallocation.php?action=resub'),
    'overdue_url'           => new moodle_url('/local/learner/markallocation.php?action=overdue'),
    'imm_url'               => new moodle_url('/local/learner/markallocation.php?action=imm'),
    'inactive_learners_link' => new moodle_url('/local/learner/tutorlearners.php', ['id' => $USER->id, 'action' => 'inactive']),
    'progressions_url'      => new moodle_url('/local/learnerprogression/index.php'),
    'mycourse_link'         => new moodle_url('/my/courses.php'),

    // Draft feedback.
    'draft_feedback_count' => $draft_feedback_count,
    'draft_feedback_url'   => new moodle_url('/local/draftfeedback/index.php'),
    'has_draft_feedback'   => ($draft_feedback_count > 0),

    // News.
    'recent_news'          => $recent_news,
    'has_news'             => $has_news,
    'unread_news_count'    => $unread_news_count,
    'news_link'            => new moodle_url('/local/news/index.php'),

    // AI Check Tool.
    'ai_checks_url'        => new moodle_url('/local/learner/aichecks.php'),
];

echo $OUTPUT->render_from_template('local_learner/tutordash', $templatecontext);

echo $OUTPUT->footer();
