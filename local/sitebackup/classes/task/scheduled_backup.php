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

namespace local_sitebackup\task;

defined('MOODLE_INTERNAL') || die();

/**
 * Scheduled task: runs a full site backup daily.
 */
class scheduled_backup extends \core\task\scheduled_task {

    public function get_name(): string {
        return get_string('taskbackup', 'local_sitebackup');
    }

    public function execute(): void {
        mtrace('Starting scheduled site backup...');

        $manager = new \local_sitebackup\backup_manager();
        $success = $manager->run();

        if ($success) {
            mtrace('Scheduled site backup completed successfully.');
        } else {
            mtrace('Scheduled site backup failed. Check backup logs for details.');
        }
    }
}
