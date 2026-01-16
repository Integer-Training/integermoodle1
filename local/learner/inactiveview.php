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
require_once("filter_form.php");
global $DB,$CFG;

require_login();
$action = optional_param('action','',PARAM_RAW);

$context = context_system::instance();
require_capability('local/learner:view', $context);
echo '<link href="https://gecko.atomlms.co.uk/scripts/css/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://gecko.atomlms.co.uk/scripts/scss/icons/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel=stylesheet>';
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


$post_url = new moodle_url('/local/learner/view.php');

$mform = new filter_form(null,[]);
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/learner/inactiveview.php'));
}else {
    $fromform = data_submitted(); 
}
$result = '';
if($fromform && !$action){
    //print_r($fromform);die;
    if($fromform->courses && !$fromform->firstname && !$fromform->email){
        $sql = "SELECT uu.*
                    FROM {user_enrolments} ue
                    JOIN {enrol} en ON ue.enrolid = en.id
                    JOIN {course} c ON c.id = en.courseid
                    JOIN {user} uu ON uu.id = ue.userid
                    WHERE c.id=".$fromform->courses."  AND en.enrol='manual' AND c.visible=1 AND uu.id>2";
        if($fromform->isactive || !$fromform->isactive){
            $active = ($fromform->isactive)?0:1;
            $sql .= "  AND uu.suspended =".$active;
        } 
        $sql .= ' AND (
               uu.lastaccess IS NOT NULL AND uu.lastaccess!=0
                AND uu.lastaccess < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            ) AND uu.deleted=0 AND uu.suspended=0';
        $records = $DB->get_records_sql($sql);
    }else if($fromform->courses && ($fromform->firstname || $fromform->email)){
        $sql = "SELECT uu.*
                    FROM {user_enrolments} ue
                    JOIN {enrol} en ON ue.enrolid = en.id
                    JOIN {course} c ON c.id = en.courseid
                    JOIN {user} uu ON uu.id = ue.userid
                    WHERE c.id=".$fromform->courses."  AND en.enrol='manual' AND c.visible=1 AND uu.id>2";
        if($fromform->firstname){
           $sql .= "  AND uu.firstname LIKE '%".$fromform->firstname."%'";
        }
        if($fromform->email){
            $sql .= "  AND uu.email LIKE '%".$fromform->email."%'";
        }
        if($fromform->isactive || !$fromform->isactive){
            $active = ($fromform->isactive)?0:1;
            $sql .= "  AND uu.suspended =".$active;
        }
        $sql .= ' AND (
               uu.lastaccess IS NOT NULL AND uu.lastaccess!=0
                AND uu.lastaccess < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            ) AND uu.deleted=0 AND uu.suspended=0';
        $records = $DB->get_records_sql($sql);
    }else{
        $sql = "SELECT *  FROM {user} u ";
        $sql .= " WHERE u.id>2"; 
        if($fromform->firstname){
           $sql .= "  AND u.firstname LIKE '%".$fromform->firstname."%'";
        }
        //
        if($fromform->email){
            $sql .= "  AND u.email LIKE '%".$fromform->email."%'";
        }
        //
        if($fromform->isactive || !$fromform->isactive){
            $active = ($fromform->isactive)?0:1;
            $sql .= "  AND u.suspended =".$active;
        }
        $sql .= ' AND (
               u.lastaccess IS NOT NULL AND u.lastaccess!=0
                AND u.lastaccess < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            ) AND u.deleted=0 AND u.suspended=0';
        $records = $DB->get_records_sql($sql);
    }
    
    
    //echo $sql;die;
    //print_object($records);die;
   
    $result = '';
    if($records){
        //$count_recs = count($records);
        $table = new html_table();
        $table->id = "learners";
        $table->head = array(
            get_string('firstname', 'local_learner'),
            get_string('lastname', 'local_learner'),
            get_string('email', 'local_learner'),
            get_string('lc', 'local_learner'),
            get_string('sup', 'local_learner'),
             'Courses',
             'Time Spent',
             'Last Login',
            get_string('status', 'local_learner'),
            get_string('edit', 'local_learner'),
            get_string('loginas', 'local_learner'),
            get_string('sendlogin', 'local_learner'),
            get_string('active', 'local_learner'),  
        );
        $table->data = array();
        //get teachers list hers
        $role_id = 4;
        $role_users = $DB->get_records('role_assignments',['roleid'=>$role_id]);
        $role_arr = [];
        foreach($role_users as $users){
             $role_arr[]  = $users->userid;
        }
       // print_object($role_arr);die;
        foreach($records as $record){
            if(in_array($record->id,$role_arr)){
                 continue;
            }
            $count_recs_arr[] = $record->id;
            $row = array();
            $row['firstname'] = $record->firstname;
            $row['lastname'] = $record->lastname;
            $row['email'] =  $record->email;
            $row['lc'] = 'Gecko';
            $row['sup'] = 'Gecko (Integer)';
            //
            if($fromform->courses){
                $sql = "SELECT c.id,c.fullname
                        FROM {course} c
                        WHERE c.id=".$fromform->courses." AND c.visible=1";
            }else if(!$fromform->courses){
                $sql = "SELECT c.id,c.fullname
                    FROM {user_enrolments} ue
                    JOIN {enrol} en ON ue.enrolid = en.id
                    JOIN {course} c ON c.id = en.courseid
                    JOIN {user} uu ON uu.id = ue.userid
                    WHERE uu.id=".$record->id."  AND en.enrol='manual' AND c.visible=1";
            }
            //echo $sql;
            //
            if(!$fromform->courses){
                $enrol_courses = $DB->get_records_sql($sql);
                $timespent = array();
                foreach($enrol_courses as $val){
                    $recs = array();
                    $recs  = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$val->id.'">'.$val->fullname.' </a>';
                    $import_arr[] = $recs;
                    $timespent[] = $val->id;
                }
                $row['Courses'] = count($enrol_courses)?implode('<br><br>',$import_arr):'N/A';
            }else{
                $enrol_course = $DB->get_record_sql($sql);
                $row['Courses'] = $enrol_course->fullname;
            }
            
            //print_object($import_arr);die;
           
            //
            if($enrol_courses && $record->id){
                $sql = "SELECT 
                        userid,
                        TIME_FORMAT(SEC_TO_TIME(SUM(diff_seconds)), '%H:%i') AS total_time
                        FROM (
                            SELECT 
                                userid,
                                IF(
                                    @prev_user = userid,
                                    timecreated - @prev_time,
                                    0
                                ) AS diff_seconds,

                                @prev_user := userid,
                                @prev_time := timecreated

                            FROM r6ua_logstore_standard_log
                            CROSS JOIN (SELECT @prev_user := NULL, @prev_time := NULL) vars

                            WHERE courseid IN (".implode(',',$timespent).")
                              AND component = 'mod_hvp'
                              AND userid = ".$record->id."
                            ORDER BY userid, timecreated
                        ) t
                        GROUP BY userid";
                        //echo $sql;die;
                $recordset = $DB->get_record_sql($sql);
                $row['timespent'] = ($recordset->total_time)?$recordset->total_time:'00:00';
            }else{
                $row['timespent'] = '00:00';
            }

            //status changed on conditons
            $lastaccess = $record->lastaccess;
            if($lastaccess && !$record->suspended){
                $row['lastlogin'] = date('d-m-Y h:i A',$lastaccess);
                $row['status'] = 'Active';
            }else if($record->suspended){
                $row['lastlogin'] = 0;
                $row['status'] = 'Suspended';
            }else if(!$record->lastaccess && !$record->firstaccess){
                $row['lastlogin'] = 0;
                $row['status'] = 'Created';
            }

            //
            //$row['status'] =  (!$record->suspended)?'Active':'Inactive';
            $edit_url = new moodle_url('/local/learner/edit.php',['id'=>$record->id]);
            $login_as = new moodle_url('/course/loginas.php',['id'=>1,'user'=>$record->id,'sesskey'=>\sesskey()]);
            $send_login = new moodle_url('/local/learner/email.php',['id'=>$record->id]);

            $row['edit'] =  '<a href="'.$edit_url.'" class="btn btn-info"><i class="ionicons ion-edit"></i></a>';
            $row['loginas'] =  '<a href="'.$login_as.'" target="_blank" class="btn btn-primary login_as"><i class="ionicons ionicons ion-log-in"></i></a>';
            $row['sendlogin'] =  '<a href="'.$send_login.'" class="btn btn-success btn-labeled login_as"><span class="fa fa-envelope"></span> </a>';
            if($record->suspended){
                $row['active'] = '<a href="#" class="btn btn-danger" onclick="return activate('.$record->id.')" title="Not active"> <i class="fa fa-minus fa-fw"></i></a>';
            }else{
                $row['active'] =  '<a href="#" class="btn btn-success login_as" onclick="return deactivate('.$record->id.')" title="Active"><i class="fa fa-check fa-fw"></i></a>';
            }
            
            //
            //print_object($row);die;
            $table->data[] = $row;
        }
        $result .= html_writer::table($table);
    }else{
        $result .= '<div class=" alert alert-danger alert-block fade in" align="center">No records available</div>';
    }
}else{
    $sql = 'select * from {user} s where id>2';
    $sql .= ' AND (
                s.lastaccess IS NOT NULL AND s.lastaccess!=0
                AND s.lastaccess < UNIX_TIMESTAMP(DATE_SUB(NOW(), INTERVAL 30 DAY))
            ) AND s.deleted=0 AND s.suspended=0';
    $records = $DB->get_records_sql($sql);

    $result = '';
     //get teachers list hers
        $role_id = 4;
        $role_users = $DB->get_records('role_assignments',['roleid'=>$role_id]);
        $role_arr = [];
        foreach($role_users as $users){
             $role_arr[]  = $users->userid;
        }
        //print_object($role_arr);die;
    if($records){
        
        $table = new html_table();
        $table->id = "learners";
        $table->head = array(
            get_string('firstname', 'local_learner'),
            get_string('lastname', 'local_learner'),
            get_string('email', 'local_learner'),
            get_string('lc', 'local_learner'),
            get_string('sup', 'local_learner'),
             'Courses',
             'Time Spent',
             'Last Login',
            get_string('status', 'local_learner'),
            get_string('edit', 'local_learner'),
            get_string('loginas', 'local_learner'),
            get_string('sendlogin', 'local_learner'),
            get_string('active', 'local_learner'),  
        );
        $table->data = array();
        foreach($records as $record){
            if(in_array($record->id,$role_arr)){
                 continue;
            }
            $count_recs_arr[] = $record->id;
            $row = array();
            $row['firstname'] = $record->firstname;
            $row['lastname'] = $record->lastname;
            $row['email'] =  $record->email;
            $row['lc'] = 'Gecko';
            $row['sup'] = 'Gecko (Integer)';
            //
            $sql = "SELECT c.id,c.fullname
                    FROM {user_enrolments} ue
                    JOIN {enrol} en ON ue.enrolid = en.id
                    JOIN {course} c ON c.id = en.courseid
                    JOIN {user} uu ON uu.id = ue.userid
                    WHERE uu.id=".$record->id."  AND en.enrol='manual' AND c.visible=1";
            //
            $enrol_courses = $DB->get_records_sql($sql);
            $import_arr = array();
             $timespent = array();
            foreach($enrol_courses as $val){
                $recs = array();
                $recs  = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$val->id.'">'.$val->fullname.' </a>';
                $import_arr[] = $recs;
                $timespent[] = $val->id;
            }
            //print_object(implode('<br>',$import_arr));die;
            $row['Courses'] = count($enrol_courses)?implode('<br><br>',$import_arr):'N/A';
            //
            
            if($enrol_courses && $record->id){
                $sql = "SELECT 
                        userid,
                        TIME_FORMAT(SEC_TO_TIME(SUM(diff_seconds)), '%H:%i') AS total_time
                        FROM (
                            SELECT 
                                userid,
                                IF(
                                    @prev_user = userid,
                                    timecreated - @prev_time,
                                    0
                                ) AS diff_seconds,

                                @prev_user := userid,
                                @prev_time := timecreated

                            FROM r6ua_logstore_standard_log
                            CROSS JOIN (SELECT @prev_user := NULL, @prev_time := NULL) vars

                            WHERE courseid IN (".implode(',',$timespent).")
                              AND component = 'mod_hvp'
                              AND userid = ".$record->id."
                              AND YEAR(FROM_UNIXTIME(timecreated)) = YEAR(CURDATE())

                            ORDER BY userid, timecreated
                        ) t
                        GROUP BY userid";
                        //echo $sql;die;
                $recordset = $DB->get_record_sql($sql);
                $row['timespent'] = ($recordset->total_time)?$recordset->total_time:'00:00';
            }else{
                $row['timespent'] = '00:00';
            }
            //status changed on conditons
            $lastaccess = $record->lastaccess;
            if($lastaccess && !$record->suspended){
                $row['lastlogin'] = date('d-m-Y h:i A',$lastaccess);
                $row['status'] = 'Active';
            }else if($record->suspended){
                $row['lastlogin'] = 0;
                $row['status'] = 'Suspended';
            }else if(!$record->lastaccess && !$record->firstaccess){
                $row['lastlogin'] = 0;
                $row['status'] = 'Created';
            }
            //$row['status'] =  (!$record->suspended)?'Active':'Inactive';
            $edit_url = new moodle_url('/local/learner/edit.php',['id'=>$record->id]);
            $login_as = new moodle_url('/course/loginas.php',['id'=>1,'user'=>$record->id,'sesskey'=>\sesskey()]);
            $send_login = new moodle_url('/local/learner/email.php',['id'=>$record->id]);

            $row['edit'] =  '<a href="'.$edit_url.'" class="btn btn-info"><i class="ionicons ion-edit"></i></a>';
            $row['loginas'] =  '<a href="'.$login_as.'" target="_blank" class="btn btn-primary login_as"><i class="ionicons ionicons ion-log-in"></i></a>';
            $row['sendlogin'] =  '<a href="'.$send_login.'" class="btn btn-success btn-labeled login_as"><span class="fa fa-envelope"></span> </a>';
            if($record->suspended){
                $row['active'] = '<a href="#" class="btn btn-danger" onclick="return activate('.$record->id.')" title="Not active"> <i class="fa fa-minus fa-fw"></i></a>';
            }else{
                $row['active'] =  '<a href="#" class="btn btn-success login_as" onclick="return deactivate('.$record->id.')" title="Active"><i class="fa fa-check fa-fw"></i></a>';
            }
           
            //
            $table->data[] = $row;
        }
        $result .= html_writer::table($table);
    }
}
echo $OUTPUT->header();
/*$sql = 'select * from {user} where id>2';
$records = $DB->get_records_sql($sql);*/
$count_recs = ($count_recs_arr)?count($count_recs_arr):0;
echo '<h4>Learner Management</h4>';
echo '<ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="https://epearlacademy.com/my/">Home</a></li>
                <li class="breadcrumb-item active" style="margin-top: 4px;">Manage Learners</li>
            </ol>';
