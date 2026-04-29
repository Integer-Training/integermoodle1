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

namespace local_sequentialsubmission;

defined('MOODLE_INTERNAL') || die();

/**
 * Core lock check logic.
 *
 * Decides whether a learner may submit a given assignment. Three rules stack,
 * reported in priority order:
 *
 *   2A — within the target course, all prior non-exempt units must be Passed
 *   1A — no other assignment may be Referred (learner must resubmit the refer first)
 *   4B — the learner may not have any other pending submission anywhere
 *
 * Admin / impersonation / temp-bypass / kill-switch / course-exempt /
 * assignment-exempt all short-circuit as "not locked".
 *
 * @package local_sequentialsubmission
 */
class lock_checker {

    /** Default exempt name patterns (case-insensitive). */
    const DEFAULT_EXEMPT_PATTERNS = ['IAG', 'ID Proof', 'Case Stud'];

    /** @var array Per-request cache to avoid re-querying during one page load. */
    protected static $cache = [];

    /**
     * Main entry: is this learner locked from submitting this assignment?
     *
     * @param int $userid Learner user ID
     * @param int $cmid   Course module ID of the target assignment
     * @return object {locked, reason, blocking_cmid, blocking_name, blocking_course, blocking_status, days_waiting}
     */
    public static function is_locked($userid, $cmid) {
        global $DB, $SESSION, $USER;

        $cachekey = "lock_{$userid}_{$cmid}";
        if (isset(self::$cache[$cachekey])) {
            return self::$cache[$cachekey];
        }

        $result = self::build_not_locked();

        // Kill switch.
        if (!self::is_enabled()) {
            return self::$cache[$cachekey] = $result;
        }

        // Bypass: site admin, :bypass capability, or mid-impersonation.
        if (self::user_should_bypass($userid)) {
            return self::$cache[$cachekey] = $result;
        }

        // Temp bypass (admin force-unlock).
        if (self::has_active_bypass($userid)) {
            return self::$cache[$cachekey] = $result;
        }

        // Resolve cm → assign → course.
        $cm = $DB->get_record_sql(
            "SELECT cm.id, cm.instance, cm.course, cm.visible, a.name AS assignname, c.fullname AS coursename
             FROM {course_modules} cm
             JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
             JOIN {assign} a ON a.id = cm.instance
             JOIN {course} c ON c.id = cm.course
             WHERE cm.id = :cmid",
            ['cmid' => $cmid]
        );

        if (!$cm) {
            return self::$cache[$cachekey] = $result; // Not an assignment cm, nothing to lock.
        }

        // Exempt course.
        if (self::is_course_exempt($cm->course)) {
            return self::$cache[$cachekey] = $result;
        }

        // Exempt assignment (by name pattern).
        if (self::is_assignment_exempt($cm->assignname)) {
            return self::$cache[$cachekey] = $result;
        }

        // Get all the learner's pending + referred submissions platform-wide.
        $blockers = self::get_blockers_for_user($userid, (int) $cm->instance);

        if (empty($blockers)) {
            return self::$cache[$cachekey] = $result;
        }

        // Priority 1: rule 2A — find the earliest-in-order blocker within THIS course.
        $incourse = array_filter($blockers, function($b) use ($cm) {
            return (int) $b->courseid === (int) $cm->course;
        });

        if (!empty($incourse)) {
            // Pick the one with the lowest sequence position (i.e. earliest unit in order).
            usort($incourse, function($a, $b) {
                if ((int) $a->sequence_pos === (int) $b->sequence_pos) {
                    return (int) $a->cmid <=> (int) $b->cmid;
                }
                return (int) $a->sequence_pos <=> (int) $b->sequence_pos;
            });
            $b = reset($incourse);
            $reason = ($b->gradestate === 'refer') ? 'rule_1a_refer' : 'rule_2a_order';
            return self::$cache[$cachekey] = self::build_locked($reason, $b);
        }

        // Priority 2: rule 4B — pending on another course.
        usort($blockers, function($a, $b) {
            return (int) $a->submission_time <=> (int) $b->submission_time;
        });
        $b = reset($blockers);
        return self::$cache[$cachekey] = self::build_locked('rule_4b_pending', $b);
    }

