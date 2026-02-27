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
 * AI Detection Report for standalone AI checks.
 *
 * Displays sentence-level AI probability analysis for documents
 * uploaded via the AI Check Tool (stored in local_learner_aichecks).
 *
 * @package   local_learner
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();

$id = required_param('id', PARAM_INT);

global $DB, $PAGE, $OUTPUT, $USER;

// Get the scan record.
$scan = $DB->get_record('local_learner_aichecks', ['id' => $id], '*', MUST_EXIST);

// Permission check: scan owner or admin.
$is_admin = has_capability('local/learner:view', context_system::instance());
if (!$is_admin && $scan->userid != $USER->id) {
    throw new moodle_exception('nopermission');
}

// Get the user who ran the scan.
$scanner = $DB->get_record('user', ['id' => $scan->userid], '*', MUST_EXIST);

// Setup page.
$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/learner/aicheckreport.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title('AI Detection Report - ' . $scan->filename);
$PAGE->set_heading('AI Detection Report');

// Parse scan data.
$scandata = json_decode($scan->scandata, true);
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

.ai-score-label {
    font-size: 18px;
    text-transform: capitalize;
    margin-top: 4px;
}

.ai-score-details h3 {
    margin: 0 0 8px 0;
    color: #1a2238;
}

.ai-score-message {
    color: #666;
    margin: 0 0 12px 0;
}

.ai-probability-pills {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 8px;
}

.ai-pill {
    display: inline-flex;
    align-items: center;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 500;
    border: 1px solid #ddd;
    background: #f5f5f5;
    color: #666;
}

.ai-pill-ai { background: #fff3e0; border-color: #ffcc80; color: #e65100; }
.ai-pill-mixed { background: #fff8e1; border-color: #ffe082; color: #f57f17; }
.ai-pill-human { background: #e8f5e9; border-color: #a5d6a7; color: #2e7d32; }
.ai-pill-human.highlighted { background: #2e7d32; border-color: #2e7d32; color: white; font-weight: 600; }

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

.ai-sentence.high-ai { background-color: #FEBD69; }
.ai-sentence.medium-ai { background-color: #FFE4B5; }
.ai-sentence.low-ai { background-color: transparent; }
.ai-sentence:hover { cursor: help; }

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

@media print {
    .action-buttons, .back-button, .print-button,
    #page-header, #page-footer, .drawer, .navbar, nav, aside {
        display: none !important;
    }
    .ai-report-container { max-width: 100% !important; padding: 0 !important; margin: 0 !important; }
    .ai-report-header {
        background: #1a2238 !important;
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
    }
    .ai-score-circle {
        -webkit-print-color-adjust: exact !important;
        print-color-adjust: exact !important;
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
    body { background: white !important; }
    .ai-score-card, .ai-stat-card, .ai-text-panel {
        box-shadow: none !important;
        border: 1px solid #ddd !important;
    }
    @page { margin: 1cm; }
}
</style>

<div class="ai-report-container">
    <div class="action-buttons">
        <a href="<?php echo new moodle_url('/local/learner/aichecks.php'); ?>" class="back-button">
            <i class="fa fa-arrow-left"></i> Back to AI Checks
        </a>
        <button onclick="window.print();" class="print-button">
            <i class="fa fa-download"></i> Download Report
        </button>
    </div>

    <div class="ai-report-header">
        <h1><i class="fa fa-shield"></i> AI Detection Report</h1>
        <div class="ai-report-meta">
            <div class="ai-report-meta-item">
                <div class="ai-report-meta-label">Document</div>
                <div class="ai-report-meta-value"><?php echo s($scan->filename); ?></div>
            </div>
            <div class="ai-report-meta-item">
                <div class="ai-report-meta-label">Checked By</div>
                <div class="ai-report-meta-value"><?php echo fullname($scanner); ?></div>
            </div>
            <div class="ai-report-meta-item">
                <div class="ai-report-meta-label">Date</div>
                <div class="ai-report-meta-value"><?php echo userdate($scan->timecreated, '%d %b %Y %H:%M'); ?></div>
            </div>
            <div class="ai-report-meta-item">
                <div class="ai-report-meta-label">Words Scanned</div>
                <div class="ai-report-meta-value"><?php echo number_format($scan->wordcount); ?></div>
            </div>
        </div>
    </div>

    <?php if ($doc): ?>
    <?php
    $predictedClass = $doc['predicted_class'] ?? 'unknown';
    $classProbs = $doc['class_probabilities'] ?? [];
    $aiPct = isset($classProbs['ai']) ? round($classProbs['ai'] * 100) : $scan->ai_pct;
    $mixedPct = isset($classProbs['mixed']) ? round($classProbs['mixed'] * 100) : $scan->mixed_pct;
    $humanPct = isset($classProbs['human']) ? round($classProbs['human'] * 100) : $scan->human_pct;
    $resultMessage = $doc['result_message'] ?? '';
    $sentences = $doc['sentences'] ?? [];
    ?>

    <div class="ai-score-card">
        <div class="ai-score-circle <?php echo $predictedClass; ?>">
            <span class="ai-score-label"><?php echo ucfirst($predictedClass); ?></span>
        </div>
        <div class="ai-score-details">
            <h3>Detection Result</h3>
            <p class="ai-score-message"><?php echo s($resultMessage); ?></p>
            <div class="ai-probability-pills">
                <span class="ai-pill ai-pill-ai">AI <?php echo $aiPct; ?>%</span>
                <span class="ai-pill ai-pill-mixed">Mixed <?php echo $mixedPct; ?>%</span>
                <span class="ai-pill ai-pill-human <?php echo $predictedClass === 'human' ? 'highlighted' : ''; ?>">Human <?php echo $humanPct; ?>%</span>
            </div>
        </div>
    </div>

    <div class="ai-stats-grid">
        <div class="ai-stat-card">
            <div class="ai-stat-value"><?php echo count($sentences); ?></div>
            <div class="ai-stat-label">Sentences Analyzed</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-value" style="color: #e65100;"><?php echo $aiPct; ?>%</div>
            <div class="ai-stat-label">AI Probability</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-value" style="color: #f57f17;"><?php echo $mixedPct; ?>%</div>
            <div class="ai-stat-label">Mixed</div>
        </div>
        <div class="ai-stat-card">
            <div class="ai-stat-value" style="color: #2e7d32;"><?php echo $humanPct; ?>%</div>
            <div class="ai-stat-label">Human</div>
        </div>
    </div>

    <div class="ai-legend">
        <div class="ai-legend-item">
            <span class="ai-legend-color high-ai"></span>
            <span>High AI probability (&ge;80%)</span>
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
        <strong>No scan data available.</strong> The scan may have failed or the data was not stored correctly.
    </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
