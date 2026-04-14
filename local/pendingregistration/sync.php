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
 * Batch sync endpoint — processes students in small batches to avoid timeout.
 *
 * Called via AJAX from the Pending Registration page. Each call processes
 * a batch of students (default 5) and returns progress. The browser
 * keeps calling until all students are processed.
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

$sesskey = required_param('sesskey', PARAM_RAW);
if (!confirm_sesskey($sesskey)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid session key']);
    die();
}

header('Content-Type: application/json');

$offset = optional_param('offset', 0, PARAM_INT);
$batchsize = 5;

// Get ALL non-deleted Moodle users (excluding guest and primary admin).
// Pearl LMS learners may not have a Moodle 'student' role yet — they may
// exist as users but not be enrolled in any course. We check everyone
// against the Pearl LMS API and let the API response + criteria filter decide.
$students = $DB->get_records_sql(
    "SELECT u.id, u.email
     FROM {user} u
     WHERE u.deleted = 0
       AND u.id > 2
       AND u.email IS NOT NULL
       AND u.email <> ''
       AND u.email NOT LIKE '%@example.com'
     ORDER BY u.id ASC"
);

$all_students = array_values($students);
$total = count($all_students);

// Slice the batch.
$batch = array_slice($all_students, $offset, $batchsize);

if (empty($batch)) {
    echo json_encode([
        'success' => true,
        'done' => true,
        'total' => $total,
        'processed' => $offset,
        'synced' => 0,
        'skipped' => 0,
    ]);
    die();
}

require_once($CFG->libdir . '/filelib.php');
$api = new \local_pendingregistration\api_client();
$synced = 0;
$skipped = 0;
$now = time();

foreach ($batch as $student) {
    $email = strtolower(trim($student->email));

    $learner = $api->get_learner($email);

    if ($learner === null) {
        $existing = $DB->get_record('local_pendingregistration', ['learner_email' => $email]);
        if ($existing && $existing->is_active) {
            $DB->update_record('local_pendingregistration', (object) [
                'id' => $existing->id,
                'is_active' => 0,
                'last_synced' => $now,
                'timemodified' => $now,
            ]);
        }
        $skipped++;
        continue;
    }

    $meets = $api->meets_criteria($learner);
    $existing = $DB->get_record('local_pendingregistration', ['learner_email' => $email]);

    if ($existing) {
        $DB->update_record('local_pendingregistration', (object) [
            'id' => $existing->id,
            'learner_id' => $learner->learner_id ?? $existing->learner_id,
            'learner_name' => $learner->name ?? $existing->learner_name,
            'company' => $learner->company ?? $existing->company,
            'paid_to_date' => (float) ($learner->payment->paid_to_date ?? 0),
            'outstanding' => (float) ($learner->payment->outstanding ?? 0),
            'total_fee' => (float) ($learner->payment->total_fee ?? 0),
            'subscription_status' => $learner->stripe->subscription_status ?? '',
            'is_active' => $meets ? 1 : 0,
            'last_synced' => $now,
            'timemodified' => $now,
        ]);
        $synced++;
    } else if ($meets) {
        $DB->insert_record('local_pendingregistration', (object) [
            'learner_email' => $email,
            'learner_id' => $learner->learner_id ?? '',
            'learner_name' => $learner->name ?? '',
            'company' => $learner->company ?? '',
            'paid_to_date' => (float) ($learner->payment->paid_to_date ?? 0),
            'outstanding' => (float) ($learner->payment->outstanding ?? 0),
            'total_fee' => (float) ($learner->payment->total_fee ?? 0),
            'subscription_status' => $learner->stripe->subscription_status ?? '',
            'registration_check' => 0,
            'tutor_check' => 0,
            'admin_check' => 0,
            'notes' => '',
            'links' => '',
            'is_active' => 1,
            'last_synced' => $now,
            'timecreated' => $now,
            'timemodified' => $now,
        ]);
        $synced++;
    } else {
        $skipped++;
    }
}

$new_offset = $offset + $batchsize;
echo json_encode([
    'success' => true,
    'done' => $new_offset >= $total,
    'total' => $total,
    'processed' => min($new_offset, $total),
    'synced' => $synced,
    'skipped' => $skipped,
]);
