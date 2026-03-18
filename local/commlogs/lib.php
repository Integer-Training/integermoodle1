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
 * @package   local_commlogs
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend Moodle navigation with a sidebar link for managers.
 *
 * @param global_navigation $nav
 */
function local_commlogs_extend_navigation(global_navigation $nav) {
    global $PAGE;

    // Alpha theme hardcodes sidebar — skip DB queries here.
    if (get_config('local_performance', 'enable_nav_shortcircuit')
        && isset($PAGE->theme->name) && $PAGE->theme->name === 'alpha') {
        return;
    }

    $context = context_system::instance();

    if (has_capability('local/commlogs:view', $context)) {
        $node = $nav->add(
            get_string('commlogs', 'local_commlogs'),
            new moodle_url('/local/commlogs/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_commlogs',
            new pix_icon('i/email', '')
        );
        $node->showinflatnavigation = true;
    }
}
