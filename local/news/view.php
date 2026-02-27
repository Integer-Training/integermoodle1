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
 * Single news item view page.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/news:view', $context);

$id = required_param('id', PARAM_INT);
$acknowledge = optional_param('acknowledge', 0, PARAM_BOOL);

$news = \local_news\manager::get_news($id);
if (!$news) {
    throw new moodle_exception('error_newsnotfound', 'local_news');
}

// Check if news is published and within date range.
$now = time();
$visible = $news->published &&
           $news->publishdate <= $now &&
           (empty($news->expirydate) || $news->expirydate > $now);

// Allow admins to view unpublished news.
$canmanage = has_capability('local/news:manage', $context);
if (!$visible && !$canmanage) {
    throw new moodle_exception('error_newsnotfound', 'local_news');
}

$PAGE->set_url(new moodle_url('/local/news/view.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(format_string($news->title));
$PAGE->set_heading(format_string($news->title));
$PAGE->set_pagelayout('standard');

// Handle acknowledge action.
if ($acknowledge && confirm_sesskey()) {
    \local_news\manager::mark_as_acknowledged($id, $USER->id);
    redirect(new moodle_url('/local/news/view.php', ['id' => $id]),
        get_string('acknowledgementrecorded', 'local_news'), null, \core\output\notification::NOTIFY_SUCCESS);
}

// Mark as read.
\local_news\manager::mark_as_read($id, $USER->id);

// Get read status.
$readstatus = \local_news\manager::get_user_read_status($id, $USER->id);
$isacknowledged = $readstatus && $readstatus->acknowledged;

// Get author info.
$author = $DB->get_record('user', ['id' => $news->createdby]);

// Category info.
$categorylabel = \local_news\manager::get_category_label($news->category);
$categoryclass = 'nw-category-' . str_replace('_', '-', $news->category);

// Output page.
echo $OUTPUT->header();
?>

<div class="nw-view-container">
    <div class="nw-view-back">
        <a href="<?php echo (new moodle_url('/local/news/index.php'))->out(); ?>" class="btn btn-link p-0">
            <i class="bi bi-arrow-left me-1"></i><?php echo get_string('backtonews', 'local_news'); ?>
        </a>
    </div>

    <article class="nw-view-article">
        <!-- Header -->
        <header class="nw-view-header">
            <span class="badge <?php echo $categoryclass; ?> nw-view-category"><?php echo $categorylabel; ?></span>

            <h1 class="nw-view-title">
                <?php if ($news->important): ?>
                    <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
                <?php endif; ?>
                <?php echo format_string($news->title); ?>
            </h1>

            <div class="nw-view-meta">
                <?php
                $postdata = new stdClass();
                $postdata->name = fullname($author);
                $postdata->date = userdate($news->publishdate, get_string('dateformat', 'local_news'));
                echo get_string('postedby', 'local_news', $postdata);
                ?>
            </div>

            <?php if (!$visible): ?>
                <div class="alert alert-warning mt-3">
                    <i class="bi bi-eye-slash me-2"></i>This news item is not currently visible to users
                    (<?php echo !$news->published ? 'Draft' : 'Outside date range'; ?>).
                </div>
            <?php endif; ?>
        </header>

        <!-- Content -->
        <div class="nw-view-content">
            <?php echo format_text($news->content, $news->contentformat); ?>
        </div>

        <!-- Acknowledgement Section -->
        <?php if ($news->requires_acknowledgement): ?>
            <div class="nw-view-acknowledge">
                <?php if ($isacknowledged): ?>
                    <div class="nw-acknowledge-done">
                        <i class="bi bi-check-circle-fill text-success me-2"></i>
                        <span>
                            <?php
                            echo get_string('youacknowledged', 'local_news',
                                userdate($readstatus->timeacknowledged, get_string('datetimeformat', 'local_news')));
                            ?>
                        </span>
                    </div>
                <?php else: ?>
                    <div class="nw-acknowledge-required">
                        <div class="nw-acknowledge-notice">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <?php echo get_string('requiresack_notice', 'local_news'); ?>
                        </div>
                        <form method="post" action="">
                            <input type="hidden" name="sesskey" value="<?php echo sesskey(); ?>">
                            <input type="hidden" name="acknowledge" value="1">
                            <button type="submit" class="btn btn-primary btn-lg nw-acknowledge-btn">
                                <i class="bi bi-check-lg me-2"></i><?php echo get_string('acknowledgeconfirm', 'local_news'); ?>
                            </button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </article>

    <?php if ($canmanage): ?>
        <div class="nw-view-admin-actions">
            <a href="<?php echo (new moodle_url('/local/news/edit.php', ['id' => $id]))->out(); ?>" class="btn btn-outline-primary">
                <i class="bi bi-pencil me-1"></i><?php echo get_string('edit', 'local_news'); ?>
            </a>
            <a href="<?php echo (new moodle_url('/local/news/stats.php', ['id' => $id]))->out(); ?>" class="btn btn-outline-info">
                <i class="bi bi-bar-chart me-1"></i><?php echo get_string('viewstats', 'local_news'); ?>
            </a>
        </div>
    <?php endif; ?>
</div>

<style>
.nw-view-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 0 20px 40px;
}

.nw-view-back {
    margin-bottom: 20px;
}

.nw-view-article {
    background: white;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.08);
    overflow: hidden;
}

