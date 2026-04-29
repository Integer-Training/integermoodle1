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
 * @package   local_tutor
 * @copyright 2025 shiva@gecko
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("filter_form.php");
global $DB,$CFG;

require_login();
$action = optional_param('action','',PARAM_RAW);
$context = context_system::instance();
require_capability('local/tutors:view', $context);
echo '<link href="https://gecko.atomlms.co.uk/scripts/css/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://gecko.atomlms.co.uk/scripts/scss/icons/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel=stylesheet>';
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
//
$PAGE->set_url(new moodle_url('/local/tutors/reassign.php'));
$PAGE->set_context($context);
$PAGE->set_title('Tutors');

//$PAGE->navbar->add('Learners');
$PAGE->set_context(context_system::instance()); 
//$PAGE->requires->jquery();
//$PAGE->requires->jquery('ui');

//
$PAGE->requires->js('/local/learner/js/jquery.dataTables.min.js',true);
$PAGE->requires->css('/local/learner/js/jquery.dataTables.min.css',true);

echo '<!-- jQuery (required) -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<!-- Select2 CSS -->
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>';
if($action == 'store' && $action){
   //to store data into DB
    $object_data = $_POST;
    if($object_data){
        //
       // print_object($object_data);
        $sql = "SELECT gm_students.id as gmid,gm_students.userid as studentid
                FROM mdl_groups_members gm_teacher
                JOIN mdl_groups g ON g.id = gm_teacher.groupid
                JOIN mdl_course c ON c.id = g.courseid
                -- teacher
                JOIN mdl_user t ON t.id = ".$object_data['tutor']."
                -- students in SAME group
                JOIN mdl_groups_members gm_students ON gm_students.groupid = g.id
                JOIN mdl_user s ON s.id = gm_students.userid
                -- student role check
                JOIN mdl_role_assignments ra ON ra.userid = s.id
                JOIN mdl_context ctx ON ctx.id = ra.contextid
                JOIN mdl_role r ON r.id = ra.roleid
                WHERE ctx.contextlevel = 50
                    AND ctx.instanceid = c.id
                    AND c.id = ".$object_data['course']."
                    AND r.shortname = 'student'
                    AND s.id in(".implode(',',$object_data['selected']).")";
        $finaldata = $DB->get_records_sql($sql);
       // print_object($finaldata);

        
        foreach($finaldata as $rec){ 
           $DB->delete_records('groups_members',['id'=>$rec->gmid]);
            //
            $sql = "SELECT 
                        g.id AS groupid,
                        g.name AS groupname
                    FROM mdl_groups_members gm_teacher
                    JOIN mdl_groups g ON g.id = gm_teacher.groupid
                    JOIN mdl_course c ON c.id = g.courseid
                    -- teacher
                    JOIN mdl_user t ON t.id = ".$object_data['reassigntutor']."

                    -- students in SAME group
                    JOIN mdl_groups_members gm_students ON gm_students.groupid = g.id
                    JOIN mdl_user s ON s.id = gm_students.userid

                    -- student role check
                    JOIN mdl_role_assignments ra ON ra.userid = s.id
                    JOIN mdl_context ctx ON ctx.id = ra.contextid
                    JOIN mdl_role r ON r.id = ra.roleid

                    WHERE ctx.contextlevel = 50
                        AND ctx.instanceid = c.id
                        AND gm_teacher.userid = ".$object_data['reassigntutor']."
                        AND c.id = ".$object_data['course']."
                        AND r.shortname = 'student'";
            $newres = $DB->get_record_sql($sql);
            //print_object($newres);die;
            $reassign_obj = new stdClass();
            $reassign_obj->groupid = $newres->groupid;
            $reassign_obj->userid = $rec->studentid;
            $reassign_obj->component = '';
            $reassign_obj->timeadded = time();
            $reassign_obj->itemid = 0;
            $insert = $DB->insert_record('groups_members',$reassign_obj);

        }
        redirect(new moodle_url('/local/tutors/reassign.php',array('context'=>1)),'Reassign Caselaod has been Allocated');
    }
}
$mform = new filter_reassign_form(null,[]);
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/tutors/view.php'));
} else if($data = $mform->get_data()){
    $fromform = data_submitted(); 
}
$result = '';
if($fromform){
    $result .= '<form id="dateForm" action="reassign.php?action=store" method="POST">';
    $result .= '<input type="hidden" class="userslist" name="tutor" value="'.$fromform->tutor.'" >';
    $result .= '<input type="hidden" class="courselist" name="course" value="'.$fromform->course.'" />';
    $conditions_sql = true;
    //print_object($fromform);die;
    $sql = "SELECT s.id AS studentid,
		    c.id AS courseid,
		    c.fullname AS coursename,
		    g.id AS groupid,
		    g.name AS groupname,
            gm_teacher.userid as teacherid,
		    CONCAT(s.firstname, ' ', s.lastname) AS studentname,
		    s.email
		FROM mdl_groups_members gm_teacher
		JOIN mdl_groups g ON g.id = gm_teacher.groupid
		JOIN mdl_course c ON c.id = g.courseid

		-- teacher
	    JOIN mdl_user t ON t.id = ".$fromform->tutor."

		-- students in SAME group
		JOIN mdl_groups_members gm_students ON gm_students.groupid = g.id
		JOIN mdl_user s ON s.id = gm_students.userid

		-- student role check
		JOIN mdl_role_assignments ra ON ra.userid = s.id
		JOIN mdl_context ctx ON ctx.id = ra.contextid
		JOIN mdl_role r ON r.id = ra.roleid

		WHERE ctx.contextlevel = 50
		    AND ctx.instanceid = c.id
		    AND r.shortname = 'student'";
    //
    if($fromform->course){
        //$sql .= "JOIN {assign} a ON  a.id=ag.assignment"
        $sql .= "  AND c.id=".$fromform->course."";
    }
    //
    if($fromform->tutor){
        $sql .= "  AND gm_teacher.userid =".$fromform->tutor." ";
    }
    $sql .= " ORDER BY c.fullname, g.name, s.firstname";
    $records = $DB->get_records_sql($sql);
   // print_object($records);die;
    if($records){
        $table = new html_table();
        $table->id = "learners";
        $table->head = array(
            '<input type="checkbox" class="selectall" name="selected[]" />',
            'Learner Name',
            'Main Assessor',
            'Qualification',
            'Enrolled Date',  
        );
        $table->data = array();
        foreach($records as $record){
            $row = array();
            $row['chk'] = '<input type="checkbox" class="row-check" name="selected[]" value="'.$record->studentid.'"/>';
            $row['learnername'] = $DB->get_field('user','firstname',['id'=>$record->studentid]).' '.$DB->get_field('user','lastname',['id'=>$record->studentid]);
            $row['Assessor'] = $DB->get_field('user','firstname',['id'=>$record->teacherid]).' '.$DB->get_field('user','lastname',['id'=>$record->teacherid]);
            $row['Qualification'] = $record->coursename;
            $enrol = $DB->get_record('enrol',['courseid'=>$record->courseid,'enrol'=>'manual']);
            $enrol_date = $DB->get_record('user_enrolments',['enrolid'=>$enrol->id,'userid'=>$record->studentid]);
            $row['enrolldate'] = date('d-m-Y',$enrol_date->timemodified);
            $table->data[] = $row;
        }
        $result .= html_writer::table($table);
        $result .= '<div id="fitem_id_tutor" class="form-group row px-0 mt-1 mb-3 mt-md-2  fitem">
                    <div class="col-form-label mb-sm-1 mb-md-0 pl-0 col-md-6">                     
                        <label id="id_tutor_label" class="d-inline " for="id_tutor">
                           Re-Assign Learners to
                        </label>
                        <sup class="sup"></sup>
                    
                        <select class="form-select" name="reassigntutor" id="id_reassigntutor"  aria-hidden="true" style="margin-left: 20px;">
                            <option value="">--Select Assessor--</option>
                            <option value="83">Anaswara Sebastian</option>
                            <option value="51">Selvi Chinnathambi</option>
                            <option value="297">Mary Divya Dassan</option>
                            <option value="177">Meenakshi Kapila</option>
                            <option value="206">Nargis Ansari</option>
                            <option value="31">Saima Rashid</option>
                            <option value="33">Katie Fletcher</option>
                            <option value="269">Evie May</option>
                        </select>

                    </div>
                </div>';
        $result .= '<button type="submit" class="btn btn-primary px-4">Re-assign Selected</button>';
        $result .= '</form>';
    }else{
        $result .= '<div class=" alert alert-danger alert-block fade in" align="center">No records available</div>';
    }
}
echo $OUTPUT->header();
//$records = $DB->get_records_sql($sql);

