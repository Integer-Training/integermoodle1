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

use local_errorlogger\logger;

/**
 * Scheduled task to aggregate failures from task_log and file_conversion tables.
 *
 * @package    local_errorlogger
 * @copyright  2026 Epearl Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class aggregate_errors extends \core\task\scheduled_task {

    /**
     * Get the task name.
     *
     * @return string
     */
    public function get_name(): string {
        return get_string('task_aggregate', 'local_errorlogger');
    }

    /**
     * Execute the task.
     */
    public function execute(): void {
        $this->aggregate_task_failures();
        $this->aggregate_conversion_failures();
    }

    /**
     * Pull failed cron tasks from task_log.
     */
    private function aggregate_task_failures(): void {
        global $DB;

        $lastcheck = (int) get_config('local_errorlogger', 'last_task_check');
        if ($lastcheck <= 0) {
            $lastcheck = time() - DAYSECS;
        }

        $failures = $DB->get_records_select(
            'task_log',
            'result = 1 AND timestart > :lastcheck',
            ['lastcheck' => $lastcheck],
            'timestart ASC',
            'id, component, classname, userid, timestart, output',
            0,
            500
        );

        $count = 0;
        foreach ($failures as $f) {
            logger::log([
                'type'      => 'task_failure',
                'severity'  => 2,
                'message'   => 'Cron task failed: ' . $f->classname,
                'component' => $f->component ?: '',
                'details'   => json_encode([
                    'classname'   => $f->classname,
                    'task_log_id' => $f->id,
                    'output'      => substr($f->output ?? '', 0, 5000),
                ], JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]);
            $count++;
        }

        set_config('last_task_check', time(), 'local_errorlogger');

        if ($count > 0) {
            mtrace("Error Logger: aggregated {$count} task failures.");
        }
    }

    /**
     * Pull failed file conversions from file_conversion.
     */
    private function aggregate_conversion_failures(): void {
        global $DB;

        $lastcheck = (int) get_config('local_errorlogger', 'last_conversion_check');
        if ($lastcheck <= 0) {
            $lastcheck = time() - DAYSECS;
        }

        $failures = $DB->get_records_select(
            'file_conversion',
            'status = -1 AND timemodified > :lastcheck',
            ['lastcheck' => $lastcheck],
            'timemodified ASC',
            'id, usermodified, timecreated, timemodified, sourcefileid, targetformat, statusmessage, converter',
            0,
            500
        );

        $count = 0;
        foreach ($failures as $f) {
            logger::log([
                'type'      => 'conversion_failure',
                'severity'  => 2,
                'message'   => 'File conversion failed' . ($f->converter ? ': ' . $f->converter : ''),
                'component' => 'core_files',
                'details'   => json_encode([
                    'conversion_id' => $f->id,
                    'sourcefileid'  => $f->sourcefileid,
                    'targetformat'  => $f->targetformat,
                    'statusmessage' => $f->statusmessage,
                    'converter'     => $f->converter,
                ], JSON_PARTIAL_OUTPUT_ON_ERROR),
            ]);
            $count++;
        }

        set_config('last_conversion_check', time(), 'local_errorlogger');

        if ($count > 0) {
            mtrace("Error Logger: aggregated {$count} conversion failures.");
        }
    }
}
