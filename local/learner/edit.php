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
//
$action = optional_param('action','',PARAM_RAW);
if($action){
  //print_object($_POST);die;
  $user_rec = $DB->get_record('user',array('id'=>$_POST['student_id']));
 // print_object($user_rec);die;
  if($user_rec){
     $update_obj = new stdClass();
     $update_obj->id = $user_rec->id;
     $update_obj->firstname = $_POST['firstname'];
     $update_obj->lastname = $_POST['lastname'];
     $update_obj->email = $_POST['email'];
     //print_object($update_obj);die;
     $DB->update_record('user',$update_obj);
     //insert
     $insert_obj = new stdClass();
     $insert_obj->userid = $user_rec->id;
     $insert_obj->dob = $_POST['dob'];
     $insert_obj->phone1 = $_POST['phone1'];
     $insert_obj->email = $_POST['email'];
     $insert_obj->gender = $_POST['gender'];
     $insert_obj->ethnicity = $_POST['ethnicity'];
     $insert_obj->disable = $_POST['disable'];
     $insert_obj->college_ref = $_POST['college_ref'];
     $insert_obj->ni_number = $_POST['ni_number'];
     $insert_obj->registration_number = $_POST['registration_number'];
     $insert_obj->contact_status = $_POST['contact_status'];
     $insert_obj->aeb_region = $_POST['aeb_region'];
     $insert_obj->address1 = $_POST['address1'];
     $insert_obj->address2 = $_POST['address2'];
     $insert_obj->town = $_POST['town'];
     $insert_obj->county = $_POST['county'];
     $insert_obj->postcode = $_POST['postcode'];
     $insert_obj->note_text = $_POST['note_text'];
     $insert = $DB->insert_record('local_users',$insert_obj);
     if($insert){
        redirect(new moodle_url('/local/learner/view.php'));
     }
      
  }
}

$context = context_system::instance();
require_capability('local/learner:view', $context);
$id = required_param('id',PARAM_INT);

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
echo $OUTPUT->header();
 echo '<!-- Google Font -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
  <!-- Bootstrap 5 -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <!-- Bootstrap Icons -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
$data = array();
//logic started here
$action_url = new moodle_url('/local/learner/edit.php?action=edit');
$data['action_url'] = $action_url;
$user_obj = $DB->get_record('user',['id'=>$id]);
$data['fullname'] = fullname($user_obj);
$data['back_url'] = new moodle_url('/local/learner/view.php');
$login_as = new moodle_url('/course/loginas.php',['id'=>1,'user'=>$user_obj->id,'sesskey'=>\sesskey()]);
//
$data['username'] = $user_obj->username;
$data['login_as'] = $login_as;
$data['created'] = date('d-m-Y h:i:s',$user_obj->timecreated);
$data['last_login'] = ($user_obj->lastaccess)?date('d-m-Y h:i:s',$user_obj->lastaccess):'N/A';
$data['firstname'] = $user_obj->firstname;
$data['lastname'] = $user_obj->lastname;
$data['email'] = $user_obj->email;
$data['phone'] = $user_obj->phone1;
$data['fullname'] = fullname($user_obj);
//
$data['userid'] = $user_obj->id;
//log
$result_data = $DB->get_records('local_leaner_user',['userid'=>$user_obj->id]);
$set = [];
foreach($result_data as $rec){
    $row = [];
    //$user_obj = $DB->get_record('user',['id'=>$rec->userid]);
    $row['learnername'] = fullname($DB->get_record('user',['id'=>$rec->userid]));
    $act_obj = $DB->get_record('user',['id'=>$rec->actionby]);
    $row['actionname'] = fullname($act_obj);
    $row['status'] = $rec->status;
    $row['time'] = date('d-m-Y h:i',$rec->timecreated);
    $set[] = $row;

}
$data['log'] = $set;
//activiti log
$sql = "SELECT l.id,
            c.fullname as coursename,
            CONCAT(u.firstname, ' ', u.lastname) AS learnername,
            COALESCE(a.name, q.name) AS activityname,
            l.action,
            l.target,
            FROM_UNIXTIME(l.timecreated) AS logtime
        FROM mdl_logstore_standard_log l
        JOIN mdl_user u ON u.id = l.userid
        JOIN mdl_course_modules cm ON cm.id = l.contextinstanceid
        JOIN mdl_modules m ON m.id = cm.module
        JOIN mdl_course c ON c.id=cm.course
        LEFT JOIN mdl_assign a ON m.name = 'assign' AND a.id = cm.instance
        LEFT JOIN mdl_hvp q ON m.name = 'hvp' AND q.id = cm.instance
        WHERE l.userid = ".$user_obj->id."
        ORDER BY l.timecreated DESC";
$activity_logs = $DB->get_records_sql($sql);
//print_object($activity_logs);die;
//
$datalog = array();
foreach($activity_logs as $logdata){
    $row = array();
    $row['coursename'] = $logdata->coursename;
    $row['activityname'] = $logdata->activityname;
    //
    $row['action'] = $logdata->action;
    $row['target'] = $logdata->target;
    $row['logtime'] = $logdata->logtime;
    $datalog[] = $row;
}
$data['datalog'] = $datalog;
//print_object($datalog);die;
echo $OUTPUT->render_from_template('local_learner/edit', $data);
echo $OUTPUT->footer();
echo '<style>
    :root{--brand-blue:#1728ff;--brand-accent:#7c5bdc}
    body{background:#eaf6f4;font-family:"Inter",system-ui,-apple-system,"Segoe UI",Roboto,"Helvetica Neue",Arial}
    .page-header{background:linear-gradient(90deg,var(--brand-blue) 0%, #1e20ff 50%, #0f14e8 100%);color:white;padding:22px 28px;border-radius:4px}
    .page-header h2{margin:0;font-weight:600;font-size:1.45rem}
    .breadcrumb{background:transparent;padding:0;margin-bottom:8px}
    .avatar-large{width:160px;height:160px;border-radius:50%;border:6px solid rgba(255,255,255,0.6);box-shadow:inset 0 0 0 6px rgba(0,0,0,0.02)}
    .profile-card{background:#fff;border-radius:4px}
    .info-label{font-weight:600;color:#6c757d;font-size:.9rem}
    .login-btn{background:var(--brand-accent);border:none;color:#fff}
    .back-btn{background:#f2a40b;color:#fff;border-radius:6px;padding:8px 14px;border:none}
    .section-sep{border-top:1px solid rgba(0,0,0,0.06);margin-top:24px;padding-top:18px}
    .form-card{background:transparent;padding:18px;border-radius:6px}
    .is-valid{border-color:#43b97e !important;box-shadow:none}
    .input-group .input-group-text{background:#e9f7f4;border-right:0}
    .avatar-col{display:flex;align-items:center;justify-content:center}
    .nav-tabs .nav-link.active{background:transparent;border:none;color:var(--brand-blue);font-weight:600}
    @media(min-width:1200px){.center-wrap{max-width:1180px;margin:0 auto}}
    .login-btn:hover{
        background-color:#3F4EB5;
    }
  </style>';



    

