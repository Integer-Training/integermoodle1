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
 * AJAX endpoint for news actions.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');

require_login();
require_sesskey();

header('Content-Type: application/json');

$action = required_param('action', PARAM_ALPHA);
$newsid = required_param('newsid', PARAM_INT);

$context = context_system::instance();

// Check basic view capability.
if (!has_capability('local/news:view', $context)) {
    echo json_encode(['success' => false, 'error' => get_string('error_nopermission', 'local_news')]);
    exit;
}

$result = ['success' => false];

try {
    switch ($action) {
        case 'markread':
            $result['success'] = \local_news\manager::mark_as_read($newsid, $USER->id);
            break;

        case 'acknowledge':
            $result['success'] = \local_news\manager::mark_as_acknowledged($newsid, $USER->id);
            if ($result['success']) {
                $result['message'] = get_string('acknowledgementrecorded', 'local_news');
            }
            break;

        case 'getnews':
            $news = \local_news\manager::get_news($newsid);
            if ($news) {
                $result['success'] = true;
                $result['news'] = [
                    'id' => $news->id,
                    'title' => format_string($news->title),
                    'content' => format_text($news->content, $news->contentformat),
                    'category' => $news->category,
                    'category_label' => \local_news\manager::get_category_label($news->category),
                    'important' => (bool) $news->important,
                    'requires_acknowledgement' => (bool) $news->requires_acknowledgement,
                    'publishdate' => userdate($news->publishdate, get_string('dateformat', 'local_news')),
                ];
            } else {
                $result['error'] = get_string('error_newsnotfound', 'local_news');
            }
            break;

        default:
            $result['error'] = 'Unknown action';
    }
} catch (Exception $e) {
    $result['success'] = false;
    $result['error'] = $e->getMessage();
}

echo json_encode($result);
