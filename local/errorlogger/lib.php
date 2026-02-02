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

defined('MOODLE_INTERNAL') || die();

/**
 * Extend the main navigation to add Error Logger to the sidebar.
 *
 * @param global_navigation $nav
 */
function local_errorlogger_extend_navigation(global_navigation $nav) {
    $context = context_system::instance();

    if (!has_capability('local/errorlogger:view', $context)) {
        return;
    }

    $node = $nav->add(
        get_string('pluginname', 'local_errorlogger'),
        new moodle_url('/local/errorlogger/view.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_errorlogger',
        new pix_icon('i/warning', get_string('pluginname', 'local_errorlogger'))
    );
    $node->showinflatnavigation = true;
}

/**
 * Legacy after_config callback.
 * Kept as fallback for the modern db/hooks.php registration.
 */
function local_errorlogger_after_config() {
    global $CFG;

    if (during_initial_install() || isset($CFG->upgraderunning)) {
        return;
    }

    if (!get_config('local_errorlogger', 'enabled')) {
        return;
    }

    \local_errorlogger\handler::register();
}
