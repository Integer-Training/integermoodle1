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

namespace local_sequentialsubmission\task;

defined('MOODLE_INTERNAL') || die();

use core\task\adhoc_task;
use core\message\message;
use local_sequentialsubmission\lock_checker;

/**
 * One-time ad-hoc task — sends the grandfather notification to every learner
 * who already has 2 or more pending submissions when the plugin is installed.
 *
 * Runs in batches to stay under Hostinger's PHP timeout (pattern borrowed
 * from local/pendingregistration). If the queue is large, the task
 * re-queues itself for the remaining users.
 *
 * Message is sent "from" each learner's assigned tutor, with fallback to
 * the site support user if the learner has no assigned tutor.
 *
 * @package local_sequentialsubmission
 */
class notify_existing extends adhoc_task {

    /** Max learners per run — tuned for ~30s Hostinger PHP timeout. */
    const BATCH_SIZE = 25;

    public function execute() {
        global $DB, $CFG;

        mtrace('[local_sequentialsubmission] notify_existing: starting batch');

        $data = $this->get_custom_data();
        $alreadynotified = isset($data->notified) ? (array) $data->notified : [];

        // Find all learners with 2+ pending submissions, excluding any we've already notified.
        $candidates = self::find_candidates($alreadynotified);
        if (empty($candidates)) {
            mtrace('[local_sequentialsubmission] notify_existing: no (more) candidates. Done.');
            return;
        }

        mtrace('[local_sequentialsubmission] notify_existing: ' . count($candidates) . ' candidates in this run');

        $batch = array_slice($candidates, 0, self::BATCH_SIZE);
        foreach ($batch as $row) {
            try {
                self::notify_learner($row);
                $alreadynotified[] = (int) $row->userid;
                lock_checker::log_event((int) $row->userid, 'grandfather_sent', [
                    'notes' => 'Sent grandfather notification. Pending count: ' . (int) $row->pending_count,
                ]);
            } catch (\Exception $e) {
                mtrace('[local_sequentialsubmission] error notifying user ' . $row->userid . ': ' . $e->getMessage());
            }
        }

        // If there are more candidates, re-queue.
        if (count($candidates) > self::BATCH_SIZE) {
            $nexttask = new self();
            $nexttask->set_custom_data((object) ['notified' => $alreadynotified]);
            \core\task\manager::queue_adhoc_task($nexttask);
            mtrace('[local_sequentialsubmission] notify_existing: re-queued for remaining ' .
                (count($candidates) - self::BATCH_SIZE) . ' users');
        } else {
            mtrace('[local_sequentialsubmission] notify_existing: finished');
        }
    }

    /**
     * Users with 2+ currently-pending non-exempt submissions.
     *
     * @param array $excludeids Learners to skip (already notified)
     * @return array Rows with {userid, firstname, lastname, email, pending_count}
     */
    protected static function find_candidates(array $excludeids = []): array {
        global $DB;

        $patterns = lock_checker::get_exempt_patterns();
        $params = [];
        $excl = '';
        foreach ($patterns as $i => $p) {
            $k = "ep{$i}";
            $excl .= " AND a.name NOT LIKE :{$k}";
            $params[$k] = '%' . $p . '%';
        }

        $excludesql = '';
        if (!empty($excludeids)) {
            [$in, $inparams] = $DB->get_in_or_equal($excludeids, SQL_PARAMS_NAMED, 'exc', false);
            $excludesql = " AND u.id {$in}";
            $params = array_merge($params, $inparams);
        }

        // Count pending submissions per user (scale-agnostic: graded Pass filtered via derive_grade_state in PHP).
        // Here SQL-level "pending" = submitted AND (ungraded OR grade = 1 which maps to Refer on Pass/Refer scales).
        $sql = "SELECT u.id AS userid, u.firstname, u.lastname, u.email,
                       COUNT(DISTINCT sub.id) AS pending_count
                FROM {user} u
                JOIN {assign_submission} sub ON sub.userid = u.id AND sub.latest = 1 AND sub.status = 'submitted'
                JOIN {assign} a ON a.id = sub.assignment
                JOIN {modules} m ON m.name = 'assign'
                JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = m.id AND cm.visible = 1
                JOIN {user_enrolments} ue ON ue.userid = u.id
                JOIN {enrol} en ON en.id = ue.enrolid AND en.courseid = a.course AND en.status = 0
                LEFT JOIN {assign_grades} gr ON gr.assignment = a.id AND gr.userid = u.id
                    AND gr.attemptnumber = sub.attemptnumber
                WHERE u.suspended = 0 AND u.deleted = 0
                  AND ue.status = 0
                  AND (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0 OR gr.grade = 1)
                  {$excl}
                  {$excludesql}
                GROUP BY u.id, u.firstname, u.lastname, u.email
                HAVING COUNT(DISTINCT sub.id) >= 2
                ORDER BY u.id";

        return $DB->get_records_sql($sql, $params);
    }

    /**
     * Send the grandfather message to a single learner.
     *
     * @param object $row
     */
    protected static function notify_learner($row) {
        global $DB;

        $user = $DB->get_record('user', ['id' => $row->userid], '*', MUST_EXIST);

        // Find tutor (teacher role in any shared group). Fallback to noreply.
        $tutor = self::get_assigned_tutor($row->userid);
        if (!$tutor) {
            $tutor = \core_user::get_noreply_user();
            $tutorname = 'Integer Training';
        } else {
            $tutorname = fullname($tutor);
        }

        $a = (object) [
            'firstname' => $user->firstname,
            'pending_count' => (int) $row->pending_count,
            'tutorname' => $tutorname,
        ];

        $msg = new message();
        $msg->component = 'local_sequentialsubmission';
        $msg->name = 'grandfather';
        $msg->userfrom = $tutor;
        $msg->userto = $user;
        $msg->subject = get_string('msg_grandfather_subject', 'local_sequentialsubmission');
        $msg->fullmessage = get_string('msg_grandfather_body', 'local_sequentialsubmission', $a);
        $msg->fullmessageformat = FORMAT_PLAIN;
        $msg->fullmessagehtml = nl2br(htmlspecialchars(
            get_string('msg_grandfather_body', 'local_sequentialsubmission', $a)
        ));
        $msg->smallmessage = get_string('msg_grandfather_subject', 'local_sequentialsubmission');
        $msg->notification = 1;

        message_send($msg);
    }

    /**
     * Resolve the learner's assigned tutor via shared group membership.
     */
    protected static function get_assigned_tutor($learnerid) {
        global $DB;

        return $DB->get_record_sql(
            "SELECT DISTINCT u.*
             FROM {groups_members} gm_l
             JOIN {groups_members} gm_t ON gm_t.groupid = gm_l.groupid AND gm_t.userid <> gm_l.userid
             JOIN {user} u ON u.id = gm_t.userid AND u.suspended = 0 AND u.deleted = 0
             JOIN {role_assignments} ra ON ra.userid = u.id
             JOIN {context} ctx ON ctx.id = ra.contextid AND ctx.contextlevel = 50
             JOIN {role} r ON r.id = ra.roleid AND r.shortname = 'teacher'
             WHERE gm_l.userid = :learnerid
             LIMIT 1",
            ['learnerid' => $learnerid]
        );
    }
}