    /**
     * Get all blocking submissions for a user, platform-wide, excluding a target assignment.
     *
     * A blocker is a submission that is either:
     *   - pending (submitted but ungraded)
     *   - referred (graded Refer, not resubmitted yet)
     *
     * @param int $userid
     * @param int $exclude_assignid Assignment ID to exclude (so users can resubmit the thing they're on)
     * @return array
     */
    protected static function get_blockers_for_user($userid, $exclude_assignid) {
        global $DB;

        $patterns = self::get_exempt_patterns();
        $exclude_sql = '';
        $params = ['userid' => $userid, 'excludeassign' => $exclude_assignid];
        foreach ($patterns as $i => $p) {
            $key = "exp{$i}";
            $exclude_sql .= " AND a.name NOT LIKE :{$key}";
            $params[$key] = '%' . $p . '%';
        }

        $disabledcourses = self::get_disabled_courses();
        $disabled_sql = '';
        if (!empty($disabledcourses)) {
            [$din, $dinparams] = $DB->get_in_or_equal($disabledcourses, SQL_PARAMS_NAMED, 'dis', false);
            $disabled_sql = " AND a.course {$din}";
            $params = array_merge($params, $dinparams);
        }

        $sql = "SELECT sub.id AS subid, sub.assignment AS assignid, sub.attemptnumber, sub.timemodified AS submission_time,
                       cm.id AS cmid, cm.course AS courseid, cm.section AS sectionid,
                       a.name AS assignname, c.fullname AS coursename, cs.sequence AS section_sequence, cs.section AS section_number,
                       gr.grade AS grade_value, gi.scaleid, sc.scale AS scale_values
                FROM {assign_submission} sub
                JOIN {assign} a ON a.id = sub.assignment
                JOIN {course} c ON c.id = a.course
                JOIN {modules} m ON m.name = 'assign'
                JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = m.id AND cm.course = a.course AND cm.visible = 1
                JOIN {course_sections} cs ON cs.id = cm.section
                JOIN {user_enrolments} ue ON ue.userid = sub.userid
                JOIN {enrol} en ON en.id = ue.enrolid AND en.courseid = a.course AND en.status = 0
                LEFT JOIN {assign_grades} gr ON gr.assignment = a.id AND gr.userid = sub.userid
                     AND gr.attemptnumber = sub.attemptnumber
                LEFT JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
                LEFT JOIN {scale} sc ON sc.id = gi.scaleid
                WHERE sub.userid = :userid
                  AND sub.latest = 1
                  AND sub.status = 'submitted'
                  AND sub.assignment <> :excludeassign
                  AND ue.status = 0
                  {$exclude_sql}
                  {$disabled_sql}
                ORDER BY cm.course, cs.section, sub.timemodified";

        $rows = $DB->get_records_sql($sql, $params);

        $blockers = [];
        foreach ($rows as $r) {
            $state = self::derive_grade_state($r->grade_value, $r->scaleid, $r->scale_values);

            // Only include rows that actually block (pending or referred — not Pass).
            if ($state === 'pass') {
                continue;
            }

            $r->gradestate = $state; // 'ungraded' | 'refer' | 'graded_other'
            $r->sequence_pos = self::position_in_sequence($r->section_number, $r->section_sequence, $r->cmid);
            $r->days_waiting = max(0, (int) floor((time() - $r->submission_time) / 86400));
            $blockers[] = $r;
        }

        // Rule 2A also requires: gaps in Pass. A learner might have Unit 1 Pass but never
        // submitted Unit 2 → they're asking to submit Unit 5. Unit 2,3,4 are gaps.
        // For the TARGET assignment only, we need to verify all prior units are Pass.
        // That check is added on top by the caller using prior_unit_blocker().

        return $blockers;
    }

