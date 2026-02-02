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
$PAGE->set_url(new moodle_url('/local/learner/mydash.php'));
$PAGE->set_context($context);
$PAGE->set_title('Dashboard');
//
$PAGE->requires->jquery();
//
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet" />';
echo '<script src="https://code.highcharts.com/highcharts.js"></script>
<script src="https://code.highcharts.com/modules/data.js"></script>
<script src="https://code.highcharts.com/modules/drilldown.js"></script>
<script src="https://code.highcharts.com/modules/exporting.js"></script>
<script src="https://code.highcharts.com/modules/export-data.js"></script>
<script src="https://code.highcharts.com/modules/accessibility.js"></script>
<script src="https://code.highcharts.com/themes/adaptive.js"></script>';
echo $OUTPUT->header();
// Renderable object (templatable)
//nav links
$templatecontext['dashlink'] = $CFG->wwwroot .'/my/dashboard.php';
$templatecontext['mylearnlink'] = $CFG->wwwroot .'/local/master/mylearn.php';
$templatecontext['catlink'] = $CFG->wwwroot .'/local/master/cat.php';
$templatecontext['speclink'] = $CFG->wwwroot .'/local/master/specialists.php';
//
//picture
//
$templatecontext['mycourse_link'] = new moodle_url('/my/courses.php');
//teachers list
//get teachers list hers
$role_id = 3;
$role_users = $DB->get_records('role_assignments',['roleid'=>$role_id,'contextid'=>1]);
$role_arr = [];
foreach($role_users as $users){
     $role_arr[]  = $users->userid;
}
$teachers = array();
foreach($role_arr as $key=>$val){
	//
	$row = array();
	$user_obj = $DB->get_record('user',['id'=>$val]);
	$row['fullname'] = $user_obj->firstname.' '.$user_obj->lastname;
	$user_picture = new user_picture($user_obj, array('size' => 100, 'class' => 'userpic', 'link'=>false));
	$user_picture = $user_picture->get_url($PAGE);
	$userpic = $user_picture->out();
	$row['userpic'] = $userpic;
	$row['message'] = new moodle_url('/message/index.php',['id'=>$user_obj->id]);
	$teachers[] = $row;
}
$templatecontext['teachers'] = $teachers;
$templatecontext['name'] = $USER->firstname;
$userid = $USER->id; // user ID you want
$courses = enrol_get_users_courses($userid);
$templatecontext['enrolled_courses'] = count($courses);
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
//ends
$all_dueassignments = $DB->get_records_sql('select * from {assign_submission} where userid='.$USER->id.' and assignment in('.implode(',',$all_assigns).') and (status = "draft" or status = "new")');
$templatecontext['all_dueassignments'] = count($all_dueassignments);
//
$all_compassignments = $DB->get_records_sql('select * from {assign_submission} where userid='.$USER->id.' and assignment in('.implode(',',$all_assigns).') and (status = "submitted")');
$templatecontext['all_compassignments'] = count($all_compassignments);
$templatecontext['all_assignments'] = count($all_assigns);
//
$courselist = array();
$inv = array();
foreach ($courses as $course) {
	
	$assign_ments = $DB->get_records('assign',['course'=>$course->id]);
	foreach($assign_ments as $assign){
        $row = array();
        //$row[] = $assign->id;
        $inv[] = $assign->id;
	} 
	$compact_ids = implode(',',$inv);
	$dueassignments = $DB->get_records_sql('select * from {assign_submission} where userid='.$USER->id.' and assignment in('.$compact_ids.') and (status = "draft" or status = "new")');

	//print_object($dueassignments);die;
	$compassignments = $DB->get_records_sql('select * from {assign_submission} where userid='.$USER->id.' and assignment in('.$compact_ids.') and (status = "submitted")');

   $row = array();
   $course_context = context_course::instance($course->id);
   $row['coursename'] = $course->fullname;
   $row['dueassignments'] = count($dueassignments);
   $row['completedassignments'] = count($compassignments);
   $row['enrolledusers'] = count_enrolled_users($course_context);
   $courselist[] = $row;
}
$templatecontext['courses'] = $courselist;
//for charts
$sql = "SELECT 
          log_month,
		 SEC_TO_TIME(SUM(diff_seconds)) AS total_time
		FROM (
		    SELECT 
		        userid,
		        DATE_FORMAT(FROM_UNIXTIME(timecreated), '%m') AS log_month,

		        IF(
		            @prev_user = userid 
		            AND @prev_month = DATE_FORMAT(FROM_UNIXTIME(timecreated), '%m'),
		            timecreated - @prev_time,
		            0
		        ) AS diff_seconds,

		        @prev_user := userid,
		        @prev_month := DATE_FORMAT(FROM_UNIXTIME(timecreated), '%m'),
		        @prev_time := timecreated

		    FROM mdl_logstore_standard_log
		    CROSS JOIN (SELECT @prev_user := NULL, @prev_month := NULL, @prev_time := NULL) vars

		    WHERE component = 'mod_hvp'
		      AND userid=".$USER->id."
		      AND YEAR(FROM_UNIXTIME(timecreated)) = YEAR(CURDATE())
		 

		    ORDER BY userid, timecreated
		) AS t

		GROUP BY userid, log_month
		ORDER BY log_month DESC, userid";
