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
 * AI Detection Report for draft feedback.
 *
 * @package    local_draftfeedback
 * @copyright  2026 Integer Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_draftfeedback\manager;

$id = required_param('id', PARAM_INT);

require_login();

$draft = manager::get_draft($id);
if (!$draft) {
    throw new moodle_exception('invaliddraftid', 'local_draftfeedback');
}

if (empty($draft->aicheck_data)) {
    throw new moodle_exception('noaicheckdata', 'local_draftfeedback');
}

$cm = get_coursemodule_from_id('assign', $draft->cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_course::instance($course->id);

// Check permission.
$canview = false;
if ($draft->userid == $USER->id) {
    $canview = has_capability('local/draftfeedback:viewown', $context);
} else if (has_capability('local/draftfeedback:review', $context)) {
    $canview = true;
} else if (has_capability('local/draftfeedback:viewall', context_system::instance())) {
    $canview = true;
}

if (!$canview) {
    throw new moodle_exception('nopermission', 'local_draftfeedback');
}

$PAGE->set_url(new moodle_url('/local/draftfeedback/aireport.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('aireport', 'local_draftfeedback'));
$PAGE->set_heading(get_string('aireport', 'local_draftfeedback'));

// Parse AI check data.
$aidata = json_decode($draft->aicheck_data, true);
$doc = $aidata['documents'][0] ?? [];
$sentences = $doc['sentences'] ?? [];
$overall_prob = round(($doc['completely_generated_prob'] ?? 0) * 100);
$result = $draft->aicheck_result;

// Extract GPTZero's document-level class probabilities (the authoritative numbers).
$classProbs = $doc['class_probabilities'] ?? [];
$aiPct = round(($classProbs['ai'] ?? 0) * 100);
$mixedPct = round(($classProbs['mixed'] ?? 0) * 100);
$humanPct = round(($classProbs['human'] ?? 0) * 100);
$total_sentences = count($sentences);

echo $OUTPUT->header();
?>

<style>
.air-container {
    max-width: 1000px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.air-header {
    background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
    color: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.air-header h2 {
    margin: 0 0 8px 0;
    color: white !important;
    font-family: 'Libre Baskerville', Georgia, serif;
}

.air-header-meta {
    opacity: 0.9;
    font-size: 14px;
}

.air-actions {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
}

.air-btn {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 10px 20px;
    border-radius: 8px;
    text-decoration: none;
    font-weight: 500;
    border: none;
    cursor: pointer;
}

.air-btn--secondary { background: #1a2238; color: white; }
.air-btn--secondary:hover { background: #3a5ba0; color: white; }
.air-btn--print { background: #6ea3c1; color: white; }
.air-btn--print:hover { background: #5a8fad; color: white; }
.air-btn--download { background: #4CAF50; color: white; }
.air-btn--download:hover { background: #45a049; color: white; }

.air-summary {
    display: grid;
    grid-template-columns: auto 1fr;
    gap: 24px;
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 24px;
    align-items: center;
}

.air-circle {
    width: 120px;
    height: 120px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
}

.air-circle.human { background: linear-gradient(135deg, #8AD4BA, #4CAF50); }
.air-circle.ai { background: linear-gradient(135deg, #FEBD69, #FF9800); }
.air-circle.mixed { background: linear-gradient(135deg, #E9D2FF, #9C27B0); }

.air-circle-percent {
    font-size: 32px;
    line-height: 1;
}

.air-circle-label {
    font-size: 12px;
    text-transform: uppercase;
    margin-top: 4px;
}

.air-stats {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 16px;
}

.air-stat {
    text-align: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
}

.air-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #1a2238;
}

.air-stat-label {
    font-size: 12px;
    color: #666;
    text-transform: uppercase;
    margin-top: 4px;
}

.air-content {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
}

.air-content h3 {
    margin: 0 0 16px 0;
    color: #1a2238;
    font-family: 'Libre Baskerville', Georgia, serif;
}

.air-text {
    line-height: 1.8;
    font-size: 15px;
}

.air-sentence {
    padding: 2px 4px;
    border-radius: 3px;
    cursor: help;
    position: relative;
}

.air-sentence.high { background: #FFCC80; }
.air-sentence.medium { background: #FFE0B2; }
.air-sentence.low { background: transparent; }

.air-legend {
    display: flex;
    gap: 20px;
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #eee;
    font-size: 13px;
}

.air-legend-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.air-legend-color {
    width: 20px;
    height: 14px;
    border-radius: 3px;
}

.air-legend-color.high { background: #FFCC80; }
.air-legend-color.medium { background: #FFE0B2; }
.air-legend-color.low { background: #f0f0f0; }

@media print {
    .air-actions { display: none; }
    .air-header { background: #9C27B0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .air-circle { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
    .air-sentence.high, .air-sentence.medium { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
}
</style>

<div class="air-container">
    <div class="air-header">
        <h2><i class="bi bi-shield-check"></i> AI Detection Report</h2>
        <div class="air-header-meta">
            <strong><?php echo fullname($draft); ?></strong> &middot;
            <?php echo s($draft->assignmentname); ?> &middot;
            <?php echo s($draft->courseshortname); ?>
        </div>
    </div>

    <div class="air-actions">
        <a href="<?php echo new moodle_url('/local/draftfeedback/view.php', ['id' => $id]); ?>" class="air-btn air-btn--secondary">
            <i class="bi bi-arrow-left"></i> Back to Draft
        </a>
        <button onclick="window.print();" class="air-btn air-btn--print">
            <i class="bi bi-printer"></i> Print Report
        </button>
        <button onclick="downloadReport();" class="air-btn air-btn--download">
            <i class="bi bi-download"></i> Download PDF
        </button>
    </div>

    <script>
    function downloadReport() {
        // Set filename for download
        var learner = '<?php echo addslashes(fullname($draft)); ?>';
        var assignment = '<?php echo addslashes($draft->assignmentname); ?>';
        document.title = 'AI_Report_' + learner.replace(/\s+/g, '_') + '_' + assignment.replace(/\s+/g, '_').substring(0, 30);

        // Trigger print dialog - user can select "Save as PDF"
        window.print();

        // Reset title
        setTimeout(function() {
            document.title = '<?php echo addslashes(get_string('aireport', 'local_draftfeedback')); ?>';
        }, 1000);
    }
    </script>

    <div class="air-summary">
        <div class="air-circle <?php echo $result; ?>">
            <span class="air-circle-label" style="font-size: 18px; margin-bottom: 4px;"><?php echo ucfirst($result); ?></span>
        </div>
        <div>
            <h3 style="margin: 0 0 8px 0; color: #1a2238;">Detection Result</h3>
            <p style="color: #666; margin: 0 0 12px 0;"><?php echo s($doc['result_message'] ?? ''); ?></p>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                <span style="display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; background: #fff3e0; border: 1px solid #ffcc80; color: #e65100;">AI <?php echo $aiPct; ?>%</span>
                <span style="display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; background: #fff8e1; border: 1px solid #ffe082; color: #f57f17;">Mixed <?php echo $mixedPct; ?>%</span>
                <span style="display: inline-flex; align-items: center; padding: 6px 14px; border-radius: 20px; font-size: 13px; font-weight: 500; <?php echo $result === 'human' ? 'background: #2e7d32; border-color: #2e7d32; color: white; font-weight: 600;' : 'background: #e8f5e9; border: 1px solid #a5d6a7; color: #2e7d32;'; ?>">Human <?php echo $humanPct; ?>%</span>
            </div>
        </div>
    </div>

    <div class="air-summary" style="grid-template-columns: 1fr;">
        <div class="air-stats">
            <div class="air-stat">
                <div class="air-stat-value"><?php echo $total_sentences; ?></div>
                <div class="air-stat-label">Sentences Analyzed</div>
            </div>
            <div class="air-stat">
                <div class="air-stat-value" style="color: #E65100;"><?php echo $aiPct; ?>%</div>
                <div class="air-stat-label">AI Probability</div>
            </div>
            <div class="air-stat">
                <div class="air-stat-value" style="color: #f57f17;"><?php echo $mixedPct; ?>%</div>
                <div class="air-stat-label">Mixed</div>
            </div>
            <div class="air-stat">
                <div class="air-stat-value" style="color: #2E7D32;"><?php echo $humanPct; ?>%</div>
                <div class="air-stat-label">Human</div>
            </div>
        </div>
    </div>

    <div class="air-content">
        <h3><i class="bi bi-file-text"></i> Analyzed Text</h3>
        <div class="air-text">
            <?php
            if (!empty($sentences)) {
                foreach ($sentences as $sentence) {
                    $text = $sentence['sentence'] ?? '';
                    $prob = $sentence['generated_prob'] ?? 0;
                    $prob_percent = round($prob * 100);

                    if ($prob >= 0.8) {
                        $class = 'high';
                    } else if ($prob >= 0.5) {
                        $class = 'medium';
                    } else {
                        $class = 'low';
                    }

                    echo '<span class="air-sentence ' . $class . '" title="AI probability: ' . $prob_percent . '%">';
                    echo s($text);
                    echo '</span> ';
                }
            } else {
                echo '<p class="text-muted">No sentence-level analysis available.</p>';
            }
            ?>
        </div>

        <div class="air-legend">
            <div class="air-legend-item">
                <div class="air-legend-color high"></div>
                <span>High AI probability (≥80%)</span>
            </div>
            <div class="air-legend-item">
                <div class="air-legend-color medium"></div>
                <span>Medium AI probability (50-80%)</span>
            </div>
            <div class="air-legend-item">
                <div class="air-legend-color low"></div>
                <span>Low AI probability (&lt;50%)</span>
            </div>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
