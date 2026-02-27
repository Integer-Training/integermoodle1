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
 * AJAX handler for learner activate/deactivate and group member lookup.
 *
 * @package   local_learner
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

global $DB, $USER;

require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/learner:view', $context);

header('Content-Type: application/json');

$action = required_param('action', PARAM_ALPHA);
$userid = optional_param('userid', 0, PARAM_INT);
$courseid = optional_param('courseid', 0, PARAM_INT);

// Activate a learner (un-suspend).
if ($action === 'active' && $userid) {
    $user_rec = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $rec = new stdClass();
    $rec->id = $user_rec->id;
    $rec->suspended = 0;
    $DB->update_record('user', $rec);

    // Log status change.
    $newobj = new stdClass();
    $newobj->userid = $userid;
    $newobj->status = 'Active';
    $newobj->actionby = $USER->id;
    $newobj->timecreated = time();
    $DB->insert_record('local_leaner_user', $newobj);

    echo json_encode(['status' => 'success']);
    die;
}

// Deactivate a learner (suspend).
if ($action === 'deactive' && $userid) {
    $user_rec = $DB->get_record('user', ['id' => $userid], '*', MUST_EXIST);
    $rec = new stdClass();
    $rec->id = $user_rec->id;
    $rec->suspended = 1;
    $DB->update_record('user', $rec);

    // Log status change.
    $newobj = new stdClass();
    $newobj->userid = $userid;
    $newobj->status = 'InActive';
    $newobj->actionby = $USER->id;
    $newobj->timecreated = time();
    $DB->insert_record('local_leaner_user', $newobj);

    echo json_encode(['status' => 'success']);
    die;
}

// Get group members for a course (used by other forms).
if ($action === 'getuser' && $courseid) {
    $groups = $DB->get_records('groups_members', ['userid' => $USER->id]);
    $actual_course_group = [];
    foreach ($groups as $rec) {
        $grp = $DB->get_record('groups', ['id' => $rec->groupid, 'courseid' => $courseid]);
        if ($grp) {
            $actual_course_group[] = $grp;
        }
    }

    $act_gm = [];
    foreach ($actual_course_group as $group) {
        $members = $DB->get_records('groups_members', ['groupid' => $group->id]);
        foreach ($members as $res) {
            if ($res->userid == $USER->id) {
                continue;
            }
            $act_gm[$res->userid] = $DB->get_record('user', ['id' => $res->userid], 'id,firstname,lastname,email');
        }
    }

    echo json_encode($act_gm);
    die;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
