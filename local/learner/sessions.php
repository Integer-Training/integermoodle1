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
 * @copyright 2025 shiva@gecko
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("filters_form.php");
global $DB,$CFG,$USER;

require_login();
$action = optional_param('action','',PARAM_RAW);

$context = context_system::instance();
$teachers = local_get_teacher_courses();
if(!$teachers){
    throw new Exception("You Dont have permissions", 1);
    
}
//require_capability('local/learner:view', $context);
/*echo '<link href="https://gecko.atomlms.co.uk/scripts/css/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://gecko.atomlms.co.uk/scripts/scss/icons/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel=stylesheet>';*/
/*echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';*/
//
$PAGE->set_url(new moodle_url('/local/learner/sessions.php'));
$PAGE->set_context($context);
$PAGE->set_title('sessions');

//$PAGE->navbar->add('Learners');
$PAGE->set_context(context_system::instance()); 
$PAGE->requires->jquery();
$PAGE->requires->jquery('ui');
//
$PAGE->requires->js('/local/learner/js/jquery.dataTables.min.js',true);
$PAGE->requires->css('/local/learner/js/jquery.dataTables.min.css',true);
//
$cid = optional_param('id',0,PARAM_INT);
$mform = new filters_session_form(new moodle_url('/local/learner/sessions.php',['id'=>$cid]),['id'=>$cid]);
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/course/view.php?id='.$cid.''));
} else {
    $fromform = data_submitted();
}
$result = '';
if($fromform){
    //print_object($fromform);die;
    $cms = $fromform->cms;
    $cid = ($cid)?$cid:$fromform->cid;
    $result = '';
    if($cms){
        $table = new html_table();
        $table->id = "sessions";
        $table->head = array('Student Name','Activity Name','Date','Session Time');
        $table->data = array();
        //get course users first or group users
        $course_context = context_course::instance($cid);
        $groups = $DB->get_records('groups_members',['userid'=>$USER->id]);
        $actual_course_group = [];
        foreach($groups as $rec){
            $actual_course_group[] = $DB->get_record('groups',['id'=>$rec->groupid,'courseid'=>$cid]);
        }
 
        $gm = array();
        foreach($actual_course_group as $group){
             $gm[] = $DB->get_records('groups_members',['groupid'=>$group->id]);;
        }
        $act_gm = [];
        foreach($gm as $grp_mem){
            foreach($grp_mem as $res){
              if($res->userid == $USER->id)
                continue;

                $act_gm[] = $res->userid;
            }
        }
        if($act_gm){
            //print_object()
            
            if(is_array($fromform->users)){
               $userids = implode(',',$fromform->users);
            }else{
                $userids = implode(',',$act_gm);
            }
           $sql = 'SELECT 
                    userid,
                    log_day,
                    SEC_TO_TIME(SUM(diff_seconds)) AS total_time
                    FROM (
                        SELECT 
                            userid,
                            DATE(FROM_UNIXTIME(timecreated)) AS log_day,
                            IF(@prev_user = userid AND @prev_day = DATE(FROM_UNIXTIME(timecreated)),
                                timecreated - @prev_time,
                                0
                            ) AS diff_seconds,
                            @prev_user := userid,
                            @prev_day := DATE(FROM_UNIXTIME(timecreated)),
                            @prev_time := timecreated
                        FROM r6ua_logstore_standard_log, 
                             (SELECT @prev_user := NULL, @prev_day := NULL, @prev_time := NULL) vars
                             WHERE  objectid='.$fromform->cms.' AND component = "mod_hvp" and userid in ('.$userids.')
                        ORDER BY userid, timecreated
                    ) t

                    GROUP BY userid, log_day
                    ORDER BY  log_day DESC';
        }
        //echo $sql;die;
        $records_result = $DB->get_recordset_sql($sql);
        //get teachers list hers
        $i = 1;
        foreach($records_result as $rec){
            $assignment = $DB->get_record('hvp', ['course' => $fromform->cid,'id'=>$fromform->cms]);
            $courselist = $DB->get_record('course', ['id' => $fromform->cms]);
            $row = array();
            $row['studentname'] = $DB->get_field('user','firstname',['id'=>$rec->userid]).' '.$DB->get_field('user','lastname',['id'=>$rec->userid]);
            //$row['coursename'] = $courselist->fullname;
            $row['activityname'] =  $assignment->name;
            
            $row['date'] = $rec->log_day;
            $row['session_time'] = $rec->total_time;
            $table->data[] = $row;
            $i++;
        }
        $result .= html_writer::table($table);
    }else{
        $result .= '<div class=" alert alert-danger alert-block fade in" align="center">No records available</div>';
    }
}
echo $OUTPUT->header();
function local_get_teacher_courses(){
    global $DB,$COURSE,$USER;
    // Get context levels for courses.
    $contextlevel = CONTEXT_COURSE;

    // Find teacher roles (editingteacher, teacher, etc.)
    $teacherroles = $DB->get_records_sql(
        "SELECT id FROM {role} WHERE shortname IN ('editingteacher', 'teacher')"
    );

    if (empty($teacherroles)) {
        return [];
    }

    $roleids = array_keys($teacherroles);
    list($insql, $params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);

    // Query to get all courses where user has teacher roles.
    $sql = "SELECT c.id, c.fullname, c.shortname, c.startdate, c.enddate
              FROM {course} c
              JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel
              JOIN {role_assignments} ra ON ra.contextid = ctx.id
             WHERE ra.userid = :userid AND ra.roleid $insql
          ORDER BY c.fullname ASC";

    $params['contextlevel'] = $contextlevel;
    $params['userid'] = $USER->id;

    return $DB->get_records_sql($sql, $params);
}

echo '<script src="https://cdn.datatables.net/buttons/1.6.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.flash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.print.min.js"></script>';
echo '<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">
      <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.6.2/css/buttons.dataTables.min.css">';

echo $mform->display();
$courselist = $DB->get_record('course', ['id' => $cid]);
echo '<h4>Attendance Management for '.$courselist->fullname.'</h4>';
echo $result;
echo $OUTPUT->footer();



echo  html_writer::script("$(document).ready(function() {
                                        var oTable = $('#sessions').DataTable({
                                            dom: 'Blfrtip',
                                            'lengthMenu': [[15,25, 50, 100, 200, -1], [15,25, 50,100, 200, 'All']],
                                            buttons: [
                                            'excel', 'pdf'
                                            ],
                                           
                                        });
                                        $('.dataTables_filter').css('display','none'); 
                                        $('.dataTables_info').css('display','block !important');
                                        $('.dataTables_length').css('float','right !important');
                                       
                                        })
                                        "
                                    ); 
echo html_writer::script('$("#id_courses").change(function(){
             var courseid = $(this).val();
             if(courseid){
             $.ajax({
                type: "POST",
                data:{courseid:courseid,action:"getuser"},
                url: "'.$CFG->wwwroot.'/local/learner/process.php",
                dataType: "json",
                success: function (r) {
                    console.log(r);
                        var response = "";                    
                        response += "<option value =null>--Select User--</option>";
                        $.each(r, function( index, value){
                          response += "<option value = " + index + " >" +value+ "</option>";
                        });
                        $("#id_users").html(response);
                    
                }
            });
            }else{
                var response = "";                    
                response += "<option value =null>--Select User--</option>";
                $("#id_user").html(response);
            }
    });
        $(".selectall").click(function(){
            $("input:checkbox").not(this).prop("checked", this.checked);
        });

');

echo '<style>
         .dataTables_length{
            float:right !important;
        }
     </style>';
