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
 * AI Detection Detailed Report Page.
 *
 * @package   local_learner
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

require_login();

$submissionid = required_param('id', PARAM_INT);

global $DB, $PAGE, $OUTPUT;

// Get submission and related data.
$submission = $DB->get_record('assign_submission', ['id' => $submissionid], '*', MUST_EXIST);
$assignment = $DB->get_record('assign', ['id' => $submission->assignment], '*', MUST_EXIST);
$learner = $DB->get_record('user', ['id' => $submission->userid], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $assignment->course], '*', MUST_EXIST);

// Get course module.
$module = $DB->get_record('modules', ['name' => 'assign'], '*', MUST_EXIST);
$cm = $DB->get_record('course_modules', [
    'instance' => $assignment->id,
    'course' => $assignment->course,
    'module' => $module->id
], '*', MUST_EXIST);

// Check capability using module context.
$modcontext = context_module::instance($cm->id);
require_capability('mod/assign:grade', $modcontext);

// Setup page using course context (avoids cm mismatch errors for local plugin).
$coursecontext = context_course::instance($course->id);
$PAGE->set_url(new moodle_url('/local/learner/aireport.php', ['id' => $submissionid]));
$PAGE->set_context($coursecontext);
$PAGE->set_title('AI Detection Report - ' . $assignment->name);
$PAGE->set_heading('AI Detection Report');

// Use module context for file access.
$context = $modcontext;

// Get submission content.
$content = '';
$filename = '';

// Check for online text.
$onlinetext = $DB->get_record('assignsubmission_onlinetext', [
    'assignment' => $assignment->id,
    'submission' => $submissionid
]);

if ($onlinetext && !empty($onlinetext->onlinetext)) {
    $content = strip_tags($onlinetext->onlinetext);
    $filename = 'Online Text Submission';
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

$file = null;
if (!empty($files)) {
    $file = reset($files);
    $filename = $file->get_filename();
}

// Get existing scan result.
$existingscan = $DB->get_record('plagiarism_gptzero_files', [
    'cm' => $cm->id,
    'userid' => $submission->userid
]);

// If no existing scan or no detailed data, run a new scan.
$scandata = null;
$needscan = true;

if ($existingscan && !empty($existingscan->scanurl)) {
    // Check if we have stored detailed data.
    $scandata = $DB->get_field('plagiarism_gptzero_files', 'scanurl', ['id' => $existingscan->id]);
    if (strpos($scandata, '{') === 0) {
        // It's JSON data.
        $scandata = json_decode($scandata, true);
        $needscan = false;
    }
}

if ($needscan) {
    // Run a fresh scan to get detailed data.
    require_once($CFG->dirroot . '/plagiarism/gptzero/classes/api.php');

    $apikey = get_config('plagiarism_gptzero', 'gptzero_apikey');
    if (empty($apikey)) {
        throw new moodle_exception('GPTZero API key not configured');
    }

    if ($file) {
        // Scan file.
        $filecontent = $file->get_content();
        $filetype = $file->get_mimetype();

        $boundary = '----CustomBoundary' . uniqid();
        $payload = '--' . $boundary . "\r\n";
        $payload .= 'Content-Disposition: form-data; name="files"; filename="' . basename($filename) . "\"\r\n";
        $payload .= 'Content-Type: ' . $filetype . "\r\n\r\n";
        $payload .= $filecontent . "\r\n";
        $payload .= '--' . $boundary . "--\r\n";

        $curl = curl_init();
        curl_setopt_array($curl, [
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
        $response = curl_exec($curl);
        curl_close($curl);
    } else if (!empty($content)) {
        // Scan text.
        $data = json_encode(['document' => $content]);

        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => 'https://api.gptzero.me/v2/predict/text',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 120,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Content-Type: application/json',
                'x-api-key: ' . $apikey,
            ],
        ]);
        $response = curl_exec($curl);
        curl_close($curl);
    } else {
        throw new moodle_exception('No content found to scan');
    }

    $scandata = json_decode($response, true);

    // Store the detailed scan data.
    if ($existingscan) {
        $existingscan->scanurl = json_encode($scandata);
        $DB->update_record('plagiarism_gptzero_files', $existingscan);
    }
}

// Extract document data.
$doc = null;
if (isset($scandata['documents']) && !empty($scandata['documents'])) {
    $doc = $scandata['documents'][0];
}

echo $OUTPUT->header();
?>

