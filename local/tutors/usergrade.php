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
$courseid = required_param('id', PARAM_INT);
$context = context_system::instance();

echo '<link href="https://gecko.atomlms.co.uk/scripts/css/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://gecko.atomlms.co.uk/scripts/scss/icons/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel=stylesheet>';
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
//
$PAGE->set_url(new moodle_url('/local/tutors/view.php'));
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
$mform = new filter_form(null,[]);
    $conditions_sql = false;
    $sql = 'SELECT *  FROM {assign_grades} WHERE grader IS NOT NULL AND grader !=-1 AND grader >2 and userid='.$USER->id.'
            group by userid,assignment';
    $records = $DB->get_recordset_sql($sql);
    //
    if($records){
        $table = new html_table();
        $table->id = "learners";
        $table->head = array(
            'Learner Name',
            'Assessor',
            'Qualification',
            'Assignment Name',
             'Submission Date',
            'Date Marked',
            'Feedback',
            'Result',  
        );
        $table->data = array();
        foreach($records as $record){
            $row = array();
            $row['learnername'] = $DB->get_field('user','firstname',['id'=>$record->userid]).' '.$DB->get_field('user','lastname',['id'=>$record->userid]);
            $row['Assessor'] = $DB->get_field('user','firstname',['id'=>$record->grader]).' '.$DB->get_field('user','lastname',['id'=>$record->grader]);
            $assign_obj = $DB->get_record('assign',['id'=>$record->assignment]);
            $course_obj = $DB->get_record('course',['id'=>$assign_obj->course]);
            if($course_obj->id != $courseid){
               continue;
            }
            //
            $cm_obj = $DB->get_record('course_modules',['instance'=>$assign_obj->id,'course'=>$assign_obj->course,'module'=>1]);
            if(!$cm_obj->visible && $cm_obj->visible != 1){
               continue;
            }
            $row['Qualification'] =  $course_obj->fullname;
            $row['Assignment'] = $assign_obj->name;
           // $row['Resubmission'] = 'N/A';
            //
            $sub_sql = "SELECT sub.timemodified as subtime FROM {assign_submission} sub
                    WHERE sub.status = 'submitted'
                    AND userid=".$record->userid." AND assignment=".$assign_obj->id."";
            //
            $sub_time = $DB->get_record_sql($sub_sql);
            
            $row['Submission'] = date('d-m-Y',$sub_time->subtime);
            $row['Date'] =  date('d-m-Y',$record->timemodified);
            //
            //result query her
            $r_sql = 'SELECT
                        TRIM(
                            SUBSTRING_INDEX(
                                SUBSTRING_INDEX(sc.scale, ",", gg.finalgrade),
                                ",",
                                -1
                            )
                        ) AS grade_label,
                        FROM_UNIXTIME(ag.timemodified) AS graded_on
                    FROM r6ua_assign_grades ag
                    JOIN r6ua_user t ON t.id = ag.grader          -- TUTOR
                    JOIN r6ua_user su ON su.id = ag.userid        -- STUDENT
                    JOIN r6ua_assign a ON a.id = ag.assignment
                    JOIN r6ua_grade_items gi ON gi.iteminstance = a.id
                        AND gi.itemmodule = "assign"
                    JOIN r6ua_grade_grades gg ON gg.itemid = gi.id
                        AND gg.userid = ag.userid
                    JOIN r6ua_scale sc ON sc.id = gi.scaleid
                    WHERE gg.finalgrade IS NOT NULL AND su.id = '.$record->userid.'
                    AND a.id = '.$assign_obj->id.'                                                                                                                                  
                    ORDER BY su.id,a.id';
            $resultobj = $DB->get_record_sql($r_sql);
            //echo $result->grade_label;
            //feedback files
            $mod_context = context_module::instance($cm_obj->id);
            //
            $fs = get_file_storage();

            $files = $fs->get_area_files(
                $mod_context->id,
                'assignfeedback_file',
                'feedback_files',
                $record->id,
                'filename',
                false
            );
            if($files){
                //echo $record->grader;
                //echo '<br>';
                foreach ($files as $file) {
                    $url = moodle_url::make_pluginfile_url(
                        $mod_context->id,
                        'assignfeedback_file',
                        'feedback_files',
                        $record->id,
                        '/',
                        $file->get_filename()
                    );
                }                                                                                           
            }else{
                $url = "#";
            }
                           
            //ends                                                                                                                                  
            $row['Feedback'] =  '<a href="'.$url.'" target="_blank"><i class="fa text-danger fa-file-pdf-o fa-2x"></i></a>';
            if($resultobj->grade_label == 'Pass'){
                $row['Result'] =  '<a href="/tutors-remark/781031/2433/" class="fbox btn btn-success" style="color:#ffffff">Pass</a>';
            }else if($resultobj->grade_label == 'Refer'){
                $row['Result'] =  '<a href="/tutors-remark/781031/2433/" class="fbox btn btn-danger" style="color:#ffffff">Refer</a>';
            }else if($resultobj->grade_label){
                $row['Result'] = ucowrds($result->grade_label);
            }else{
                $row['Result'] = 'N/A';
            }
           // $row['Result'] =  '<a href="/tutors-remark/781031/2433/" class="fbox btn btn-success" style="color:#ffffff">Pass</a>';
            //$row['Result'] =  $result->grade_label;
            
            
            //
            $table->data[] = $row;
        }
        $count_recs = count($table->data);
        
        
        if(!$count_recs){
            $url = new moodle_url('/course/view.php',['id'=>$courseid]);
            $result .= '<div class=" alert alert-danger alert-block fade in" align="center">No Grades Available</div>
                        <a class="btn btn-primary" align="center" href='.$url.' style="">Back to Course</a>';
        }else{
            $result .= html_writer::table($table);
        }

    }