echo '<div class="card-header-lms">
                        <h4 class="m-b-0 text-white headline-lms">
                            <div class="pull-left m-2" >
                                <strong style="padding-right:12px"> Total Learners</strong><span class="label label-rounded label-warning m-l-10 label-all">'.$count_recs.'</span>
                            </div>
                            <a href="'.$CFG->wwwroot.'/user/editadvanced.php?id=-1" target="_blank"  class="btn btn-success waves-effect waves-light pull-right mr-2">
                                <span class="btn-label">
                                                <i class="fa fa-lg fa-plus"></i>
                                </span>New Learner 
                            </a>
                            <a href="'.$CFG->wwwroot.'/local/learner/inactiveview.php" target="_blank" class="btn btn-warning waves-effect waves-light pull-right mr-2">
                                <span class="btn-label">
                                    <i class="fa fa-lg fa-plus"></i>
                                </span>InActive Learners
                            </a>     
                                        
                        </h4>
        </div>';
echo '<br>';
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
                                        var oTable = $('#learners').DataTable({
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
echo $mform->display();
echo $result;
echo $OUTPUT->footer();
//
echo html_writer::script('function activate(userid){
                            Swal.fire({
                              title: "Are you sure?",
                              text: "You wont be able to revert this",
                              icon: "warning",
                              showCancelButton: true,
                              confirmButtonColor: "#3085d6",
                              cancelButtonColor: "#d33",
                              confirmButtonText: "Activate"
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        type: "POST",
                                        data:{userid:userid,action:"active"},
                                        url: "'.$CFG->wwwroot.'/local/learner/process.php",
                                        dataType: "json",
                                        success: function (r) {
                                            console.log(r);
                                            var response = ""; 
                                            location.reload(true);              
                                            
                                        }
                                    });
                                    
                                }
                                location.reload();
                            });
                        }
                ');
