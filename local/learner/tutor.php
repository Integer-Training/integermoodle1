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

$context = context_system::instance();
require_capability('local/learner:view', $context);
echo '<link href="https://gecko.atomlms.co.uk/scripts/css/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://gecko.atomlms.co.uk/scripts/scss/icons/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel=stylesheet>';
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
//
$PAGE->set_url(new moodle_url('/local/learner/index.php'));
$PAGE->set_context($context);
$PAGE->set_title('Tutors');

//$PAGE->navbar->add('Learners');
$PAGE->set_context(context_system::instance()); 
$PAGE->requires->jquery();
$PAGE->requires->jquery('ui');
//
$PAGE->requires->js('/local/learner/js/jquery.dataTables.min.js',true);
$PAGE->requires->css('/local/learner/js/jquery.dataTables.min.css',true);
//
$result = '';
$count_recs_arr = [];

$contextlevel = CONTEXT_COURSE;

// Find tutor roles. Match both 'teacher' (Epearl convention per CLAUDE.md)
// and 'editingteacher' so the page works even when only editingteacher
// assignments exist in the imported data.
$teacherroles = $DB->get_records_sql(
    "SELECT id FROM {role} WHERE shortname IN ('teacher', 'editingteacher')"
);

if (empty($teacherroles)) {
    return [];
}

$roleids = array_keys($teacherroles);
list($insql, $params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);

// Query to get all courses where user has teacher roles.
$sql = "SELECT ra.userid,u.*
          FROM  {role_assignments} ra
          JOIN {user} u ON u.id=ra.userid
         WHERE  ra.roleid $insql
      GROUP BY u.id";
//echo $sql;die;
//$params['contextlevel'] = $contextlevel;


//return $DB->get_records_sql($sql, $params);
$records = $DB->get_recordset_sql($sql,$params);
//print_object($records);die;
if($records){
    $table = new html_table();
    $table->id = "tutors";
    $table->head = array(
        get_string('firstname', 'local_learner'),
        get_string('lastname', 'local_learner'),
        get_string('email', 'local_learner'),
        'CaseLoads',
        'Learners',
        get_string('edit', 'local_learner'),
        get_string('loginas', 'local_learner'),
        get_string('sendlogin', 'local_learner'),
        get_string('active', 'local_learner'),  
    );
    $table->data = array();
    foreach($records as $record){
        $row = array();
        $row['firstname'] = $record->firstname;
        $row['lastname'] = $record->lastname;
        $row['email'] =  $record->email;
        $sql = "SELECT c.id, c.fullname
                FROM {user_enrolments} ue
                JOIN {enrol} en ON ue.enrolid = en.id
                JOIN {course} c ON c.id = en.courseid
                JOIN {user} uu ON uu.id = ue.userid
                WHERE uu.id = :userid AND en.enrol = 'manual' AND c.visible = 1";
        //
        $enrol_courses = $DB->get_records_sql($sql, ['userid' => $record->id]);
        $import_arr = array();
        $cids = array();
        foreach($enrol_courses as $val){
            $recs = array();
            $recs  = '<a href="'.$CFG->wwwroot.'/course/view.php?id='.$val->id.'">'.$val->fullname.' </a>';
            $import_arr[] = $recs;
            $cids[] = $val->id;

        }
        //print_object(implode('<br>',$import_arr));die;
        $row['Courses'] = count($enrol_courses)?implode('<br><br>',$import_arr):'N/A';
        //
        // Build parameterized query for caseload.
        $caseload = [];
        if (!empty($cids)) {
            list($cids_sql, $cids_params) = $DB->get_in_or_equal($cids, SQL_PARAMS_NAMED, 'cid');
            $sql = "SELECT s.id AS studentid,
                        c.id AS courseid,
                        c.fullname AS coursename,
                        g.id AS groupid,
                        g.name AS groupname,
                        CONCAT(s.firstname, ' ', s.lastname) AS studentname,
                        s.email
                    FROM {groups_members} gm_teacher
                    JOIN {groups} g ON g.id = gm_teacher.groupid
                    JOIN {course} c ON c.id = g.courseid
                    JOIN {user} t ON t.id = :teacherid
                    JOIN {groups_members} gm_students ON gm_students.groupid = g.id
                    JOIN {user} s ON s.id = gm_students.userid
                    JOIN {role_assignments} ra ON ra.userid = s.id
                    JOIN {context} ctx ON ctx.id = ra.contextid
                    JOIN {role} r ON r.id = ra.roleid
                    WHERE
                        gm_teacher.userid = :tutorid
                        AND ctx.contextlevel = 50
                        AND ctx.instanceid $cids_sql
                        AND r.shortname = 'student'
                    ORDER BY c.fullname, g.name, s.firstname";
            //
            $query_params = array_merge(['teacherid' => $record->id, 'tutorid' => $record->id], $cids_params);
            $caseload = $DB->get_records_sql($sql, $query_params);
        }
        $url = new moodle_url('/local/learner/tutorlearners.php',['id'=>$record->id,'context'=>trim(implode(',',$cids))]);
        $row['caseload_count'] = '<center><a href="'.$url.'" target="_blank" style="text-align:center">'.count($caseload).'</a></center>';
        //
        
        $edit_url = new moodle_url('/local/learner/edit.php',['id'=>$record->id]);
        $login_as = new moodle_url('/local/learner/loginasfun.php',['id'=>$record->id]);
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
        $count_recs_arr[] = $record->id;
    }
    $result .= html_writer::table($table);
}

echo $OUTPUT->header();

$count_recs = count($count_recs_arr);
echo '<h3>Tutor Management</h3>';
echo '<ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="https://gecko.atomlms.co.uk/">Home</a></li>
                <li class="breadcrumb-item active" style="margin-top: 4px;">Manage Tutors</li>
            </ol>';
echo '<div class="card-header-lms">
                        <h4 class="m-b-0  headline-lms">
                            <div class="pull-left m-2" >
                                <strong style="padding-right:12px"> Tutor Accounts</strong><span class="label label-rounded label-warning m-l-10 label-all">'.$count_recs.'</span>
                            </div>
                            <a href="'.$CFG->wwwroot.'/user/editadvanced.php?id=-1" target="_blank"  class="btn btn-success waves-effect waves-light pull-right mr-2">
                                <span class="btn-label">
                                                <i class="fa fa-lg fa-plus"></i>
                                </span>New Tutor 
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
                                        var oTable = $('#tutors').DataTable({
                                            dom: 'Blfrtip',
                                            'lengthMenu': [[15,25, 50, 100, 200, -1], [15,25, 50,100, 200, 'All']],
                                            buttons: [
                                            'excel', 'pdf'
                                            ],
                                           
                                        });
                                        $('.dataTables_filter').css('display','block'); 
                                        $('.dataTables_info').css('display','block !important');
                                        $('.dataTables_length').css('float','right !important');
                                       
                                        })
                                        "
                                    ); 
echo $result;
echo $OUTPUT->footer();
//
/*$url = $CFG->wwwroot .'/local/learner/js/tutor.js';
echo '<script src='.$url.'></script>';*/
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

    

