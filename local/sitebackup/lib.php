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
 * Add navigation node under Site administration.
 */
function local_sitebackup_extend_navigation(global_navigation $navigation) {
    global $PAGE;

    if (!has_capability('local/sitebackup:manage', context_system::instance())) {
        return;
    }

    $node = $navigation->add(
        get_string('pluginname', 'local_sitebackup'),
        new moodle_url('/local/sitebackup/view.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_sitebackup',
        new pix_icon('i/backup', '')
    );

    if ($PAGE->url->compare(new moodle_url('/local/sitebackup/view.php'), URL_MATCH_BASE)) {
        $node->make_active();
    }
}
