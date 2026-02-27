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
 * News manager class - handles all business logic.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_news;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager class for news items CRUD and related operations.
 */
class manager {

    /** @var string Category constant for updates */
    const CATEGORY_UPDATE = 'update';

    /** @var string Category constant for announcements */
    const CATEGORY_ANNOUNCEMENT = 'announcement';

    /** @var string Category constant for maintenance */
    const CATEGORY_MAINTENANCE = 'maintenance';

    /** @var string Category constant for policy changes */
    const CATEGORY_POLICY = 'policy_change';

    /**
     * Get all available categories.
     *
     * @return array Category key => label pairs
     */
    public static function get_categories(): array {
        return [
            self::CATEGORY_UPDATE => get_string('category_update', 'local_news'),
            self::CATEGORY_ANNOUNCEMENT => get_string('category_announcement', 'local_news'),
            self::CATEGORY_MAINTENANCE => get_string('category_maintenance', 'local_news'),
            self::CATEGORY_POLICY => get_string('category_policy_change', 'local_news'),
        ];
    }

    /**
     * Get category label for a given category key.
     *
     * @param string $category Category key
     * @return string Category label
     */
    public static function get_category_label(string $category): string {
        $categories = self::get_categories();
        return $categories[$category] ?? $category;
    }

    /**
     * Create a new news item.
     *
     * @param object $data News item data
     * @return int The new news item ID
     */
    public static function create_news(object $data): int {
        global $DB, $USER;

        $now = time();
        $record = new \stdClass();
        $record->title = $data->title;
        $record->content = $data->content;
        $record->contentformat = $data->contentformat ?? FORMAT_HTML;
        $record->category = $data->category ?? self::CATEGORY_ANNOUNCEMENT;
        $record->important = !empty($data->important) ? 1 : 0;
        $record->requires_acknowledgement = !empty($data->requires_acknowledgement) ? 1 : 0;
        $record->published = !empty($data->published) ? 1 : 0;
        $record->publishdate = $data->publishdate ?? $now;
        $record->expirydate = !empty($data->expirydate) ? $data->expirydate : null;
        $record->createdby = $USER->id;
        $record->timecreated = $now;
        $record->timemodified = $now;

        return $DB->insert_record('local_news', $record);
    }

    /**
     * Update an existing news item.
     *
     * @param int $id News item ID
     * @param object $data Updated data
     * @return bool Success status
     */
    public static function update_news(int $id, object $data): bool {
        global $DB, $USER;

        $record = $DB->get_record('local_news', ['id' => $id]);
        if (!$record) {
            return false;
        }

        $record->title = $data->title;
        $record->content = $data->content;
        $record->contentformat = $data->contentformat ?? FORMAT_HTML;
        $record->category = $data->category ?? self::CATEGORY_ANNOUNCEMENT;
        $record->important = !empty($data->important) ? 1 : 0;
        $record->requires_acknowledgement = !empty($data->requires_acknowledgement) ? 1 : 0;
        $record->published = !empty($data->published) ? 1 : 0;
        $record->publishdate = $data->publishdate ?? $record->publishdate;
        $record->expirydate = !empty($data->expirydate) ? $data->expirydate : null;
        $record->modifiedby = $USER->id;
        $record->timemodified = time();

        return $DB->update_record('local_news', $record);
    }

    /**
     * Delete a news item and associated read records.
     *
     * @param int $id News item ID
     * @return bool Success status
     */
    public static function delete_news(int $id): bool {
        global $DB;

        // Delete read tracking records first.
        $DB->delete_records('local_news_read', ['newsid' => $id]);
        $DB->delete_records('local_news_notifications', ['newsid' => $id]);

        return $DB->delete_records('local_news', ['id' => $id]);
    }

    /**
     * Get a single news item by ID.
     *
     * @param int $id News item ID
     * @return object|null News item or null
     */
    public static function get_news(int $id): ?object {
        global $DB;

        $record = $DB->get_record('local_news', ['id' => $id]);
        return $record ?: null;
    }

