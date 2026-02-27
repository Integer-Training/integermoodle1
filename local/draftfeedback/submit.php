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
 * Submit draft for feedback page.
 *
 * @package    local_draftfeedback
 * @copyright  2026 Integer Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_once($CFG->dirroot . '/mod/assign/locallib.php');

use local_draftfeedback\manager;

$cmid = required_param('cmid', PARAM_INT);
$confirm = optional_param('confirm', 0, PARAM_BOOL);

// Get course module and assignment.
$cm = get_coursemodule_from_id('assign', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);
$assign = $DB->get_record('assign', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);

$context = context_module::instance($cm->id);
require_capability('local/draftfeedback:submit', $context);

$PAGE->set_url(new moodle_url('/local/draftfeedback/submit.php', ['cmid' => $cmid]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('submitdraft', 'local_draftfeedback'));
$PAGE->set_heading(get_string('submitdraft', 'local_draftfeedback'));

// Check if already has pending draft.
if (manager::has_pending_draft($cmid, $USER->id)) {
    redirect(
        new moodle_url('/mod/assign/view.php', ['id' => $cmid]),
        get_string('alreadypending', 'local_draftfeedback'),
        null,
        \core\output\notification::NOTIFY_WARNING
    );
}

// Create form.
require_once($CFG->libdir . '/formslib.php');

class local_draftfeedback_submit_form extends moodleform {
    protected function definition() {
        $mform = $this->_form;
        $cmid = $this->_customdata['cmid'];
        $context = $this->_customdata['context'];

        $mform->addElement('hidden', 'cmid', $cmid);
        $mform->setType('cmid', PARAM_INT);

        // Online text editor.
        $mform->addElement('editor', 'drafttext', get_string('onlinetext', 'local_draftfeedback'), [
            'rows' => 15,
        ]);
        $mform->setType('drafttext', PARAM_RAW);

        // File upload.
        $mform->addElement('filemanager', 'draftfiles', get_string('uploadfile', 'local_draftfeedback'), null, [
            'subdirs' => 0,
            'maxfiles' => 10,
            'accepted_types' => ['.pdf', '.doc', '.docx', '.txt', '.rtf'],
        ]);

        $mform->addElement('static', 'note', '', get_string('submitdraft_help', 'local_draftfeedback'));

        $this->add_action_buttons(true, get_string('submitdraftforfeedback', 'local_draftfeedback'));
    }

    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        // Check that either text or file is provided.
        $hastext = !empty($data['drafttext']['text']) && trim(strip_tags($data['drafttext']['text'])) !== '';
        $hasfiles = false;

        if (!empty($data['draftfiles'])) {
            $fs = get_file_storage();
            $usercontext = context_user::instance($this->_customdata['userid']);
            $files = $fs->get_area_files($usercontext->id, 'user', 'draft', $data['draftfiles'], 'filename', false);
            $hasfiles = !empty($files);
        }

        if (!$hastext && !$hasfiles) {
            $errors['drafttext'] = get_string('nofileordraft', 'local_draftfeedback');
        }

        return $errors;
    }
}

$form = new local_draftfeedback_submit_form(null, [
    'cmid' => $cmid,
    'context' => $context,
    'userid' => $USER->id,
]);

if ($form->is_cancelled()) {
    redirect(new moodle_url('/mod/assign/view.php', ['id' => $cmid]));
}

if ($data = $form->get_data()) {
    // Submit the draft.
    $drafttext = !empty($data->drafttext['text']) ? $data->drafttext['text'] : null;
    $draftitemid = !empty($data->draftfiles) ? $data->draftfiles : null;

    $draftid = manager::submit_draft($cmid, $USER->id, $drafttext, $draftitemid);

    redirect(
        new moodle_url('/mod/assign/view.php', ['id' => $cmid]),
        get_string('draftsubmitted', 'local_draftfeedback'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

echo $OUTPUT->header();

echo html_writer::tag('p', get_string('submitdraft_help', 'local_draftfeedback'), ['class' => 'lead']);

echo html_writer::start_div('card mb-4');
echo html_writer::start_div('card-header');
echo html_writer::tag('h5', $assign->name, ['class' => 'mb-0']);
echo html_writer::end_div();
echo html_writer::start_div('card-body');
echo html_writer::tag('p', format_text($assign->intro, $assign->introformat), ['class' => 'card-text']);
echo html_writer::end_div();
echo html_writer::end_div();

$form->display();

echo $OUTPUT->footer();
