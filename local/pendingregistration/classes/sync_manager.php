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
 * Sync manager — pulls learner payment data from Pearl LMS API.
 *
 * @package   local_pendingregistration
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pendingregistration;

defined('MOODLE_INTERNAL') || die();

class sync_manager {

    /** @var api_client */
    private $api;

    public function __construct() {
        $this->api = new api_client();
    }

    /**
     * Sync all Moodle student emails against the Pearl LMS API.
     *
     * @return array ['synced' => int, 'skipped' => int, 'errors' => int]
     */
    public function sync_all(): array {
        global $DB;

        $result = ['synced' => 0, 'skipped' => 0, 'errors' => 0];

        if (!$this->api->is_configured()) {
            return $result;
        }

        // Get ALL non-deleted Moodle users (excluding guest and primary admin).
        // Pearl LMS learners may not have a Moodle 'student' role yet.
        $students = $DB->get_records_sql(
            "SELECT u.id, u.email, u.firstname, u.lastname
             FROM {user} u
             WHERE u.deleted = 0
               AND u.id > 2
               AND u.email IS NOT NULL
               AND u.email <> ''
               AND u.email NOT LIKE '%@example.com'"
        );

        $now = time();

        foreach ($students as $student) {
            $email = strtolower(trim($student->email));

            // Call the Pearl LMS API.
            $learner = $this->api->get_learner($email);

            if ($learner === null) {
                // No data from API — check if exists in DB and mark inactive.
                $existing = $DB->get_record('local_pendingregistration', ['learner_email' => $email]);
                if ($existing && $existing->is_active) {
                    $DB->update_record('local_pendingregistration', (object) [
                        'id' => $existing->id,
                        'is_active' => 0,
                        'last_synced' => $now,
                        'timemodified' => $now,
                    ]);
                }
                $result['skipped']++;
                continue;
            }

            $meets = $this->api->meets_criteria($learner);
            $existing = $DB->get_record('local_pendingregistration', ['learner_email' => $email]);

            if ($existing) {
                // Update existing record with latest payment data.
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
                $result['synced']++;
            } else if ($meets) {
                // Insert new record — only if they meet criteria.
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
                $result['synced']++;
            } else {
                $result['skipped']++;
            }
        }

        return $result;
    }
}