    /**
     * Rule 2A explicit check: is there an earlier-in-sequence unit in the same course
     * that has never been Passed? Returns the blocking cm or null.
     *
     * @param int $userid
     * @param int $target_cmid
     * @return object|null
     */
    public static function prior_unit_blocker($userid, $target_cmid) {
        global $DB;

        // Get target's section + position.
        $target = $DB->get_record_sql(
            "SELECT cm.id, cm.course, cm.section, cm.instance, cs.sequence, cs.section AS section_number, a.name
             FROM {course_modules} cm
             JOIN {course_sections} cs ON cs.id = cm.section
             JOIN {assign} a ON a.id = cm.instance
             WHERE cm.id = :cmid",
            ['cmid' => $target_cmid]
        );
        if (!$target) {
            return null;
        }

        $target_pos = self::position_in_sequence($target->section_number, $target->sequence, $target_cmid);

        // Get every assignment in this course with its ordering + grade state.
        $patterns = self::get_exempt_patterns();
        $params = ['courseid' => $target->course, 'userid' => $userid];
        $excl = '';
        foreach ($patterns as $i => $p) {
            $k = "ep{$i}";
            $excl .= " AND a.name NOT LIKE :{$k}";
            $params[$k] = '%' . $p . '%';
        }

        $sql = "SELECT cm.id AS cmid, cm.instance, a.name, cs.section AS section_number, cs.sequence,
                       sub.id AS subid, sub.status AS substatus, sub.attemptnumber, sub.timemodified AS submission_time,
                       gr.grade AS grade_value, gi.scaleid, sc.scale AS scale_values
                FROM {course_modules} cm
                JOIN {course_sections} cs ON cs.id = cm.section
                JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
                JOIN {assign} a ON a.id = cm.instance
                LEFT JOIN {assign_submission} sub ON sub.assignment = a.id AND sub.userid = :userid AND sub.latest = 1
                LEFT JOIN {assign_grades} gr ON gr.assignment = a.id AND gr.userid = :userid
                    AND gr.attemptnumber = sub.attemptnumber
                LEFT JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
                LEFT JOIN {scale} sc ON sc.id = gi.scaleid
                WHERE cm.course = :courseid
                  AND cm.visible = 1
                  AND cm.id <> {$target_cmid}
                  {$excl}";

        $rows = $DB->get_records_sql($sql, $params);

        $earliest_blocker = null;
        $earliest_pos = PHP_INT_MAX;

        foreach ($rows as $r) {
            $pos = self::position_in_sequence($r->section_number, $r->sequence, $r->cmid);
            if ($pos >= $target_pos) {
                continue; // Only EARLIER units block.
            }
            $state = self::derive_grade_state($r->grade_value, $r->scaleid, $r->scale_values);
            if ($state === 'pass') {
                continue;
            }
            // Not Pass → blocking. Keep the earliest one.
            if ($pos < $earliest_pos) {
                $earliest_pos = $pos;

                $obj = new \stdClass();
                $obj->cmid = (int) $r->cmid;
                $obj->assignid = (int) $r->instance;
                $obj->assignname = $r->name;
                $obj->coursename = null;
                $obj->courseid = (int) $target->course;
                $obj->gradestate = $state;
                $obj->submission_time = (int) ($r->submission_time ?? 0);
                $obj->days_waiting = $r->submission_time ? max(0, (int) floor((time() - $r->submission_time) / 86400)) : 0;
                $obj->sequence_pos = $pos;
                $earliest_blocker = $obj;
            }
        }

        return $earliest_blocker;
    }

