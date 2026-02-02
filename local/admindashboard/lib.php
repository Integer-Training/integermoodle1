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
 * Navigation hooks for local_admindashboard.
 *
 * @package   local_admindashboard
 * @copyright 2025 Epearl Academy
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend the main navigation to add Admin Dashboard to the sidebar.
 *
 * This adds the link to Moodle's standard flat navigation (sidebar).
 * For the Alpha theme's custom sidebar, add the item via:
 *   Theme settings > Sidebar Navigation > Custom Item
 *   OR add it to theme/alpha/classes/output/core_renderer.php mainsidebarmenu()
 *
 * @param global_navigation $nav
 */
function local_admindashboard_extend_navigation(global_navigation $nav) {
    $context = context_system::instance();

    if (!has_capability('local/admindashboard:view', $context)) {
        return;
    }

    $node = $nav->add(
        get_string('admindashboard', 'local_admindashboard'),
        new moodle_url('/local/admindashboard/index.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_admindashboard',
        new pix_icon('i/dashboard', get_string('admindashboard', 'local_admindashboard'))
    );
    $node->showinflatnavigation = true;
}
