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
 * tutor file
 *
 * 
 *
 * @package   local_tutors
 * @copyright 2025 shiva
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_tutors\output;

use moodle_url;


/**
 * Class menu
 *
 * @package local_tutors\output
 */
class tutor {

    /**
     * Function fetch_all_gradings
     *
     * @throws \fetch_all_gradings
     * @throws \fetch_all_gradings
     */
    public static function fetch_all_gradings() {
        global $DB, $CFG;

        $isadmin = has_capability("local/tutors:view", \context_system::instance());
    }
}
