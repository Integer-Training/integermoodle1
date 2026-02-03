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
 * Manager class for Draft Feedback plugin.
 *
 * @package    local_draftfeedback
 * @copyright  2026 Epearl Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_draftfeedback;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager class containing business logic for draft feedback.
 */
class manager {

    /** @var string Status: awaiting feedback */
    const STATUS_PENDING = 'pending';

    /** @var string Status: feedback provided */
    const STATUS_REVIEWED = 'reviewed';

    /** @var string Status: learner has revised */
    const STATUS_REVISED = 'revised';

    /**
     * Submit a new draft for feedback.
     *
     * @param int $cmid Course module ID
     * @param int $userid User ID
     * @param string|null $drafttext Online text content
     * @param int|null $draftitemid File draft area item ID
     * @return int The new draft record ID
     */
    public static function submit_draft($cmid, $userid, $drafttext = null, $draftitemid = null) {
        global $DB;

        $now = time();

        // Check if there's already a pending draft for this user/assignment.
        $existing = $DB->get_record('local_draftfeedback', [
            'cmid' => $cmid,
            'userid' => $userid,
            'status' => self::STATUS_PENDING,
        ]);

        if ($existing) {
            // Update existing draft.
            $existing->drafttext = $drafttext;
            $existing->timemodified = $now;
            $DB->update_record('local_draftfeedback', $existing);
            return $existing->id;
        }

        // Create new draft record.
        $draft = new \stdClass();
        $draft->cmid = $cmid;
        $draft->userid = $userid;
        $draft->status = self::STATUS_PENDING;
        $draft->drafttext = $drafttext;
        $draft->timecreated = $now;
        $draft->timemodified = $now;

        $draftid = $DB->insert_record('local_draftfeedback', $draft);

        // Handle file uploads if provided.
        if ($draftitemid) {
            self::save_draft_files($draftid, $draftitemid, $cmid);
        }

        return $draftid;
    }

    /**
     * Save draft files from draft area.
     *
     * @param int $draftid Draft record ID
     * @param int $draftitemid Draft area item ID
     * @param int $cmid Course module ID
     */
    public static function save_draft_files($draftid, $draftitemid, $cmid) {
        global $DB;

        $context = \context_module::instance($cmid);
        $fs = get_file_storage();

        // Save files from draft area.
        file_save_draft_area_files(
            $draftitemid,
            $context->id,
            'local_draftfeedback',
            'draftfiles',
            $draftid,
            ['subdirs' => 0, 'maxfiles' => 10]
        );

        // Update draft record with filename.
        $files = $fs->get_area_files($context->id, 'local_draftfeedback', 'draftfiles', $draftid, 'filename', false);
        if ($files) {
            $file = reset($files);
            $DB->set_field('local_draftfeedback', 'draftfile', $file->get_filename(), ['id' => $draftid]);
        }
    }

    /**
     * Get a single draft by ID.
     *
     * @param int $draftid Draft ID
     * @return object|false Draft record or false if not found
     */
    public static function get_draft($draftid) {
        global $DB;

        $sql = "SELECT df.*,
                       u.firstname, u.lastname, u.email,
                       cm.course AS courseid,
                       a.name AS assignmentname,
                       c.fullname AS coursename, c.shortname AS courseshortname,
                       fb.firstname AS feedbackbyfirstname, fb.lastname AS feedbackbylastname
                FROM {local_draftfeedback} df
                JOIN {course_modules} cm ON cm.id = df.cmid
                JOIN {assign} a ON a.id = cm.instance
                JOIN {course} c ON c.id = cm.course
                JOIN {user} u ON u.id = df.userid
                LEFT JOIN {user} fb ON fb.id = df.feedbackby
                WHERE df.id = :draftid";

        return $DB->get_record_sql($sql, ['draftid' => $draftid]);
    }

