<?php
/**
 * This file is part of eAbyas
 *
 * Copyright eAbyas Info Solutons Pvt Ltd, India
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @author shiva@gecko
 * @package lms
 * @subpackage local_learner
 */

//define('CLI_SCRIPT', true);
// Set the maximum execution time to unlimited (use with caution, especially in production)
ini_set('max_execution_time', 0); 
require_once(dirname(__FILE__) . '/../../config.php');
global $CFG, $USER, $PAGE, $OUTPUT;
define('OVERRIDE_DAYS',15);
$id = optional_param('id', 0, PARAM_INT);
$one_day_minus = strtotime('-1 day',time());
//cron started
$sql = 'SELECT id,email,FROM_UNIXTIME(firstaccess)  FROM {user} WHERE firstaccess != 0 and firstaccess IS NOT NULL and id>2 and suspended=0';

//
$records = $DB->get_records_sql($sql);

foreach($records as $rec){
    //logic
    //first remove the tutors list
    $role_id = 4;
    $role_users = $DB->get_records('role_assignments',['roleid'=>$role_id]);
    $role_arr = [];
    foreach($role_users as $users){
         $role_arr[]  = $users->userid;
    }
    //
    if(in_array($rec->id,$role_arr)){
        continue;
    }
    //get user enrolled courses to set the workbook dates
    $sql = "SELECT c.id,c.fullname,uu.id as userid, uu.firstaccess
            FROM {user_enrolments} ue
            JOIN {enrol} en ON ue.enrolid = en.id
            JOIN {course} c ON c.id = en.courseid
            JOIN {user} uu ON uu.id = ue.userid
            WHERE uu.id=".$rec->id."  AND en.enrol='manual' AND c.visible=1";
    $enrol_courses = $DB->get_records_sql($sql);
    foreach($enrol_courses as $course){
        $sql = "SELECT *  FROM mdl_course_modules WHERE course = ".$course->id." and module =1 and visible=1 order by instance";
        $course_modules_list = $DB->get_records_sql($sql);
        //
        //print_object($course_modules_list);die;
        //echo $rec->id;
        //echo OVERRIDE_DAYS;die;
        $i = 1;
        foreach($course_modules_list as $cm){
          $assignment_obj = $DB->get_record('assign', array('course' => $course->id,'id'=>$cm->instance));
          //
          //make the override object now
          if($i == 1){
            $override_obj = new stdClass();
            $override_obj->assignid = $assignment_obj->id;
            $override_obj->userid = $course->userid;
            $override_obj->allowsubmissionsfromdate = $course->firstaccess;
            $override_obj->duedate = strtotime("+ ".OVERRIDE_DAYS." days",$course->firstaccess);
            $override_obj->cutoffdate = 0; // No hard cutoff - allow late submissions
            $rec_exits = $DB->get_record('assign_overrides',['userid'=>$course->userid,'assignid'=>$assignment_obj->id]);
            //print_object($override_obj);die;
            if($rec_exits){
                $override_obj->id = $rec_exits->id;
                $update = $DB->update_record('assign_overrides',$override_obj);
            }else{
                $insert = $DB->insert_record('assign_overrides',$override_obj);
            }
            //update log event now
            /*$update_log = new stdClass();
            $update_log->id =  $rec->id;
            $update_log->overriden = 1;
            $DB->update_record('local_leaner_email',$update_log);*/
          }else{
            
            $override_obj = new stdClass();
            $override_obj->assignid = $assignment_obj->id;
            $override_obj->userid = $course->userid;
            $override_obj->allowsubmissionsfromdate = $course->firstaccess;
            $days = OVERRIDE_DAYS * $i;
            //echo $days;die;
            $override_obj->duedate = strtotime("+ ".$days." days",$course->firstaccess);
            $override_obj->cutoffdate = 0; // No hard cutoff - allow late submissions
            $rec_exits = $DB->get_record('assign_overrides',['userid'=>$course->userid,'assignid'=>$assignment_obj->id]);
            //print_object($override_obj);die;
            if($rec_exits){
                $override_obj->id = $rec_exits->id;
                $update = $DB->update_record('assign_overrides',$override_obj);
            }else{
                $insert = $DB->insert_record('assign_overrides',$override_obj);
            }
          }
            
            $i++;
        }

    }
    echo 'Successfully Inserted/Updated';
    echo "<br>";
    //die;
}
//cron end
