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
 * AJAX handler for checkbox toggles and notes saving.
 *
 * @package   local_pendingregistration
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);
require_once('../../config.php');
require_login();

// Access check: admins, managers, teachers, editing teachers.
$has_access = is_siteadmin() || $DB->record_exists_sql(
    "SELECT 1 FROM {role_assignments} ra
     JOIN {role} r ON r.id = ra.roleid AND r.shortname IN ('manager', 'teacher', 'editingteacher')
     WHERE ra.userid = ?", [$USER->id]
);
if (!$has_access) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'No permission']);
    die();
}

$action = required_param('action', PARAM_ALPHA);
$id = required_param('id', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);

if (!confirm_sesskey($sesskey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid session key']);
    die();
}

$record = $DB->get_record('local_pendingregistration', ['id' => $id]);
if (!$record) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Record not found']);
    die();
}

header('Content-Type: application/json');

switch ($action) {
    case 'toggle':
        $field = required_param('field', PARAM_ALPHANUMEXT);
        $value = required_param('value', PARAM_INT);

        $allowed_fields = ['registration_check', 'tutor_check', 'admin_check'];
        if (!in_array($field, $allowed_fields)) {
            echo json_encode(['success' => false, 'error' => 'Invalid field']);
            die();
        }

        $DB->update_record('local_pendingregistration', (object) [
            'id' => $id,
            $field => $value ? 1 : 0,
            'timemodified' => time(),
        ]);

        echo json_encode(['success' => true, 'field' => $field, 'value' => $value]);
        break;

    case 'notes':
        $notes = required_param('notes', PARAM_RAW);
        // Clean notes — allow basic text only.
        $notes = clean_param($notes, PARAM_TEXT);

        $DB->update_record('local_pendingregistration', (object) [
            'id' => $id,
            'notes' => $notes,
            'timemodified' => time(),
        ]);

        echo json_encode(['success' => true]);
        break;

    case 'links':
        $links = required_param('links', PARAM_RAW);
        $links = clean_param($links, PARAM_TEXT);

        $DB->update_record('local_pendingregistration', (object) [
            'id' => $id,
            'links' => $links,
            'timemodified' => time(),
        ]);

        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
