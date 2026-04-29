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

use core\task\scheduled_task;
use core\message\message;
use local_sequentialsubmission\lock_checker;

/**
 * Daily scan — finds learners whose pending submission has been awaiting
 * marking for more than the configured SLA threshold. Sends a single digest
 * message to every site admin listing all stuck learners.
 *
 * @package local_sequentialsubmission
 */
class stuck_learner_scan extends scheduled_task {

    public function get_name() {
        return get_string('pluginname', 'local_sequentialsubmission') . ' — Stuck Learner Scan';
    }

    public function execute() {
        global $DB, $CFG;

        if (!lock_checker::is_enabled()) {
            mtrace('[local_sequentialsubmission] stuck_learner_scan: plugin disabled, skipping');
            return;
        }

        $sla_days = (int) get_config('local_sequentialsubmission', 'sla_days');
        if ($sla_days <= 0) {
            $sla_days = 7;
        }
        $threshold = time() - ($sla_days * 86400);

        $patterns = lock_checker::get_exempt_patterns();
        $params = ['threshold' => $threshold];
        $excl = '';
        foreach ($patterns as $i => $p) {
            $k = "ep{$i}";
            $excl .= " AND a.name NOT LIKE :{$k}";
            $params[$k] = '%' . $p . '%';
        }

        $sql = "SELECT sub.id AS subid, sub.userid, sub.timemodified,
                       u.firstname, u.lastname,
                       a.name AS assignname, c.fullname AS coursename, cm.id AS cmid
                FROM {assign_submission} sub
                JOIN {user} u ON u.id = sub.userid AND u.suspended = 0 AND u.deleted = 0
                JOIN {assign} a ON a.id = sub.assignment
                JOIN {course} c ON c.id = a.course
                JOIN {modules} m ON m.name = 'assign'
                JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = m.id AND cm.visible = 1
                LEFT JOIN {assign_grades} gr ON gr.assignment = a.id AND gr.userid = sub.userid
                    AND gr.attemptnumber = sub.attemptnumber
                WHERE sub.latest = 1
                  AND sub.status = 'submitted'
                  AND sub.timemodified < :threshold
                  AND (gr.id IS NULL OR gr.grade IS NULL OR gr.grade < 0 OR gr.grade = 1)
                  {$excl}
                ORDER BY sub.timemodified ASC";

        $stuck = $DB->get_records_sql($sql, $params);
        if (empty($stuck)) {
            mtrace('[local_sequentialsubmission] stuck_learner_scan: no stuck learners');
            return;
        }

        mtrace('[local_sequentialsubmission] stuck_learner_scan: ' . count($stuck) . ' stuck learners (>' . $sla_days . ' days)');

        // Log each one (de-dupe: only log if last alert was >24h ago to avoid daily spam in audit).
        $yesterday = time() - 86400;
        foreach ($stuck as $row) {
            $already = $DB->record_exists_sql(
                "SELECT 1 FROM {local_ss_log}
                 WHERE userid = :uid AND action = 'stuck_alert' AND blocked_cmid = :cmid
                   AND timecreated > :since",
                ['uid' => $row->userid, 'cmid' => $row->cmid, 'since' => $yesterday]
            );
            if ($already) {
                continue;
            }
            $days = (int) floor((time() - $row->timemodified) / 86400);
            lock_checker::log_event((int) $row->userid, 'stuck_alert', [
                'blocked_cmid' => (int) $row->cmid,
                'reason' => 'sla_stuck',
                'notes' => "Pending {$days} days on {$row->assignname} in {$row->coursename}",
            ]);
        }

        // Send digest message to all site admins.
        $admins = get_admins();
        if (empty($admins)) {
            return;
        }

        $lines = [];
        foreach ($stuck as $row) {
            $days = (int) floor((time() - $row->timemodified) / 86400);
            $lines[] = "• {$row->firstname} {$row->lastname} — {$row->assignname} ({$row->coursename}) — {$days} day(s)";
        }

        $a = (object) [
            'count' => count($stuck),
            'days' => $sla_days,
            'learner_list' => implode("\n", array_slice($lines, 0, 50)) .
                (count($lines) > 50 ? "\n\n…and " . (count($lines) - 50) . ' more.' : ''),
            'admin_url' => (new \moodle_url('/local/sequentialsubmission/index.php'))->out(false),
        ];

        foreach ($admins as $admin) {
            $msg = new message();
            $msg->component = 'local_sequentialsubmission';
            $msg->name = 'stuckalert';
            $msg->userfrom = \core_user::get_noreply_user();
            $msg->userto = $admin;
            $msg->subject = get_string('msg_stuck_subject', 'local_sequentialsubmission', $a);
            $msg->fullmessage = get_string('msg_stuck_body', 'local_sequentialsubmission', $a);
            $msg->fullmessageformat = FORMAT_PLAIN;
            $msg->fullmessagehtml = nl2br(htmlspecialchars(
                get_string('msg_stuck_body', 'local_sequentialsubmission', $a)
            ));
            $msg->smallmessage = get_string('msg_stuck_subject', 'local_sequentialsubmission', $a);
            $msg->notification = 1;
            $msg->contexturl = $a->admin_url;
            $msg->contexturlname = get_string('managelocks', 'local_sequentialsubmission');
            message_send($msg);
        }
    }
}