//}

echo '<h3>Re-Assign Caseload</h3>';
echo '<ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="'.$CFG->wwwroot.'/">Home</a></li>
            </ol>';
echo '<div class="card-header-lms">
                    <h3 class="m-b-0 text-white">
                        <div class="pull-left">
                           Re-Assign Caseload
                        </div>
                        
                    </h3>
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
                                            'lengthMenu': [[10,50, 100, 200, -1], [10, 50,100, 200, 'All']],
                                            buttons: [
                                            'excel'
                                            ],
                                           
                                        });
                                        $('.dataTables_filter').css('display','block'); 
                                        $('.dataTables_info').css('display','block !important');
                                        $('.dataTables_length').css('float','left !important');
                                       
                                        })
                                        "
                                    ); 
echo $mform->display();
echo $result;
echo $OUTPUT->footer();
//


echo "<script>
      $(document).ready(function() {
        $('.col-form-label').removeClass('col-md-3').addClass('col-md-6');
        $('.form-inline').removeClass('col-md-9').addClass('col-md-6');
        $('.col-form-label').removeClass('text-md-right');
        $('.fdate_selector').removeClass('flex-wrap'); 
        $('#id_user').select2();
        $('#id_course').select2();
        $('#id_tutor').select2();
        $('#id_reassigntutor').select2();
      });
       $('.selectall').click(function(){
            $('input:checkbox').not(this).prop('checked', this.checked);
        });
     </script>";
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
            float:left;
        }
        .text-danger {
            color: #e25959 !important;
        }
        div.dt-buttons {
            display:none !important;
        }
        .fa-2x {
            font-size: 2em !important;
        }
        .fa.fa-file-pdf-o {
            font-family: "Font Awesome 6 Free";
            font-weight: bold !important;
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
            color: #000000 !important;
            height: 20px;
        }
        th.header{
            color:#000 !important;
        }
        table.dataTable  th, .table th {
            font-size: 14px;
            vertical-align: bottom;
        }
        .card-header-lms{
            padding: 1.75rem 1.25rem;
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
            color: #fff;
            background-color: #d9534f;
            border-color: #d9534f;
        }
      </style>';

    

