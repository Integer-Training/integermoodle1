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
 * Scheduled task to send important news notifications.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_news\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Send notifications task.
 */
class send_notifications extends \core\task\scheduled_task {

    /**
     * Get task name.
     *
     * @return string Task name
     */
    public function get_name() {
        return get_string('task_send_notifications', 'local_news');
    }

    /**
     * Execute the task.
     */
    public function execute() {
        global $DB;

        $now = time();

        // Find important published news that hasn't been fully notified.
        // We check for news where at least one user hasn't been notified yet.
        $sql = "SELECT DISTINCT n.*
                FROM {local_news} n
                WHERE n.important = 1
                AND n.published = 1
                AND n.publishdate <= :now1
                AND (n.expirydate IS NULL OR n.expirydate > :now2)
                AND EXISTS (
                    SELECT 1 FROM {user} u
                    WHERE u.deleted = 0 AND u.suspended = 0 AND u.id > 1
                    AND NOT EXISTS (
                        SELECT 1 FROM {local_news_notifications} nn
                        WHERE nn.newsid = n.id AND nn.userid = u.id
                    )
                )";

        $newsitems = $DB->get_records_sql($sql, ['now1' => $now, 'now2' => $now]);

        $totalcount = 0;

        foreach ($newsitems as $news) {
            mtrace("Sending notifications for news: {$news->title} (ID: {$news->id})");

            $count = \local_news\manager::send_important_notification($news->id);
            $totalcount += $count;

            mtrace("  Sent {$count} notifications.");
        }

        mtrace("Total notifications sent: {$totalcount}");
    }
}
