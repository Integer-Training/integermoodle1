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

namespace local_sequentialsubmission\task;

defined('MOODLE_INTERNAL') || die();

use core\task\scheduled_task;
use local_sequentialsubmission\lock_checker;

/**
 * Hourly housekeeping — deactivates bypass rows that have either exceeded
 * their TTL or whose blocking submission has been marked Passed.
 *
 * The lock_checker::has_active_bypass() function already consumes bypasses
 * on read when it detects the blocker is now Pass, so this task is mostly
 * for TTL cleanup and audit accuracy.
 *
 * @package local_sequentialsubmission
 */
class expire_bypasses extends scheduled_task {

    public function get_name() {
        return get_string('pluginname', 'local_sequentialsubmission') . ' — Expire Bypasses';
    }

    public function execute() {
        global $DB;

        $now = time();

        // TTL expiries.
        $ttlexpired = $DB->count_records_select('local_ss_bypass', 'active = 1 AND expiresat <= :now', ['now' => $now]);
        if ($ttlexpired > 0) {
            $DB->execute(
                'UPDATE {local_ss_bypass} SET active = 0 WHERE active = 1 AND expiresat <= :now',
                ['now' => $now]
            );
            mtrace("[local_sequentialsubmission] expire_bypasses: deactivated {$ttlexpired} TTL-expired bypasses");
        }

        // Blocker-resolved expiries: walk active bypasses, check grade state.
        $rows = $DB->get_records_select('local_ss_bypass', 'active = 1 AND blocking_cmid IS NOT NULL');
        $cleared = 0;
        foreach ($rows as $r) {
            $state = lock_checker::get_assignment_grade_state_for_user($r->userid, $r->blocking_cmid);
            if ($state === 'pass') {
                $DB->set_field('local_ss_bypass', 'active', 0, ['id' => $r->id]);
                $cleared++;
            }
        }
        if ($cleared > 0) {
            mtrace("[local_sequentialsubmission] expire_bypasses: deactivated {$cleared} bypasses whose blocker is now Pass");
        }
    }
}
