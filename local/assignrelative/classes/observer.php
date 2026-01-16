<?php
namespace local_assignrelative;

defined('MOODLE_INTERNAL') || die();

class observer {

    public static function adjust_dates($event) {
        global $DB, $USER;

        $cmid = $event->contextinstanceid;

        // Get assignment details
        $cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
        $assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);

        // Get user's enrolment date
        $ue = $DB->get_record_sql("
            SELECT ue.*
              FROM {user_enrolments} ue
              JOIN {enrol} e ON e.id = ue.enrolid
             WHERE ue.userid = :userid AND e.courseid = :courseid
             ORDER BY ue.timestart ASC
             LIMIT 1",
            ['userid' => $USER->id, 'courseid' => $cm->course]
        );

        if (!$ue) {
            return true; // no enrolment found
        }

        $enrolstart = $ue->timestart;

        // Example: open 1 day after enrolment, due 15 days after enrolment, cutoff 20 days
        $newopen   = $enrolstart + (1 * DAYSECS);
        $newdue    = $enrolstart + (15 * DAYSECS);
        $newcutoff = $enrolstart + (20 * DAYSECS);

        // Override in memory – affects what the student sees
        $assign->allowsubmissionsfromdate = $newopen;
        $assign->duedate = $newdue;
        $assign->cutoffdate = $newcutoff;

        // Update DB just for this user view (⚠️ optional)
        // Better: cache per user, but here we do direct override
        $DB->execute("UPDATE {assign}
                         SET allowsubmissionsfromdate = :open,
                             duedate = :due,
                             cutoffdate = :cutoff
                       WHERE id = :id",
            ['open' => $newopen, 'due' => $newdue, 'cutoff' => $newcutoff, 'id' => $assign->id]);

        return true;
    }
}
