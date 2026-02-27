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
 * SMS log viewer for local_twiliosms.
 *
 * @package   local_twiliosms
 * @copyright 2025 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

global $DB, $OUTPUT, $PAGE;

// Handle retry action.
$action = optional_param('action', '', PARAM_ALPHA);
$logid  = optional_param('logid', 0, PARAM_INT);

if ($action === 'retry' && $logid > 0 && confirm_sesskey()) {
    $log = $DB->get_record('local_twiliosms_log', ['id' => $logid]);
    if ($log) {
        $user = $DB->get_record('user', ['id' => $log->userid]);
        if ($user) {
            // Re-build message from current template.
            $template = get_config('local_twiliosms', 'messagetemplate');
            if (empty($template)) {
                $template = 'Integer Training - User: {username} Pass: Integer@123 Login: epearlacademy.com';
            }
            $message = str_replace(
                ['{firstname}', '{lastname}', '{username}', '{email}'],
                [$user->firstname, $user->lastname, $user->username, $user->email],
                $template
            );
            $message = \local_twiliosms\observer::sanitize_gsm7($message);

            // Format phone.
            $phone = \local_twiliosms\observer::format_uk_phone($user->phone1);
            if ($phone === false && !empty($log->phone)) {
                $phone = $log->phone; // Fall back to the phone stored in the log.
            }

            if ($phone) {
                $result = \local_twiliosms\observer::send_sms($phone, $message);
                if ($result['success']) {
                    \local_twiliosms\observer::log_sms($user->id, $phone, $message, 'sent', $result['sid'], '');
                } else {
                    \local_twiliosms\observer::log_sms($user->id, $phone, $message, 'failed', '', $result['error']);
                }
            }
        }
    }
    redirect(new moodle_url('/local/twiliosms/logs.php'));
}

$PAGE->set_url(new moodle_url('/local/twiliosms/logs.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_title(get_string('smslogs', 'local_twiliosms'));
$PAGE->set_heading(get_string('smslogs', 'local_twiliosms'));

// Fetch all logs with user info.
$sql = "SELECT l.*, u.firstname, u.lastname, u.username
          FROM {local_twiliosms_log} l
     LEFT JOIN {user} u ON u.id = l.userid
      ORDER BY l.timecreated DESC";
$logs = $DB->get_records_sql($sql);

$logdata = [];
foreach ($logs as $log) {
    $canretry = ($log->status === 'failed' || $log->status === 'skipped');
    $retryurl = $canretry
        ? (new moodle_url('/local/twiliosms/logs.php', [
            'action' => 'retry',
            'logid'  => $log->id,
            'sesskey' => sesskey(),
          ]))->out(false)
        : '';

    $logdata[] = [
        'fullname'    => ($log->firstname ?? '') . ' ' . ($log->lastname ?? ''),
        'username'    => $log->username ?? '',
        'phone'       => $log->phone,
        'message'     => $log->message,
        'status'      => $log->status,
        'is_sent'     => ($log->status === 'sent'),
        'is_failed'   => ($log->status === 'failed'),
        'is_skipped'  => ($log->status === 'skipped'),
        'can_retry'   => $canretry,
        'retry_url'   => $retryurl,
        'twilio_sid'  => $log->twilio_sid ?? '',
        'error_msg'   => $log->error_msg ?? '',
        'timecreated' => userdate($log->timecreated, '%d %b %Y %H:%M'),
    ];
}

$templatecontext = [
    'logs'     => $logdata,
    'haslogs'  => !empty($logdata),
    'settings_url' => (new moodle_url('/admin/settings.php', ['section' => 'local_twiliosms']))->out(false),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_twiliosms/logs', $templatecontext);
echo $OUTPUT->footer();
