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
 * Admin settings for local_twiliosms.
 *
 * @package   local_twiliosms
 * @copyright 2025 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {

    $settings = new admin_settingpage('local_twiliosms', get_string('pluginname', 'local_twiliosms'));

    // Enable/disable.
    $settings->add(new admin_setting_configcheckbox(
        'local_twiliosms/enabled',
        get_string('enabled', 'local_twiliosms'),
        get_string('enabled_desc', 'local_twiliosms'),
        0
    ));

    // Twilio Account SID.
    $settings->add(new admin_setting_configtext(
        'local_twiliosms/accountsid',
        get_string('accountsid', 'local_twiliosms'),
        get_string('accountsid_desc', 'local_twiliosms'),
        'ACff6f9ad50d8748ae757b63994a9e2011',
        PARAM_TEXT
    ));

    // Twilio Auth Token (masked).
    $settings->add(new admin_setting_configpasswordunmask(
        'local_twiliosms/authtoken',
        get_string('authtoken', 'local_twiliosms'),
        get_string('authtoken_desc', 'local_twiliosms'),
        ''
    ));

    // From phone number.
    $settings->add(new admin_setting_configtext(
        'local_twiliosms/fromnumber',
        get_string('fromnumber', 'local_twiliosms'),
        get_string('fromnumber_desc', 'local_twiliosms'),
        '+447307280358',
        PARAM_TEXT
    ));

    // Message template.
    $settings->add(new admin_setting_configtextarea(
        'local_twiliosms/messagetemplate',
        get_string('messagetemplate', 'local_twiliosms'),
        get_string('messagetemplate_desc', 'local_twiliosms'),
        'Integer Training - User: {username} Pass: Integer@123 Login: epearlacademy.com'
    ));

    // Link to SMS logs.
    $settings->add(new admin_setting_heading(
        'local_twiliosms/logsheading',
        get_string('smslogs', 'local_twiliosms'),
        '<a href="' . new moodle_url('/local/twiliosms/logs.php') . '">' .
            get_string('viewsmslogs', 'local_twiliosms') . '</a>'
    ));

    $ADMIN->add('localplugins', $settings);
}