    /**
     * Get pending drafts for a tutor.
     *
     * @param int $tutorid Tutor user ID
     * @return array Array of draft records
     */
    public static function get_pending_drafts_for_tutor($tutorid) {
        global $DB;

        $sql = "SELECT df.*,
                       u.firstname, u.lastname, u.email,
                       a.name AS assignmentname,
                       c.fullname AS coursename, c.shortname AS courseshortname
                FROM {local_draftfeedback} df
                JOIN {course_modules} cm ON cm.id = df.cmid
                JOIN {assign} a ON a.id = cm.instance
                JOIN {course} c ON c.id = cm.course
                JOIN {user} u ON u.id = df.userid AND u.suspended = 0 AND u.deleted = 0
                JOIN {groups_members} gm_t ON gm_t.userid = :tutorid
                JOIN {groups} g ON g.id = gm_t.groupid AND g.courseid = c.id
                JOIN {groups_members} gm_s ON gm_s.groupid = g.id AND gm_s.userid = df.userid
                WHERE df.status = :status
                AND a.name NOT LIKE '%IAG%'
                AND a.name NOT LIKE '%ID Proof%'
                AND a.name NOT LIKE '%Case Stud%'
                ORDER BY df.timecreated ASC";

        return $DB->get_records_sql($sql, [
            'tutorid' => $tutorid,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Get all pending drafts (for managers).
     *
     * @return array Array of draft records
     */
    public static function get_all_pending_drafts() {
        global $DB;

        $sql = "SELECT df.*,
                       u.firstname, u.lastname, u.email,
                       a.name AS assignmentname,
                       c.fullname AS coursename, c.shortname AS courseshortname
                FROM {local_draftfeedback} df
                JOIN {course_modules} cm ON cm.id = df.cmid
                JOIN {assign} a ON a.id = cm.instance
                JOIN {course} c ON c.id = cm.course
                JOIN {user} u ON u.id = df.userid AND u.suspended = 0 AND u.deleted = 0
                WHERE df.status = :status
                AND a.name NOT LIKE '%IAG%'
                AND a.name NOT LIKE '%ID Proof%'
                AND a.name NOT LIKE '%Case Stud%'
                ORDER BY df.timecreated ASC";

        return $DB->get_records_sql($sql, ['status' => self::STATUS_PENDING]);
    }

    /**
     * Count pending drafts for a tutor.
     *
     * @param int $tutorid Tutor user ID
     * @return int Count of pending drafts
     */
    public static function count_pending_drafts_for_tutor($tutorid) {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT df.id)
                FROM {local_draftfeedback} df
                JOIN {course_modules} cm ON cm.id = df.cmid
                JOIN {assign} a ON a.id = cm.instance
                JOIN {course} c ON c.id = cm.course
                JOIN {user} u ON u.id = df.userid AND u.suspended = 0 AND u.deleted = 0
                JOIN {groups_members} gm_t ON gm_t.userid = :tutorid
                JOIN {groups} g ON g.id = gm_t.groupid AND g.courseid = c.id
                JOIN {groups_members} gm_s ON gm_s.groupid = g.id AND gm_s.userid = df.userid
                WHERE df.status = :status
                AND a.name NOT LIKE '%IAG%'
                AND a.name NOT LIKE '%ID Proof%'
                AND a.name NOT LIKE '%Case Stud%'";

        return $DB->count_records_sql($sql, [
            'tutorid' => $tutorid,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Count all pending drafts (global).
     *
     * @return int Count of pending drafts
     */
    public static function count_all_pending_drafts() {
        global $DB;

        $sql = "SELECT COUNT(DISTINCT df.id)
                FROM {local_draftfeedback} df
                JOIN {course_modules} cm ON cm.id = df.cmid
                JOIN {assign} a ON a.id = cm.instance
                JOIN {user} u ON u.id = df.userid AND u.suspended = 0 AND u.deleted = 0
                WHERE df.status = :status
                AND a.name NOT LIKE '%IAG%'
                AND a.name NOT LIKE '%ID Proof%'
                AND a.name NOT LIKE '%Case Stud%'";

        return $DB->count_records_sql($sql, ['status' => self::STATUS_PENDING]);
    }

    /**
     * Save tutor feedback on a draft.
     *
     * @param int $draftid Draft ID
     * @param string $feedback Feedback text
     * @param int $tutorid Tutor user ID
     * @return bool Success
     */
    public static function save_feedback($draftid, $feedback, $tutorid) {
        global $DB;

        $now = time();

        $draft = $DB->get_record('local_draftfeedback', ['id' => $draftid], '*', MUST_EXIST);

        $draft->feedback = $feedback;
        $draft->feedbackby = $tutorid;
        $draft->feedbacktime = $now;
        $draft->status = self::STATUS_REVIEWED;
        $draft->timemodified = $now;

        $result = $DB->update_record('local_draftfeedback', $draft);

        if ($result) {
            // Send notification to learner.
            self::notify_learner($draft);
        }

        return $result;
    }

    /**
     * Save AI check result for a draft.
     *
     * @param int $draftid Draft ID
     * @param string $result AI result (human, ai, mixed)
     * @param float $probability AI probability percentage
     * @param string|null $fulldata Full JSON response
     * @return bool Success
     */
    public static function save_aicheck_result($draftid, $result, $probability, $fulldata = null) {
        global $DB;

        return $DB->update_record('local_draftfeedback', [
            'id' => $draftid,
            'aicheck_result' => $result,
            'aicheck_probability' => $probability,
            'aicheck_data' => $fulldata,
            'timemodified' => time(),
        ]);
    }

    /**
     * Get drafts for a learner on a specific assignment.
     *
     * @param int $cmid Course module ID
     * @param int $userid User ID
     * @return array Array of draft records
     */
    public static function get_drafts_for_learner($cmid, $userid) {
        global $DB;

        return $DB->get_records('local_draftfeedback', [
            'cmid' => $cmid,
            'userid' => $userid,
        ], 'timecreated DESC');
    }

    /**
     * Check if learner has a pending draft for an assignment.
     *
     * @param int $cmid Course module ID
     * @param int $userid User ID
     * @return bool True if pending draft exists
     */
    public static function has_pending_draft($cmid, $userid) {
        global $DB;

        return $DB->record_exists('local_draftfeedback', [
            'cmid' => $cmid,
            'userid' => $userid,
            'status' => self::STATUS_PENDING,
        ]);
    }

    /**
     * Send notification to learner about feedback.
     *
     * @param object $draft Draft record
     */
    protected static function notify_learner($draft) {
        global $DB;

        $learner = $DB->get_record('user', ['id' => $draft->userid]);
        $tutor = $DB->get_record('user', ['id' => $draft->feedbackby]);
        $cm = get_coursemodule_from_id('assign', $draft->cmid);
        $course = $DB->get_record('course', ['id' => $cm->course]);
        $assign = $DB->get_record('assign', ['id' => $cm->instance]);

        $message = new \core\message\message();
        $message->component = 'local_draftfeedback';
        $message->name = 'draftfeedback';
        $message->userfrom = $tutor;
        $message->userto = $learner;
        $message->subject = get_string('notifylearner_subject', 'local_draftfeedback');
        $message->fullmessage = get_string('notifylearner_body', 'local_draftfeedback', [
            'assignment' => $assign->name,
            'course' => $course->fullname,
        ]);
        $message->fullmessageformat = FORMAT_PLAIN;
        $message->fullmessagehtml = '';
        $message->smallmessage = $message->subject;
        $message->notification = 1;
        $message->contexturl = new \moodle_url('/local/draftfeedback/view.php', ['id' => $draft->id]);
        $message->contexturlname = get_string('viewfeedback', 'local_draftfeedback');

        message_send($message);
    }

    /**
     * Get draft content (file or text) for display/scanning.
     *
     * @param object $draft Draft record
     * @return array ['type' => 'file'|'text', 'content' => string, 'filename' => string|null]
     */
    public static function get_draft_content($draft) {
        global $DB;

        // Check for file first.
        $context = \context_module::instance($draft->cmid);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'local_draftfeedback', 'draftfiles', $draft->id, 'filename', false);

        if ($files) {
            $file = reset($files);
            return [
                'type' => 'file',
                'content' => $file->get_content(),
                'filename' => $file->get_filename(),
                'mimetype' => $file->get_mimetype(),
                'file' => $file,
            ];
        }

        // Fall back to text.
        if (!empty($draft->drafttext)) {
            return [
                'type' => 'text',
                'content' => strip_tags($draft->drafttext),
                'filename' => null,
            ];
        }

        return [
            'type' => 'none',
            'content' => '',
            'filename' => null,
        ];
    }
}
