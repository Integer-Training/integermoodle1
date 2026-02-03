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

require_once(dirname(dirname(__FILE__)) . '/../config.php');
require_once($CFG->libdir.'/adminlib.php');
require_once($CFG->libdir.'/plagiarismlib.php');
require_once($CFG->dirroot.'/plagiarism/gptzero/lib.php');
require_once($CFG->dirroot.'/plagiarism/gptzero/plagiarism_form.php');

require_login();
admin_externalpage_setup('plagiarismgptzero');

$context = context_system::instance();

require_capability('moodle/site:config', $context, $USER->id, true, "nopermissions");

require_once('plagiarism_form.php');
$mform = new plagiarism_setup_form();
$plagiarismplugin = new plagiarism_plugin_gptzero();

if ($mform->is_cancelled()) {
    redirect('');
}

echo $OUTPUT->header();

if (($data = $mform->get_data()) && confirm_sesskey()) {
    if (isset($data->gptzero_enabled)) {
        set_config('enabled', $data->gptzero_enabled, 'plagiarism_gptzero');
    }

    foreach ($data as $field => $value) {
        if (strpos($field, 'gptzero') === 0) {
            set_config($field, $value, 'plagiarism_gptzero');
        }
    }
    cache_helper::invalidate_by_definition('core', 'config', [], 'plagiarism_gptzero');
    echo $OUTPUT->notification(get_string('savedconfigsuccess', 'plagiarism_gptzero'), 'notifysuccess');
}
$plagiarismsettings = array_merge((array)get_config('plagiarism'), (array)get_config('plagiarism_gptzero'));
$mform->set_data($plagiarismsettings);

echo $OUTPUT->box_start('generalbox boxaligncenter', 'intro');
$mform->display();
echo $OUTPUT->box_end();
echo $OUTPUT->footer();