echo $OUTPUT->header();



echo '<h3>Gradebook</h3>';
echo '<ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="https://epearlacademy.com/">Home</a></li>
            </ol>';

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
                                            'lengthMenu': [[25,50, 100, 200, -1], [25, 50,100, 200, 'All']],
                                            buttons: [
                                            'excel'
                                            ],
                                           
                                        });
                                        $('.dataTables_filter').css('display','none'); 
                                        $('.dataTables_info').css('display','block !important');
                                        $('.dataTables_length').css('float','right !important');
                                       
                                        })
                                        "
                                    ); 
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
                $("#id_user").html(response);
            }
    });
    
');
echo "<script>
      $(document).ready(function() {
        $('.col-form-label').removeClass('col-md-3').addClass('col-md-6');
        $('.form-inline').removeClass('col-md-9').addClass('col-md-6');
        $('.col-form-label').removeClass('text-md-right');
        $('.fdate_selector').removeClass('flex-wrap'); 
        $('#id_user').select2();
        $('#id_course').select2();
        $('#id_tutor').select2();
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
            float:right;
        }
        td{
            text-align:center !important;
        }
        .text-danger {
            color: #e25959 !important;
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
            background: #cbd446;
            height: 75px;
        }
        th.header{
            color:#ffff !important;
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
echo "<script>
document.addEventListener('DOMContentLoaded', function () {
    const currentUrl = window.location.pathname.split('/').pop();

    document.querySelectorAll('.dropdown-item').forEach(link => {
        if (link.getAttribute('href') === currentUrl) {
            // highlight active link
            link.classList.add('active');

            // open parent collapse
            const collapseEl = link.closest('.collapse');
            if (collapseEl) {
                new bootstrap.Collapse(collapseEl, { toggle: true });

                // update aria-expanded
                const toggle = collapseEl.previousElementSibling;
                if (toggle) toggle.setAttribute('aria-expanded', 'true');
            }
        }
    });
});
</script>";
    

