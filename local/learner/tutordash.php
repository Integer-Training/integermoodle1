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
 * phpcs:disable moodle.Files.RequireLogin.Missing
 *
 * index file
 *
 * introduced 23/05/17 17:59
 *
 * @package   local_learner
 * @copyright 2025
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();
$defaultpage = get_default_home_page();
$context = context_system::instance();
//
$PAGE->set_url(new moodle_url('/local/learner/tutordash.php'));
$PAGE->set_context($context);
$PAGE->set_title('Dashboard');
//
$PAGE->requires->jquery();
//
echo '<!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
echo '<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/data.js"></script>
<script src="https://code.highcharts.com/modules/drilldown.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<script src="https://code.highcharts.com/themes/adaptive.js"></script>';
echo $OUTPUT->header();

$templatecontext['mycourse_link'] = new moodle_url('/my/courses.php');
//teachers list
//get teachers list hers
$userid = $USER->id; // user ID you want
$courses = enrol_get_users_courses($userid);
$templatecontext['enrolled_courses'] = count($courses);
//their students
/*$sql = "SELECT s.id AS studentid,
		    c.id AS courseid,
		    c.fullname AS coursename,
		    g.id AS groupid,
		    g.name AS groupname,
		    CONCAT(s.firstname, ' ', s.lastname) AS studentname,
		    s.email
		FROM r6ua_groups_members gm_teacher
		JOIN r6ua_groups g ON g.id = gm_teacher.groupid
		JOIN r6ua_course c ON c.id = g.courseid

		-- teacher
		JOIN r6ua_user t ON t.id = ".$USER->id."

		-- students in SAME group
		JOIN r6ua_groups_members gm_students ON gm_students.groupid = g.id
		JOIN r6ua_user s ON s.id = gm_students.userid

		-- student role check
		JOIN r6ua_role_assignments ra ON ra.userid = s.id
		JOIN r6ua_context ctx ON ctx.id = ra.contextid
		JOIN r6ua_role r ON r.id = ra.roleid

		WHERE
		    gm_teacher.userid = ".$USER->id."
		    AND ctx.contextlevel = 50
		    AND ctx.instanceid = c.id
		    AND r.shortname = 'student'

		ORDER BY c.fullname, g.name, s.firstname";
$your_learners = $DB->get_records_sql($sql);
$templatecontext['your_learners'] = count($your_learners);*/
//start
$allcourses = array();
foreach ($courses as $course) {
  $allcourses[] = $course->id;
}
$assign_ments = $DB->get_records_sql('select * from {assign} where course in('.implode(',',$allcourses).')');
$all_assigns = array();
foreach($assign_ments as $assign){
    $row = array();
    //$row[] = $assign->id;
    $all_assigns[] = $assign->id;
} 
//
$templatecontext['total_assignments'] = count($assign_ments);
//ends
//
$mark_sql = "SELECT
			    COUNT(DISTINCT sub.id) AS total_notgraded
			FROM r6ua_groups_members gm_t
			JOIN r6ua_groups g
			    ON g.id = gm_t.groupid
			JOIN r6ua_groups_members gm_s
			    ON gm_s.groupid = g.id
			    AND gm_s.userid <> gm_t.userid
			JOIN r6ua_role_assignments ra
			    ON ra.userid = gm_s.userid
			JOIN r6ua_context ctx
			    ON ctx.id = ra.contextid
			    AND ctx.contextlevel = 50
			    AND ctx.instanceid = g.courseid
			JOIN r6ua_role r
			    ON r.id = ra.roleid
			    AND r.shortname = 'student'
			JOIN r6ua_assign a
			    ON a.course = g.courseid
			JOIN r6ua_assign_submission sub
			    ON sub.assignment = a.id
			    AND sub.userid = gm_s.userid
			    AND sub.status = 'submitted'
			LEFT JOIN r6ua_assign_grades gr
			    ON gr.assignment = a.id
			    AND gr.userid = gm_s.userid
			WHERE
			    gm_t.userid = ".$USER->id."
			    AND a.name NOT LIKE '%IAG%'
			    AND (gr.id IS NULL OR gr.grade IS NULL)";
