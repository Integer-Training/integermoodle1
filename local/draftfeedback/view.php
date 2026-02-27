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
 * View a single draft with AI check option.
 *
 * @package    local_draftfeedback
 * @copyright  2026 Integer Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_draftfeedback\manager;

$id = required_param('id', PARAM_INT);
$aicheck = optional_param('aicheck', 0, PARAM_BOOL);

require_login();

$draft = manager::get_draft($id);
if (!$draft) {
    throw new moodle_exception('invaliddraftid', 'local_draftfeedback');
}

$cm = get_coursemodule_from_id('assign', $draft->cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_course::instance($course->id);
$modcontext = context_module::instance($cm->id); // For file operations

// Check permission - owner, tutor with review, or manager with viewall.
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

$canreview = has_capability('local/draftfeedback:review', $context) ||
             has_capability('local/draftfeedback:viewall', context_system::instance());

$PAGE->set_url(new moodle_url('/local/draftfeedback/view.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('viewdraft', 'local_draftfeedback'));
$PAGE->set_heading(get_string('viewdraft', 'local_draftfeedback'));

// Handle AI check request.
if ($aicheck && $canreview) {
    require_sesskey();

    // Get draft content.
    $content = manager::get_draft_content($draft);

    if ($content['type'] !== 'none') {
        // Call GPTZero API.
        $apikey = get_config('plagiarism_gptzero', 'gptzero_apikey');

        if (!empty($apikey)) {
            if ($content['type'] === 'file') {
                // File scan.
                $boundary = '----CustomBoundary' . uniqid();
                $payload = '--' . $boundary . "\r\n";
                $payload .= 'Content-Disposition: form-data; name="files"; filename="' . $content['filename'] . "\"\r\n";
                $payload .= 'Content-Type: ' . $content['mimetype'] . "\r\n\r\n";
                $payload .= $content['content'] . "\r\n";
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
            } else {
                // Text scan.
                $data = json_encode(['document' => $content['content']]);

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
            }

            $scandata = json_decode($response, true);

            if (isset($scandata['documents'][0])) {
                $doc = $scandata['documents'][0];
                $result = $doc['predicted_class'] ?? 'unknown';
                $probability = 0;
                if (isset($doc['class_probabilities'][$result])) {
                    $probability = round($doc['class_probabilities'][$result] * 100, 2);
                }

                manager::save_aicheck_result($id, $result, $probability, json_encode($scandata));

                // Refresh draft data.
                $draft = manager::get_draft($id);
            }
        }
    }

    redirect(new moodle_url('/local/draftfeedback/view.php', ['id' => $id]));
}

// Get draft content for display.
$content = manager::get_draft_content($draft);

echo $OUTPUT->header();
?>

<style>
.df-view-container {
    max-width: 1000px;
    margin: 0 auto;
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
}

.df-header {
    background: linear-gradient(135deg, #9C27B0 0%, #7B1FA2 100%);
    color: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.df-header h2 {
    margin: 0 0 8px 0;
    color: white !important;
}

.df-meta {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 12px;
    margin-top: 16px;
}

.df-meta-item {
    background: rgba(255,255,255,0.15);
    padding: 10px 14px;
    border-radius: 8px;
}

.df-meta-label {
    font-size: 11px;
    text-transform: uppercase;
    opacity: 0.8;
}

.df-meta-value {
    font-size: 15px;
    font-weight: 600;
    margin-top: 2px;
}

.df-actions {
    display: flex;
    gap: 12px;
    margin-bottom: 24px;
    flex-wrap: wrap;
}

.df-btn {
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

.df-btn--primary { background: #9C27B0; color: white; }
.df-btn--primary:hover { background: #7B1FA2; color: white; }
.df-btn--secondary { background: #1a2238; color: white; }
.df-btn--secondary:hover { background: #3a5ba0; color: white; }
.df-btn--success { background: #4CAF50; color: white; }
.df-btn--success:hover { background: #45a049; color: white; }

.df-card {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    margin-bottom: 24px;
    overflow: hidden;
}

.df-card-header {
    background: #f8f9fa;
    padding: 16px 20px;
    border-bottom: 1px solid #eee;
    font-weight: 600;
    color: #1a2238;
}

.df-card-body {
    padding: 20px;
}

.df-ai-result {
    display: flex;
    align-items: center;
    gap: 16px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    margin-bottom: 20px;
}

.df-ai-circle {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    color: white;
}

.df-ai-circle.human { background: linear-gradient(135deg, #8AD4BA, #4CAF50); }
.df-ai-circle.ai { background: linear-gradient(135deg, #FEBD69, #FF9800); }
.df-ai-circle.mixed { background: linear-gradient(135deg, #E9D2FF, #9C27B0); }

.df-content-text {
    line-height: 1.7;
    white-space: pre-wrap;
    background: #fafafa;
    padding: 20px;
    border-radius: 8px;
    border: 1px solid #eee;
    max-height: 400px;
    overflow-y: auto;
}

.df-feedback-box {
    background: #E8F5E9;
    border-left: 4px solid #4CAF50;
    padding: 16px 20px;
    border-radius: 0 8px 8px 0;
}

.df-feedback-header {
    font-weight: 600;
    color: #2E7D32;
    margin-bottom: 8px;
}

.df-feedback-by {
    font-size: 13px;
    color: #666;
    margin-top: 12px;
}
</style>

<div class="df-view-container">
    <div class="df-header">
        <h2><i class="bi bi-file-earmark-text"></i> Draft Feedback Request</h2>
        <div class="df-meta">
            <div class="df-meta-item">
                <div class="df-meta-label">Learner</div>
                <div class="df-meta-value"><?php echo fullname($draft); ?></div>
            </div>
            <div class="df-meta-item">
                <div class="df-meta-label">Assignment</div>
                <div class="df-meta-value"><?php echo s($draft->assignmentname); ?></div>
            </div>
            <div class="df-meta-item">
                <div class="df-meta-label">Course</div>
                <div class="df-meta-value"><?php echo s($draft->courseshortname); ?></div>
            </div>
            <div class="df-meta-item">
                <div class="df-meta-label">Submitted</div>
                <div class="df-meta-value"><?php echo userdate($draft->timecreated, '%d %b %Y %H:%M'); ?></div>
            </div>
        </div>
    </div>

    <div class="df-actions">
        <a href="<?php echo new moodle_url('/local/draftfeedback/index.php'); ?>" class="df-btn df-btn--secondary">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
        <?php if ($canreview && $draft->status === 'pending'): ?>
            <a href="<?php echo new moodle_url('/local/draftfeedback/feedback.php', ['id' => $id]); ?>" class="df-btn df-btn--success">
                <i class="bi bi-chat-square-text"></i> Provide Feedback
            </a>
        <?php endif; ?>
        <?php if ($canreview && empty($draft->aicheck_result)): ?>
            <a href="<?php echo new moodle_url('/local/draftfeedback/view.php', ['id' => $id, 'aicheck' => 1, 'sesskey' => sesskey()]); ?>" class="df-btn df-btn--primary">
                <i class="bi bi-shield-check"></i> Run AI Check
            </a>
        <?php endif; ?>
    </div>

    <?php if (!empty($draft->aicheck_result)): ?>
    <div class="df-card">
        <div class="df-card-header"><i class="bi bi-shield-check"></i> AI Detection Result</div>
        <div class="df-card-body">
            <div class="df-ai-result">
                <div class="df-ai-circle <?php echo $draft->aicheck_result; ?>">
                    <span style="font-size: 22px;"><?php echo round($draft->aicheck_probability); ?>%</span>
                    <span style="font-size: 11px; text-transform: uppercase;"><?php echo ucfirst($draft->aicheck_result); ?></span>
                </div>
                <div style="flex: 1;">
                    <strong>Detection Result: <?php echo ucfirst($draft->aicheck_result); ?> Generated</strong>
                    <p style="margin: 8px 0 0 0; color: #666;">
                        <?php
                        if ($draft->aicheck_result === 'human') {
                            echo 'This text appears to be primarily human-written.';
                        } else if ($draft->aicheck_result === 'ai') {
                            echo 'This text shows strong indicators of AI generation.';
                        } else {
                            echo 'This text contains a mix of human and AI-generated content.';
                        }
                        ?>
                    </p>
                </div>
                <a href="<?php echo new moodle_url('/local/draftfeedback/aireport.php', ['id' => $id]); ?>" class="df-btn df-btn--primary" style="white-space: nowrap;">
                    <i class="bi bi-file-text"></i> View Report
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($draft->feedback)): ?>
    <div class="df-card">
        <div class="df-card-header"><i class="bi bi-chat-square-text"></i> Tutor Feedback</div>
        <div class="df-card-body">
            <div class="df-feedback-box">
                <div class="df-feedback-header">Feedback from <?php echo s($draft->feedbackbyfirstname . ' ' . $draft->feedbackbylastname); ?></div>
                <?php echo format_text($draft->feedback, FORMAT_HTML); ?>
                <div class="df-feedback-by">
                    Provided on <?php echo userdate($draft->feedbacktime, '%d %B %Y at %H:%M'); ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="df-card">
        <div class="df-card-header"><i class="bi bi-file-text"></i> Draft Content</div>
        <div class="df-card-body">
            <?php if ($content['type'] === 'file'): ?>
                <p><strong>File:</strong> <?php echo s($content['filename']); ?></p>
                <?php
                $fs = get_file_storage();
                $file = $content['file'];
                $url = moodle_url::make_pluginfile_url(
                    $modcontext->id,
                    'local_draftfeedback',
                    'draftfiles',
                    $draft->id,
                    '/',
                    $content['filename']
                );
                ?>
                <a href="<?php echo $url; ?>" class="df-btn df-btn--secondary" target="_blank">
                    <i class="bi bi-download"></i> Download File
                </a>
            <?php elseif ($content['type'] === 'text'): ?>
                <div class="df-content-text"><?php echo s($content['content']); ?></div>
            <?php else: ?>
                <p class="text-muted">No content available.</p>
            <?php endif; ?>

            <?php if (!empty($draft->drafttext) && $content['type'] === 'file'): ?>
            <div style="margin-top: 16px; padding: 14px 16px; background: #EDE7F6; border-left: 4px solid #9C27B0; border-radius: 0 8px 8px 0;">
                <div style="font-weight: 600; color: #7B1FA2; font-size: 13px; margin-bottom: 6px;">
                    <i class="bi bi-chat-dots"></i> Learner's Comments
                </div>
                <div style="color: #333; font-size: 14px; line-height: 1.6;"><?php echo format_text($draft->drafttext, FORMAT_HTML); ?></div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <?php
    // Show feedback files if any.
    $feedbackfiles = manager::get_feedback_files($draft->id, $draft->cmid);
    if (!empty($feedbackfiles)):
    ?>
    <div class="df-card">
        <div class="df-card-header"><i class="bi bi-paperclip"></i> Feedback Attachments</div>
        <div class="df-card-body">
            <?php foreach ($feedbackfiles as $fbfile):
                $fburl = moodle_url::make_pluginfile_url(
                    $modcontext->id,
                    'local_draftfeedback',
                    'feedbackfiles',
                    $draft->id,
                    '/',
                    $fbfile->get_filename()
                );
            ?>
            <div style="display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #eee;">
                <i class="bi bi-file-earmark" style="font-size: 18px; color: #666;"></i>
                <a href="<?php echo $fburl; ?>" target="_blank" style="color: #1a2238; font-weight: 500;">
                    <?php echo s($fbfile->get_filename()); ?>
                </a>
                <span style="font-size: 12px; color: #999;">(<?php echo display_size($fbfile->get_filesize()); ?>)</span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php
echo $OUTPUT->footer();
