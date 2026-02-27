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
 * Upgrade steps for local_news plugin.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade function for local_news.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_local_news_upgrade($oldversion) {
    global $DB;

    // Assign view capability to all standard roles (student, teacher, editingteacher, manager, user).
    if ($oldversion < 2026020504) {
        $systemcontext = context_system::instance();

        // Get role IDs for standard roles.
        $roles = $DB->get_records_menu('role', [], '', 'shortname, id');

        // Roles that should have the view capability.
        $viewroles = ['student', 'teacher', 'editingteacher', 'manager', 'user', 'guest'];

        foreach ($viewroles as $rolename) {
            if (isset($roles[$rolename])) {
                // Check if capability already assigned.
                $existing = $DB->get_record('role_capabilities', [
                    'roleid' => $roles[$rolename],
                    'capability' => 'local/news:view',
                    'contextid' => $systemcontext->id,
                ]);

                if (!$existing) {
                    // Assign the capability.
                    $cap = new stdClass();
                    $cap->contextid = $systemcontext->id;
                    $cap->roleid = $roles[$rolename];
                    $cap->capability = 'local/news:view';
                    $cap->permission = CAP_ALLOW;
                    $cap->timemodified = time();
                    $cap->modifierid = 0;
                    $DB->insert_record('role_capabilities', $cap);
                }
            }
        }

        // Also assign to the authenticated user role (roleid 7 typically).
        $authenticatedrole = $DB->get_record('role', ['archetype' => 'user']);
        if ($authenticatedrole) {
            $existing = $DB->get_record('role_capabilities', [
                'roleid' => $authenticatedrole->id,
                'capability' => 'local/news:view',
                'contextid' => $systemcontext->id,
            ]);

            if (!$existing) {
                $cap = new stdClass();
                $cap->contextid = $systemcontext->id;
                $cap->roleid = $authenticatedrole->id;
                $cap->capability = 'local/news:view';
                $cap->permission = CAP_ALLOW;
                $cap->timemodified = time();
                $cap->modifierid = 0;
                $DB->insert_record('role_capabilities', $cap);
            }
        }

        upgrade_plugin_savepoint(true, 2026020504, 'local', 'news');
    }

    return true;
}
