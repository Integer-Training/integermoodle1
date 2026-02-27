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
 * News management page.
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

$delete = optional_param('delete', 0, PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);
$category = optional_param('category', '', PARAM_ALPHA);

$PAGE->set_url(new moodle_url('/local/news/manage.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('newsmanagement', 'local_news'));
$PAGE->set_heading(get_string('newsmanagement', 'local_news'));
$PAGE->set_pagelayout('admin');

// Handle delete action.
if ($delete && confirm_sesskey()) {
    $news = \local_news\manager::get_news($delete);
    if (!$news) {
        throw new moodle_exception('error_newsnotfound', 'local_news');
    }

    if ($confirm) {
        \local_news\manager::delete_news($delete);
        redirect(new moodle_url('/local/news/manage.php'), get_string('newsdeleted', 'local_news'), null, \core\output\notification::NOTIFY_SUCCESS);
    }

    // Show confirmation page.
    echo $OUTPUT->header();
    echo $OUTPUT->confirm(
        get_string('confirmdeletenews', 'local_news') . '<br><br><strong>' . format_string($news->title) . '</strong>',
        new moodle_url('/local/news/manage.php', ['delete' => $delete, 'confirm' => 1, 'sesskey' => sesskey()]),
        new moodle_url('/local/news/manage.php')
    );
    echo $OUTPUT->footer();
    exit;
}

// Get all news items.
$newsitems = \local_news\manager::get_all_news($category);

// Prepare data for display.
$categories = \local_news\manager::get_categories();
foreach ($newsitems as $item) {
    $item->category_label = $categories[$item->category] ?? $item->category;
    $item->status_label = $item->published ? get_string('published', 'local_news') : get_string('draft', 'local_news');
    $item->status_class = $item->published ? 'badge-success' : 'badge-secondary';
    $item->formatted_date = userdate($item->timecreated, get_string('dateformat', 'local_news'));
    $item->author_name = fullname($item);

    // Get read stats.
    $stats = \local_news\manager::get_read_stats($item->id);
    $item->read_count = $stats->readcount;
    $item->total_users = $stats->totalusers;

    // URLs.
    $item->edit_url = (new moodle_url('/local/news/edit.php', ['id' => $item->id]))->out();
    $item->delete_url = (new moodle_url('/local/news/manage.php', ['delete' => $item->id, 'sesskey' => sesskey()]))->out();
    $item->stats_url = (new moodle_url('/local/news/stats.php', ['id' => $item->id]))->out();
    $item->view_url = (new moodle_url('/local/news/view.php', ['id' => $item->id]))->out();

    // Category color class.
    $item->category_class = 'nw-category-' . str_replace('_', '-', $item->category);
}

// Output page.
echo $OUTPUT->header();

// Add button.
$addurl = new moodle_url('/local/news/edit.php');
?>

<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">

<div class="nw-manage-container">
    <div class="nw-manage-header">
        <div class="nw-manage-header-content">
            <h2><i class="bi bi-megaphone me-2"></i><?php echo get_string('newsmanagement', 'local_news'); ?></h2>
            <p class="mb-0">Create and manage news items, announcements, and important updates</p>
        </div>
        <div class="nw-manage-actions">
            <a href="<?php echo $addurl->out(); ?>" class="btn btn-light">
                <i class="bi bi-plus-lg me-1"></i><?php echo get_string('addnews', 'local_news'); ?>
            </a>
        </div>
    </div>

    <div class="nw-manage-filters mb-3">
        <form method="get" action="" class="form-inline">
            <label class="me-2"><?php echo get_string('filterby', 'local_news'); ?>:</label>
            <select name="category" class="form-select form-select-sm" onchange="this.form.submit()">
                <option value=""><?php echo get_string('allcategories', 'local_news'); ?></option>
                <?php foreach ($categories as $key => $label): ?>
                    <option value="<?php echo $key; ?>" <?php echo $category === $key ? 'selected' : ''; ?>><?php echo $label; ?></option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>

    <?php if (empty($newsitems)): ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i><?php echo get_string('nonews', 'local_news'); ?>
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover nw-manage-table" id="newsTable">
                <thead>
                    <tr>
                        <th><?php echo get_string('title', 'local_news'); ?></th>
                        <th><?php echo get_string('category', 'local_news'); ?></th>
                        <th><?php echo get_string('status', 'local_news'); ?></th>
                        <th><?php echo get_string('views', 'local_news'); ?></th>
                        <th><?php echo get_string('actions', 'local_news'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($newsitems as $item): ?>
                        <tr>
                            <td>
                                <a href="<?php echo $item->view_url; ?>" class="nw-title-link">
                                    <?php if ($item->important): ?>
                                        <i class="bi bi-exclamation-triangle-fill text-danger me-1" title="Important"></i>
                                    <?php endif; ?>
                                    <?php echo format_string($item->title); ?>
                                </a>
                                <div class="small text-muted">
                                    <?php echo $item->formatted_date; ?> &bull; <?php echo $item->author_name; ?>
                                </div>
                            </td>
                            <td>
                                <span class="badge <?php echo $item->category_class; ?>">
                                    <?php echo $item->category_label; ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?php echo $item->status_class; ?>">
                                    <?php echo $item->status_label; ?>
                                </span>
                            </td>
                            <td>
                                <span class="nw-view-count"><?php echo $item->read_count; ?>/<?php echo $item->total_users; ?></span>
                            </td>
                            <td class="nw-actions">
                                <a href="<?php echo $item->edit_url; ?>" class="btn btn-sm btn-outline-primary" title="<?php echo get_string('edit', 'local_news'); ?>">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="<?php echo $item->stats_url; ?>" class="btn btn-sm btn-outline-info" title="<?php echo get_string('viewstats', 'local_news'); ?>">
                                    <i class="bi bi-bar-chart"></i>
                                </a>
                                <a href="<?php echo $item->delete_url; ?>" class="btn btn-sm btn-outline-danger" title="<?php echo get_string('delete', 'local_news'); ?>">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<style>
.nw-manage-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.nw-manage-header {
    background: linear-gradient(135deg, #1a2238 0%, #3a5ba0 100%);
    color: #fff !important;
    padding: 40px;
    border-radius: 12px;
    margin-bottom: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nw-manage-header,
.nw-manage-header h2,
.nw-manage-header h2 i,
.nw-manage-header p,
.nw-manage-header-content h2,
.nw-manage-header-content p {
    color: #fff !important;
}

.nw-manage-header-content h2 {
    margin: 0 0 8px;
    font-family: 'Libre Baskerville', serif;
}

.nw-manage-header-content p {
    opacity: 0.9;
}

.nw-manage-filters {
    background: #f8f9fa;
    padding: 16px 20px;
    border-radius: 8px;
}

.nw-manage-table th {
    background: #f8f9fa;
    font-weight: 600;
}

.nw-title-link {
    font-weight: 500;
    color: #1a2238;
    text-decoration: none;
}

.nw-title-link:hover {
    color: #3a5ba0;
}

.nw-actions .btn {
    margin-right: 4px;
    padding: 6px 10px;
}

.nw-actions .btn i {
    font-size: 14px;
}

.nw-category-update { background-color: #E3F2FD !important; color: #1565C0 !important; }
.nw-category-announcement { background-color: #E8F5E9 !important; color: #2E7D32 !important; }
.nw-category-maintenance { background-color: #FFF3E0 !important; color: #E65100 !important; }
.nw-category-policy-change { background-color: #F3E5F5 !important; color: #7B1FA2 !important; }

.badge-success { background-color: #2E7D32 !important; color: white !important; }
.badge-secondary { background-color: #6c757d !important; color: white !important; }

@media (max-width: 768px) {
    .nw-manage-header {
        flex-direction: column;
        text-align: center;
        padding: 30px 20px;
    }
    .nw-manage-actions {
        margin-top: 16px;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#newsTable').DataTable({
            pageLength: 25,
            order: [[0, 'desc']],
            language: {
                search: "Search:",
                lengthMenu: "Show _MENU_ entries"
            }
        });
    }
});
</script>

<?php
echo $OUTPUT->footer();
