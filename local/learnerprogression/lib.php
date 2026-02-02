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
 * Library functions — navigation hook.
 *
 * @package   local_learnerprogression
 * @copyright 2026 Epearl Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend Moodle navigation with a sidebar link for managers and tutors.
 *
 * @param global_navigation $nav
 */
function local_learnerprogression_extend_navigation(global_navigation $nav) {
    global $USER, $DB, $PAGE;

    // Alpha theme hardcodes sidebar — skip DB queries here (saves 2+ queries/page).
    if (get_config('local_performance', 'enable_nav_shortcircuit')
        && isset($PAGE->theme->name) && $PAGE->theme->name === 'alpha') {
        return;
    }

    $context = context_system::instance();

    // Managers: check both plugin capability and existing learner:view.
    $show = has_capability('local/learnerprogression:view', $context)
         || has_capability('local/learner:view', $context);

    // Tutors: teacher role assignment.
    if (!$show) {
        $teacher_role = $DB->get_record('role', ['shortname' => 'teacher']);
        if ($teacher_role) {
            $show = $DB->record_exists('role_assignments', [
                'roleid' => $teacher_role->id,
                'userid' => $USER->id,
            ]);
        }
    }

    if ($show) {
        $node = $nav->add(
            get_string('learnerprogressions', 'local_learnerprogression'),
            new moodle_url('/local/learnerprogression/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_learnerprogression',
            new pix_icon('i/report', '')
        );
        $node->showinflatnavigation = true;
    }
}
