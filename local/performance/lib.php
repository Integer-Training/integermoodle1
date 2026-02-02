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
 * Navigation hook — adds Health Dashboard link for managers.
 * Only effective with non-Alpha themes (Alpha hardcodes sidebar).
 */
function local_performance_extend_navigation(global_navigation $nav) {
    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();
    if (has_capability('local/performance:view', $context)) {
        $nav->add(
            get_string('dashboard_title', 'local_performance'),
            new moodle_url('/local/performance/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_performance',
            new pix_icon('i/settings', '')
        )->showinflatnavigation = true;
    }
}
