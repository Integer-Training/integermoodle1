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
echo '<link href="https://gecko.atomlms.co.uk/scripts/css/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://gecko.atomlms.co.uk/scripts/scss/icons/font-awesome/css/font-awesome.min.css" rel=stylesheet>';
echo '<link href="https://code.ionicframework.com/ionicons/2.0.1/css/ionicons.min.css" rel=stylesheet>';
echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';
//
$PAGE->set_url(new moodle_url('/local/learner/marlallocation.php'));
$PAGE->set_context($context);
$PAGE->set_title(ucwords($action));

//$PAGE->navbar->add('Learners');
$PAGE->set_context(context_system::instance()); 
$PAGE->requires->jquery();
$PAGE->requires->jquery('ui');
//
$PAGE->requires->js('/local/learner/js/jquery.dataTables.min.js',true);
$PAGE->requires->css('/local/learner/js/jquery.dataTables.min.css',true);
//
$prefix = $CFG->prefix;
$result = '';
$table = new html_table();
$table->id = "learners";
if($action == 'mark'){
    $table->head = array(
        'Learner Name',
        'Course Title',
        'Assignment Name',
        'Submission Date',
        'Status',
        'Grade',
        'AI Check',
        'Tutor',
    );
    $mark_sql = "SELECT sub.id as submision_id,sub.userid,a.course,a.id as assign_id,sub.timemodified as subtime
            FROM {$prefix}groups_members gm_t
            JOIN {$prefix}groups g
                ON g.id = gm_t.groupid
            JOIN {$prefix}groups_members gm_s
                ON gm_s.groupid = g.id
                AND gm_s.userid <> gm_t.userid
            JOIN {$prefix}role_assignments ra
                ON ra.userid = gm_s.userid
            JOIN {$prefix}context ctx
                ON ctx.id = ra.contextid
                AND ctx.contextlevel = 50
                AND ctx.instanceid = g.courseid
            JOIN {$prefix}role r
                ON r.id = ra.roleid
                AND r.shortname = 'student'
            JOIN {$prefix}user u
                ON u.id = gm_s.userid
                AND u.suspended = 0
                AND u.deleted = 0
            JOIN {$prefix}assign a
                ON a.course = g.courseid
            JOIN {$prefix}modules mod_m
                ON mod_m.name = 'assign'
            JOIN {$prefix}course_modules cm
                ON cm.instance = a.id
                AND cm.course = a.course
                AND cm.module = mod_m.id
                AND cm.visible = 1
            JOIN {$prefix}assign_submission sub
                ON sub.assignment = a.id
                AND sub.userid = gm_s.userid
                AND sub.status = 'submitted'
                AND sub.latest = 1
                AND sub.attemptnumber = 0
            LEFT JOIN {$prefix}assign_grades gr
                ON gr.assignment = a.id
                AND gr.userid = gm_s.userid
                AND gr.attemptnumber = sub.attemptnumber
            WHERE
                gm_t.userid = ".(int)$USER->id."
                AND a.name NOT LIKE '%IAG%'
                AND a.name NOT LIKE '%ID Proof%'
                AND (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0)";
//
    $results = $DB->get_recordset_sql($mark_sql);

}else if($action == 'resub'){
    // Resubmissions Awaiting Review — attemptnumber > 0.
    $table->head = array(
        'Learner Name',
        'Course Title',
        'Assignment Name',
        'Submission Date',
        'Status',
        'Grade',
        'AI Check',
        'Tutor',
    );
    // ============================================================
    // RESUBMISSIONS AWAITING REVIEW
    //   Case 1: Normal resubmission (attemptnumber > 0) not yet graded.
    //   Case 2: Re-upload on same attempt — learner updated their submission
    //           AFTER receiving a grade (sub.timemodified > gr.timemodified).
    //           This catches the common scenario where attemptreopenmethod
    //           didn't fire and the learner re-uploaded on attemptnumber = 0.
    // ============================================================
    $resub_sql = "SELECT sub.id as submision_id,sub.userid,a.course,a.id as assign_id,sub.timemodified as subtime
            FROM {$prefix}groups_members gm_t
            JOIN {$prefix}groups g
                ON g.id = gm_t.groupid
            JOIN {$prefix}groups_members gm_s
                ON gm_s.groupid = g.id
                AND gm_s.userid <> gm_t.userid
            JOIN {$prefix}role_assignments ra
                ON ra.userid = gm_s.userid
            JOIN {$prefix}context ctx
                ON ctx.id = ra.contextid
                AND ctx.contextlevel = 50
                AND ctx.instanceid = g.courseid
            JOIN {$prefix}role r
                ON r.id = ra.roleid
                AND r.shortname = 'student'
            JOIN {$prefix}user u
                ON u.id = gm_s.userid
                AND u.suspended = 0
                AND u.deleted = 0
            JOIN {$prefix}assign a
                ON a.course = g.courseid
            JOIN {$prefix}modules mod_r
                ON mod_r.name = 'assign'
            JOIN {$prefix}course_modules cm_r
                ON cm_r.instance = a.id
                AND cm_r.course = a.course
                AND cm_r.module = mod_r.id
                AND cm_r.visible = 1
            JOIN {$prefix}assign_submission sub
                ON sub.assignment = a.id
                AND sub.userid = gm_s.userid
                AND sub.status = 'submitted'
                AND sub.latest = 1
            LEFT JOIN {$prefix}assign_grades gr
                ON gr.assignment = a.id
                AND gr.userid = gm_s.userid
                AND gr.attemptnumber = sub.attemptnumber
            WHERE
                gm_t.userid = ".(int)$USER->id."
                AND a.name NOT LIKE '%IAG%'
                AND a.name NOT LIKE '%ID Proof%'
                AND (
                    (sub.attemptnumber > 0 AND (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0))
                    OR
                    (gr.id IS NOT NULL AND gr.grade IS NOT NULL AND gr.grade >= 0
                     AND EXISTS (
                         SELECT 1 FROM {$prefix}files f
                         WHERE f.component = 'assignsubmission_file'
                         AND f.filearea = 'submission_files'
                         AND f.itemid = sub.id
                         AND f.filename <> '.'
                         AND f.timecreated > gr.timemodified
                     ))
                )";
//
    $results = $DB->get_recordset_sql($resub_sql);

}else if($action == 'overdue'){
    $table->head = array(
        'Learner Name',
        'Course Title',
        'Assignment Name',
        'Submission Date',
        'Overdue By (Days)',
        'Tutor',  
    );
    $o_sql = "SELECT
               sub.id as submision_id,sub.userid,a.course,a.id as assign_id
            FROM {$prefix}groups_members gm_t
            JOIN {$prefix}groups g
                ON g.id = gm_t.groupid
            JOIN {$prefix}groups_members gm_s
                ON gm_s.groupid = g.id
                AND gm_s.userid <> gm_t.userid
            JOIN {$prefix}user u
                ON u.id = gm_s.userid
                AND u.suspended = 0
                AND u.deleted = 0
            JOIN {$prefix}role_assignments ra
                ON ra.userid = u.id
            JOIN {$prefix}context ctx
                ON ctx.id = ra.contextid
                AND ctx.contextlevel = 50
                AND ctx.instanceid = g.courseid
            JOIN {$prefix}role r
                ON r.id = ra.roleid
                AND r.shortname = 'student'
            JOIN {$prefix}assign a
                ON a.course = g.courseid
            JOIN {$prefix}modules mod_o
                ON mod_o.name = 'assign'
            JOIN {$prefix}course_modules cm_o
                ON cm_o.instance = a.id
                AND cm_o.course = a.course
                AND cm_o.module = mod_o.id
                AND cm_o.visible = 1
            JOIN {$prefix}assign_submission sub
                ON sub.assignment = a.id
                AND sub.userid = u.id
                AND sub.latest = 1
            LEFT JOIN {$prefix}assign_grades gr
                ON gr.assignment = a.id
                AND gr.userid = gm_s.userid
                AND gr.attemptnumber = sub.attemptnumber
            WHERE
                gm_t.userid = ".(int)$USER->id."
                AND sub.status = 'submitted'
                AND a.name NOT LIKE '%IAG%'
                AND a.name NOT LIKE '%ID Proof%'
                AND (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0)
                AND sub.timemodified > 0 AND sub.timemodified IS NOT NULL
                AND sub.timemodified <= ".strtotime('now')."";
    $results = $DB->get_recordset_sql($o_sql);
    //print_object($results);die;
}else if($action = 'imm'){
    $table->head = array(
        'Learner Name',
        'Course Title',
        'Assignment Name',
        'Due Date',
        'Overdue In (Days)',
        'Tutor',  
    );
    $userid = $USER->id; // user ID you want
    $courses = enrol_get_users_courses($userid);
    $allcourses = array();
    foreach ($courses as $course) {
      $allcourses[] = $course->id;
    }
    $m_sql = "SELECT
                a.course,a.id as assign_id,u.id as userid
                FROM {$prefix}groups_members gm_t
                JOIN {$prefix}groups g
                    ON g.id = gm_t.groupid
                JOIN {$prefix}groups_members gm_s
                    ON gm_s.groupid = g.id
                    AND gm_s.userid <> gm_t.userid
                JOIN {$prefix}user u
                    ON u.id = gm_s.userid
                    AND u.suspended = 0
                    AND u.deleted = 0
                JOIN {$prefix}role_assignments ra
                    ON ra.userid = u.id
                JOIN {$prefix}context ctx
                    ON ctx.id = ra.contextid
                    AND ctx.contextlevel = 50
                    AND ctx.instanceid = g.courseid
                JOIN {$prefix}role r
                    ON r.id = ra.roleid
                    AND r.shortname = 'student'
                JOIN {$prefix}assign a
                 ON a.course = g.courseid
                JOIN {$prefix}modules mod_i
                    ON mod_i.name = 'assign'
                JOIN {$prefix}course_modules cm_i
                    ON cm_i.instance = a.id
                    AND cm_i.course = a.course
                    AND cm_i.module = mod_i.id
                    AND cm_i.visible = 1
                WHERE
                 gm_t.userid = ".(int)$USER->id." AND
                a.course in (".implode(',',$allcourses).")
                AND a.duedate > 0 AND a.duedate IS NOT NULL
                AND a.name NOT LIKE '%IAG%'
                AND a.name NOT LIKE '%ID Proof%'
                AND a.duedate <= ".strtotime("+3 days")."
                AND a.duedate >= ".strtotime('now')."";
    $results = $DB->get_recordset_sql($m_sql);
}else{
    throw_error('Invalid Request...');
}

