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
 * Provide feedback on a draft.
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

$cm = get_coursemodule_from_id('assign', $draft->cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$context = context_course::instance($course->id);

// Check permission.
$canreview = has_capability('local/draftfeedback:review', $context) ||
             has_capability('local/draftfeedback:viewall', context_system::instance());

if (!$canreview) {
    throw new moodle_exception('nopermission', 'local_draftfeedback');
}

$PAGE->set_url(new moodle_url('/local/draftfeedback/feedback.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_course($course);
$PAGE->set_title(get_string('providefeedback', 'local_draftfeedback'));
$PAGE->set_heading(get_string('providefeedback', 'local_draftfeedback'));

// Create form.
require_once($CFG->libdir . '/formslib.php');

class local_draftfeedback_feedback_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $draft = $this->_customdata['draft'];

        $mform->addElement('hidden', 'id', $draft->id);
        $mform->setType('id', PARAM_INT);

        // Feedback editor.
        $mform->addElement('editor', 'feedback', get_string('feedbacktext', 'local_draftfeedback'), [
            'rows' => 10,
        ]);
        $mform->setType('feedback', PARAM_RAW);
        $mform->addRule('feedback', get_string('required'), 'required');
        $mform->addHelpButton('feedback', 'feedbacktext', 'local_draftfeedback');

        // Pre-fill with existing feedback if any.
        if (!empty($draft->feedback)) {
            $mform->setDefault('feedback', ['text' => $draft->feedback, 'format' => FORMAT_HTML]);
        }

        // File upload for annotated/marked documents.
        $mform->addElement('filemanager', 'feedbackfiles', 'Attach files (e.g. marked workbook)', null, [
            'subdirs' => 0,
            'maxfiles' => 10,
            'accepted_types' => ['.pdf', '.doc', '.docx', '.txt', '.rtf', '.xlsx', '.xls', '.pptx', '.png', '.jpg'],
        ]);

        $this->add_action_buttons(true, get_string('savefeedback', 'local_draftfeedback'));
    }
}

$form = new local_draftfeedback_feedback_form(null, ['draft' => $draft]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/draftfeedback/view.php', ['id' => $id]));
}

if ($data = $form->get_data()) {
    $feedback = $data->feedback['text'];
    $feedbackfileitemid = !empty($data->feedbackfiles) ? $data->feedbackfiles : null;
    manager::save_feedback($id, $feedback, $USER->id, $feedbackfileitemid);

    redirect(
        new moodle_url('/local/draftfeedback/index.php'),
        get_string('feedbacksaved', 'local_draftfeedback'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

// Get draft content for display.
$content = manager::get_draft_content($draft);

echo $OUTPUT->header();
?>

<style>
.df-feedback-container {
    max-width: 900px;
    margin: 0 auto;
}

.df-preview-card {
    background: #f8f9fa;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 24px;
}

.df-preview-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    margin-bottom: 16px;
}

.df-preview-title {
    font-weight: 600;
    color: #1a2238;
}

.df-preview-meta {
    font-size: 14px;
    color: #666;
}

.df-preview-content {
    background: white;
    padding: 16px;
    border-radius: 8px;
    border: 1px solid #ddd;
    max-height: 300px;
    overflow-y: auto;
    white-space: pre-wrap;
    line-height: 1.6;
}

.df-ai-badge {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 600;
}

.df-ai-badge.human { background: #E8F5E9; color: #2E7D32; }
.df-ai-badge.ai { background: #FFF3E0; color: #E65100; }
.df-ai-badge.mixed { background: #F3E5F5; color: #7B1FA2; }
</style>

<div class="df-feedback-container">
    <div class="df-preview-card">
        <div class="df-preview-header">
            <div>
                <div class="df-preview-title"><?php echo s($draft->assignmentname); ?></div>
                <div class="df-preview-meta">
                    <strong><?php echo fullname($draft); ?></strong> &middot;
                    <?php echo s($draft->courseshortname); ?> &middot;
                    Submitted <?php echo userdate($draft->timecreated, '%d %b %Y %H:%M'); ?>
                </div>
            </div>
            <?php if (!empty($draft->aicheck_result)): ?>
            <span class="df-ai-badge <?php echo $draft->aicheck_result; ?>">
                <i class="bi bi-shield-check"></i>
                <?php echo ucfirst($draft->aicheck_result); ?> (<?php echo round($draft->aicheck_probability); ?>%)
            </span>
            <?php endif; ?>
        </div>

        <div class="df-preview-content">
            <?php
            if ($content['type'] === 'file') {
                echo '<em>File submission: ' . s($content['filename']) . '</em>';
                echo '<br><a href="' . new moodle_url('/local/draftfeedback/view.php', ['id' => $id]) . '">View/download file</a>';
            } else if ($content['type'] === 'text') {
                echo s($content['content']);
            } else {
                echo '<em>No content available</em>';
            }
            ?>
        </div>
        <?php if (!empty($draft->drafttext) && $content['type'] === 'file'): ?>
        <div style="margin-top: 14px; padding: 14px 16px; background: #EDE7F6; border-left: 4px solid #9C27B0; border-radius: 0 8px 8px 0;">
            <div style="font-weight: 600; color: #7B1FA2; font-size: 13px; margin-bottom: 6px;">
                <i class="bi bi-chat-dots"></i> Learner's Comments
            </div>
            <div style="color: #333; font-size: 14px; line-height: 1.6; white-space: pre-wrap;"><?php echo format_text($draft->drafttext, FORMAT_HTML); ?></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header">
            <h5 class="mb-0"><i class="bi bi-chat-square-text"></i> Your Feedback</h5>
        </div>
        <div class="card-body">
            <?php $form->display(); ?>
        </div>
    </div>
</div>

<?php
echo $OUTPUT->footer();