.nw-view-header {
    background: linear-gradient(135deg, #1a2238 0%, #3a5ba0 100%);
    color: #fff !important;
    padding: 40px;
}

.nw-view-header,
.nw-view-header h1,
.nw-view-header .nw-view-title,
.nw-view-header .nw-view-title i,
.nw-view-header .nw-view-meta,
.nw-view-header span:not(.badge) {
    color: #fff !important;
}

.nw-view-category {
    margin-bottom: 16px;
    display: inline-block;
}

.nw-view-title {
    font-family: 'Libre Baskerville', serif;
    font-size: 2rem;
    margin-bottom: 16px;
    line-height: 1.3;
}

.nw-view-meta {
    opacity: 0.9;
    font-size: 14px;
}

.nw-view-content {
    padding: 40px;
    font-size: 16px;
    line-height: 1.8;
    color: #333;
}

.nw-view-content h2, .nw-view-content h3 {
    font-family: 'Libre Baskerville', serif;
    margin-top: 24px;
}

.nw-view-content ul, .nw-view-content ol {
    margin-left: 20px;
}

.nw-view-acknowledge {
    padding: 30px 40px;
    background: #f8f9fa;
    border-top: 1px solid #e0e0e0;
}

.nw-acknowledge-notice {
    background: #fff3cd;
    color: #856404;
    padding: 16px;
    border-radius: 8px;
    margin-bottom: 20px;
    font-weight: 500;
}

.nw-acknowledge-btn {
    width: 100%;
    padding: 16px;
    font-size: 18px;
}

.nw-acknowledge-done {
    display: flex;
    align-items: center;
    padding: 16px;
    background: #d4edda;
    color: #155724;
    border-radius: 8px;
    font-weight: 500;
}

.nw-view-admin-actions {
    margin-top: 20px;
    padding: 20px;
    background: #f8f9fa;
    border-radius: 8px;
    display: flex;
    gap: 12px;
}

/* Category colors */
.nw-category-update { background-color: #E3F2FD !important; color: #1565C0 !important; }
.nw-category-announcement { background-color: #E8F5E9 !important; color: #2E7D32 !important; }
.nw-category-maintenance { background-color: #FFF3E0 !important; color: #E65100 !important; }
.nw-category-policy-change { background-color: #F3E5F5 !important; color: #7B1FA2 !important; }

@media (max-width: 768px) {
    .nw-view-header, .nw-view-content, .nw-view-acknowledge {
        padding: 24px;
    }

    .nw-view-title {
        font-size: 1.5rem;
    }
}
</style>

<?php
echo $OUTPUT->footer();
