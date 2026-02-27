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
 * News create/edit page.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$context = context_system::instance();
require_capability('local/news:manage', $context);

$id = optional_param('id', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/news/edit.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');

// Get existing news item if editing.
$news = null;
if ($id) {
    $news = \local_news\manager::get_news($id);
    if (!$news) {
        throw new moodle_exception('error_newsnotfound', 'local_news');
    }
    $PAGE->set_title(get_string('editnews', 'local_news'));
    $PAGE->set_heading(get_string('editnews', 'local_news'));
} else {
    $PAGE->set_title(get_string('createnews', 'local_news'));
    $PAGE->set_heading(get_string('createnews', 'local_news'));
}

// Prepare form data.
$formdata = new stdClass();
$formdata->id = $id;

if ($news) {
    $formdata->title = $news->title;
    $formdata->category = $news->category;
    $formdata->important = $news->important;
    $formdata->requires_acknowledgement = $news->requires_acknowledgement;
    $formdata->published = $news->published;
    $formdata->publishdate = $news->publishdate;
    $formdata->expirydate = $news->expirydate ?: 0;

    // Prepare editor content.
    $formdata->content_editor = [
        'text' => $news->content,
        'format' => $news->contentformat,
    ];
}

// Create form.
$form = new \local_news\form\news_form();
$form->set_data($formdata);

// Handle form submission.
if ($form->is_cancelled()) {
    redirect(new moodle_url('/local/news/manage.php'));
}

if ($data = $form->get_data()) {
    // Prepare data object.
    $newsdata = new stdClass();
    $newsdata->title = $data->title;
    $newsdata->content = $data->content_editor['text'];
    $newsdata->contentformat = $data->content_editor['format'];
    $newsdata->category = $data->category;
    $newsdata->important = !empty($data->important);
    $newsdata->requires_acknowledgement = !empty($data->requires_acknowledgement);
    $newsdata->publishdate = $data->publishdate;
    $newsdata->expirydate = !empty($data->expirydate) ? $data->expirydate : null;

    // Check if save as draft or publish.
    if (isset($data->savedraft)) {
        $newsdata->published = 0;
    } else {
        $newsdata->published = !empty($data->published);
    }

    if ($id) {
        // Update existing.
        \local_news\manager::update_news($id, $newsdata);
        $message = get_string('newssaved', 'local_news');
    } else {
        // Create new.
        $id = \local_news\manager::create_news($newsdata);
        $message = $newsdata->published ? get_string('newspublished', 'local_news') : get_string('newssaved', 'local_news');
    }

    redirect(new moodle_url('/local/news/manage.php'), $message, null, \core\output\notification::NOTIFY_SUCCESS);
}

// Output page.
echo $OUTPUT->header();
?>

<div class="nw-edit-container">
    <div class="nw-edit-header">
        <a href="<?php echo (new moodle_url('/local/news/manage.php'))->out(); ?>" class="btn btn-link">
            <i class="bi bi-arrow-left me-1"></i><?php echo get_string('backtonews', 'local_news'); ?>
        </a>
    </div>

    <!-- Feature Instructions Panel -->
    <div class="nw-instructions-panel">
        <div class="nw-instructions-toggle" onclick="this.parentElement.classList.toggle('expanded')">
            <i class="bi bi-info-circle me-2"></i>
            <span>How to use News & Updates features</span>
            <i class="bi bi-chevron-down nw-toggle-icon"></i>
        </div>
        <div class="nw-instructions-content">
            <div class="nw-instruction-grid">
                <div class="nw-instruction-item">
                    <div class="nw-instruction-icon" style="background:#FFF3E0;color:#E65100;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="nw-instruction-text">
                        <strong>Important Posts (Popup + Email)</strong>
                        <p>Check "Mark as important" to trigger an email notification to ALL users and show a popup when they log in. Users can't miss critical announcements.</p>
                    </div>
                </div>
                <div class="nw-instruction-item">
                    <div class="nw-instruction-icon" style="background:#E8F5E9;color:#2E7D32;">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="nw-instruction-text">
                        <strong>Acknowledgement Tracking</strong>
                        <p>Check "Require acknowledgement" to force users to click an "I Acknowledge" button. View who has acknowledged on the Stats page - useful for compliance and policy updates.</p>
                    </div>
                </div>
                <div class="nw-instruction-item">
                    <div class="nw-instruction-icon" style="background:#E3F2FD;color:#1565C0;">
                        <i class="bi bi-calendar-event"></i>
                    </div>
                    <div class="nw-instruction-text">
                        <strong>Scheduled Publishing</strong>
                        <p>Set a future "Publish date" to schedule posts in advance. Set an "Expiry date" to auto-hide posts after a certain time (e.g., event announcements).</p>
                    </div>
                </div>
                <div class="nw-instruction-item">
                    <div class="nw-instruction-icon" style="background:#F3E5F5;color:#7B1FA2;">
                        <i class="bi bi-bar-chart-fill"></i>
                    </div>
                    <div class="nw-instruction-text">
                        <strong>Read Tracking & Stats</strong>
                        <p>Every post tracks who has read it. Click "View Stats" on the manage page to see read percentages, acknowledgement rates, and a list of users who haven't read important posts.</p>
                    </div>
                </div>
                <div class="nw-instruction-item">
                    <div class="nw-instruction-icon" style="background:#ECEFF1;color:#546E7A;">
                        <i class="bi bi-envelope-fill"></i>
                    </div>
                    <div class="nw-instruction-text">
                        <strong>Email Notifications</strong>
                        <p>Important posts automatically trigger background email notifications to all users. Delivery status (sent/failed) is logged per user in the stats page.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="nw-edit-form">
        <?php $form->display(); ?>
    </div>
</div>

<style>
.nw-edit-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 20px;
}

.nw-edit-header {
    margin-bottom: 20px;
}

.nw-edit-form .form-group {
    margin-bottom: 20px;
}

.nw-edit-form .editor_atto_wrap {
    margin-top: 8px;
}

/* Instructions Panel */
.nw-instructions-panel {
    background: #fff;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    margin-bottom: 24px;
    overflow: hidden;
}

.nw-instructions-toggle {
    display: flex;
    align-items: center;
    padding: 16px 20px;
    cursor: pointer;
    background: linear-gradient(135deg, #1a2238 0%, #3a5ba0 100%);
    color: #fff;
    font-weight: 500;
    transition: background 0.2s;
}

.nw-instructions-toggle:hover {
    background: linear-gradient(135deg, #2a3348 0%, #4a6bb0 100%);
}

.nw-toggle-icon {
    margin-left: auto;
    transition: transform 0.3s;
}

.nw-instructions-panel.expanded .nw-toggle-icon {
    transform: rotate(180deg);
}

.nw-instructions-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
}

.nw-instructions-panel.expanded .nw-instructions-content {
    max-height: 1000px;
}

.nw-instruction-grid {
    display: grid;
    gap: 16px;
    padding: 20px;
}

.nw-instruction-item {
    display: flex;
    gap: 16px;
    align-items: flex-start;
    padding: 16px;
    background: #f8f9fa;
    border-radius: 8px;
    border-left: 4px solid #3a5ba0;
}

.nw-instruction-icon {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 22px;
    flex-shrink: 0;
}

.nw-instruction-text strong {
    display: block;
    font-size: 15px;
    margin-bottom: 6px;
    color: #1a2238;
}

.nw-instruction-text p {
    margin: 0;
    font-size: 13px;
    color: #555;
    line-height: 1.5;
}
</style>

<?php
echo $OUTPUT->footer();