//
$templatecontext['yet_to_grade'] = $DB->count_records_sql($mark_sql);
$templatecontext['yet_to_grade_url'] = new moodle_url('/local/learner/markallocation.php?action=mark');
$templatecontext['overdue_url'] = new moodle_url('/local/learner/markallocation.php?action=overdue');
$templatecontext['imm_url'] = new moodle_url('/local/learner/markallocation.php?action=imm');
//
$courselist = array();
$inv = array();
foreach ($courses as $course) {
   $row['coursename'] = $course->fullname;
   $sql = "SELECT s.id AS studentid,
		    c.id AS courseid,
		    c.fullname AS coursename,
		    g.id AS groupid,
		    g.name AS groupname,
		    CONCAT(s.firstname, ' ', s.lastname) AS studentname,
		    s.email
		FROM r6ua_groups_members gm_teacher
		JOIN r6ua_groups g ON g.id = gm_teacher.groupid
		JOIN r6ua_course c ON c.id = g.courseid
		-- teacher
		JOIN r6ua_user t ON t.id = ".$USER->id."
		JOIN r6ua_groups_members gm_students ON gm_students.groupid = g.id
		JOIN r6ua_user s ON s.id = gm_students.userid
		JOIN r6ua_role_assignments ra ON ra.userid = s.id
		JOIN r6ua_context ctx ON ctx.id = ra.contextid
		JOIN r6ua_role r ON r.id = ra.roleid
		WHERE
		    gm_teacher.userid = ".$USER->id."
		    AND ctx.contextlevel = 50
		    AND ctx.instanceid = ".$course->id."
		    AND r.shortname = 'student'

		ORDER BY c.fullname, g.name, s.firstname";
	//
	$caseload = $DB->get_records_sql($sql);
	$row['caseload'] = count($caseload);
	$row['users_link'] = new moodle_url('/user/index.php',['id'=>$course->id]);
    $courselist[] = $row;
}
$templatecontext['courses'] = $courselist;
$your_learners = array();
foreach ($courselist as $arr) {
	$your_learners[] = $arr['caseload'];
}
$templatecontext['your_learners'] = array_sum($your_learners);
//for charts
$sql = 'select count(id) from r6ua_assign_grades where grader='.$USER->id;
$templatecontext['graded'] = $DB->count_records_sql($sql);
//for overdue
$o_sql = "SELECT
			   COUNT(sub.id) AS overdue_count
			FROM r6ua_groups_members gm_t
			JOIN r6ua_groups g
			    ON g.id = gm_t.groupid
			-- students in teacher’s groups
			JOIN r6ua_groups_members gm_s
			    ON gm_s.groupid = g.id
			    AND gm_s.userid <> gm_t.userid
			JOIN r6ua_user u
			    ON u.id = gm_s.userid
			-- ensure STUDENT role in COURSE context
			JOIN r6ua_role_assignments ra
			    ON ra.userid = u.id
			JOIN r6ua_context ctx
			    ON ctx.id = ra.contextid
			    AND ctx.contextlevel = 50
			    AND ctx.instanceid = g.courseid
			JOIN r6ua_role r
			    ON r.id = ra.roleid
			    AND r.shortname = 'student'
			-- assignments with passed due date
			JOIN r6ua_assign a
			    ON a.course = g.courseid
			-- ONLY submitted attempts cancel overdue
            JOIN r6ua_assign_submission sub
			    ON sub.assignment = a.id
			    AND sub.userid = u.id
			LEFT  JOIN r6ua_assign_grades gr
			    ON gr.assignment = a.id
			    AND gr.userid = gm_s.userid  AND sub.userid = gr.userid
			WHERE
			    gm_t.userid = ".$USER->id."
			    AND sub.status = 'submitted'
			    AND a.name NOT LIKE '%IAG%'
			    AND (gr.id IS  NULL OR gr.grade IS  NULL)
			    AND sub.timemodified > 0 AND sub.timemodified IS NOT NULL
			    AND sub.timemodified <= ".strtotime('now')."";
$overdue_assigns = $DB->count_records_sql($o_sql);
$templatecontext['overdue_assigns'] = $overdue_assigns; 
//for immentiate
$m_sql = "SELECT
                count(concat(a.id,'-',u.id))
                FROM r6ua_groups_members gm_t
                JOIN r6ua_groups g
                    ON g.id = gm_t.groupid
                -- students in teacher’s groups
                JOIN r6ua_groups_members gm_s
                    ON gm_s.groupid = g.id
                    AND gm_s.userid <> gm_t.userid
                JOIN r6ua_user u
                    ON u.id = gm_s.userid
                -- ensure STUDENT role in COURSE context
                JOIN r6ua_role_assignments ra
                    ON ra.userid = u.id
                JOIN r6ua_context ctx
                    ON ctx.id = ra.contextid
                    AND ctx.contextlevel = 50
                    AND ctx.instanceid = g.courseid
                JOIN r6ua_role r
                    ON r.id = ra.roleid
                    AND r.shortname = 'student'
                -- assignments with passed due date
                JOIN  r6ua_assign a
                 ON a.course = g.courseid
                WHERE
                 gm_t.userid = ".$USER->id." AND 
                a.course in (".implode(',',$allcourses).")
                AND a.duedate > 0 AND a.duedate IS NOT NULL
                AND a.name NOT LIKE '%IAG%'
                AND a.duedate <= ".strtotime("+3 days")."
                AND a.duedate >= ".strtotime('now')."";

