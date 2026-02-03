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
 * AJAX endpoint for manual GPTZero AI detection scanning.
 *
 * @package   local_learner
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

// Security checks.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json');

// Get parameters.
$submissionid = required_param('submissionid', PARAM_INT);
$action = optional_param('action', 'scan', PARAM_ALPHA);

global $DB, $USER;

// Check if GPTZero plugin is installed and configured.
$apikey = get_config('plagiarism_gptzero', 'gptzero_apikey');
if (empty($apikey)) {
    echo json_encode([
        'success' => false,
        'error' => 'GPTZero is not configured. Please set up the API key in Site Administration.'
    ]);
    exit;
}

try {
    // Get the submission record.
    $submission = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);

    // Get assignment details.
    $assignment = $DB->get_record('assign', ['id' => $submission->assignment], '*', MUST_EXIST);

    // Get course module for the assignment.
    $module = $DB->get_record('modules', ['name' => 'assign'], '*', MUST_EXIST);
    $cm = $DB->get_record('course_modules', [
        'instance' => $assignment->id,
        'course' => $assignment->course,
        'module' => $module->id
    ], '*', MUST_EXIST);

    // Check capability - must be able to grade assignments.
    $context = context_module::instance($cm->id);
    require_capability('mod/assign:grade', $context);

    // Get learner details.
    $learner = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);

    // Check if we already have a scan result for this submission.
    $existingscan = $DB->get_record('plagiarism_gptzero_files', [
        'cm' => $cm->id,
        'userid' => $submission->userid
    ]);

    if ($existingscan && !empty($existingscan->predicted_class)) {
        // Return existing results with link to detailed report.
        $reporturl = new moodle_url('/local/learner/aireport.php', ['id' => $submissionid]);
        echo json_encode([
            'success' => true,
            'cached' => true,
            'predicted_class' => $existingscan->predicted_class,
            'class_probability' => round($existingscan->class_probability * 100),
            'scan_url' => $reporturl->out(false),
            'learner_name' => fullname($learner),
            'assignment_name' => $assignment->name
        ]);
        exit;
    }

    // Get submission content - try online text first, then files.
    $content = '';
    $hasfile = false;
    $file = null;

    // Check for online text submission.
    $onlinetext = $DB->get_record('assignsubmission_onlinetext', [
        'assignment' => $assignment->id,
        'submission' => $submissionid
    ]);

    if ($onlinetext && !empty($onlinetext->onlinetext)) {
        $content = strip_tags($onlinetext->onlinetext);
    }

    // Check for file submission.
    $fs = get_file_storage();
    $files = $fs->get_area_files(
        $context->id,
        'assignsubmission_file',
        'submission_files',
        $submissionid,
        'filename',
        false
    );

    if (!empty($files)) {
        $file = reset($files); // Get first file.
        $hasfile = true;
    }

    // Ensure we have something to scan.
    if (empty($content) && !$hasfile) {
        echo json_encode([
            'success' => false,
            'error' => 'No submission content found to scan.'
        ]);
        exit;
    }

    // Load GPTZero API class.
    require_once($CFG->dirroot . '/plagiarism/gptzero/classes/api.php');
    $api = new \plagiarism_gptzero\api();

    // Prepare API parameters.
    $params = [
        'assignmentName' => $assignment->name,
        'userId' => $learner->id,
        'userName' => $learner->username,
        'userEmail' => $learner->email,
    ];

    // Check if there's a GPTZero assignment ID configured.
    $gptzeroconfig = $DB->get_record('plagiarism_gptzero_config', ['cm' => $cm->id, 'name' => 'use_gptzero']);
    if ($gptzeroconfig) {
        $params['assignmentId'] = $gptzeroconfig->gptzero_assignment_id;
    }

    // Call GPTZero API.
    if ($hasfile && $file) {
        $response = $api->submit_file($file, $params);
    } else {
        $response = $api->submit_text($content, $params);
    }

    $response = json_decode($response, true);

    if (isset($response['error'])) {
        echo json_encode([
            'success' => false,
            'error' => 'GPTZero API error: ' . $response['error']
        ]);
        exit;
    }

    if (!isset($response['results'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid response from GPTZero API.'
        ]);
        exit;
    }

    // Store the result.
    $plagiarismfile = new stdClass();
    $plagiarismfile->cm = $cm->id;
    $plagiarismfile->userid = $learner->id;
    $plagiarismfile->useremail = $learner->email;
    $plagiarismfile->identifier = $hasfile ? $file->get_contenthash() : md5(trim($content));
    $plagiarismfile->filename = $hasfile ? $file->get_filename() : 'onlinetext_' . $submissionid;
    $plagiarismfile->attempt = $submission->attemptnumber;
    $plagiarismfile->timesubmitted = time();
    $plagiarismfile->predicted_class = $response['results']['predicted_class'];
    $plagiarismfile->class_probability = $response['results']['class_probability'];
    $plagiarismfile->confidence_category = $response['results']['confidence_category'] ?? '';
    $plagiarismfile->scanid = $response['results']['scanId'] ?? '';
    $plagiarismfile->scanurl = $response['results']['scanUrl'] ?? '';

    // Insert or update the record.
    if ($existingscan) {
        $plagiarismfile->id = $existingscan->id;
        $DB->update_record('plagiarism_gptzero_files', $plagiarismfile);
    } else {
        $DB->insert_record('plagiarism_gptzero_files', $plagiarismfile);
    }

    // Return success with results - include link to detailed report.
    $reporturl = new moodle_url('/local/learner/aireport.php', ['id' => $submissionid]);
    echo json_encode([
        'success' => true,
        'cached' => false,
        'predicted_class' => $response['results']['predicted_class'],
        'class_probability' => round($response['results']['class_probability'] * 100),
        'scan_url' => $reporturl->out(false),
        'learner_name' => fullname($learner),
        'assignment_name' => $assignment->name
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
