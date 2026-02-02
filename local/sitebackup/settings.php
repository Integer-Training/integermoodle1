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
    $settings = new admin_settingpage('local_sitebackup',
        get_string('pluginname', 'local_sitebackup'));

    // Google OAuth credentials.
    $settings->add(new admin_setting_configtext(
        'local_sitebackup/google_client_id',
        get_string('google_client_id', 'local_sitebackup'),
        get_string('google_client_id_desc', 'local_sitebackup'),
        ''
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_sitebackup/google_client_secret',
        get_string('google_client_secret', 'local_sitebackup'),
        get_string('google_client_secret_desc', 'local_sitebackup'),
        ''
    ));

    // Drive folder name.
    $settings->add(new admin_setting_configtext(
        'local_sitebackup/drive_folder_name',
        get_string('drive_folder_name', 'local_sitebackup'),
        get_string('drive_folder_name_desc', 'local_sitebackup'),
        'Moodle-Backups'
    ));

    // Retention count.
    $settings->add(new admin_setting_configtext(
        'local_sitebackup/retention_count',
        get_string('retention_count', 'local_sitebackup'),
        get_string('retention_count_desc', 'local_sitebackup'),
        '10',
        PARAM_INT
    ));

    // Backup contents toggles.
    $settings->add(new admin_setting_configcheckbox(
        'local_sitebackup/include_database',
        get_string('include_database', 'local_sitebackup'),
        get_string('include_database_desc', 'local_sitebackup'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_sitebackup/include_themes',
        get_string('include_themes', 'local_sitebackup'),
        get_string('include_themes_desc', 'local_sitebackup'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_sitebackup/include_plugins',
        get_string('include_plugins', 'local_sitebackup'),
        get_string('include_plugins_desc', 'local_sitebackup'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_sitebackup/include_config',
        get_string('include_config', 'local_sitebackup'),
        get_string('include_config_desc', 'local_sitebackup'),
        1
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_sitebackup/include_moodledata',
        get_string('include_moodledata', 'local_sitebackup'),
        get_string('include_moodledata_desc', 'local_sitebackup'),
        0
    ));

    $ADMIN->add('localplugins', $settings);
}
