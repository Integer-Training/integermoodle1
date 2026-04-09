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
 * Pending Registration — main page showing learners awaiting registration.
 *
 * @package   local_pendingregistration
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/pendingregistration:view', $context);

$PAGE->set_url(new moodle_url('/local/pendingregistration/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('pendingregistration', 'local_pendingregistration'));
$PAGE->set_heading(get_string('pendingregistration', 'local_pendingregistration'));
$PAGE->set_pagelayout('standard');

$PAGE->requires->jquery();

echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';

echo $OUTPUT->header();

// Fetch pending records: active, not yet registered.
$records = $DB->get_records_select(
    'local_pendingregistration',
    'registration_check = 0 AND is_active = 1',
    null,
    'learner_name ASC'
);

// Get last sync time.
$last_synced = $DB->get_field_sql(
    "SELECT MAX(last_synced) FROM {local_pendingregistration}"
);

// Build template data.
$learners = [];
foreach ($records as $r) {
    $learners[] = [
        'id' => (int) $r->id,
        'learner_id' => $r->learner_id,
        'learner_name' => $r->learner_name,
        'learner_email' => $r->learner_email,
        'company' => $r->company ?: '-',
        'paid_to_date' => number_format((float) $r->paid_to_date, 2),
        'outstanding' => number_format((float) $r->outstanding, 2),
        'subscription_status' => ucfirst($r->subscription_status),
        'registration_check' => (int) $r->registration_check,
        'tutor_check' => (int) $r->tutor_check,
        'admin_check' => (int) $r->admin_check,
        'notes' => $r->notes ?? '',
        'links' => $r->links ?? '',
        'sub_active' => in_array(strtolower($r->subscription_status), ['active', 'trialing']),
    ];
}

$templatecontext = [
    'learners' => $learners,
    'has_learners' => !empty($learners),
    'total_count' => count($learners),
    'sync_url' => (new moodle_url('/local/pendingregistration/sync.php', ['sesskey' => sesskey()]))->out(false),
    'ajax_url' => (new moodle_url('/local/pendingregistration/ajax.php'))->out(false),
    'sesskey' => sesskey(),
    'last_synced' => $last_synced ? userdate($last_synced, '%d %b %Y, %H:%M') : 'Never',
    'norecords' => get_string('norecords', 'local_pendingregistration'),
];

echo $OUTPUT->render_from_template('local_pendingregistration/pending_table', $templatecontext);

echo $OUTPUT->footer();
