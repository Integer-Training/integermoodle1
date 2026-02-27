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
global $DB,$CFG;
use core\message\send_email;
echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.0.2/dist/css/bootstrap.min.css" rel="stylesheet">';

echo $OUTPUT->header();
//
//print_object($_POST);die;
$to = 'student.support@integertraining.com';
$subject = "Integer Training Contact Us";
//
$htmlmessage = '<p>Hi ,</p>
               <p>I would like to enquire on Integer Training Courses.
               <p>Please reach me by:-</p>
               <p>Name: '.$_POST['name'].'</p>
               <p>Email: '.$_POST['email'].'</p>
               <p>Message: '.$_POST['message'].'</p>
               <p>Phone: '.$_POST['phone'].'</p>';


$from = $DB->get_record('user',['id'=>2]);
$mail = get_mailer();

$mail->isHTML(true);
$mail->Subject = $subject;
$mail->Body    = $htmlmessage;
$mail->AltBody = 'contact email';

$mail->addAddress('student.support@integertraining.com');
$mail->setFrom($CFG->noreplyaddress, format_string($SITE->fullname));

if (!$mail->send()) {
    debugging('Email sending failed: ' . $mail->ErrorInfo, DEBUG_DEVELOPER);
}else{
    redirect(new moodle_url('/local/learner/contact.php'),'Thank You!. We will Reach to You..');die;
}

echo $OUTPUT->footer();
