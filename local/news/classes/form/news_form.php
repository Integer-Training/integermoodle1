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
 * News edit form.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_news\form;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/formslib.php');

/**
 * Form for creating/editing news items.
 */
class news_form extends \moodleform {

    /**
     * Define the form elements.
     */
    protected function definition() {
        $mform = $this->_form;

        // Hidden fields.
        $mform->addElement('hidden', 'id');
        $mform->setType('id', PARAM_INT);

        // Title.
        $mform->addElement('text', 'title', get_string('title', 'local_news'), ['size' => 80]);
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('error_titlerequired', 'local_news'), 'required', null, 'client');

        // Content editor.
        $mform->addElement('editor', 'content_editor', get_string('content', 'local_news'), null, [
            'maxfiles' => EDITOR_UNLIMITED_FILES,
            'noclean' => true,
            'context' => \context_system::instance(),
            'subdirs' => false,
        ]);
        $mform->setType('content_editor', PARAM_RAW);
        $mform->addRule('content_editor', get_string('error_contentrequired', 'local_news'), 'required', null, 'client');

        // Category with descriptions.
        $categories = \local_news\manager::get_categories();
        $mform->addElement('select', 'category', get_string('category', 'local_news'), $categories);
        $mform->setDefault('category', \local_news\manager::CATEGORY_ANNOUNCEMENT);
        $mform->addHelpButton('category', 'category', 'local_news');

        // Options section.
        $mform->addElement('header', 'optionsheader', get_string('publishingoptions', 'local_news'));

        // Important checkbox.
        $mform->addElement('advcheckbox', 'important', get_string('important', 'local_news'));
        $mform->addHelpButton('important', 'important', 'local_news');

        // Requires acknowledgement checkbox.
        $mform->addElement('advcheckbox', 'requires_acknowledgement', get_string('requiresacknowledgement', 'local_news'));
        $mform->addHelpButton('requires_acknowledgement', 'requiresacknowledgement', 'local_news');

        // Published checkbox.
        $mform->addElement('advcheckbox', 'published', get_string('published', 'local_news'));
        $mform->setDefault('published', 1);

        // Publish date.
        $mform->addElement('date_time_selector', 'publishdate', get_string('publishdate', 'local_news'));
        $mform->addHelpButton('publishdate', 'publishdate', 'local_news');
        $mform->setDefault('publishdate', time());

        // Expiry date (optional).
        $mform->addElement('date_time_selector', 'expirydate', get_string('expirydate', 'local_news'), ['optional' => true]);
        $mform->addHelpButton('expirydate', 'expirydate', 'local_news');

        // Submit buttons.
        $buttonarray = [];
        $buttonarray[] = $mform->createElement('submit', 'submitbutton', get_string('publish', 'local_news'));
        $buttonarray[] = $mform->createElement('submit', 'savedraft', get_string('saveasdraft', 'local_news'));
        $buttonarray[] = $mform->createElement('cancel');
        $mform->addGroup($buttonarray, 'buttonar', '', [' '], false);
    }

    /**
     * Validate the form data.
     *
     * @param array $data Form data
     * @param array $files Uploaded files
     * @return array Validation errors
     */
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);

        if (empty(trim($data['title']))) {
            $errors['title'] = get_string('error_titlerequired', 'local_news');
        }

        if (empty($data['content_editor']['text'])) {
            $errors['content_editor'] = get_string('error_contentrequired', 'local_news');
        }

        return $errors;
    }
}
