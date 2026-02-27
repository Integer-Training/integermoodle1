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
 * Ad-hoc task: runs a site backup in the background via cron.
 *
 * Queued by view.php when the admin clicks "Local Backup" or "Backup + Drive".
 * Cron picks it up and executes without blocking the browser.
 *
 * @package   local_sitebackup
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class run_backup extends \core\task\adhoc_task {

    public function execute(): void {
        global $DB;

        $data = $this->get_custom_data();
        $skipdrive = !empty($data->skipdrive);
        $logid = !empty($data->logid) ? (int) $data->logid : 0;

        mtrace('Ad-hoc backup task started (skipdrive=' . ($skipdrive ? 'yes' : 'no') . ', logid=' . $logid . ')');

        // If a pre-created log record exists, update its status from 'queued' to 'in_progress'.
        if ($logid && $DB->record_exists('local_sitebackup_logs', ['id' => $logid])) {
            $DB->set_field('local_sitebackup_logs', 'status', 'in_progress', ['id' => $logid]);
            $DB->set_field('local_sitebackup_logs', 'progress_step', 'Starting backup...', ['id' => $logid]);
        }

        try {
            $manager = new \local_sitebackup\backup_manager();
            $success = $manager->run($skipdrive, $logid);

            if ($success) {
                mtrace('Ad-hoc backup task completed successfully.');
            } else {
                mtrace('Ad-hoc backup task failed. Check backup logs.');
            }
        } catch (\Exception $e) {
            mtrace('Ad-hoc backup task exception: ' . $e->getMessage());
        }
    }
}
