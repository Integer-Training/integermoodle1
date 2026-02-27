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
 * AJAX endpoint for standalone GPTZero AI detection scanning.
 *
 * Receives an uploaded document, sends it to the GPTZero API,
 * and stores the result in local_learner_aichecks.
 *
 * @package   local_learner
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');

// Security checks.
require_login();
require_sesskey();

// Set JSON header.
header('Content-Type: application/json');

global $DB, $USER;

// Permission check: admin or teacher.
$is_admin = has_capability('local/learner:view', context_system::instance());
$is_teacher = $DB->record_exists_sql(
    "SELECT 1 FROM {role_assignments} ra
     JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('teacher','editingteacher')
     WHERE ra.userid = ?",
    [$USER->id]
);

if (!$is_admin && !$is_teacher) {
    echo json_encode(['success' => false, 'error' => 'Permission denied.']);
    exit;
}

// Check GPTZero API key.
$apikey = get_config('plagiarism_gptzero', 'gptzero_apikey');
if (empty($apikey)) {
    echo json_encode([
        'success' => false,
        'error' => 'GPTZero is not configured. Please set up the API key in Site Administration.'
    ]);
    exit;
}

// Check for uploaded file.
if (empty($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
    $errmsg = 'No file uploaded.';
    if (!empty($_FILES['document']['error'])) {
        switch ($_FILES['document']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $errmsg = 'File is too large. Maximum size is 10MB.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $errmsg = 'No file was selected.';
                break;
            default:
                $errmsg = 'File upload error (code: ' . $_FILES['document']['error'] . ').';
        }
    }
    echo json_encode(['success' => false, 'error' => $errmsg]);
    exit;
}

try {
    $uploadedfile = $_FILES['document'];
    $filename = clean_param(basename($uploadedfile['name']), PARAM_FILE);
    $tmppath = $uploadedfile['tmp_name'];
    $filesize = $uploadedfile['size'];
    $mimetype = $uploadedfile['type'];

    // Validate file size (10MB max).
    if ($filesize > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File is too large. Maximum size is 10MB.']);
        exit;
    }

    // Validate file type.
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $allowed = ['docx', 'doc', 'pdf', 'txt', 'rtf'];
    if (!in_array($ext, $allowed)) {
        echo json_encode([
            'success' => false,
            'error' => 'Unsupported file type. Allowed: ' . implode(', ', $allowed)
        ]);
        exit;
    }

    // Read file content.
    $filecontent = file_get_contents($tmppath);
    if ($filecontent === false || strlen($filecontent) === 0) {
        echo json_encode(['success' => false, 'error' => 'Could not read uploaded file.']);
        exit;
    }

    $filehash = md5($filecontent);

    // Try to extract text for DOCX files (for word counting).
    $textcontent = '';
    if ($ext === 'docx' || $mimetype === 'application/vnd.openxmlformats-officedocument.wordprocessingml.document') {
        $textcontent = extract_text_from_docx_standalone($tmppath);
    } else if ($ext === 'txt' || $mimetype === 'text/plain') {
        $textcontent = $filecontent;
    }

    // Call GPTZero API.
    $boundary = '----CustomBoundary' . uniqid();
    $payload = '--' . $boundary . "\r\n";
    $payload .= 'Content-Disposition: form-data; name="files"; filename="' . $filename . "\"\r\n";
    $payload .= 'Content-Type: ' . ($mimetype ?: 'application/octet-stream') . "\r\n\r\n";
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

    // Extract document-level data.
    $doc = $scandata['documents'][0];
    $classProbs = $doc['class_probabilities'] ?? [];
    $predictedClass = $doc['predicted_class'] ?? 'unknown';
    $classProbability = $classProbs[strtolower($predictedClass)] ?? ($doc['completely_generated_prob'] ?? 0);

    $aiPct = round(($classProbs['ai'] ?? 0) * 100);
    $mixedPct = round(($classProbs['mixed'] ?? 0) * 100);
    $humanPct = round(($classProbs['human'] ?? 0) * 100);

    // Calculate word count.
    $wordcount = 0;
    if (!empty($textcontent)) {
        $wordcount = str_word_count(strip_tags($textcontent));
    } else {
        // Estimate from file content for binary files.
        $wordcount = str_word_count(strip_tags($filecontent));
    }

    // Store result in database.
    $record = new stdClass();
    $record->userid = $USER->id;
    $record->filename = $filename;
    $record->filehash = $filehash;
    $record->predicted_class = $predictedClass;
    $record->class_probability = $classProbability;
    $record->ai_pct = $aiPct;
    $record->mixed_pct = $mixedPct;
    $record->human_pct = $humanPct;
    $record->scandata = json_encode($scandata);
    $record->wordcount = $wordcount;
    $record->timecreated = time();

    $recordid = $DB->insert_record('local_learner_aichecks', $record);

    // Track word usage for quota monitoring.
    if ($wordcount > 0) {
        $currenttotal = (int)get_config('plagiarism_gptzero', 'words_used');
        $currentscans = (int)get_config('plagiarism_gptzero', 'scans_count');
        set_config('words_used', $currenttotal + $wordcount, 'plagiarism_gptzero');
        set_config('scans_count', $currentscans + 1, 'plagiarism_gptzero');
        set_config('last_scan_time', time(), 'plagiarism_gptzero');
    }

    // Return success.
    $reporturl = new moodle_url('/local/learner/aicheckreport.php', ['id' => $recordid]);
    echo json_encode([
        'success' => true,
        'id' => $recordid,
        'predicted_class' => $predictedClass,
        'class_probability' => round($classProbability * 100),
        'ai_pct' => $aiPct,
        'mixed_pct' => $mixedPct,
        'human_pct' => $humanPct,
        'report_url' => $reporturl->out(false),
        'filename' => $filename,
        'wordcount' => $wordcount,
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}

/**
 * Extract plain text from a DOCX file.
 *
 * @param string $filepath Path to the DOCX file
 * @return string Extracted text content
 */
function extract_text_from_docx_standalone($filepath) {
    if (!file_exists($filepath)) {
        return '';
    }

    $text = '';
    $zip = new ZipArchive();

    if ($zip->open($filepath) === true) {
        $content = $zip->getFromName('word/document.xml');
        $zip->close();

        if ($content) {
            $content = str_replace('</w:p>', "\n", $content);
            $content = str_replace('</w:tr>', "\n", $content);
            $text = strip_tags($content);
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/\n{3,}/', "\n\n", $text);
            $text = trim($text);
        }
    }

    return $text;
}
