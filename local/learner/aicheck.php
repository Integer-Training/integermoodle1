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
 * NOTE: This function is currently DISABLED. The whole document is scanned
 * without filtering. A future enhancement will implement workbook-aware
 * filtering by matching against actual course content.
 *
 * @param string $text The full submission text
 * @return string The original text unchanged (filtering disabled)
 */
function filter_questions_from_text($text) {
    // DISABLED: Return full document without filtering.
    // Future: Implement workbook-aware filtering by fetching course content
    // and removing known question text from submissions before scanning.
    return $text;
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

    // If the scan is older than the submission, it's stale (e.g., learner resubmitted).
    $scanIsStale = ($existingscan && !empty($existingscan->timesubmitted)
        && $existingscan->timesubmitted < $submission->timemodified);

    if ($existingscan && !empty($existingscan->predicted_class) && !$scanIsStale) {
        // Return existing results with link to detailed report.
        $reporturl = new moodle_url('/local/learner/aireport.php', ['id' => $submissionid]);

        // Try to get actual class_probabilities from stored JSON scan data.
        $cls = strtolower($existingscan->predicted_class);
        $prob = round($existingscan->class_probability * 100);
        $aiPct = 0;
        $mixedPct = 0;
        $humanPct = 0;
        $gotActualProbs = false;

        if (!empty($existingscan->scanurl) && strpos($existingscan->scanurl, '{') === 0) {
            $scandata = json_decode($existingscan->scanurl, true);
            if (isset($scandata['documents'][0]['class_probabilities'])) {
                $classProbs = $scandata['documents'][0]['class_probabilities'];
                $aiPct = round(($classProbs['ai'] ?? 0) * 100);
                $mixedPct = round(($classProbs['mixed'] ?? 0) * 100);
                $humanPct = round(($classProbs['human'] ?? 0) * 100);
                $gotActualProbs = true;
            }
        }

        // Fallback to estimation if no actual data stored.
        if (!$gotActualProbs) {
            if ($cls === 'ai') {
                $aiPct = $prob;
                $humanPct = 100 - $prob;
            } else if ($cls === 'human') {
                $humanPct = $prob;
                $aiPct = 100 - $prob;
            } else {
                $mixedPct = $prob;
                $aiPct = round((100 - $prob) / 2);
                $humanPct = 100 - $prob - $aiPct;
            }
        }

        echo json_encode([
            'success' => true,
            'cached' => true,
            'predicted_class' => $existingscan->predicted_class,
            'class_probability' => $prob,
            'ai_pct' => $aiPct,
            'mixed_pct' => $mixedPct,
            'human_pct' => $humanPct,
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

    // Call GPTZero API directly to get full response with sentence-level data.
    // (Bypasses plagiarism plugin's transform_response which strips sentences and class_probabilities.)
    if ($hasfile && $file) {
        $filecontent = $file->get_content();
        $filetype = $file->get_mimetype();
        $boundary = '----CustomBoundary' . uniqid();
        $payload = '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="files"; filename="' . basename($file->get_filename()) . "\"\r\n";
        $payload .= 'Content-Type: ' . $filetype . "\r\n\r\n";
        $payload .= $filecontent . "\r\n";
        $payload .= '--' . $boundary . "--\r\n";

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.gptzero.me/v2/predict/files',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: multipart/form-data; boundary=' . $boundary,
                'x-api-key: ' . $apikey,
            ],
        ]);
        $rawresponse = curl_exec($ch);
        $curlerror = curl_error($ch);
        curl_close($ch);
    } else {
        $postdata = json_encode(['document' => $content]);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.gptzero.me/v2/predict/text',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $postdata,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'x-api-key: ' . $apikey,
            ],
        ]);
        $rawresponse = curl_exec($ch);
        $curlerror = curl_error($ch);
        curl_close($ch);
    }

    if (!empty($curlerror)) {
        echo json_encode([
            'success' => false,
            'error' => 'GPTZero API connection error: ' . $curlerror
        ]);
        exit;
    }

    $scandata = json_decode($rawresponse, true);

    if (isset($scandata['error'])) {
        echo json_encode([
            'success' => false,
            'error' => 'GPTZero API error: ' . ($scandata['error'] ?? 'Unknown error')
        ]);
        exit;
    }

    if (!isset($scandata['documents']) || empty($scandata['documents'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Invalid response from GPTZero API.'
        ]);
        exit;
    }

    // Extract document-level data from raw GPTZero response.
    $doc = $scandata['documents'][0];
    $classProbs = $doc['class_probabilities'] ?? [];
    $predictedClass = $doc['predicted_class'] ?? 'unknown';
    $classProbability = $classProbs[strtolower($predictedClass)] ?? ($doc['completely_generated_prob'] ?? 0);

    $confidenceCategory = 'low';
    if ($classProbability >= 0.8) {
        $confidenceCategory = 'high';
    } else if ($classProbability >= 0.5) {
        $confidenceCategory = 'medium';
    }

    // Store the result with FULL GPTZero response (including sentences for aireport.php).
    $plagiarismfile = new stdClass();
    $plagiarismfile->cm = $cm->id;
    $plagiarismfile->userid = $learner->id;
    $plagiarismfile->useremail = $learner->email;
    $plagiarismfile->identifier = $hasfile ? $file->get_contenthash() : md5(trim($content));
    $plagiarismfile->filename = $hasfile ? $file->get_filename() : 'onlinetext_' . $submissionid;
    $plagiarismfile->attempt = $submission->attemptnumber;
    $plagiarismfile->timesubmitted = time();
    $plagiarismfile->predicted_class = $predictedClass;
    $plagiarismfile->class_probability = $classProbability;
    $plagiarismfile->confidence_category = $confidenceCategory;
    $plagiarismfile->scanid = uniqid('gptzero_');
    // Store the complete GPTZero response so aireport.php doesn't need to rescan.
    $plagiarismfile->scanurl = json_encode($scandata);

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

    // Extract class probabilities directly from GPTZero response.
    $aiPct = round(($classProbs['ai'] ?? 0) * 100);
    $mixedPct = round(($classProbs['mixed'] ?? 0) * 100);
    $humanPct = round(($classProbs['human'] ?? 0) * 100);

    // Return success with results.
    $reporturl = new moodle_url('/local/learner/aireport.php', ['id' => $submissionid]);
    echo json_encode([
        'success' => true,
        'cached' => false,
        'predicted_class' => $predictedClass,
        'class_probability' => round($classProbability * 100),
        'ai_pct' => $aiPct,
        'mixed_pct' => $mixedPct,
        'human_pct' => $humanPct,
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
