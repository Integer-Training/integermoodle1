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
 * Scheduled task — sync learner payment data hourly.
 *
 * @package   local_pendingregistration
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pendingregistration\task;

defined('MOODLE_INTERNAL') || die();

class sync_learners extends \core\task\scheduled_task {

    public function get_name() {
        return get_string('sync_learners', 'local_pendingregistration');
    }

    public function execute() {
        $manager = new \local_pendingregistration\sync_manager();
        $result = $manager->sync_all();
        mtrace("Pending Registration sync: {$result['synced']} synced, {$result['skipped']} skipped.");
    }
}
