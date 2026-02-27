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
$userid = optional_param('id',0,PARAM_INT);

$context = context_system::instance();
echo $OUTPUT->header();
//email logic
// Send confirmation email message to the sender.
$from = $DB->get_record('user',['id'=>5]);
$to = $DB->get_record('user',['id'=>$userid]);
//
$sql = "SELECT c.id,c.fullname
                    FROM {user_enrolments} ue
                    JOIN {enrol} en ON ue.enrolid = en.id
                    JOIN {course} c ON c.id = en.courseid
                    JOIN {user} uu ON uu.id = ue.userid
                    WHERE uu.id=".$userid."  AND en.enrol='manual' AND c.visible=1
                    ORDER BY c.id DESC 
                    LIMIT 1";
//
$enrol_courses = $DB->get_records_sql($sql);
$store = array();
foreach($enrol_courses as $val){
    $recs = array();
    $recs  = $val->fullname;
    $store[] = $recs;

}
//print_r($import_arr[0]);die;
//
$subject = "Your Course Access Details – ".$store[0]."";
//
$htmlmessage = '<p>Hi <strong>'.$to->firstname.'</strong>,</p>
            <p>We’re pleased to confirm that you have <strong>successfully registered</strong> for your course — <span class="fw-semibold">'.$store[0].'</span>.</p>

            <div class="mb-3">
              <h4 class="mb-2">Here are your login details</h4>
              <ul class="list-unstyled ms-3">
                <li>Learning website: <a href="https://epearlacademy.com" target="_blank" rel="noopener">https://epearlacademy.com</a></li>
                <li>Username: <span class="credential">'.$to->username.'</span></li>
                <li>Password: <span class="credential">'.ucwords('Integer@123').'</span></li>
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
                <a href="https://epearlacademy.com/login/index.php" target="_blank" class="btn btn-primary">Log in to your account</a>
              </div>
            </div>';
//print_object($to);
$status = email_to_user($to,$from,$subject, html_to_text($htmlmessage), $htmlmessage, '', '', true);
if($status){
  //insert
  $newobj = new stdClass();
  $newobj->userid = $userid;
  $newobj->email_status = 'Email Sent';
  $newobj->sentby = $USER->id;
  $newobj->timecreated = time();
  $DB->insert_record('local_leaner_email',$newobj);
   redirect(new moodle_url('/local/learner/view.php'),'Email has been Sent...');
   die;
}
//
echo $OUTPUT->footer();
//


    

