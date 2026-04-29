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
require_login();
global $DB,$CFG,$USER;
require_once($CFG->dirroot.'/user/lib.php');
//
$action = optional_param('action','',PARAM_RAW);

$context = context_system::instance();

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
$mform = new filters_sales_form(null,[]);
if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/learner/users.php'));
} else if($usernew = $mform->get_data()){
    
    $fromform = data_submitted();
    //print_object($fromform);die;
    if($fromform){
       $newuserobj = new stdClass();
        //$newuser = new stdClass();
        $newuserobj->auth          = 'manual';
        $newuserobj->username      = clean_param($fromform->username, PARAM_USERNAME);
        $newuserobj->firstname     = clean_param($fromform->firstname, PARAM_NOTAGS);
        $newuserobj->lastname      = clean_param($fromform->lastname, PARAM_NOTAGS);
        $newuserobj->email         = clean_param($fromform->email, PARAM_EMAIL);
        $newuserobj->phone1        = clean_param($fromform->phone1, PARAM_TEXT);

        $newuserobj->confirmed     = 1;
        $newuserobj->suspended     = 0;
        $newuserobj->deleted       = 0;
        $newuserobj->policyagreed  = 1;

        $newuserobj->mnethostid    = $CFG->mnet_localhost_id;
        $newuserobj->timecreated   = time();
        $newuserobj->timemodified  = time();
        $newuserobj->lang          = current_language();
       //print_object($newuserobj);die;
       //$newuserobj->firstname = ucwords($fromform->firstname);
       //$newuserobj->id = user_create_user($newuserobj, false, false);
      
      // This converts $6$ → $2y$
       //print_object($updateuser);die;
       $newuserobj->id = user_create_user($newuserobj, false, false);

      // Set password safely
      update_internal_user_password($newuserobj, 'Integer@123');

      // Force password change on first login
      set_user_preference('auth_forcepasswordchange', 1, $newuserobj->id);

      // Trigger user_created event for plugins (e.g. Twilio SMS).
      \core\event\user_created::create_from_userid($newuserobj->id)->trigger();

      //$updateuser = core_user::get_user_by_username($fromform->username);
       //print_object(implode(',',$fromform->courses));die;
       if($fromform->courses){
          //now enrol to  choossen courses
        $store = array();
          foreach($fromform->courses as $key=>$val){
            $instance = $DB->get_record('enrol', ['courseid' => $val, 'enrol' => 'manual']);
            //print_r($instance);die;
            $enrolplugin = enrol_get_plugin($instance->enrol);
            $enrolplugin->enrol_user($instance, $newuserobj->id, 5, time(), strtotime("+365 days",time()));
            $store[] = $DB->get_field('course','fullname',['id'=>$val]);
          }
          $to = $DB->get_record('user',['id'=>$newuserobj->id]);
          $subject = "Your Course Access Details – ".$store[0]."";
        //
        $htmlmessage = '<p>Hi <strong>'.$to->firstname.'</strong>,</p>
            <p>We’re pleased to confirm that you have <strong>successfully registered</strong> for your course — <span class="fw-semibold">'.$store[0].'</span>.</p>

            <div class="mb-3">
              <h4 class="mb-2">Here are your login details</h4>
              <ul class="list-unstyled ms-3">
                <li>Learning website: <a href="'.$CFG->wwwroot.'" target="_blank" rel="noopener">'.$CFG->wwwroot.'</a></li>
                <li>Username: <span class="credential">'.$to->username.'</span></li>
                <li>Password: <span class="credential">Integer@123</span></li>
              </ul>
            </div>

            <div class="mb-3">
              <h4 class="mb-2">How to access your course</h4>
              <ol>
                <li>Go to the learning website and click <strong>Log In</strong> at the top right corner.</li>
                <li>Enter your username and password above.</li>
                <li>Once logged in, click on <strong>My Course</strong> from the left-hand menu.</li>
                <li>You will see your course listed there.</li>
                <li>Click on <strong>Unit 1</strong> to find your reading material and learner workbook (assignment).</li>
              </ol>
            </div>

            <div class="alert alert-light border-start border-4 border-primary d-flex align-items-start" role="alert">
              <div class="me-3">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#0d6efd" viewBox="0 0 16 16"><path d="M8 1a7 7 0 1 0 0 14A7 7 0 0 0 8 1zM7.002 4a1 1 0 1 1 2 0 1 1 0 0 1-2 0zM6.1 7.995c.03-.48.405-1.02.9-1.274.545-.285 1.1-.492 1.1-1.721 0-.6-.49-1.1-1.09-1.1-.6 0-1.09.5-1.09 1.1H6c0-1.1.89-2 2-2s2 .9 2 2c0 1.2-.83 1.66-1.41 1.92-.52.24-.59.45-.59.98v.5H6.1v-.9z"/></svg>
              </div>
              <div>
                <strong>Tip: Please complete and submit the IAG Document and Enrol, and Smart first to get the unit unrestricted.</strong><br>
                       If you face any difficulty accessing your portal or course materials, please reach out to me immediately. I’ll make sure you get the help you need right away.
              </div>
            </div>


            <div class="mt-4 d-flex align-items-center">
              <div>
                <strong>Kind regards,</strong>
                <div class="small-muted">Course Support Team</div>
              </div>
              <div class="ms-auto">
                <a href="'.$CFG->wwwroot.'/login/index.php" target="_blank" class="btn btn-primary">Log in to your account</a>
              </div>
            </div>';
        
        $from = $DB->get_record('user',['id'=>2]);
        $status = email_to_user($to,$from,$subject, html_to_text($htmlmessage), $htmlmessage, '', '', true);
        if($status){
          //insert
          $newobj = new stdClass();
          $newobj->userid = $to->id;
          $newobj->email_status = 'Email Sent';
          $newobj->courses = implode(',',$fromform->courses);
          $newobj->sentby = $USER->id;
          $newobj->timecreated = time();
          $DB->insert_record('local_leaner_email',$newobj);
           //redirect(new moodle_url('/local/learner/view.php'),'Email has been Sent...');
        }
          redirect(''.$CFG->wwwroot.'/my/index.php','Registration Success..');die;
       }
    }
}
  
echo $OUTPUT->header();
echo '<h3>User Registration</h3>';

  echo $mform->display(); 

echo $OUTPUT->footer();    