$results = $DB->get_records_sql($sql);

//
$monthsarr = ['01'=>'January','02'=>'February','03'=>'March','04'=>'April','05'=>'May','06'=>'June','07'=>'July','08'=>'August','09'=>'September','10'=>'October','11'=>'November','12'=>'December'];
//print_object($monthsarr);
/*foreach(){

}*/
$main_arr = [];
foreach($results as $rec){//cal
	$val = strtok($rec->total_time, ':');
	$calculation =  (round($val)/720)*100;
    $main_arr[$rec->log_month] = round($calculation);
}
//
foreach($monthsarr as $key=>$val){
    if($key == '01'){
        $templatecontext['January'] = ($main_arr[$key])?$main_arr[$key]:0;
        
	}
	//
	if($key == '02'){
        $templatecontext['February'] = ($main_arr[$key])?$main_arr[$key]:0;
        
	}
	//
	if($key == '03'){
		//$calculation =  (strtok($rec->total_time, ':')/720)*100;
        $templatecontext['March'] = ($main_arr[$key])?$main_arr[$key]:0; 
       
	}
	//
	if($key == '04'){
		//$calculation =  (strtok($rec->total_time, ':')/720)*100;
        $templatecontext['April'] = ($main_arr[$key])?$main_arr[$key]:0;
       
	}
	//
	if($key == '05'){
		//$calculation =  (strtok($rec->total_time, ':')/720)*100;
        $templatecontext['May'] = ($main_arr[$key])?$main_arr[$key]:0; 
       
	}
	//
	if($key == '06'){
		//$calculation =  (strtok($rec->total_time, ':')/720)*100;
        $templatecontext['June'] = ($main_arr[$key])?$main_arr[$key]:0;
        
	}
	//
	if($key == '07'){
		//$calculation =  (strtok($rec->total_time, ':')/720)*100;
        $templatecontext['July'] = ($main_arr[$key])?$main_arr[$key]:0;
       
	}
	//
	if($key == '08'){
		//$calculation =  (strtok($rec->total_time, ':')/720)*100;
        $templatecontext['August'] = ($main_arr[$key])?$main_arr[$key]:0;
       
	}
	//
	if($key == '09'){
		//$calculation =  (strtok($rec->total_time, ':')/720)*100;
        $templatecontext['September'] = ($main_arr[$key])?$main_arr[$key]:0;
       
	}
	//
	if($key == '10'){
		//$calculation =  (strtok($rec->total_time, ':')/720)*100;
        $templatecontext['October'] = ($main_arr[$key])?$main_arr[$key]:0;
       
	}
	//
	if($key == '11'){
		//$calculation =  (strtok($rec->total_time, ':')/720)*100;
        $templatecontext['November'] = ($main_arr[$key])?$main_arr[$key]:0;
       
	}
	//
	if($key == '12'){
        $templatecontext['December'] = ($main_arr[$key])?$main_arr[$key]:0;
       
	}
}
//print_object($templatecontext);die;
//$templatecontext['December'] = 0; 
echo $OUTPUT->render_from_template('local_learner/mydash', $templatecontext);
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
        .highcharts-no-tooltip{
        	display:none !important;
        }
        #hourscontainer{
        	height:250px !important;
        }
        .highcharts-credits{
        	display:none !important;
        }
      </style>';