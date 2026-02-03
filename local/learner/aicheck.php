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

/**
 * Filter out assignment questions from submission text to save API quota.
 *
 * Removes lines that appear to be questions or task prompts rather than
 * student-written answers. This helps conserve GPTZero word quota.
 *
 * @param string $text The full submission text
 * @return string Filtered text with questions removed
 */
function filter_questions_from_text($text) {
    if (empty($text)) {
        return $text;
    }

    // Split into lines for processing.
    $lines = preg_split('/\r\n|\r|\n/', $text);
    $filtered = [];

    foreach ($lines as $i => $line) {
        $trimmed = trim($line);

        // Skip empty lines but keep them for structure.
        if (empty($trimmed)) {
            $filtered[] = '';
            continue;
        }

        // Pattern 1: Lines with AC reference codes - these are assignment questions
        // e.g., "(AC 1.1)", "(AC 2.2, 4.2)", "AC 3.3"
        if (preg_match('/\(?\s*AC\s*\d+\.\d+/i', $trimmed) && strlen($trimmed) < 200) {
            continue; // Skip - this is an assessment criteria question.
        }

        // Pattern 2: Discussion Questions / Reflective Prompt headers
        if (preg_match('/^(?:Discussion\s+Questions?|Reflective\s+Prompt|Short\s+Answer\s+Questions?|Case\s+Study\s+\d+)\s*[\:\-]?/i', $trimmed)) {
            continue; // Skip section headers.
        }

        // Pattern 3: Lines that are clearly just a question starting with - or bullet
        // e.g., "- Which pieces of legislation..." or "- How can Fatima promote..."
        if (preg_match('/^[\-\•\*]\s*(?:Which|What|How|Why|When|Where|Who)\s+/i', $trimmed) && preg_match('/\?\s*$/', $trimmed)) {
            continue; // Skip bulleted questions.
        }

        // Pattern 4: Unit/Module title headers
        if (preg_match('/^(?:Unit\s*(?:Title)?|Module|AC\s*M\d+|Case\s+Study)\s*[\:\-]/i', $trimmed)) {
            continue; // Skip unit headers.
        }

        // Pattern 5: Mark allocation lines - "(10 marks)", "[5 points]"
        if (preg_match('/^\s*(?:\(|\[)?\s*\d+\s*(?:marks?|points?)\s*(?:\)|\])?\s*$/i', $trimmed)) {
            continue; // Skip standalone mark allocation lines.
        }

        // Pattern 6: Very short lines that are just headers (under 50 chars, no lowercase)
        $lower = preg_replace('/[^a-z]/', '', $trimmed);
        if (strlen($trimmed) < 50 && strlen($lower) < 5 && !preg_match('/\d/', $trimmed)) {
            continue; // Skip short all-caps headers.
        }

        // Keep this line - it's likely student content.
        $filtered[] = $line;
    }

    // Rejoin and clean up excessive blank lines.
    $result = implode("\n", $filtered);
    $result = preg_replace('/\n{4,}/', "\n\n\n", $result); // Max 3 newlines.
    $result = trim($result);

    return $result;
}

/**
 * Extract plain text from a DOCX file.
 *
 * DOCX files are ZIP archives containing XML. This function extracts
 * the text content from word/document.xml.
 *
 * @param string $filepath Path to the DOCX file
 * @return string Extracted text content
 */
function extract_text_from_docx($filepath) {
    if (!file_exists($filepath)) {
        return '';
    }

    $text = '';
    $zip = new ZipArchive();

    if ($zip->open($filepath) === true) {
        // Read the main document content.
        $content = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($content) {
            // Remove XML tags but preserve paragraph breaks.
            $content = str_replace('</w:p>', "\n", $content);
            $content = str_replace('</w:tr>', "\n", $content); // Table rows.
            $text = strip_tags($content);
            // Clean up whitespace.
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/\n{3,}/', "\n\n", $text);
            $text = trim($text);
        }
    }

    return $text;
}

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
        $rawcontent = strip_tags($onlinetext->onlinetext);
        $content = filter_questions_from_text($rawcontent);
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

        // Try to extract text from file so we can filter out questions.
        $mimetype = $file->get_mimetype();
        $filename = strtolower($file->get_filename());
        $filecontent = '';

        // Extract text based on file type.
        if ($mimetype === 'text/plain' || substr($filename, -4) === '.txt') {
            // Plain text file - read directly.
            $filecontent = $file->get_content();
        } else if (substr($filename, -5) === '.docx' || $mimetype === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
            // Word document - extract text from XML.
            $temppath = make_request_directory() . '/' . $file->get_filename();
            $file->copy_content_to($temppath);
            $filecontent = extract_text_from_docx($temppath);
            @unlink($temppath);
        }

        // If we extracted text, filter it and use text submission instead of file.
        if (!empty($filecontent)) {
            $filteredcontent = filter_questions_from_text(strip_tags($filecontent));
            if (!empty($filteredcontent) && strlen($filteredcontent) > 50) {
                // Use filtered text instead of file.
                $content = $filteredcontent;
                $hasfile = false; // Don't send file, send filtered text.
            }
        }
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

    // Track word usage for quota monitoring.
    $wordcount = 0;
    if ($hasfile && $file) {
        // For files, estimate word count from content (if text-based).
        $filecontent = $file->get_content();
        $wordcount = str_word_count(strip_tags($filecontent));
    } else if (!empty($content)) {
        $wordcount = str_word_count($content);
    }

    if ($wordcount > 0) {
        // Get current total and add this scan's words.
        $currenttotal = (int)get_config('plagiarism_gptzero', 'words_used');
        $currentscans = (int)get_config('plagiarism_gptzero', 'scans_count');
        set_config('words_used', $currenttotal + $wordcount, 'plagiarism_gptzero');
        set_config('scans_count', $currentscans + 1, 'plagiarism_gptzero');
        set_config('last_scan_time', time(), 'plagiarism_gptzero');
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
