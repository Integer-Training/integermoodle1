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
 * Admin settings for Pending Registration.
 *
 * @package   local_pendingregistration
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_pendingregistration',
        get_string('pluginname', 'local_pendingregistration')
    );

    $settings->add(new admin_setting_configtext(
        'local_pendingregistration/apiurl',
        get_string('apiurl', 'local_pendingregistration'),
        get_string('apiurl_desc', 'local_pendingregistration'),
        'https://mrebutwqngfxtooeyydc.supabase.co/functions/v1/moodle-payment-api',
        PARAM_URL
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_pendingregistration/apikey',
        get_string('apikey', 'local_pendingregistration'),
        get_string('apikey_desc', 'local_pendingregistration'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_pendingregistration/apitoken',
        get_string('apitoken', 'local_pendingregistration'),
        get_string('apitoken_desc', 'local_pendingregistration'),
        ''
    ));

    $ADMIN->add('localplugins', $settings);
}
