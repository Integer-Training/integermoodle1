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
 * Event observer — redirect students to learner dashboard after login.
 *
 * @package   local_learnerdashboard
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_learnerdashboard;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Redirect students to the learner dashboard after login.
     *
     * Only fires when the user has the student role and is NOT an admin
     * or teacher. Respects any pre-existing wantsurl (e.g. deep links).
     */
    public static function user_loggedin(\core\event\user_loggedin $event) {
        global $DB, $SESSION, $CFG;

        $userid = (int) $event->userid;

        // Never redirect site admins.
        if (is_siteadmin($userid)) {
            return;
        }

        // Check for teacher / editingteacher role — they should NOT get the learner dash.
        $teacher_roles = $DB->get_records_select('role',
            "shortname IN ('teacher', 'editingteacher')",
            null, '', 'id');
        if (!empty($teacher_roles)) {
            list($rsql, $rparams) = $DB->get_in_or_equal(array_keys($teacher_roles), SQL_PARAMS_NAMED);
            $rparams['uid'] = $userid;
            if ($DB->record_exists_select('role_assignments',
                "roleid {$rsql} AND userid = :uid", $rparams)) {
                return;
            }
        }

        // Check the user actually has the student role.
        $student_role = $DB->get_record('role', ['shortname' => 'student']);
        if (!$student_role) {
            return;
        }
        if (!$DB->record_exists('role_assignments', [
            'roleid' => $student_role->id,
            'userid' => $userid,
        ])) {
            return;
        }

        // Only override if no specific deep-link was requested.
        $dashboard = new \moodle_url('/local/learnerdashboard/index.php');
        $siteroot  = rtrim($CFG->wwwroot, '/') . '/';

        if (empty($SESSION->wantsurl)
            || $SESSION->wantsurl === $siteroot
            || $SESSION->wantsurl === $CFG->wwwroot
            || strpos($SESSION->wantsurl, '/my/') !== false
            || strpos($SESSION->wantsurl, '/login/') !== false) {
            $SESSION->wantsurl = $dashboard->out(false);
        }
    }
}
