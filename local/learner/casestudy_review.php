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
 * AJAX endpoint for case study approve/reject/resubmit actions.
 *
 * @package    local_learner
 * @copyright  2026 Integer Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');

require_login();
require_sesskey();

global $DB, $USER;

$action   = required_param('action', PARAM_ALPHA);
$assignid = required_param('assignid', PARAM_INT);
$userid   = required_param('userid', PARAM_INT);
$feedback = optional_param('feedback', '', PARAM_TEXT);

$now = time();

header('Content-Type: application/json');

try {
    if ($action === 'approve' || $action === 'reject') {
        // Tutor/manager action — require grading capability.
        $assign = $DB->get_record('assign', ['id' => $assignid], '*', MUST_EXIST);
        $context = context_course::instance($assign->course);
        require_capability('mod/assign:grade', $context);

        $existing = $DB->get_record('local_casestudy_reviews', [
            'assignid' => $assignid,
            'userid'   => $userid,
        ]);

        $record = new stdClass();
        $record->assignid    = $assignid;
        $record->userid      = $userid;
        $record->status      = $action === 'approve' ? 'approved' : 'rejected';
        $record->feedback    = $action === 'reject' ? $feedback : '';
        $record->reviewedby  = $USER->id;
        $record->reviewedat  = $now;
        $record->timemodified = $now;

        if ($existing) {
            $record->id = $existing->id;
            $DB->update_record('local_casestudy_reviews', $record);
        } else {
            $record->timecreated = $now;
            $DB->insert_record('local_casestudy_reviews', $record);
        }

        // Send notification to learner.
        \local_learner\observer::send_casestudy_notification(
            $userid,
            $assign->name,
            $action === 'approve' ? 'approved' : 'rejected',
            $feedback
        );

        // On rejection, revert submission to draft so learner can edit.
        if ($action === 'reject') {
            // Get the course module for this assignment.
            $cm = get_coursemodule_from_instance('assign', $assignid, $assign->course, false, MUST_EXIST);
            $context_mod = context_module::instance($cm->id);

            require_once($CFG->dirroot . '/mod/assign/locallib.php');
            $assignobj = new assign($context_mod, $cm, null);
            $assignobj->revert_to_draft($userid);
        }

        echo json_encode(['success' => true, 'status' => $record->status]);

    } else if ($action === 'resubmit') {
        // Learner action — must be the learner themselves.
        if ((int) $USER->id !== $userid) {
            throw new moodle_exception('nopermission', 'error', '', null, 'You can only resubmit your own case study.');
        }

        $existing = $DB->get_record('local_casestudy_reviews', [
            'assignid' => $assignid,
            'userid'   => $userid,
        ]);

        if (!$existing || $existing->status !== 'rejected') {
            throw new moodle_exception('invalidrequest', 'error', '', null, 'Case study is not in rejected state.');
        }

        $existing->status       = 'resubmitted';
        $existing->timemodified = $now;
        $DB->update_record('local_casestudy_reviews', $existing);

        echo json_encode(['success' => true, 'status' => 'resubmitted']);

    } else {
        throw new moodle_exception('invalidaction', 'error', '', null, 'Unknown action: ' . $action);
    }
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
