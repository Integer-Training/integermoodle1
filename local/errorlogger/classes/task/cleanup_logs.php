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

namespace local_errorlogger\task;

/**
 * Scheduled task to clean up old error log entries.
 *
 * @package    local_errorlogger
 * @copyright  2026 Epearl Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class cleanup_logs extends \core\task\scheduled_task {

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_cleanup', 'local_errorlogger');
    }

    /**
     * Execute the task.
     */
    public function execute(): void {
        global $DB;

        $days = (int) get_config('local_errorlogger', 'retention_days');
        if ($days <= 0) {
            $days = 30;
        }

        $cutoff = time() - ($days * DAYSECS);
        $DB->delete_records_select(
            'local_errorlogger_logs',
            'timecreated < :cutoff',
            ['cutoff' => $cutoff]
        );

        mtrace("Error Logger: cleaned up records older than {$days} days.");
    }
}
