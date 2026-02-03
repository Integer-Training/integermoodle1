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
 * forms used by the gptzero plagiarism plugin.
 *
 * @package    plagiarism_gptzero
 * @copyright  2024 GPTZero <team@gptzero.me>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot.'/lib/formslib.php');

/**
 * Form for setting up the GPTZero plagiarism plugin configurations.
 * This form allows administrators to enable or disable the plugin, set API keys,
 * configure disclosures for students, and specify which Moodle modules (like Assignments or Forums)
 * should use GPTZero for plagiarism detection.
 */
class plagiarism_setup_form extends moodleform {
    /**
     * Defines the form elements for the GPTZero plagiarism plugin setup.
     */
    public function definition() {
        $mform =& $this->_form;

        $mform->addElement('html', get_string('gptzeroexplain', 'plagiarism_gptzero'));

        $mform->addElement('advcheckbox', 'gptzero_enabled', get_string('enableplugin', 'plagiarism_gptzero'));
        $mform->setDefault('gptzero_enabled', 0);

        $mform->addElement('textarea', 'gptzero_student_disclosure', get_string('studentdisclosure', 'plagiarism_gptzero'),
                           'wrap="virtual" rows="6" cols="50"');
        $mform->addHelpButton('gptzero_student_disclosure', 'studentdisclosure', 'plagiarism_gptzero');
        $mform->setDefault('gptzero_student_disclosure', get_string('studentdisclosuredefault', 'plagiarism_gptzero'));

        $supportedmodules = ['assign', 'forum'];
        foreach ($supportedmodules as $module) {
            $mform->addElement(
                'advcheckbox',
                'gptzero_mod_' . $module,
                get_string('gptzero_enableplugin', 'plagiarism_gptzero', ucfirst($module == 'assign' ? 'Assignment' : $module))
            );
        }

        $mform->addElement('passwordunmask', 'gptzero_apikey', get_string('apikey', 'plagiarism_gptzero'));
        $mform->setType('gptzero_apikey', PARAM_TEXT);
        $mform->addRule('gptzero_apikey', get_string('required'), 'required');

        $mform->addElement('html', '<div class="form-group row fitem"><div class="col-md-12 col-form-label">'
                            .get_string("apikeyhelp", "plagiarism_gptzero"));

        $this->add_action_buttons(true);
    }
}

