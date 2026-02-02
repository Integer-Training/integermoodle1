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
 * Navigation hooks for local_learner plugin.
 *
 * @package   local_learner
 * @copyright 2025 Gecko
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend the main navigation to add sidebar links.
 *
 * @param global_navigation $nav
 */
function local_learner_extend_navigation(global_navigation $nav) {
    global $USER, $DB;

    $context = context_system::instance();
    $is_manager = has_capability('local/learner:view', $context);

    // Manager-only links: Learners list and Admin Dashboard.
    if ($is_manager) {
        $learnersnode = $nav->add(
            'Learners',
            new moodle_url('/local/learner/view.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_learner_view',
            new pix_icon('i/users', 'Learners')
        );
        $learnersnode->showinflatnavigation = true;

        $dashnode = $nav->add(
            get_string('admindashboard', 'local_learner'),
            new moodle_url('/local/learner/admindash.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_learner_admindash',
            new pix_icon('i/dashboard', get_string('admindashboard', 'local_learner'))
        );
        $dashnode->showinflatnavigation = true;
    }
}
