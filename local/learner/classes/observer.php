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
 * Event observer for local_learner — sends notifications when assignments are graded.
 *
 * @package   local_learner
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_learner;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Handle the submission_graded event.
     *
     * Sends a Moodle notification (popup + email) to the learner when their
     * workbook assignment is graded as Pass or Refer.
     *
     * Case studies are excluded here — they use the casestudy_review.php flow
     * which calls send_casestudy_notification() directly.
     *
     * @param \mod_assign\event\submission_graded $event
     */
    public static function submission_graded(\mod_assign\event\submission_graded $event) {
        global $DB;

        $data = $event->get_data();
        $learnerid = (int) $data['relateduserid'];
        $assignid  = (int) $data['other']['assignid'];
        $graderid  = (int) $data['userid'];

        // Get assignment details.
        $assign = $DB->get_record('assign', ['id' => $assignid], 'id, name, course');
        if (!$assign) {
            return;
        }

        // Skip case studies — they have their own notification flow.
        if (stripos($assign->name, 'Case Stud') !== false) {
            return;
        }

        // Skip IAG and ID Proof assignments.
        if (stripos($assign->name, 'IAG') !== false || stripos($assign->name, 'ID Proof') !== false) {
            return;
        }

        // Get the grade to determine Pass or Refer.
        $grade = $DB->get_record_sql(
            "SELECT ag.grade, gi.scaleid, sc.scale
             FROM {assign_grades} ag
             LEFT JOIN {grade_items} gi ON gi.iteminstance = ag.assignment AND gi.itemmodule = 'assign'
                 AND gi.courseid = :courseid
             LEFT JOIN {scale} sc ON sc.id = gi.scaleid
             WHERE ag.assignment = :assignid AND ag.userid = :userid
             ORDER BY ag.attemptnumber DESC
             LIMIT 1",
            ['assignid' => $assignid, 'userid' => $learnerid, 'courseid' => $assign->course]
        );

        if (!$grade || $grade->grade === null || $grade->grade < 0) {
            return; // No valid grade yet (sentinel -1 or null).
        }

        // Determine result from scale.
        $result = 'Refer';
        if (!empty($grade->scale)) {
            $scale_items = explode(',', $grade->scale);
            $idx = (int) $grade->grade - 1;
            if (isset($scale_items[$idx]) && trim($scale_items[$idx]) === 'Pass') {
                $result = 'Pass';
            }
        }

        // Get course name.
        $course = $DB->get_record('course', ['id' => $assign->course], 'fullname');
        $coursename = $course ? $course->fullname : '';

        // Get grader name.
        $grader = $DB->get_record('user', ['id' => $graderid], 'firstname, lastname');
        $gradername = $grader ? $grader->firstname . ' ' . $grader->lastname : 'Your tutor';

        // Get learner record.
        $learner = $DB->get_record('user', ['id' => $learnerid]);
        if (!$learner) {
            return;
        }

        // Build message.
        if ($result === 'Pass') {
            $subject = 'Assignment Passed: ' . $assign->name;
            $body = "Hi {$learner->firstname},\n\n"
                  . "Great news! Your assignment \"{$assign->name}\" in {$coursename} has been marked as Pass by {$gradername}.\n\n"
                  . "Keep up the good work!\n\n"
                  . "Epearl Academy";
        } else {
            $subject = 'Assignment Needs Revision: ' . $assign->name;
            $body = "Hi {$learner->firstname},\n\n"
                  . "Your assignment \"{$assign->name}\" in {$coursename} has been marked as Refer by {$gradername}.\n\n"
                  . "Please review the feedback and resubmit your work.\n\n"
                  . "Epearl Academy";
        }

        self::send_notification($learner, $subject, $body, 'workbook_graded');
    }

    /**
     * Send a case study notification (approved/rejected).
     *
     * Called directly from casestudy_review.php.
     *
     * @param int    $learnerid
     * @param string $assignname
     * @param string $action     'approved' or 'rejected'
     * @param string $feedback   Optional rejection feedback
     */
    public static function send_casestudy_notification($learnerid, $assignname, $action, $feedback = '') {
        global $DB, $USER;

        $learner = $DB->get_record('user', ['id' => $learnerid]);
        if (!$learner) {
            return;
        }

        $reviewername = fullname($USER);

        if ($action === 'approved') {
            $subject = 'Case Study Approved: ' . $assignname;
            $body = "Hi {$learner->firstname},\n\n"
                  . "Your case study \"{$assignname}\" has been approved by {$reviewername}.\n\n"
                  . "Well done!\n\n"
                  . "Epearl Academy";
            $provider = 'casestudy_approved';
        } else {
            $subject = 'Case Study Needs Resubmission: ' . $assignname;
            $body = "Hi {$learner->firstname},\n\n"
                  . "Your case study \"{$assignname}\" has been reviewed by {$reviewername} and requires resubmission.\n\n";
            if (!empty($feedback)) {
                $body .= "Feedback: {$feedback}\n\n";
            }
            $body .= "Please review the feedback and resubmit.\n\n"
                    . "Epearl Academy";
            $provider = 'casestudy_rejected';
        }

        self::send_notification($learner, $subject, $body, $provider);
    }

    /**
     * Send a Moodle notification (popup + email).
     *
     * @param object $learner    User record
     * @param string $subject    Message subject
     * @param string $body       Message body (plain text)
     * @param string $provider   Message provider name
     */
    private static function send_notification($learner, $subject, $body, $provider) {
        $message = new \core\message\message();
        $message->component         = 'local_learner';
        $message->name              = $provider;
        $message->userfrom          = \core_user::get_noreply_user();
        $message->userto            = $learner;
        $message->subject           = $subject;
        $message->fullmessage       = $body;
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml   = nl2br(s($body));
        $message->smallmessage      = $subject;
        $message->notification      = 1;

        message_send($message);
    }
}