if($results){
    $table->data = array();
    foreach($results as $record){
        $row = array();
        $row['learnername'] = fullname($DB->get_record('user',['id'=>$record->userid]));
        $url = new moodle_url('/course/view.php',['id'=>$record->course]);
        $row['coursename'] = '<a href="'.$url.'">'.$DB->get_field('course','fullname',['id'=>$record->course]).'</a>';
        $row['assignmentname'] =  $DB->get_field('assign','name',['id'=>$record->assign_id]);
        //
        $assign_obj = $DB->get_record('assign',['id'=>$record->assign_id]);
        // Visibility and suspended filters are now handled in SQL.
        // Get course_module using modules table for correct module ID.
        $mod_id = $DB->get_field('modules', 'id', ['name' => 'assign']);
        $cm_obj = $DB->get_record('course_modules',['instance'=>$assign_obj->id,'course'=>$assign_obj->course,'module'=>$mod_id]);
        if(!$cm_obj || !$cm_obj->visible){
            continue;
        }
        $user_object = $DB->get_record('user',['id'=>$record->userid]);
        if(!$user_object || $user_object->suspended || $user_object->deleted){
            continue;
        }
        if($action == 'mark' || $action == 'resub'){
            $sub_time = $record->subtime;
            $row['submission_date'] = date('d-m-Y', $sub_time);

            // Check if submission is late (compare with override or global duedate).
            $override = $DB->get_record('assign_overrides', ['assignid' => $record->assign_id, 'userid' => $record->userid]);
            $duedate = $override && $override->duedate ? $override->duedate : $assign_obj->duedate;

            if ($duedate && $sub_time > $duedate) {
                $days_late = ceil(($sub_time - $duedate) / 86400);
                $row['status'] = '<span class="badge badge-late" style="background:#f59e0b;color:#fff;padding:4px 10px;border-radius:4px;font-size:11px;font-weight:600;">LATE (' . $days_late . ' day' . ($days_late > 1 ? 's' : '') . ')</span>';
            } else {
                $row['status'] = '<span class="badge badge-ontime" style="background:#22c55e;color:#fff;padding:4px 10px;border-radius:4px;font-size:11px;font-weight:600;">On Time</span>';
            }

            $cm = $DB->get_record('course_modules',['course'=>$record->course,'instance'=>$record->assign_id,'module'=>$mod_id]);
            $url = new moodle_url('/mod/assign/view.php',['action'=>'grader','userid'=>$record->userid,'id'=>$cm->id]);

           $row['grade'] = '<a href="'.$url.'" class="btn btn-info"><i class="ionicons ion-edit"></i></a>';

           // AI Check button - manual GPTZero scan.
           // Check for existing scan result (only if GPTZero table exists).
           $existing_scan = null;
           $gptzero_table_exists = $DB->get_manager()->table_exists('plagiarism_gptzero_files');
           if ($gptzero_table_exists) {
               $existing_scan = $DB->get_record('plagiarism_gptzero_files', [
                   'cm' => $cm->id,
                   'userid' => $record->userid
               ]);
           }

           // For resubmissions, invalidate stale scan results (done before re-upload).
           if ($action == 'resub' && $existing_scan && !empty($existing_scan->timesubmitted)
               && $existing_scan->timesubmitted < $record->subtime) {
               $existing_scan = null;
           }

           if ($existing_scan && !empty($existing_scan->predicted_class)) {
               // Show View Report button only.
               $report_url = new moodle_url('/local/learner/aireport.php', ['id' => $record->submision_id]);

               $row['aicheck'] = '<a href="'.$report_url.'" class="btn btn-view-report" title="View detailed AI report">
                   <i class="fa fa-file-text"></i> View Report
               </a>';
           } else if ($gptzero_table_exists && get_config('plagiarism_gptzero', 'gptzero_apikey')) {
               // Show AI Check button for new scan (only if GPTZero is configured).
               $row['aicheck'] = '<button type="button" class="btn btn-ai-check"
                   data-submissionid="'.$record->submision_id.'"
                   data-learnername="'.s(fullname($user_object)).'"
                   data-assignname="'.s($assign_obj->name).'"
                   onclick="runAICheck(this)">
                   <i class="fa fa-search"></i> AI Check
               </button>
               <span class="ai-result" id="ai-result-'.$record->submision_id.'"></span>';
           } else {
               // GPTZero not configured - show N/A.
               $row['aicheck'] = '<span class="text-muted">N/A</span>';
           }
        }else if($action == 'overdue'){
            $row['submission_date'] = date('d-m-Y',$DB->get_field('assign_submission','timemodified',['id'=>$record->submision_id]));
            $today    = new DateTime("today");
            $pastDate = DateTime::createFromFormat('d-m-Y', $row['submission_date']);
           // $today    = new DateTime();
            $diff = $today->diff($pastDate);
            $row['oveduedays'] = $diff->days;
        }else if($action == 'imm'){
            $row['duedate'] = date('d-m-Y',$DB->get_field('assign','duedate',['id'=>$record->assign_id]));
            $today    = new DateTime("today");
            $pastDate = DateTime::createFromFormat('d-m-Y', $row['duedate']);
            $diff = $today->diff($pastDate);
            $row['oveduedays'] = $diff->days;
        }
        
        $row['tutor'] = fullname($USER);
        $table->data[] = $row;
    }
    $result .= html_writer::table($table);
}

