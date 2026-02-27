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
 * News read statistics page.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->libdir . '/adminlib.php');

require_login();

$context = context_system::instance();
require_capability('local/news:viewstats', $context);

$id = required_param('id', PARAM_INT);
$export = optional_param('export', '', PARAM_ALPHA);

$news = \local_news\manager::get_news($id);
if (!$news) {
    throw new moodle_exception('error_newsnotfound', 'local_news');
}

$PAGE->set_url(new moodle_url('/local/news/stats.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('readstatistics', 'local_news'));
$PAGE->set_heading(get_string('readstatistics', 'local_news'));
$PAGE->set_pagelayout('admin');

// Get statistics.
$stats = \local_news\manager::get_read_stats($id);
$readdetails = \local_news\manager::get_read_details($id);
$unreadusers = \local_news\manager::get_unread_users($id);

// Handle CSV export.
if ($export === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="news_stats_' . $id . '.csv"');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['User', 'Email', 'Read At', 'Acknowledged', 'Acknowledged At']);

    foreach ($readdetails as $detail) {
        fputcsv($output, [
            fullname($detail),
            $detail->email,
            userdate($detail->timeread, get_string('datetimeformat', 'local_news')),
            $detail->acknowledged ? 'Yes' : 'No',
            $detail->timeacknowledged ? userdate($detail->timeacknowledged, get_string('datetimeformat', 'local_news')) : '-',
        ]);
    }

    foreach ($unreadusers as $user) {
        fputcsv($output, [
            fullname($user),
            $user->email,
            '-',
            'No',
            '-',
        ]);
    }

    fclose($output);
    exit;
}

// Output page.
echo $OUTPUT->header();
?>

<div class="nw-stats-container">
    <div class="nw-stats-header">
        <a href="<?php echo (new moodle_url('/local/news/manage.php'))->out(); ?>" class="btn btn-link">
            <i class="bi bi-arrow-left me-1"></i><?php echo get_string('backtonews', 'local_news'); ?>
        </a>
    </div>

    <div class="nw-stats-title">
        <h3><i class="bi bi-bar-chart me-2"></i><?php echo get_string('readstatistics', 'local_news'); ?></h3>
        <h4 class="text-muted"><?php echo format_string($news->title); ?></h4>
    </div>

    <!-- Overview Cards -->
    <div class="nw-stats-overview">
        <div class="row">
            <div class="col-md-4">
                <div class="card nw-stat-card">
                    <div class="card-body text-center">
                        <h5 class="card-title"><?php echo get_string('totalusers', 'local_news'); ?></h5>
                        <p class="nw-stat-number"><?php echo $stats->totalusers; ?></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card nw-stat-card nw-stat-read">
                    <div class="card-body text-center">
                        <h5 class="card-title"><?php echo get_string('readcount', 'local_news'); ?></h5>
                        <p class="nw-stat-number"><?php echo $stats->readcount; ?> <span class="nw-stat-percent">(<?php echo $stats->readpercent; ?>%)</span></p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card nw-stat-card nw-stat-ack">
                    <div class="card-body text-center">
                        <h5 class="card-title"><?php echo get_string('acknowledgedcount', 'local_news'); ?></h5>
                        <p class="nw-stat-number"><?php echo $stats->acknowledgedcount; ?> <span class="nw-stat-percent">(<?php echo $stats->acknowledgedpercent; ?>%)</span></p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Details Table -->
    <div class="nw-stats-details mt-4">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h5><i class="bi bi-people me-2"></i><?php echo get_string('userdetails', 'local_news'); ?></h5>
            <a href="<?php echo (new moodle_url('/local/news/stats.php', ['id' => $id, 'export' => 'csv']))->out(); ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download me-1"></i><?php echo get_string('exportcsv', 'local_news'); ?>
            </a>
        </div>

        <div class="table-responsive">
            <table class="table table-striped table-hover" id="statsTable">
                <thead>
                    <tr>
                        <th>User</th>
                        <th><?php echo get_string('readat', 'local_news'); ?></th>
                        <th><?php echo get_string('acknowledgedat', 'local_news'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($readdetails as $detail): ?>
                        <tr>
                            <td>
                                <strong><?php echo fullname($detail); ?></strong>
                                <div class="small text-muted"><?php echo $detail->email; ?></div>
                            </td>
                            <td>
                                <span class="badge bg-success"><?php echo userdate($detail->timeread, get_string('datetimeformat', 'local_news')); ?></span>
                            </td>
                            <td>
                                <?php if ($detail->acknowledged): ?>
                                    <span class="badge bg-primary"><?php echo userdate($detail->timeacknowledged, get_string('datetimeformat', 'local_news')); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo get_string('notacknowledged', 'local_news'); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php foreach ($unreadusers as $user): ?>
                        <tr class="table-warning">
                            <td>
                                <strong><?php echo fullname($user); ?></strong>
                                <div class="small text-muted"><?php echo $user->email; ?></div>
                            </td>
                            <td>
                                <span class="badge bg-warning text-dark"><?php echo get_string('notread', 'local_news'); ?></span>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo get_string('notacknowledged', 'local_news'); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.nw-stats-container {
    max-width: 1200px;
    margin: 0 auto;
    padding: 20px;
}

.nw-stats-title {
    margin-bottom: 24px;
    padding-bottom: 16px;
    border-bottom: 1px solid #e0e0e0;
}

.nw-stats-title h3 {
    font-family: 'Libre Baskerville', serif;
    color: #1a2238;
    margin-bottom: 8px;
}

.nw-stat-card {
    border: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    border-radius: 8px;
}

.nw-stat-card .card-title {
    color: #666;
    font-size: 14px;
    text-transform: uppercase;
}

.nw-stat-number {
    font-size: 36px;
    font-weight: 700;
    color: #1a2238;
    margin: 0;
}

.nw-stat-percent {
    font-size: 18px;
    font-weight: normal;
    color: #666;
}

.nw-stat-read {
    border-left: 4px solid #2E7D32;
}

.nw-stat-ack {
    border-left: 4px solid #1565C0;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    if (typeof $.fn.DataTable !== 'undefined') {
        $('#statsTable').DataTable({
            pageLength: 50,
            order: [[1, 'desc']],
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
