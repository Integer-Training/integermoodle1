<?php
// This file is part of the Contact Form plugin for Moodle - http://moodle.org/
//
// Contact Form is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Contact Form is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Contact Form.  If not, see <http://www.gnu.org/licenses/>.

/**
 * This plugin for Moodle is used to send emails through a web form.
 *
 * @package    local_learner
 * @copyright  gecko
 * @author     shiva
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');    

//
$request_obj = $_REQUEST;

ini_set('max_execution_time', 0);
//
global $OUTPUT,$DB,$USER, $CFG;

if($request_obj['action'] == 'active' && $request_obj['userid']){
  $user_rec = $DB->get_record('user',array('id'=>$request_obj['userid']));
  $rec = new stdClass();
  $rec->id = $user_rec->id;
  $rec->emaii = $user_rec->email;
  $rec->suspended = 0;
  $DB->update_record('user',$rec);
  //insert
  $newobj = new stdClass();
  $newobj->userid = $request_obj['userid'];
  $newobj->status = 'Active';
  $newobj->actionby = $USER->id;
  $newobj->timecreated = time();
  $DB->insert_record('local_leaner_user',$newobj);
  echo 'success';
  die;
}
//
if($request_obj['action'] == 'deactive' && $request_obj['userid']){
  $user_rec = $DB->get_record('user',array('id'=>$request_obj['userid']));
  $rec = new stdClass();
  $rec->id = $user_rec->id;
  $rec->emaii = $user_rec->email;
  $rec->suspended = 1;
  $DB->update_record('user',$rec);
  //insert
  $newobj = new stdClass();
  $newobj->userid = $request_obj['userid'];
  $newobj->status = 'InActive';
  $newobj->actionby = $USER->id;
  $newobj->timecreated = time();
  $DB->insert_record('local_leaner_user',$newobj);
  echo 'success';
  die;
}
//
if($request_obj['action'] == 'getuser' && $request_obj['courseid']){
   $course_context = context_course::instance($request_obj['courseid']);
   $groups = $DB->get_records('groups_members',['userid'=>$USER->id]);
   $actual_course_group = [];
   foreach($groups as $rec){
        $actual_course_group[] = $DB->get_record('groups',['id'=>$rec->groupid,'courseid'=>$request_obj['courseid']]);
   }
   //
   $gm = array();
   foreach($actual_course_group as $group){
         $gm[] = $DB->get_records('groups_members',['groupid'=>$group->id]);;
   }
   $act_gm = [];
   foreach($gm as $grp_mem){
        foreach($grp_mem as $res){
          if($res->userid == $USER->id)
            continue;

            $act_gm[$res->userid] = $DB->get_field('user','email',['id'=>$res->userid]);
        }
   }
   echo json_encode($act_gm);
   die;
}