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
    $cms = $fromform->cms;
    $cid = ($cid)?$cid:$fromform->cid;
    $result = '';
    if($cms){
        $table = new html_table();
        $table->id = "sessions";
        $table->head = array('Student Name','Activity Name','Date','Session Time','Total Learning Time');
        $table->data = array();
        // Get course users first or group users.
        $course_context = context_course::instance($cid);
        $groups = $DB->get_records('groups_members',['userid'=>$USER->id]);
        $actual_course_group = [];
        foreach($groups as $rec){
            $grp = $DB->get_record('groups',['id'=>$rec->groupid,'courseid'=>$cid]);
            if ($grp) {
                $actual_course_group[] = $grp;
            }
        }

        $gm = array();
        foreach($actual_course_group as $group){
             $gm[] = $DB->get_records('groups_members',['groupid'=>$group->id]);
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
            // Determine which users to query.
            if(!empty($fromform->users) && is_array($fromform->users)){
               $selected_users = array_map('intval', $fromform->users);
            }else{
                $selected_users = array_map('intval', $act_gm);
            }

            // Use {table} syntax so prefix works on both local (mdl_) and production (r6ua_).
            $prefix = $CFG->prefix;
            list($userinsql, $userparams) = $DB->get_in_or_equal($selected_users, SQL_PARAMS_NAMED, 'u');
            $cmsid = (int)$fromform->cms;

            // Per-day session query using MySQL session variables.
            $sql = "SELECT
                    userid,
                    log_day,
                    SEC_TO_TIME(SUM(diff_seconds)) AS total_time,
                    SUM(diff_seconds) AS total_seconds
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
                        FROM {$prefix}logstore_standard_log,
                             (SELECT @prev_user := NULL, @prev_day := NULL, @prev_time := NULL) vars
                             WHERE objectid = {$cmsid} AND component = 'mod_hvp' AND userid {$userinsql}
                        ORDER BY userid, timecreated
                    ) t
                    GROUP BY userid, log_day
                    ORDER BY log_day DESC";

            $records_result = $DB->get_recordset_sql($sql, $userparams);

            // Build per-user totals in a first pass, and collect rows.
            $rows = [];
            $user_totals = []; // userid => total seconds.
            foreach($records_result as $rec){
                $rows[] = clone $rec;
                if (!isset($user_totals[$rec->userid])) {
                    $user_totals[$rec->userid] = 0;
                }
                $user_totals[$rec->userid] += (int)$rec->total_seconds;
            }
            $records_result->close();

            // Cache lookups.
            $assignment = $DB->get_record('hvp', ['course' => $cid, 'id' => $cmsid]);
            $user_cache = [];

            foreach($rows as $rec){
                if (!isset($user_cache[$rec->userid])) {
                    $user_cache[$rec->userid] = $DB->get_record('user', ['id' => $rec->userid], 'id,firstname,lastname');
                }
                $u = $user_cache[$rec->userid];
                $row = array();
                $row['studentname'] = $u->firstname . ' ' . $u->lastname;
                $row['activityname'] = $assignment ? $assignment->name : '';
                $row['date'] = $rec->log_day;
                $row['session_time'] = $rec->total_time;

                // Total Learning Time badge.
                $total_secs = $user_totals[$rec->userid];
                $hours = floor($total_secs / 3600);
                $mins  = floor(($total_secs % 3600) / 60);
                $secs  = $total_secs % 60;
                $total_formatted = sprintf('%02d:%02d:%02d', $hours, $mins, $secs);
                $row['total_learning_time'] = '<span class="session-total-badge">'
                    . '<i class="fa fa-clock-o"></i> ' . $total_formatted
                    . '</span>';

                $table->data[] = $row;
            }
        }
        if (!empty($table->data)) {
            $result .= html_writer::table($table);
        } else {
            $result .= '<div class="alert alert-warning" align="center">No session data found for this activity.</div>';
        }
    }else{
        $result .= '<div class="alert alert-danger alert-block fade in" align="center">No records available</div>';
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
        .session-total-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: #7c3aed;
            color: #fff;
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }
        .session-total-badge .fa-clock-o {
            font-size: 14px;
            opacity: 0.9;
        }
     </style>';
