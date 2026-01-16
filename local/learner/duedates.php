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
global $DB,$CFG;

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
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
//
$PAGE->set_url(new moodle_url('/local/learner/index.php'));
$PAGE->set_context($context);
$PAGE->set_title('Learners');

//$PAGE->navbar->add('Learners');
$PAGE->set_context(context_system::instance()); 
$PAGE->requires->jquery();
$PAGE->requires->jquery('ui');
//
$PAGE->requires->js('/local/learner/js/jquery.dataTables.min.js',true);
$PAGE->requires->css('/local/learner/js/jquery.dataTables.min.css',true);

if($action == 'store' && $action){
   //to store data into DB
    $object_data = $_POST;
    if($object_data){
        //
        //print_object($object_data);
        foreach($object_data['selected'] as $key=>$val){ 
            $start_date = $object_data['start_date'][$val];
            $end_date = $object_data['end_date'][$val];
            $users = explode(',',$object_data['userslist']);
            foreach($users as $k=>$v){
               $assignment_obj = $DB->get_record('assign', array('course' => $object_data['courselist'],'id'=>$val));
               /*echo strtotime($end_date);
               echo '<br>';
               echo strtotime($start_date);die;*/
                if(strtotime($end_date) <= strtotime($start_date)){
                   throw new Exception(" Assignment ".$assignment_obj->name." Duedate Should be greater than Start Date", 1);
                   die;    
                }
                //make the override object now
                $override_obj = new stdClass();
                $override_obj->assignid = $assignment_obj->id;
                $override_obj->userid = $v;
                $override_obj->allowsubmissionsfromdate = strtotime($start_date);
                $override_obj->duedate = strtotime($end_date);
                $override_obj->cutoffdate = strtotime("+1 day",$override_obj->duedate);
                $rec_exits = $DB->get_record('assign_overrides',['userid'=>$v,'assignid'=>$assignment_obj->id]);
                if($rec_exits){
                    $override_obj->id = $rec_exits->id;
                    $update = $DB->update_record('assign_overrides',$override_obj);
                }else{
                    $insert = $DB->insert_record('assign_overrides',$override_obj);
                }

            }
        }
        redirect(new moodle_url('/course/view.php',array('id'=> $object_data['courselist'])),'Duedates has been Allocated');
    }
}
$mform = new filters_form(null,[]);
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/learner/duedates.php'));
} else {
    $fromform = data_submitted();
}
$result = '';
if($fromform){
    $result = '';
    $result .= '<form id="dateForm" action="duedates.php?action=store" method="POST">';
    $result .= '<input type="hidden" class="userslist" name="userslist" value="'.implode(',',$fromform->users).'" >';
    $result .= '<input type="hidden" class="courselist" name="courselist" value="'.$fromform->courses.'" />';
    $cms = $DB->get_records('course_modules', ['course' => $fromform->courses,'module'=>1]);
    if($cms){
        $table = new html_table();
        $table->id = "duedates";
        $table->head = array('<input type="checkbox" class="selectall" name="selected[]" />','S.no','Course Name','Activity Name','Start Date','End Date');
        $table->data = array();
        //get teachers list hers
        $i = 1;
        foreach($cms as $record){
            $assignment = $DB->get_record('assign', ['course' => $fromform->courses,'id'=>$record->instance]);
            $courselist = $DB->get_record('course', ['id' => $fromform->courses]);
            $row = array();
            $row['chk'] = '<input type="checkbox" class="row-check" name="selected[]" value="'.$assignment->id.'"/>';
            $row['s.no'] = $i;
            $row['coursename'] = $courselist->fullname;
            $row['activityname'] =  $assignment->name;
            $row['startdate'] = '<input type="date" name="start_date['.$assignment->id.']" class="form-control" >';
            $row['enddate'] = '<input type="date" name="end_date['.$assignment->id.']" class="form-control" >';
            $table->data[] = $row;
            $i++;
        }
        $result .= html_writer::table($table);
        $result .= '<button type="submit" class="btn btn-primary px-4">Save Selected</button>';
        $result .= '</form>';
    }else{
        $result .= '<div class=" alert alert-danger alert-block fade in" align="center">No Assignments available</div>';
    }
}else{
    
}
echo $OUTPUT->header();
echo '<h3>Duedates Management</h3>';
echo '<script src="https://cdn.datatables.net/buttons/1.6.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.flash.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/1.6.2/js/buttons.print.min.js"></script>';
echo '<link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.10.21/css/jquery.dataTables.min.css">
      <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/1.6.2/css/buttons.dataTables.min.css">';


echo  html_writer::script("$(document).ready(function() {
                                        var oTable = $('#duedates').DataTable({
                                            dom: 'Blfrtip',
                                            'lengthMenu': [[10,20, 50, 100, 200, -1], [10,20, 50,100, 200, 'All']],
                                           
                                        });
                                        $('.dataTables_filter').css('display','none'); 
                                        $('.dataTables_info').css('display','block !important');
                                        $('.dataTables_length').css('float','right !important');
                                       
                                        })
                                        "
                                    ); 

echo $mform->display();
echo $result;
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
echo $OUTPUT->footer();    
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
      .mform{
         margin-right:700px;
      }
      .wrapper-course {
            margin-top:-30px;
            padding: 0px 10px !important;
        }
        .fheader{
            border:none;
        }
        #learners_length{
            float:right;
        }
        .form-control{
            font-size: 12px;
        }
        #id_submitbutton{
            font-size: 12px;
        }
        #id_cancel{
            font-size: 12px;
        }
        .dt-button{
            font-size:11px !important;
        }
        .generaltable  thead {
            background: #0100ff;
        }
        th.header{
            color:#ffff !important;
        }
        .dataTables_length{
            float:right !important;
        }
        .dt-buttons{
            display:none !important;
        }
        table.dataTable thead .sorting {
            background-image: url(./images/sort_both.png)!important;
        }

      </style>';
