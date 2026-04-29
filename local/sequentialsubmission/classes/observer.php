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

namespace local_sequentialsubmission;

defined('MOODLE_INTERNAL') || die();

/**
 * Safety-net observer for submissions that slip past the lib.php hooks
 * (mobile app, external web service clients, etc.).
 *
 * Listens to \mod_assign\event\assessable_submitted. If the submission would
 * have been blocked by our lock, we revert it to draft and notify the learner.
 * The submission data is preserved — only the "submitted" status flips back to
 * "draft" so the learner can edit/resave when eligible.
 *
 * @package local_sequentialsubmission
 */
class observer {

    /**
     * Called when a learner submits an assignment for assessment.
     *
     * @param \core\event\base $event
     */
    public static function on_assessable_submitted(\core\event\base $event) {
        global $CFG, $DB;

        try {
            // Kill switch.
            if (!lock_checker::is_enabled()) {
                return;
            }

            $userid = (int) $event->relateduserid;
            if ($userid <= 0) {
                $userid = (int) $event->userid;
            }
            if ($userid <= 0) {
                return;
            }

            // Admin / bypass-capable → ignore.
            if (lock_checker::user_should_bypass($userid)) {
                return;
            }

            // Get the course module for this assign event.
            $cmid = 0;
            if (!empty($event->contextinstanceid)) {
                $cmid = (int) $event->contextinstanceid;
            }
            if ($cmid <= 0) {
                return;
            }

            // Re-run the full lock check as-if this submission hadn't happened.
            // We exclude the current assignment from the blocker set (it's the one being
            // submitted now) — but ALL OTHER pending submissions still apply.
            $check = lock_checker::check($userid, $cmid);
            if (!$check->locked) {
                return; // Legitimate submission, leave it alone.
            }

            // Violation detected: revert to draft.
            self::revert_submission_to_draft($cmid, $userid, $check);

        } catch (\Exception $e) {
            // Never let an observer explosion break mod_assign.
            debugging('local_sequentialsubmission observer error: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }
    }

    /**
     * Revert the user's submission on this cm back to draft status.
     * Preserves submission files / online text — only flips status.
     *
     * @param int $cmid
     * @param int $userid
     * @param object $check Result of lock_checker::check()
     */
    protected static function revert_submission_to_draft($cmid, $userid, $check) {
        global $CFG, $DB;

        require_once($CFG->dirroot . '/mod/assign/locallib.php');

        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
        $context = \context_module::instance($cm->id);

        // Instantiate the assign object so we can call its public API.
        $assign = new \assign($context, $cm, $course);

        // Get the latest submission and flip its status back to draft.
        // This is the least invasive revert — data is preserved.
        $submission = $DB->get_record('assign_submission', [
            'assignment' => $cm->instance,
            'userid' => $userid,
            'latest' => 1,
        ]);
        if (!$submission || $submission->status !== ASSIGN_SUBMISSION_STATUS_SUBMITTED) {
            return; // Already not-submitted, nothing to revert.
        }

        $submission->status = ASSIGN_SUBMISSION_STATUS_DRAFT;
        $submission->timemodified = time();
        $DB->update_record('assign_submission', $submission);

        // Log it.
        lock_checker::log_event($userid, 'mobile_revert', [
            'blocked_cmid' => $cmid,
            'blocking_cmid' => $check->blocking_cmid,
            'courseid' => (int) $cm->course,
            'reason' => $check->reason,
            'notes' => 'Submission reverted to draft via safety-net observer. ' .
                'Blocker: ' . ($check->blocking_name ?? ''),
        ]);

        // Notify the learner.
        self::notify_learner_of_revert($userid, $cm, $check);
    }

    /**
     * Send a Moodle message explaining why the submission was reverted.
     *
     * @param int $userid
     * @param \stdClass $cm
     * @param object $check
     */
    protected static function notify_learner_of_revert($userid, $cm, $check) {
        global $DB;

        $user = $DB->get_record('user', ['id' => $userid], '*', IGNORE_MISSING);
        if (!$user) {
            return;
        }
        $assignname = $DB->get_field('assign', 'name', ['id' => $cm->instance]);

        $a = (object) [
            'firstname' => $user->firstname,
            'assignmentname' => $assignname,
            'blocking_name' => $check->blocking_name ?? '',
        ];

        $msg = new \core\message\message();
        $msg->component = 'local_sequentialsubmission';
        $msg->name = 'mobilereverted';
        $msg->userfrom = \core_user::get_noreply_user();
        $msg->userto = $user;
        $msg->subject = get_string('msg_mobilerevert_subject', 'local_sequentialsubmission');
        $msg->fullmessage = get_string('msg_mobilerevert_body', 'local_sequentialsubmission', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml = nl2br(htmlspecialchars(
            get_string('msg_mobilerevert_body', 'local_sequentialsubmission', $a)
        ));
        $msg->smallmessage = get_string('msg_mobilerevert_subject', 'local_sequentialsubmission');
        $msg->notification = 1;
        $msg->contexturl = (new \moodle_url('/mod/assign/view.php', ['id' => $cm->id]))->out(false);
        $msg->contexturlname = $assignname;

        message_send($msg);
    }
}
