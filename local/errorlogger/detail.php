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

require_once('../../config.php');
require_login();

global $DB, $OUTPUT, $PAGE;

$context = context_system::instance();
require_capability('local/errorlogger:view', $context);

$id = required_param('id', PARAM_INT);

$log = $DB->get_record('local_errorlogger_logs', ['id' => $id], '*', MUST_EXIST);

$PAGE->set_url(new moodle_url('/local/errorlogger/detail.php', ['id' => $id]));
$PAGE->set_context($context);
$PAGE->set_title(get_string('logdetail', 'local_errorlogger'));
$PAGE->set_heading(get_string('logdetail', 'local_errorlogger'));

// Highlight sidebar nav.
if ($node = $PAGE->navigation->find('local_errorlogger', navigation_node::TYPE_CUSTOM)) {
    $node->make_active();
}

$username = '';
if ($log->userid) {
    $user = $DB->get_record('user', ['id' => $log->userid], 'id, firstname, lastname', IGNORE_MISSING);
    if ($user) {
        $username = fullname($user);
    }
}

$severitynames = [1 => 'Critical', 2 => 'Warning', 3 => 'Notice', 4 => 'Debug'];
$severityclasses = [1 => 'danger', 2 => 'warning', 3 => 'info', 4 => 'secondary'];

// Parse details JSON for display.
$detailspretty = '';
if ($log->details) {
    $decoded = json_decode($log->details, true);
    if ($decoded) {
        $detailspretty = json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    } else {
        $detailspretty = $log->details;
    }
}

$templatecontext = [
    'id'            => $log->id,
    'type'          => $log->type,
    'severity'      => $severitynames[$log->severity] ?? 'Unknown',
    'sev_class'     => $severityclasses[$log->severity] ?? 'secondary',
    'component'     => $log->component ?: '-',
    'message'       => $log->message,
    'details'       => $detailspretty,
    'has_details'   => !empty($log->details),
    'url'           => $log->url ?: '-',
    'username'      => $username ?: 'System',
    'userid'        => $log->userid ?: '-',
    'ipaddress'     => $log->ipaddress ?: '-',
    'timecreated'   => userdate($log->timecreated, '%d %b %Y %H:%M:%S'),
    'back_url'      => (new moodle_url('/local/errorlogger/view.php'))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_errorlogger/detail', $templatecontext);
echo $OUTPUT->footer();