echo $OUTPUT->header();
$sql = 'select * from {user} where id>2';
$records = $DB->get_records_sql($sql);
$count_recs = count($records);
if($action == 'mark'){
   echo '<h2>Awaiting Marking</h2>';
}else if($action == 'resub'){
   echo '<h2>Resubmissions Awaiting Review</h2>';
}else if($action == 'overdue'){
    echo '<h2>Overdue Assignments</h2>';
}else if($action == 'imm'){
   echo '<h2>Imminent Assignments</h2>';
}

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
echo $result;
echo $OUTPUT->footer();
//

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
        td{
            text-align: left;
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
        th.header{
            width: 300px !important;
        }
        /* AI Check Button Styles */
        .btn-ai-check {
            padding: 5px 10px !important;
            font-size: 12px !important;
            cursor: pointer !important;
            color: #fff;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 5px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .btn-ai-check:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn-view-report {
            background: linear-gradient(135deg, #3a5ba0 0%, #6ea3c1 100%);
            color: white !important;
            padding: 6px 12px;
            font-size: 12px;
            border: none;
            border-radius: 5px;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            text-decoration: none !important;
            margin-right: 8px;
        }
        .btn-view-report:hover {
            background: linear-gradient(135deg, #1a2238 0%, #3a5ba0 100%);
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(58, 91, 160, 0.4);
            color: white !important;
        }
        .btn-ai-check:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-ai-check.loading {
            pointer-events: none;
        }
        .btn-ai-check.loading i {
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .ai-result {
            display: inline-block;
            margin-left: 8px;
            padding: 4px 10px;
            border-radius: 15px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .ai-result.ai-human {
            background: #8AD4BA;
            color: #1a5c3f;
        }
        .ai-result.ai-ai {
            background: #FEBD69;
            color: #8b4513;
        }
        .ai-result.ai-mixed {
            background: #E9D2FF;
            color: #5a3d7a;
        }
        .ai-result.ai-error {
            background: #ffcccc;
            color: #990000;
        }
        .ai-result a {
            color: inherit;
            text-decoration: none;
        }
        .ai-result a:hover {
            text-decoration: underline;
        }
      </style>';

// AI Check JavaScript - must be after jQuery is loaded
echo '<script>
var sesskey = "'.sesskey().'";
var wwwroot = "'.$CFG->wwwroot.'";

function runAICheck(btn) {
    var submissionId = btn.getAttribute("data-submissionid");
    var learnerName = btn.getAttribute("data-learnername");
    var assignName = btn.getAttribute("data-assignname");
    var resultSpan = document.getElementById("ai-result-" + submissionId);

    // Confirm before running (to save word quota)
    if (!confirm("Run AI detection scan for:\\n\\nLearner: " + learnerName + "\\nAssignment: " + assignName + "\\n\\nThis will use words from your GPTZero quota. Continue?")) {
        return;
    }

    // Disable button and show loading
    btn.disabled = true;
    btn.classList.add("loading");
    btn.innerHTML = \'<i class="fa fa-spinner fa-spin"></i> Scanning...\';
    resultSpan.innerHTML = "";

    // Make AJAX call
    $.ajax({
        url: wwwroot + "/local/learner/aicheck.php",
        method: "POST",
        data: {
            submissionid: submissionId,
            sesskey: sesskey,
            action: "scan"
        },
        dataType: "json",
        success: function(response) {
            btn.disabled = false;
            btn.classList.remove("loading");

            if (response.success) {
                // Clear the result span - just show View Report button
                resultSpan.innerHTML = "";
                resultSpan.className = "";

                // Replace button with View Report link
                var reportUrl = response.scan_url || (wwwroot + "/local/learner/aireport.php?id=" + submissionId);
                var viewReportBtn = document.createElement("a");
                viewReportBtn.href = reportUrl;
                viewReportBtn.className = "btn btn-view-report";
                viewReportBtn.title = "View detailed AI report";
                viewReportBtn.innerHTML = \'<i class="fa fa-file-text"></i> View Report\';
                btn.parentNode.replaceChild(viewReportBtn, btn);
            } else {
                resultSpan.textContent = "Error";
                resultSpan.className = "ai-result ai-error";
                resultSpan.title = response.error || "Unknown error";
                btn.innerHTML = \'<i class="fa fa-exclamation-triangle"></i> Retry\';
            }
        },
        error: function(xhr, status, error) {
            btn.disabled = false;
            btn.classList.remove("loading");
            btn.innerHTML = \'<i class="fa fa-exclamation-triangle"></i> Retry\';
            var errMsg = error;
            try {
                var resp = JSON.parse(xhr.responseText);
                errMsg = resp.error || resp.errorcode || error;
            } catch(e) {
                errMsg = xhr.responseText || error;
            }
            resultSpan.textContent = "Error";
            resultSpan.className = "ai-result ai-error";
            resultSpan.title = errMsg;
            console.error("AI Check error:", errMsg, xhr.responseText);
            alert("AI Check Error: " + errMsg);
        }
    });
}
</script>';