//
echo html_writer::script('function deactivate(userid){
                            Swal.fire({
                              title: "Are you sure?",
                              text: "You wont be able to revert this",
                              icon: "warning",
                              showCancelButton: true,
                              confirmButtonColor: "#3085d6",
                              cancelButtonColor: "#d33",
                              confirmButtonText: "Deactivate"
                            }).then((result) => {
                                if (result.isConfirmed) {
                                    $.ajax({
                                        type: "POST",
                                        data:{userid:userid,action:"deactive"},
                                        url: "'.$CFG->wwwroot.'/local/learner/process.php",
                                        dataType: "json",
                                        success: function (r) {
                                            console.log(r);
                                            var response = ""; 
                                                          
                                            
                                        }
                                    });
                                    
                                }
                                location.reload();
                            });
                        }
                ');
echo html_writer::script('$("#id_course").change(function(){
             var courseid = $(this).val();
             if(courseid){
             $.ajax({
                type: "POST",
                data:{courseid:courseid,action:"getuser"},
                url: "'.$CFG->wwwroot.'/blocks/stats/ajax.php",
                dataType: "json",
                success: function (r) {
                    console.log(r);
                        var response = "";                    
                        response += "<option value =null>--Select User--</option>";
                        $.each(r, function( index, value){
                          response += "<option value = " + index + " >" +value.firstname+" "+value.lastname + "</option>";
                        });
                        $("#id_user").html(response);
                    
                }
            });
            }else{
                var response = "";                    
                response += "<option value =null>--Select User--</option>";
                $("#id_user").val(response);
            }
    });
