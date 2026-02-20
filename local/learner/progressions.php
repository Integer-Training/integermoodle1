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
 * Learner Progressions — Current % with unit-wise breakdown.
 *
 * Accessible to managers (all learners) and tutors (their group learners).
 *
 * @package   local_learner
 * @copyright 2026 Epearl Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

$context = context_system::instance();

$PAGE->set_url(new moodle_url('/local/learner/progressions.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('learnerprogressions', 'local_learner'));

$PAGE->requires->jquery();

// DataTables core (same local files used by admindash).
$PAGE->requires->js('/local/learner/js/jquery.dataTables.min.js', true);
$PAGE->requires->css('/local/learner/js/jquery.dataTables.min.css', true);

// CDN includes for Bootstrap Icons.
echo '<!-- Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';

echo $OUTPUT->header();

// ===== ROLE DETECTION =====
$is_manager = has_capability('local/learner:view', $context);
$is_tutor = false;
$learner_ids = [];

$suspended_count = 0;

if ($is_manager) {
    // Manager sees ALL learners with student role (active only for table).
    $students = $DB->get_records_sql(
        "SELECT DISTINCT ra.userid
         FROM {role_assignments} ra
         JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
         JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
         JOIN {user} u ON u.id = ra.userid AND u.suspended = 0 AND u.deleted = 0"
    );
    $learner_ids = array_keys($students);
    // Count suspended learners for KPI card.
    $suspended_count = $DB->count_records_sql(
        "SELECT COUNT(DISTINCT ra.userid)
         FROM {role_assignments} ra
         JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
         JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
         JOIN {user} u ON u.id = ra.userid AND u.suspended = 1 AND u.deleted = 0"
    );
} else {
    // Check if tutor (teacher role).
    $teacher_role = $DB->get_record('role', ['shortname' => 'teacher']);
    if ($teacher_role && $DB->record_exists('role_assignments', [
        'roleid' => $teacher_role->id,
        'userid' => $USER->id
    ])) {
        $is_tutor = true;
        // Get tutor's learners via shared group membership (active only for table).
        $tutor_learners = $DB->get_records_sql(
            "SELECT DISTINCT gm_s.userid
             FROM {groups_members} gm_t
             JOIN {groups} g ON g.id = gm_t.groupid
             JOIN {groups_members} gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
             JOIN {role_assignments} ra ON ra.userid = gm_s.userid
             JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
             JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
             JOIN {user} u ON u.id = gm_s.userid AND u.suspended = 0 AND u.deleted = 0
             WHERE gm_t.userid = :tutorid",
            ['tutorid' => $USER->id]
        );
        $learner_ids = array_keys($tutor_learners);
        // Count suspended learners for KPI card.
        $suspended_count = $DB->count_records_sql(
            "SELECT COUNT(DISTINCT gm_s.userid)
             FROM {groups_members} gm_t
             JOIN {groups} g ON g.id = gm_t.groupid
             JOIN {groups_members} gm_s ON gm_s.groupid = g.id AND gm_s.userid <> gm_t.userid
             JOIN {role_assignments} ra ON ra.userid = gm_s.userid
             JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50 AND ctx.instanceid = g.courseid
             JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'student'
             JOIN {user} u ON u.id = gm_s.userid AND u.suspended = 1 AND u.deleted = 0
             WHERE gm_t.userid = :tutorid",
            ['tutorid' => $USER->id]
        );
    }
}

if (!$is_manager && !$is_tutor) {
    throw new moodle_exception('nopermission', 'error', '', null, 'You do not have permission to view this page.');
}

// ===== DATA RETRIEVAL =====
$templatecontext = [
    'is_manager' => $is_manager,
    'wwwroot' => $CFG->wwwroot,
    'learners' => [],
    'total_learners_count' => 0,
    'active_count' => 0,
    'inactive_count' => 0,
    'created_count' => 0,
    'suspended_count' => $suspended_count,
];

if (!empty($learner_ids)) {
    // Build IN clause with named params.
    list($id_sql, $id_params) = $DB->get_in_or_equal($learner_ids, SQL_PARAMS_NAMED, 'uid');

    // ===== Get user info: start dates, last access =====
    $user_dates = $DB->get_records_sql(
        "SELECT id, timecreated, lastaccess, lastlogin, CONCAT(firstname, ' ', lastname) AS fullname
         FROM {user}
         WHERE id {$id_sql}",
        $id_params
    );

    // ===== SINGLE QUERY: Per-assignment detail per learner per course =====
    // Check if case study reviews table exists (backward compat before upgrade).
    $csr_table_exists = $DB->get_manager()->table_exists('local_casestudy_reviews');
    $csr_select = $csr_table_exists ? ", cm.id AS cmid, csr.status AS review_status, csr.feedback AS review_feedback" : ", cm.id AS cmid";
    $csr_join = $csr_table_exists ? "LEFT JOIN {local_casestudy_reviews} csr ON csr.assignid = a.id AND csr.userid = u.id" : "";

    // Case study CASE WHEN branches — with review status if table exists.
    if ($csr_table_exists) {
        $cs_case = "
                         WHEN a.name LIKE '%Case Stud%' AND csr.status = 'approved' THEN 'Approved'
                         WHEN a.name LIKE '%Case Stud%' AND csr.status = 'rejected' THEN 'Rejected'
                         WHEN a.name LIKE '%Case Stud%' AND csr.status = 'resubmitted'
                              AND sub.id IS NOT NULL AND (sub.status = 'submitted' OR sub.status = 'draft')
                              THEN 'Resubmitted'
                         WHEN a.name LIKE '%Case Stud%' AND sub.id IS NOT NULL
                              AND (sub.status = 'submitted' OR sub.status = 'draft') AND csr.id IS NULL
                              THEN 'Submitted'
                         WHEN a.name LIKE '%Case Stud%' THEN 'Not Submitted'";
    } else {
        $cs_case = "
                         WHEN a.name LIKE '%Case Stud%' AND sub.id IS NOT NULL
                              AND (sub.status = 'submitted' OR sub.status = 'draft')
                         THEN 'Submitted'
                         WHEN a.name LIKE '%Case Stud%'
                         THEN 'Not Submitted'";
    }

    $query = "SELECT CONCAT(u.id, '-', a.id) AS rowkey,
                     u.id AS userid,
                     CONCAT(u.firstname, ' ', u.lastname) AS fullname,
                     a.course AS courseid,
                     c.fullname AS coursename,
                     a.id AS assignid,
                     a.name AS assignname
                     {$csr_select},
                     CASE {$cs_case}
                         WHEN gg.finalgrade IS NOT NULL
                          AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(sc.scale, ',',
                              CAST(gg.finalgrade AS UNSIGNED)), ',', -1)) = 'Pass'
                         THEN 'Pass'
                         WHEN gg.finalgrade IS NOT NULL AND gg.finalgrade > 0
                         THEN 'Refer'
                         WHEN ag.grade IS NOT NULL AND ag.grade >= 0
                          AND TRIM(SUBSTRING_INDEX(SUBSTRING_INDEX(sc.scale, ',',
                              CAST(ag.grade AS UNSIGNED)), ',', -1)) = 'Pass'
                         THEN 'Pass'
                         WHEN ag.grade IS NOT NULL AND ag.grade > 0
                         THEN 'Refer'
                         WHEN sub.id IS NOT NULL AND sub.status = 'submitted'
                         THEN 'Pending Grading'
                         WHEN sub.id IS NOT NULL AND sub.status = 'draft'
                         THEN 'Submitted'
                         ELSE 'Not Submitted'
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
              {$csr_join}
              WHERE u.id {$id_sql}
                AND a.name NOT LIKE '%IAG%'
                AND a.name NOT LIKE '%ID Proof%'
              ORDER BY u.id, a.course, a.name";

    $results = $DB->get_records_sql($query, $id_params);

    // ===== PHP AGGREGATION =====
    $learner_data = [];

    foreach ($results as $row) {
        $uid = (int) $row->userid;
        $cid = (int) $row->courseid;
        $is_passed = ($row->grade_status === 'Pass');
        // "Submitted" in the broad sense: formally submitted for grading (Pass, Refer, or Pending Grading).
        $is_submitted = in_array($row->grade_status, ['Pass', 'Refer', 'Pending Grading']);

        if (!isset($learner_data[$uid])) {
            $learner_data[$uid] = [
                'fullname' => $row->fullname,
                'total_passed' => 0,
                'total_submitted' => 0,
                'total_all' => 0,
                'courses' => [],
            ];
        }

        if (!isset($learner_data[$uid]['courses'][$cid])) {
            $learner_data[$uid]['courses'][$cid] = [
                'name' => $row->coursename,
                'total' => 0,
                'passed' => 0,
                'submitted' => 0,
                'assignments' => [],
            ];
        }

        $cd = &$learner_data[$uid]['courses'][$cid];
        $is_case_study = (stripos($row->assignname, 'Case Stud') !== false);

        if (!$is_case_study) {
            $cd['total']++;
            if ($is_passed) {
                $cd['passed']++;
            }
            if ($is_submitted) {
                $cd['submitted']++;
            }
        }

        // Individual assignment detail (case studies always appear in expandable rows).
        $assign_entry = [
            'name' => $row->assignname,
            'status' => $row->grade_status,
            'assignid' => (int) $row->assignid,
            'userid' => $uid,
            'cmid' => isset($row->cmid) ? (int) $row->cmid : 0,
            'is_case_study' => $is_case_study,
        ];
        if ($is_case_study && $csr_table_exists && !empty($row->review_feedback)) {
            $assign_entry['feedback'] = $row->review_feedback;
        }
        $cd['assignments'][] = $assign_entry;

        if (!$is_case_study) {
            $learner_data[$uid]['total_all']++;
            if ($is_passed) {
                $learner_data[$uid]['total_passed']++;
            }
            if ($is_submitted) {
                $learner_data[$uid]['total_submitted']++;
            }
        }
    }

    // Include learners with zero assignments.
    foreach ($user_dates as $u) {
        if (!isset($learner_data[(int) $u->id])) {
            $learner_data[(int) $u->id] = [
                'fullname' => $u->fullname,
                'total_passed' => 0,
                'total_submitted' => 0,
                'total_all' => 0,
                'courses' => [],
            ];
        }
    }

    // Build template array.
    $learners = [];
    $active_count = 0;
    $inactive_count = 0;
    $created_count = 0;
    $thirty_days_ago = time() - (30 * 24 * 60 * 60);

    foreach ($learner_data as $uid => $ld) {
        $total = $ld['total_all'];
        $passed = $ld['total_passed'];
        $overall_current = ($total > 0) ? round(($passed / $total) * 100) : 0;

        // Learner activity status from user record.
        $activity_status = 'Active';
        if (isset($user_dates[$uid])) {
            $u = $user_dates[$uid];
            if (empty($u->lastlogin) && empty($u->lastaccess)) {
                $created_count++;
                $activity_status = 'Created';
            } else if ($u->lastaccess < $thirty_days_ago) {
                $inactive_count++;
                $activity_status = 'Inactive';
            } else {
                $active_count++;
                $activity_status = 'Active';
            }
        }

        // Start date from user.timecreated.
        $start_date = '';
        if (isset($user_dates[$uid]) && $user_dates[$uid]->timecreated > 0) {
            $start_date = date('d M Y', $user_dates[$uid]->timecreated);
        }

        // Per-course detail with assignments (natural sort so Unit 2 < Unit 10).
        $course_details = [];
        foreach ($ld['courses'] as $cid => $cd) {
            $c_current = ($cd['total'] > 0) ? round(($cd['passed'] / $cd['total']) * 100) : 0;
            $sorted_assignments = $cd['assignments'];
            usort($sorted_assignments, function($a, $b) {
                // Extract leading number from names like "Unit 1:", "Unit : 8", "Unit 10 Learner".
                $na = preg_match('/(\d+)/', $a['name'], $ma) ? (int) $ma[1] : PHP_INT_MAX;
                $nb = preg_match('/(\d+)/', $b['name'], $mb) ? (int) $mb[1] : PHP_INT_MAX;
                if ($na !== $nb) {
                    return $na - $nb;
                }
                return strnatcasecmp($a['name'], $b['name']);
            });
            $course_details[] = [
                'name' => $cd['name'],
                'current' => $c_current,
                'passed' => $cd['passed'],
                'submitted' => $cd['submitted'],
                'total' => $cd['total'],
                'assignments' => $sorted_assignments,
            ];
        }

        $learners[] = [
            'fullname' => $ld['fullname'],
            'start_date' => $start_date,
            'activity_status' => $activity_status,
            'overall_current' => $overall_current,
            'passed_assignments' => $passed,
            'submitted_assignments' => $ld['total_submitted'],
            'total_assignments' => $total,
            'detail_json' => json_encode($course_details),
        ];
    }

    $total_learners = count($learners);

    $templatecontext['learners'] = $learners;
    $templatecontext['total_learners_count'] = $total_learners;
    $templatecontext['active_count'] = $active_count;
    $templatecontext['inactive_count'] = $inactive_count;
    $templatecontext['created_count'] = $created_count;
}

$templatecontext['sesskey'] = sesskey();

echo $OUTPUT->render_from_template('local_learner/progressions', $templatecontext);

echo $OUTPUT->footer();