    /**
     * Full lock check including rule 2A prior-unit check.
     * Merges get_blockers_for_user + prior_unit_blocker.
     *
     * @param int $userid
     * @param int $cmid
     * @return object
     */
    public static function check($userid, $cmid) {
        $primary = self::is_locked($userid, $cmid);

        // If already locked by rule 1A / 4B, that's surfaced.
        if ($primary->locked) {
            // But maybe rule 2A finds an EARLIER blocker in the same course.
            $earlier = self::prior_unit_blocker($userid, $cmid);
            if ($earlier && $earlier->sequence_pos < ($primary->sequence_pos ?? PHP_INT_MAX)) {
                return self::build_locked(
                    $earlier->gradestate === 'refer' ? 'rule_1a_refer' : 'rule_2a_order',
                    $earlier
                );
            }
            return $primary;
        }

        // Not locked by 1A/4B — check rule 2A (prior unit never Passed).
        $earlier = self::prior_unit_blocker($userid, $cmid);
        if ($earlier) {
            return self::build_locked(
                $earlier->gradestate === 'refer' ? 'rule_1a_refer' : 'rule_2a_order',
                $earlier
            );
        }

        return self::build_not_locked();
    }

    /**
     * Derive grade state from a raw grade value + scale values string.
     *
     * @param mixed $gradevalue From assign_grades.grade (null, -1, or a number)
     * @param int|null $scaleid
     * @param string|null $scalevalues Comma-separated scale items e.g. "Refer,Pass"
     * @return string 'ungraded' | 'pass' | 'refer' | 'graded_other'
     */
    public static function derive_grade_state($gradevalue, $scaleid, $scalevalues) {
        if ($gradevalue === null || $gradevalue === '' || (float) $gradevalue < 0) {
            return 'ungraded';
        }

        // Scale-based grading (e.g. Refer/Pass).
        if (!empty($scaleid) && !empty($scalevalues)) {
            $options = array_map('trim', explode(',', $scalevalues));
            $position = (int) round((float) $gradevalue);

            // Moodle scales are 1-indexed: position 1 = first option, etc.
            if ($position >= 1 && $position <= count($options)) {
                $label = $options[$position - 1];
                if (strcasecmp($label, 'Pass') === 0) {
                    return 'pass';
                }
                if (strcasecmp($label, 'Refer') === 0) {
                    return 'refer';
                }
                // Other scale values (shouldn't exist on this platform but defensive).
                return 'graded_other';
            }
            return 'ungraded';
        }

        // Point-based grading: treat any positive grade as Pass (best-effort for non-Pass/Refer assignments).
        if ((float) $gradevalue > 0) {
            return 'pass';
        }
        return 'ungraded';
    }

    /**
     * Compute a sortable integer for ordering assignments within a course.
     * Uses: section_number * 10000 + position within section.sequence.
     *
     * @param int $sectionnumber course_sections.section
     * @param string|null $sequence course_sections.sequence (comma-separated cmids)
     * @param int $cmid
     * @return int
     */
    public static function position_in_sequence($sectionnumber, $sequence, $cmid) {
        $sectionnumber = (int) $sectionnumber;
        $position_in_section = 0;
        if (!empty($sequence)) {
            $ids = array_map('intval', explode(',', $sequence));
            $idx = array_search((int) $cmid, $ids, true);
            if ($idx !== false) {
                $position_in_section = (int) $idx;
            }
        }
        return ($sectionnumber * 10000) + $position_in_section;
    }

    /**
     * Is the plugin enabled at all?
     */
    public static function is_enabled(): bool {
        return (int) get_config('local_sequentialsubmission', 'enable_lock') === 1;
    }

