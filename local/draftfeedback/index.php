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
 * List drafts awaiting feedback (tutor view).
 *
 * @package    local_draftfeedback
 * @copyright  2026 Integer Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

use local_draftfeedback\manager;

require_login();

$context = context_system::instance();
$PAGE->set_url(new moodle_url('/local/draftfeedback/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('draftlist', 'local_draftfeedback'));
$PAGE->set_heading(get_string('draftlist', 'local_draftfeedback'));

// Check capability - either review (tutor) or viewall (manager).
$canreview = false;
$canviewall = has_capability('local/draftfeedback:viewall', $context);

// Check if user is a tutor (has teacher role anywhere).
$tutorrole = $DB->get_record('role', ['shortname' => 'teacher']);
if ($tutorrole) {
    $istutoranywhere = $DB->record_exists('role_assignments', [
        'userid' => $USER->id,
        'roleid' => $tutorrole->id,
    ]);
    if ($istutoranywhere) {
        $canreview = true;
    }
}

if (!$canreview && !$canviewall) {
    throw new moodle_exception('nopermission', 'local_draftfeedback');
}

// Get drafts based on role.
if ($canviewall) {
    $drafts = manager::get_all_pending_drafts();
} else {
    $drafts = manager::get_pending_drafts_for_tutor($USER->id);
}

// Prepare template context.
$draftlist = [];
foreach ($drafts as $draft) {
    $statusclass = 'df-status--' . $draft->status;
    $statustext = get_string('status_' . $draft->status, 'local_draftfeedback');

    $airesulthtml = '';
    if (!empty($draft->aicheck_result)) {
        $aiclass = 'df-ai-pill--' . $draft->aicheck_result;
        $prob = round($draft->aicheck_probability);
        $airesulthtml = html_writer::span(
            ucfirst($draft->aicheck_result) . " ({$prob}%)",
            "df-ai-pill {$aiclass}"
        );
    }

    $draftlist[] = [
        'id' => $draft->id,
        'learner_name' => fullname($draft),
        'assignment_name' => $draft->assignmentname,
        'course_name' => $draft->courseshortname,
        'submitted_date' => userdate($draft->timecreated, '%d %b %Y %H:%M'),
        'submitted_ts' => $draft->timecreated,
        'status' => $statustext,
        'status_class' => $statusclass,
        'ai_result_html' => $airesulthtml,
        'has_ai_result' => !empty($draft->aicheck_result),
        'view_url' => (new moodle_url('/local/draftfeedback/view.php', ['id' => $draft->id]))->out(),
        'feedback_url' => (new moodle_url('/local/draftfeedback/feedback.php', ['id' => $draft->id]))->out(),
    ];
}

$templatecontext = [
    'drafts' => $draftlist,
    'has_drafts' => !empty($draftlist),
    'no_drafts_message' => get_string('nodrafts', 'local_draftfeedback'),
    'wwwroot' => $CFG->wwwroot,
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_draftfeedback/draft_list', $templatecontext);
echo $OUTPUT->footer();
