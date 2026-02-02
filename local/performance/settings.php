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

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_performance', get_string('pluginname', 'local_performance'));

    // Section heading.
    $settings->add(new admin_setting_heading(
        'local_performance/settings_heading',
        get_string('settings_heading', 'local_performance'),
        get_string('settings_heading_desc', 'local_performance')
    ));

    // Toggle 1: Role cache service.
    $settings->add(new admin_setting_configcheckbox(
        'local_performance/enable_role_cache',
        get_string('enable_role_cache', 'local_performance'),
        get_string('enable_role_cache_desc', 'local_performance'),
        1
    ));

    // Toggle 2: Nav hook short-circuit for Alpha theme.
    $settings->add(new admin_setting_configcheckbox(
        'local_performance/enable_nav_shortcircuit',
        get_string('enable_nav_shortcircuit', 'local_performance'),
        get_string('enable_nav_shortcircuit_desc', 'local_performance'),
        1
    ));

    // Toggle 3: Logstore query optimization.
    $settings->add(new admin_setting_configcheckbox(
        'local_performance/enable_logstore_fix',
        get_string('enable_logstore_fix', 'local_performance'),
        get_string('enable_logstore_fix_desc', 'local_performance'),
        1
    ));

    // Dashboard link.
    $dashurl = new moodle_url('/local/performance/index.php');
    $settings->add(new admin_setting_heading(
        'local_performance/dashboard_link',
        get_string('dashboard_link', 'local_performance'),
        get_string('dashboard_link_desc', 'local_performance', $dashurl->out())
    ));

    $ADMIN->add('localplugins', $settings);
}