$("#id_cancel").val("Reset");
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
        .card-header-lms{
            padding: 1.75rem 0.25rem;
            margin-bottom: 0;
            background-color: #0100ff;;
            border-bottom: 1px solid rgba(0, 0, 0, .125);
        }
        .headline-lms{
            margin-top: -20px;
            margin-bottom: 15px;
        }
        .btn-success,.btn-success.disabled {
            font-size: 12px;
            background: #cbd446;
            background-color: #cbd446;
            border: 1px solid #cbd446;
        }
        .btn-warning, .btn-warning.disabled {
            font-size: 12px;
            background: #ebb548;
            background-color: #ebb548;
            border: 1px solid #ebb548;
        }
        .label-warning {
            background-color: #ebb548;
        }
        .label-rounded {
            border-radius: 60px;
        }
        .label {
            padding: 2px 10px;
            line-height: 13px;
            color: #ffffff;
            font-weight: 400;
            border-radius: 10px;
            font-size: 100%;
        }
        .label-success {
            background-color: #cbd446;
        }
        .btn-label {
            /*background: rgba(0, 0, 0, 0.05);*/
            display: inline-block;
            margin: -6px 12px -6px -14px;
            color:#fff;
            font-size:13px;
        }
        table.dataTable thead .sorting {
            background-image: url(./images/sort_both.png)!important;
        }
        .btn-info {
            padding: 7px 12px !important;
            font-size: 14px !important;
            cursor: pointer !important;
            color: #fff;
            background-color: #5bc0de;
            border-color: #5bc0de;
        }
        .login_as{
            padding: 7px 12px !important;
            font-size: 14px !important;
            cursor: pointer !important;
        }
        .btn-danger:hover, .btn-danger.disabled:hover {
            background: #e25959;
            background-color: #e25959;
            opacity: 0.7;
            border: 1px solid #e25959;
            padding: 7px 12px !important;
            font-size: 14px !important;
            cursor: pointer !important;
        }
        .btn-danger{
            background: #e25959;
            background-color: #e25959;
            opacity: 0.7;
            border: 1px solid #e25959;
            padding: 7px 12px !important;
            font-size: 14px !important;
            cursor: pointer !important;
        }
      </style>';

    

