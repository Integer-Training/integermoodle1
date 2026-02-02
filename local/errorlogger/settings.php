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

    $settings = new admin_settingpage('local_errorlogger',
        get_string('pluginname', 'local_errorlogger'));

    // Enable/disable.
    $settings->add(new admin_setting_configcheckbox(
        'local_errorlogger/enabled',
        get_string('enabled', 'local_errorlogger'),
        get_string('enabled_desc', 'local_errorlogger'),
        1
    ));

    // Retention period.
    $retentionoptions = [
        7  => get_string('numdays', '', 7),
        14 => get_string('numdays', '', 14),
        30 => get_string('numdays', '', 30),
        60 => get_string('numdays', '', 60),
        90 => get_string('numdays', '', 90),
    ];
    $settings->add(new admin_setting_configselect(
        'local_errorlogger/retention_days',
        get_string('retention_days', 'local_errorlogger'),
        get_string('retention_days_desc', 'local_errorlogger'),
        30,
        $retentionoptions
    ));

    // Minimum severity.
    $severityoptions = [
        0 => get_string('severity_all', 'local_errorlogger'),
        2 => get_string('severity_warnings', 'local_errorlogger'),
        1 => get_string('severity_errors', 'local_errorlogger'),
    ];
    $settings->add(new admin_setting_configselect(
        'local_errorlogger/min_severity',
        get_string('min_severity', 'local_errorlogger'),
        get_string('min_severity_desc', 'local_errorlogger'),
        0,
        $severityoptions
    ));

    // Capture backtraces.
    $settings->add(new admin_setting_configcheckbox(
        'local_errorlogger/capture_backtraces',
        get_string('capture_backtraces', 'local_errorlogger'),
        get_string('capture_backtraces_desc', 'local_errorlogger'),
        1
    ));

    // Rate limit.
    $settings->add(new admin_setting_configtext(
        'local_errorlogger/max_logs_per_minute',
        get_string('max_logs_per_minute', 'local_errorlogger'),
        get_string('max_logs_per_minute_desc', 'local_errorlogger'),
        '100',
        PARAM_INT
    ));

    // Link to log viewer.
    $settings->add(new admin_setting_heading(
        'local_errorlogger/viewlogsheading',
        get_string('viewlogs', 'local_errorlogger'),
        '<a href="' . new moodle_url('/local/errorlogger/view.php') . '">' .
            get_string('viewlogs', 'local_errorlogger') . '</a>'
    ));

    $ADMIN->add('localplugins', $settings);
}