    /**
     * Get exempt name patterns (defaults + admin extras).
     */
    public static function get_exempt_patterns(): array {
        $configured = get_config('local_sequentialsubmission', 'exempt_patterns');
        $patterns = self::DEFAULT_EXEMPT_PATTERNS;
        if (!empty($configured)) {
            $extra = array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $configured)));
            $patterns = array_merge($patterns, $extra);
        }
        return array_values(array_unique($patterns));
    }

    /**
     * Get disabled (exempt) course IDs.
     */
    public static function get_disabled_courses(): array {
        $configured = get_config('local_sequentialsubmission', 'per_course_disable');
        if (empty($configured)) {
            return [];
        }
        return array_filter(array_map('intval', preg_split('/[\s,]+/', $configured)));
    }

    /**
     * Should this user bypass the lock entirely?
     */
    public static function user_should_bypass($userid): bool {
        global $USER, $SESSION;

        if (is_siteadmin($userid)) {
            return true;
        }
        // Admin/tutor impersonating a learner: realuser is set on $SESSION.
        if (!empty($SESSION->realuser) && (int) $userid === (int) $USER->id) {
            return true;
        }
        try {
            $context = \context_system::instance();
            if (has_capability('local/sequentialsubmission:bypass', $context, $userid)) {
                return true;
            }
        } catch (\Exception $e) {
            // Context not ready (early in request lifecycle) — treat as no bypass.
        }
        return false;
    }

    /**
     * Does the learner have an active admin-granted bypass?
     * Bypass auto-expires by TTL or when the blocking submission is marked.
     */
    public static function has_active_bypass($userid): bool {
        global $DB;

        $rows = $DB->get_records_select(
            'local_ss_bypass',
            'userid = :userid AND active = 1 AND expiresat > :now',
            ['userid' => $userid, 'now' => time()]
        );
        if (empty($rows)) {
            return false;
        }

        // Check if the blocking submission has been marked → auto-expire.
        foreach ($rows as $row) {
            if (empty($row->blocking_cmid)) {
                return true; // No specific blocker tied: pure TTL.
            }
            $state = self::get_assignment_grade_state_for_user($userid, $row->blocking_cmid);
            if ($state !== 'pass') {
                return true; // Still not Pass → bypass still applies.
            }
            // Otherwise: blocker is Pass, consume the bypass.
            $DB->set_field('local_ss_bypass', 'active', 0, ['id' => $row->id]);
        }
        return false;
    }

    /**
     * Get grade state for a user+cmid.
     */
    public static function get_assignment_grade_state_for_user($userid, $cmid): string {
        global $DB;

        $row = $DB->get_record_sql(
            "SELECT gr.grade AS grade_value, gi.scaleid, sc.scale AS scale_values
             FROM {course_modules} cm
             JOIN {assign} a ON a.id = cm.instance
             LEFT JOIN {assign_submission} sub ON sub.assignment = a.id AND sub.userid = :userid AND sub.latest = 1
             LEFT JOIN {assign_grades} gr ON gr.assignment = a.id AND gr.userid = :userid2
                  AND gr.attemptnumber = sub.attemptnumber
             LEFT JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
             LEFT JOIN {scale} sc ON sc.id = gi.scaleid
             WHERE cm.id = :cmid",
            ['userid' => $userid, 'userid2' => $userid, 'cmid' => $cmid]
        );
        if (!$row) {
            return 'ungraded';
        }
        return self::derive_grade_state($row->grade_value, $row->scaleid, $row->scale_values);
    }

    /**
     * Is a course exempt from the rule?
     */
    public static function is_course_exempt($courseid): bool {
        return in_array((int) $courseid, self::get_disabled_courses(), true);
    }

    /**
     * Does the assignment name match an exempt pattern?
     */
    public static function is_assignment_exempt($name): bool {
        foreach (self::get_exempt_patterns() as $p) {
            if (stripos((string) $name, $p) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * Get pending submissions for a user (for dashboard display).
     *
     * @param int $userid
     * @return array
     */
    public static function get_pending_for($userid): array {
        global $DB;

        $patterns = self::get_exempt_patterns();
        $params = ['userid' => $userid];
        $excl = '';
        foreach ($patterns as $i => $p) {
            $k = "ep{$i}";
            $excl .= " AND a.name NOT LIKE :{$k}";
            $params[$k] = '%' . $p . '%';
        }

        $sql = "SELECT sub.id AS subid, sub.assignment AS assignid, sub.timemodified,
                       cm.id AS cmid, cm.course AS courseid,
                       a.name AS assignname, c.fullname AS coursename,
                       gr.grade AS grade_value, gi.scaleid, sc.scale AS scale_values
                FROM {assign_submission} sub
                JOIN {assign} a ON a.id = sub.assignment
                JOIN {course} c ON c.id = a.course
                JOIN {modules} m ON m.name = 'assign'
                JOIN {course_modules} cm ON cm.instance = a.id AND cm.module = m.id AND cm.visible = 1
                JOIN {user_enrolments} ue ON ue.userid = sub.userid
                JOIN {enrol} en ON en.id = ue.enrolid AND en.courseid = a.course AND en.status = 0
                LEFT JOIN {assign_grades} gr ON gr.assignment = a.id AND gr.userid = sub.userid
                     AND gr.attemptnumber = sub.attemptnumber
                LEFT JOIN {grade_items} gi ON gi.iteminstance = a.id AND gi.itemmodule = 'assign'
                LEFT JOIN {scale} sc ON sc.id = gi.scaleid
                WHERE sub.userid = :userid
                  AND sub.latest = 1
                  AND sub.status = 'submitted'
                  AND ue.status = 0
                  {$excl}
                ORDER BY sub.timemodified ASC";

        $rows = $DB->get_records_sql($sql, $params);

        $pending = [];
        foreach ($rows as $r) {
            $state = self::derive_grade_state($r->grade_value, $r->scaleid, $r->scale_values);
            if ($state === 'pass') {
                continue;
            }
            $pending[] = (object) [
                'cmid' => (int) $r->cmid,
                'assignid' => (int) $r->assignid,
                'assignname' => $r->assignname,
                'coursename' => $r->coursename,
                'courseid' => (int) $r->courseid,
                'gradestate' => $state,
                'submission_time' => (int) $r->timemodified,
                'days_waiting' => max(0, (int) floor((time() - $r->timemodified) / 86400)),
            ];
        }
        return $pending;
    }

    /**
     * Append an entry to local_ss_log.
     */
    public static function log_event($userid, $action, $data = []) {
        global $DB;

        $rec = (object) [
            'userid' => $userid,
            'action' => $action,
            'blocked_cmid' => $data['blocked_cmid'] ?? null,
            'blocking_cmid' => $data['blocking_cmid'] ?? null,
            'courseid' => $data['courseid'] ?? null,
            'reason' => $data['reason'] ?? null,
            'initiatedby' => $data['initiatedby'] ?? null,
            'notes' => $data['notes'] ?? null,
            'timecreated' => time(),
        ];
        $DB->insert_record('local_ss_log', $rec);
    }

    /* ---------- Result builders ---------- */

    protected static function build_not_locked() {
        $o = new \stdClass();
        $o->locked = false;
        return $o;
    }

    protected static function build_locked($reason, $blocker) {
        $o = new \stdClass();
        $o->locked = true;
        $o->reason = $reason;
        $o->blocking_cmid = $blocker->cmid ?? null;
        $o->blocking_assignid = $blocker->assignid ?? null;
        $o->blocking_name = $blocker->assignname ?? '';
        $o->blocking_course = $blocker->coursename ?? '';
        $o->blocking_courseid = $blocker->courseid ?? null;
        $o->gradestate = $blocker->gradestate ?? 'ungraded';
        $o->blocking_status = ($blocker->gradestate ?? 'ungraded') === 'refer'
            ? get_string('status_referred', 'local_sequentialsubmission')
            : get_string('status_awaiting', 'local_sequentialsubmission');
        $o->days_waiting = $blocker->days_waiting ?? 0;
        $o->sequence_pos = $blocker->sequence_pos ?? null;
        return $o;
    }
}
