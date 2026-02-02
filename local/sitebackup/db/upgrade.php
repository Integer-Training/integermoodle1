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

function xmldb_local_sitebackup_upgrade($oldversion) {
    global $DB;
    $dbman = $DB->get_manager();

    if ($oldversion < 2026020102) {
        // Ensure the logs table exists (may have been missed on initial install).
        $table = new xmldb_table('local_sitebackup_logs');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('filesize', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('drive_file_id', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('drive_link', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'in_progress');
            $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('contents', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');

            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);

            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026020102, 'local', 'sitebackup');
    }

    if ($oldversion < 2026020200) {
        // Add progress_step column for live progress tracking.
        $table = new xmldb_table('local_sitebackup_logs');
        $field = new xmldb_field('progress_step', XMLDB_TYPE_CHAR, '100', null, null, null, null, 'contents');

        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }

        upgrade_plugin_savepoint(true, 2026020200, 'local', 'sitebackup');
    }

    return true;
}