<style>
.ai-report-container {
    max-width: 1000px;
    margin: 0 auto;
    padding: 20px;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.ai-report-header {
    background: linear-gradient(135deg, #1a2238 0%, #3a5ba0 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.ai-report-header h1 {
    margin: 0 0 10px 0;
    font-size: 24px;
    color: white !important;
}

.ai-report-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 16px;
    margin-top: 20px;
}

.ai-report-meta-item {
    background: rgba(255,255,255,0.1);
    padding: 12px;
    border-radius: 8px;
}

.ai-report-meta-label {
    font-size: 11px;
    text-transform: uppercase;
    opacity: 0.8;
}

.ai-report-meta-value {
    font-size: 16px;
    font-weight: 600;
    margin-top: 4px;
}

.ai-score-card {
    display: flex;
    align-items: center;
    gap: 20px;
    padding: 24px;
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 24px;
}

.ai-score-circle {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
}

.ai-score-circle.human { background: linear-gradient(135deg, #8AD4BA, #4CAF50); }
.ai-score-circle.ai { background: linear-gradient(135deg, #FEBD69, #FF9800); }
.ai-score-circle.mixed { background: linear-gradient(135deg, #E9D2FF, #9C27B0); }

.ai-score-percent {
    font-size: 28px;
    line-height: 1;
}

.ai-score-label {
    font-size: 12px;
    text-transform: uppercase;
    margin-top: 4px;
}

.ai-score-details h3 {
    margin: 0 0 8px 0;
    color: #1a2238;
}

.ai-score-message {
    color: #666;
    margin: 0;
}

.ai-legend {
    display: flex;
    gap: 20px;
    padding: 16px 24px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 24px;
}

.ai-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
}

.ai-legend-color {
    width: 20px;
    height: 20px;
    border-radius: 4px;
}

.ai-legend-color.high-ai { background: #FEBD69; }
.ai-legend-color.medium-ai { background: #FFE4B5; }
.ai-legend-color.low-ai { background: #E8F5E9; }

.ai-text-panel {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    padding: 24px;
}

.ai-text-panel h3 {
    margin: 0 0 16px 0;
    color: #1a2238;
    border-bottom: 2px solid #eee;
    padding-bottom: 12px;
}

.ai-text-content {
    line-height: 1.8;
    font-size: 15px;
    color: #333;
}

.ai-sentence {
    display: inline;
    padding: 2px 0;
    border-radius: 3px;
    transition: background-color 0.2s;
}

.ai-sentence.high-ai {
    background-color: #FEBD69;
}

.ai-sentence.medium-ai {
    background-color: #FFE4B5;
}

.ai-sentence.low-ai {
    background-color: transparent;
}

.ai-sentence:hover {
    cursor: help;
}

.ai-stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 16px;
    margin-bottom: 24px;
}

.ai-stat-card {
    background: white;
    padding: 20px;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    text-align: center;
}

.ai-stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #1a2238;
}

.ai-stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-top: 4px;
}

.back-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #1a2238;
    color: white;
    border-radius: 8px;
    text-decoration: none;
    margin-bottom: 20px;
}

.back-button:hover {
    background: #3a5ba0;
    color: white;
    text-decoration: none;
}

.print-button {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    background: #4CAF50;
    color: white;
    border-radius: 8px;
    text-decoration: none;
    margin-bottom: 20px;
    margin-left: 10px;
    border: none;
    cursor: pointer;
    font-size: 14px;
}

.print-button:hover {
    background: #45a049;
    color: white;
}

.action-buttons {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
}

/* Print-specific styles */
@media print {
    .action-buttons,
    .back-button,
    .print-button,
    #page-header,
    #page-footer,
    .drawer,
    .navbar,
    nav,
    aside {
        display: none !important;
    }

    .ai-report-container {
        max-width: 100% !important;
        padding: 0 !important;
        margin: 0 !important;
    }

    .ai-report-header {
        background: #1a2238 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    .ai-score-circle {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
        color-adjust: exact !important;
    }

    .ai-score-circle.human { background: #4CAF50 !important; }
    .ai-score-circle.ai { background: #FF9800 !important; }
    .ai-score-circle.mixed { background: #9C27B0 !important; }

    .ai-sentence.high-ai {
        background-color: #FEBD69 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .ai-sentence.medium-ai {
        background-color: #FFE4B5 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .ai-legend-color {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }

    .ai-legend-color.high-ai { background: #FEBD69 !important; }
    .ai-legend-color.medium-ai { background: #FFE4B5 !important; }
    .ai-legend-color.low-ai { background: #E8F5E9 !important; }

    body {
        background: white !important;
    }

    .ai-score-card,
    .ai-stat-card,
    .ai-text-panel {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }

    @page {
        margin: 1cm;
    }
}
</style>

<div class="ai-report-container">
    <div class="action-buttons">
        <a href="<?php echo new moodle_url('/local/learner/markallocation.php', ['action' => 'mark']); ?>" class="back-button">
            <i class="fa fa-arrow-left"></i> Back to Marking
        </a>
        <button onclick="window.print();" class="print-button">
            <i class="fa fa-download"></i> Download Report
        </button>
    </div>

    <div class="ai-report-header">
        <h1><i class="fa fa-shield"></i> AI Detection Report</h1>
        <div class="ai-report-meta">
            <div class="ai-report-meta-item">
                <div class="ai-report-meta-label">Learner</div>
                <div class="ai-report-meta-value"><?php echo fullname($learner); ?></div>
            </div>
            <div class="ai-report-meta-item">
                <div class="ai-report-meta-label">Assignment</div>
                <div class="ai-report-meta-value"><?php echo s($assignment->name); ?></div>
            </div>
            <div class="ai-report-meta-item">
                <div class="ai-report-meta-label">Course</div>
                <div class="ai-report-meta-value"><?php echo s($course->shortname); ?></div>
            </div>
            <div class="ai-report-meta-item">
                <div class="ai-report-meta-label">File</div>
                <div class="ai-report-meta-value"><?php echo s($filename); ?></div>
            </div>
        </div>
    </div>

    <?php if ($doc): ?>
    <?php
    $predictedClass = $doc['predicted_class'] ?? 'unknown';
    $confidence = isset($doc['class_probabilities'][$predictedClass])
        ? round($doc['class_probabilities'][$predictedClass] * 100)
        : (isset($doc['confidence_score']) ? round($doc['confidence_score'] * 100) : 0);
    $resultMessage = $doc['result_message'] ?? '';
    $sentences = $doc['sentences'] ?? [];

    $aiSentences = 0;
    $humanSentences = 0;
    foreach ($sentences as $s) {
        if (($s['generated_prob'] ?? 0) >= 0.8) {
            $aiSentences++;
        } else {
            $humanSentences++;
        }
    }
    ?>

    <div class="ai-score-card">
        <div class="ai-score-circle <?php echo $predictedClass; ?>">
            <span class="ai-score-percent"><?php echo $confidence; ?>%</span>
            <span class="ai-score-label"><?php echo ucfirst($predictedClass); ?></span>
        </div>
        <div class="ai-score-details">
            <h3>Detection Result: <?php echo ucfirst($predictedClass); ?> Generated</h3>
            <p class="ai-score-message"><?php echo s($resultMessage); ?></p>
        </div>
    </div>

    <div class="ai-stats-grid">
        <div class="ai-stat-card">
            <div class="ai-stat-value"><?php echo count($sentences); ?></div>
            <div class="ai-stat-label">Total Sentences</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-value" style="color: #FF9800;"><?php echo $aiSentences; ?></div>
            <div class="ai-stat-label">AI-Detected</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-value" style="color: #4CAF50;"><?php echo $humanSentences; ?></div>
            <div class="ai-stat-label">Human-Written</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-value"><?php echo count($sentences) > 0 ? round($aiSentences / count($sentences) * 100) : 0; ?>%</div>
            <div class="ai-stat-label">AI Percentage</div>
        </div>
    </div>

    <div class="ai-legend">
        <div class="ai-legend-item">
            <span class="ai-legend-color high-ai"></span>
            <span>High AI probability (≥80%)</span>
        </div>
        <div class="ai-legend-item">
            <span class="ai-legend-color medium-ai"></span>
            <span>Medium AI probability (50-80%)</span>
        </div>
        <div class="ai-legend-item">
            <span class="ai-legend-color low-ai"></span>
            <span>Low AI probability (&lt;50%)</span>
        </div>
    </div>

    <div class="ai-text-panel">
        <h3><i class="fa fa-file-text"></i> Analyzed Text with Highlights</h3>
        <div class="ai-text-content">
            <?php
            foreach ($sentences as $s) {
                $prob = $s['generated_prob'] ?? 0;
                $sentence = $s['sentence'] ?? '';
                $pctDisplay = round($prob * 100);

                $cssClass = 'low-ai';
                if ($prob >= 0.8) {
                    $cssClass = 'high-ai';
                } else if ($prob >= 0.5) {
                    $cssClass = 'medium-ai';
                }

                echo '<span class="ai-sentence ' . $cssClass . '" title="AI probability: ' . $pctDisplay . '%">';
                echo htmlspecialchars($sentence);
                echo '</span> ';
            }
            ?>
        </div>
    </div>

    <?php else: ?>
    <div class="alert alert-warning">
        <strong>No scan data available.</strong> The submission may not have been scanned yet or the scan failed.
    </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