    /**
     * Get all news items for admin management.
     *
     * @param string $category Optional category filter
     * @param bool $publishedonly Only published items
     * @return array News items
     */
    public static function get_all_news(string $category = '', bool $publishedonly = false): array {
        global $DB;

        $params = [];
        $where = [];

        if (!empty($category)) {
            $where[] = 'n.category = :category';
            $params['category'] = $category;
        }

        if ($publishedonly) {
            $where[] = 'n.published = 1';
        }

        $wheresql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

        $sql = "SELECT n.*, u.firstname, u.lastname
                FROM {local_news} n
                LEFT JOIN {user} u ON u.id = n.createdby
                $wheresql
                ORDER BY n.timecreated DESC";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get published news items visible to users.
     *
     * @param int $limit Maximum number of items (0 = unlimited)
     * @param string $category Optional category filter
     * @return array Published news items
     */
    public static function get_published_news(int $limit = 0, string $category = ''): array {
        global $DB;

        $now = time();
        $params = ['now1' => $now, 'now2' => $now];
        $where = [
            'n.published = 1',
            'n.publishdate <= :now1',
            '(n.expirydate IS NULL OR n.expirydate > :now2)',
        ];

        if (!empty($category)) {
            $where[] = 'n.category = :category';
            $params['category'] = $category;
        }

        $wheresql = 'WHERE ' . implode(' AND ', $where);

        $sql = "SELECT n.*, u.firstname, u.lastname
                FROM {local_news} n
                LEFT JOIN {user} u ON u.id = n.createdby
                $wheresql
                ORDER BY n.publishdate DESC";

        if ($limit > 0) {
            return $DB->get_records_sql($sql, $params, 0, $limit);
        }

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Get recent news for sidebar widget.
     *
     * @param int $limit Number of items to return
     * @return array Recent news items
     */
    public static function get_recent_for_widget(int $limit = 5): array {
        return self::get_published_news($limit);
    }

    /**
     * Get unread important news requiring acknowledgement for a user.
     *
     * @param int $userid User ID
     * @return array Unread important news items
     */
    public static function get_unread_important_for_user(int $userid): array {
        global $DB;

        $now = time();
        $sql = "SELECT n.*
                FROM {local_news} n
                LEFT JOIN {local_news_read} nr ON nr.newsid = n.id AND nr.userid = :userid
                WHERE n.published = 1
                AND n.important = 1
                AND n.requires_acknowledgement = 1
                AND n.publishdate <= :now1
                AND (n.expirydate IS NULL OR n.expirydate > :now2)
                AND (nr.id IS NULL OR nr.acknowledged = 0)
                ORDER BY n.publishdate DESC";

        return $DB->get_records_sql($sql, [
            'userid' => $userid,
            'now1' => $now,
            'now2' => $now,
        ]);
    }

    /**
     * Mark a news item as read by a user.
     *
     * @param int $newsid News item ID
     * @param int $userid User ID
     * @return bool Success status
     */
    public static function mark_as_read(int $newsid, int $userid): bool {
        global $DB;

        $existing = $DB->get_record('local_news_read', [
            'newsid' => $newsid,
            'userid' => $userid,
        ]);

        if ($existing) {
            return true; // Already marked as read.
        }

        $record = new \stdClass();
        $record->newsid = $newsid;
        $record->userid = $userid;
        $record->timeread = time();
        $record->acknowledged = 0;

        return (bool) $DB->insert_record('local_news_read', $record);
    }

    /**
     * Mark a news item as acknowledged by a user.
     *
     * @param int $newsid News item ID
     * @param int $userid User ID
     * @return bool Success status
     */
    public static function mark_as_acknowledged(int $newsid, int $userid): bool {
        global $DB;

        $existing = $DB->get_record('local_news_read', [
            'newsid' => $newsid,
            'userid' => $userid,
        ]);

        $now = time();

        if ($existing) {
            $existing->acknowledged = 1;
            $existing->timeacknowledged = $now;
            return $DB->update_record('local_news_read', $existing);
        }

        $record = new \stdClass();
        $record->newsid = $newsid;
        $record->userid = $userid;
        $record->timeread = $now;
        $record->acknowledged = 1;
        $record->timeacknowledged = $now;

        return (bool) $DB->insert_record('local_news_read', $record);
    }

    /**
     * Check if a news item has been read by a user.
     *
     * @param int $newsid News item ID
     * @param int $userid User ID
     * @return bool True if read
     */
    public static function is_read_by_user(int $newsid, int $userid): bool {
        global $DB;

        return $DB->record_exists('local_news_read', [
            'newsid' => $newsid,
            'userid' => $userid,
        ]);
    }

    /**
     * Check if a news item has been acknowledged by a user.
     *
     * @param int $newsid News item ID
     * @param int $userid User ID
     * @return bool True if acknowledged
     */
    public static function is_acknowledged_by_user(int $newsid, int $userid): bool {
        global $DB;

        return $DB->record_exists('local_news_read', [
            'newsid' => $newsid,
            'userid' => $userid,
            'acknowledged' => 1,
        ]);
    }

    /**
     * Get the read record for a user.
     *
     * @param int $newsid News item ID
     * @param int $userid User ID
     * @return object|null Read record or null
     */
    public static function get_user_read_status(int $newsid, int $userid): ?object {
        global $DB;

        $record = $DB->get_record('local_news_read', [
            'newsid' => $newsid,
            'userid' => $userid,
        ]);

        return $record ?: null;
    }

    /**
     * Get read statistics for a news item.
     *
     * @param int $newsid News item ID
     * @return object Statistics object with totalusers, readcount, acknowledgedcount
     */
    public static function get_read_stats(int $newsid): object {
        global $DB;

        // Count total active users.
        $totalusers = $DB->count_records_select('user', 'deleted = 0 AND suspended = 0 AND id > 1');

        // Count users who have read.
        $readcount = $DB->count_records('local_news_read', ['newsid' => $newsid]);

        // Count users who have acknowledged.
        $acknowledgedcount = $DB->count_records('local_news_read', [
            'newsid' => $newsid,
            'acknowledged' => 1,
        ]);

        $stats = new \stdClass();
        $stats->totalusers = $totalusers;
        $stats->readcount = $readcount;
        $stats->acknowledgedcount = $acknowledgedcount;
        $stats->readpercent = $totalusers > 0 ? round(($readcount / $totalusers) * 100) : 0;
        $stats->acknowledgedpercent = $totalusers > 0 ? round(($acknowledgedcount / $totalusers) * 100) : 0;

        return $stats;
    }

    /**
     * Get detailed read records for a news item.
     *
     * @param int $newsid News item ID
     * @return array Read records with user details
     */
    public static function get_read_details(int $newsid): array {
        global $DB;

        $sql = "SELECT nr.*, u.id as userid, u.firstname, u.lastname, u.email
                FROM {local_news_read} nr
                JOIN {user} u ON u.id = nr.userid
                WHERE nr.newsid = :newsid
                ORDER BY nr.timeread DESC";

        return $DB->get_records_sql($sql, ['newsid' => $newsid]);
    }

    /**
     * Get users who haven't read a news item.
     *
     * @param int $newsid News item ID
     * @return array User records
     */
    public static function get_unread_users(int $newsid): array {
        global $DB;

        $sql = "SELECT u.id, u.firstname, u.lastname, u.email
                FROM {user} u
                WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 1
                AND NOT EXISTS (
                    SELECT 1 FROM {local_news_read} nr
                    WHERE nr.newsid = :newsid AND nr.userid = u.id
                )
                ORDER BY u.lastname, u.firstname";

        return $DB->get_records_sql($sql, ['newsid' => $newsid]);
    }

    /**
     * Send email notification for important news.
     *
     * @param int $newsid News item ID
     * @return int Number of notifications sent
     */
    public static function send_important_notification(int $newsid): int {
        global $DB, $CFG;

        $news = self::get_news($newsid);
        if (!$news || !$news->important || !$news->published) {
            return 0;
        }

        $viewurl = new \moodle_url('/local/news/view.php', ['id' => $newsid]);

        // Get users who haven't been notified yet.
        $sql = "SELECT u.id, u.firstname, u.lastname, u.email
                FROM {user} u
                WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 1
                AND NOT EXISTS (
                    SELECT 1 FROM {local_news_notifications} nn
                    WHERE nn.newsid = :newsid AND nn.userid = u.id
                )";

        $users = $DB->get_records_sql($sql, ['newsid' => $newsid]);

        $count = 0;
        $admin = get_admin();

        foreach ($users as $user) {
            // Prepare message data.
            $messagedata = new \stdClass();
            $messagedata->title = $news->title;
            $messagedata->content = strip_tags($news->content);
            $messagedata->url = $viewurl->out(false);

            // Create message.
            $message = new \core\message\message();
            $message->component = 'local_news';
            $message->name = 'importantnews';
            $message->userfrom = $admin;
            $message->userto = $user;
            $message->subject = get_string('notification_subject', 'local_news', $messagedata);
            $message->fullmessage = get_string('notification_body', 'local_news', $messagedata);
            $message->fullmessageformat = FORMAT_PLAIN;

            $htmldata = new \stdClass();
            $htmldata->title = $news->title;
            $htmldata->content = $news->content;
            $htmldata->url = $viewurl->out(false);
            $message->fullmessagehtml = get_string('notification_html', 'local_news', $htmldata);

            $message->smallmessage = $news->title;
            $message->contexturl = $viewurl->out(false);
            $message->contexturlname = $news->title;

            // Send message.
            $status = 'sent';
            try {
                message_send($message);
                $count++;
            } catch (\Exception $e) {
                $status = 'failed';
            }

            // Log the notification.
            $log = new \stdClass();
            $log->newsid = $newsid;
            $log->userid = $user->id;
            $log->timesent = time();
            $log->status = $status;
            $DB->insert_record('local_news_notifications', $log);
        }

        return $count;
    }

    /**
     * Count unread news for a user (for sidebar badge).
     *
     * @param int $userid User ID
     * @return int Count of unread news
     */
    public static function count_unread_for_user(int $userid): int {
        global $DB;

        $now = time();
        $sql = "SELECT COUNT(n.id)
                FROM {local_news} n
                LEFT JOIN {local_news_read} nr ON nr.newsid = n.id AND nr.userid = :userid
                WHERE n.published = 1
                AND n.publishdate <= :now1
                AND (n.expirydate IS NULL OR n.expirydate > :now2)
                AND nr.id IS NULL";

        return $DB->count_records_sql($sql, [
            'userid' => $userid,
            'now1' => $now,
            'now2' => $now,
        ]);
    }
}
