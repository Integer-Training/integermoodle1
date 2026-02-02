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
 * @package   local_learnerdashboard
 * @copyright 2026 Epearl Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Navigation hook — fallback for non-Alpha themes.
 * Alpha theme hardcodes sidebar in core_renderer.php, so this only
 * works with standard Moodle themes (Boost, etc.).
 */
function local_learnerdashboard_extend_navigation(global_navigation $nav) {
    global $USER, $DB;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $student_role = $DB->get_record('role', ['shortname' => 'student']);
    if ($student_role && $DB->record_exists('role_assignments', [
        'roleid' => $student_role->id,
        'userid' => $USER->id,
    ])) {
        $nav->add(
            get_string('mydashboard', 'local_learnerdashboard'),
            new moodle_url('/local/learnerdashboard/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_learnerdashboard',
            new pix_icon('i/dashboard', '')
        )->showinflatnavigation = true;
    }
}