$imm_assigns = $DB->count_records_sql($m_sql);
$templatecontext['imm_assigns'] = $imm_assigns; 
//ends
//inactive learner
$ina_courselist = array();
foreach ($courses as $course) {
	$col = array();
    $in_sql = "SELECT DISTINCT
			    s.id AS studentid,
			    c.id AS courseid,
			    c.fullname AS coursename,
			    g.id AS groupid,
			    g.name AS groupname,
			    CONCAT(s.firstname, ' ', s.lastname) AS studentname,
			    s.email,
			    FROM_UNIXTIME(s.lastaccess) AS last_access
			FROM r6ua_groups_members gm_teacher
			JOIN r6ua_groups g 
			    ON g.id = gm_teacher.groupid
			JOIN r6ua_course c 
			    ON c.id = g.courseid

			-- students in same group
			JOIN r6ua_groups_members gm_students 
			    ON gm_students.groupid = g.id
			JOIN r6ua_user s 
			    ON s.id = gm_students.userid

			-- student role check
			JOIN r6ua_role_assignments ra 
			    ON ra.userid = s.id
			JOIN r6ua_context ctx 
			    ON ctx.id = ra.contextid
			JOIN r6ua_role r 
			    ON r.id = ra.roleid
		WHERE
		    gm_teacher.userid = ".$USER->id."
		    AND ctx.contextlevel = 50
		    AND ctx.instanceid = ".$course->id."
		    AND r.shortname = 'student'
		    AND s.deleted = 0
		    AND s.suspended = 0
		    AND (
                s.lastaccess IS NOT NULL AND s.lastaccess!=0
                AND s.lastaccess < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            )

		ORDER BY c.fullname, g.name, s.firstname";
	//
	$caseload_data = $DB->get_records_sql($in_sql);
	$col['caseload'] = count($caseload_data);
    $ina_courselist[] = $col;
}
//
$inactive_learners = array();
foreach ($ina_courselist as $arr) {
	$inactive_learners[] = $arr['caseload'];
}
$templatecontext['inactive_learners_link'] = new moodle_url('/local/learner/tutorlearners.php',['id'=>$USER->id,'action'=>'inactive']);
$templatecontext['inactive_learners'] = array_sum($inactive_learners);
//

echo $OUTPUT->render_from_template('local_learner/tutordash', $templatecontext);
//
echo $OUTPUT->footer();

echo '<style>
        .wrapper-course {
            margin-top:-30px;
            padding: 0px 10px !important;
        }
        .highcharts-a11y-proxy-element{
        	display:none !important;
        }
        
        #hourscontainer{
        	height:250px !important;
        }
        .highcharts-credits{
        	display:none !important;
        }
        .card { border-radius:16px; box-shadow:0 8px 24px rgba(0,0,0,.05); }
	    .stat-icon { width:44px;height:44px;display:flex;align-items:center;justify-content:center;border-radius:50%; }
	    .map-dot { width:12px;height:12px;border-radius:50%; position:absolute; }
	    .bg-success{background-color: rgba(var(--bs-success-rgb), var(--bs-bg-opacity)) !important;}
	    .bg-info{background-color: rgba(var(--bs-info-rgb), var(--bs-bg-opacity)) !important;}
	    .card-noborder{
	    	 border:none !important;
	     }
         .highcharts-no-tooltip.highcharts-button.highcharts-contextbutton.highcharts-button-normal {
		    display: none !important;
		}
		.highcharts-button-box{
        	display:none !important;
        }
        .header-right{background:linear-gradient(90deg,#8fb28a,#c7d916);color:#fff;padding:12px 20px;border-radius:4px;font-size:20px;font-weight:500;
         }
        .stat-row{display:flex;justify-content:space-between;align-items:center;padding:18px 0;font-size:18px;color:#4a5a6a;
         }
        .badge-circle{width:44px;height:44px;background:#e55a5a;color:#fff;border-radius:50%;display:flex;align-items:center;
        	justify-content:center;font-size:18px;font-weight:500;
        }
        .header-left{background:linear-gradient(90deg,#0ea5c6,#8fb28a);color:#fff;padding:12px 20px;border-radius:4px;font-size:20px;font-weight:500;
        }
        /* Header Gradient */
        .header-gradient{
            background: linear-gradient(90deg, #1aa3d9 0%, #79b07a 100%);
            color:#fff;
            padding:20px 30px;
            border-radius:6px 6px 0 0;
        }

        .header-gradient h3{
            margin:0;
            font-size:22px;
            font-weight:600;
        }

        .header-gradient small{
            font-size:14px;
            opacity:0.85;
        }

        /* Content */
        .content-box{
            border:1px solid #e6e9ec;
            border-top:0;
            border-radius:0 0 6px 6px;
            padding:30px;
        }

        .table-header{
            color:#4a5f7a;
            font-weight:600;
            padding-bottom:10px;
        }

        .programme-row{
            padding:18px 0;
            border-bottom:1px solid #e6e9ec;
            align-items:center;
        }

        .programme-row:last-child{
            border-bottom:none;
        }

        .programme-title{
            font-size:16px;
            color:#4a5f7a;
        }

        /* Caseload Badge */
        .caseload-badge{
            width:48px;
            height:48px;
            background:#cbd63a;
            color:#fff;
            border-radius:50%;
            display:flex;
            align-items:center;
            justify-content:center;
            font-weight:600;
            font-size:16px;
        }
      </style>';    

    
