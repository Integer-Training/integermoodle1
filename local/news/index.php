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
 * Public news listing page.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

require_login();

$context = context_system::instance();
require_capability('local/news:view', $context);

$category = optional_param('category', '', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);

$PAGE->set_url(new moodle_url('/local/news/index.php', ['category' => $category]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('news', 'local_news'));
$PAGE->set_heading(get_string('news', 'local_news'));
$PAGE->set_pagelayout('standard');

// Get published news.
$newsitems = \local_news\manager::get_published_news(0, $category);

// Add read status and format for each item.
$categories = \local_news\manager::get_categories();
foreach ($newsitems as $item) {
    $item->is_read = \local_news\manager::is_read_by_user($item->id, $USER->id);
    $item->is_acknowledged = \local_news\manager::is_acknowledged_by_user($item->id, $USER->id);
    $item->category_label = $categories[$item->category] ?? $item->category;
    $item->formatted_date = userdate($item->publishdate, get_string('dateformat', 'local_news'));
    $item->view_url = (new moodle_url('/local/news/view.php', ['id' => $item->id]))->out();
    $item->excerpt = shorten_text(strip_tags($item->content), 200);
    $item->category_class = 'nw-category-' . str_replace('_', '-', $item->category);
    $item->author_name = fullname($item);
}

$canmanage = has_capability('local/news:manage', $context);
$manageurl = new moodle_url('/local/news/manage.php');

// Output page.
echo $OUTPUT->header();
?>

<div class="nw-public-container">
    <!-- Header -->
    <div class="nw-public-header">
        <div class="nw-public-header-content">
            <h1><i class="bi bi-megaphone me-2"></i><?php echo get_string('news', 'local_news'); ?></h1>
            <p class="lead"><?php echo get_string('stayinformed', 'local_news'); ?></p>
        </div>
        <?php if ($canmanage): ?>
            <div class="nw-public-header-actions">
                <a href="<?php echo $manageurl->out(); ?>" class="btn btn-outline-light">
                    <i class="bi bi-gear me-1"></i><?php echo get_string('newsmanagement', 'local_news'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Filter -->
    <div class="nw-filter-bar">
        <form method="get" action="" class="form-inline">
            <label class="me-2"><?php echo get_string('filterby', 'local_news'); ?>:</label>
            <select name="category" class="form-select" onchange="this.form.submit()">
                <option value=""><?php echo get_string('allcategories', 'local_news'); ?></option>
                <?php foreach ($categories as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $category === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <!-- News List -->
    <?php if (empty($newsitems)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i><?php echo get_string('nonews', 'local_news'); ?>
        </div>
    <?php else: ?>
        <div class="nw-news-list">
            <?php foreach ($newsitems as $item): ?>
                <div class="nw-news-card <?php echo !$item->is_read ? 'nw-unread' : ''; ?>">
                    <div class="nw-news-card-header">
                        <span class="badge <?php echo $item->category_class; ?>"><?php echo $item->category_label; ?></span>
                        <span class="nw-news-date"><?php echo $item->formatted_date; ?></span>
                    </div>

                    <h3 class="nw-news-title">
                        <?php if ($item->important): ?>
                            <i class="bi bi-exclamation-triangle-fill text-danger me-1" title="Important"></i>
                        <?php endif; ?>
                        <a href="<?php echo $item->view_url; ?>"><?php echo format_string($item->title); ?></a>
                    </h3>

                    <div class="nw-news-status">
                        <?php if (!$item->is_read): ?>
                            <span class="badge bg-primary"><i class="bi bi-circle-fill me-1"></i><?php echo get_string('unread', 'local_news'); ?></span>
                        <?php elseif ($item->requires_acknowledgement && !$item->is_acknowledged): ?>
                            <span class="badge bg-warning text-dark"><i class="bi bi-exclamation-circle me-1"></i><?php echo get_string('pendingacknowledgement', 'local_news'); ?></span>
                        <?php elseif ($item->is_acknowledged): ?>
                            <span class="badge bg-success"><i class="bi bi-check-circle me-1"></i><?php echo get_string('acknowledged', 'local_news'); ?></span>
                        <?php else: ?>
                            <span class="badge bg-secondary"><i class="bi bi-check me-1"></i><?php echo get_string('read', 'local_news'); ?></span>
                        <?php endif; ?>
                    </div>

                    <p class="nw-news-excerpt"><?php echo $item->excerpt; ?></p>

                    <div class="nw-news-footer">
                        <a href="<?php echo $item->view_url; ?>" class="btn btn-sm btn-outline-primary">
                            <?php echo get_string('readmore', 'local_news'); ?> <i class="bi bi-arrow-right"></i>
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.nw-public-container {
    max-width: 900px;
    margin: 0 auto;
    padding: 0 20px 40px;
}

.nw-public-header {
    background: linear-gradient(135deg, #1a2238 0%, #3a5ba0 100%);
    color: #fff !important;
    padding: 40px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nw-public-header h1,
.nw-public-header h1 i,
.nw-public-header p,
.nw-public-header .lead {
    color: #fff !important;
}

.nw-public-header h1 {
    font-family: 'Libre Baskerville', serif;
    margin-bottom: 8px;
}

.nw-public-header .lead {
    opacity: 0.9;
    margin-bottom: 0;
}

.nw-filter-bar {
    background: #f8f9fa;
    padding: 16px 20px;
    border-radius: 8px;
    margin-bottom: 24px;
}

.nw-filter-bar .form-select {
    width: auto;
    min-width: 200px;
}

.nw-news-list {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.nw-news-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 12px;
    padding: 24px;
    transition: box-shadow 0.2s, border-color 0.2s;
}

.nw-news-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #ccc;
}

.nw-news-card.nw-unread {
    border-left: 4px solid #1976D2;
}

.nw-news-card-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
}

.nw-news-date {
    color: #666;
    font-size: 14px;
}

.nw-news-title {
    font-family: 'Libre Baskerville', serif;
    font-size: 1.25rem;
    margin-bottom: 8px;
}

.nw-news-title a {
    color: #1a2238;
    text-decoration: none;
}

.nw-news-title a:hover {
    color: #3a5ba0;
}

.nw-news-status {
    margin-bottom: 12px;
}

.nw-news-excerpt {
    color: #555;
    line-height: 1.6;
    margin-bottom: 16px;
}

.nw-news-footer {
    padding-top: 12px;
    border-top: 1px solid #eee;
}

/* Category colors */
.nw-category-update { background-color: #E3F2FD !important; color: #1565C0 !important; }
.nw-category-announcement { background-color: #E8F5E9 !important; color: #2E7D32 !important; }
.nw-category-maintenance { background-color: #FFF3E0 !important; color: #E65100 !important; }
.nw-category-policy-change { background-color: #F3E5F5 !important; color: #7B1FA2 !important; }

@media (max-width: 768px) {
    .nw-public-header {
        flex-direction: column;
        text-align: center;
    }

    .nw-public-header-actions {
        margin-top: 16px;
    }
}
</style>

<?php
echo $OUTPUT->footer();
