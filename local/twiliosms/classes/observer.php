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
 * Event observer for local_twiliosms.
 *
 * Sends an SMS via Twilio when a new user is created.
 *
 * @package   local_twiliosms
 * @copyright 2025 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_twiliosms;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Handle user_created event.
     *
     * @param \core\event\user_created $event
     */
    public static function user_created(\core\event\user_created $event) {
        global $DB, $CFG;

        // Check if plugin is enabled.
        if (!get_config('local_twiliosms', 'enabled')) {
            return;
        }

        $userid = $event->objectid;
        $user = $DB->get_record('user', ['id' => $userid]);
        if (!$user) {
            return;
        }

        // Check phone number exists.
        $phone = trim($user->phone1 ?? '');
        if (empty($phone)) {
            self::log_sms($userid, '', '', 'skipped', '', get_string('nophone', 'local_twiliosms'));
            return;
        }

        // Format and validate UK number.
        $formatted = self::format_uk_phone($phone);
        if ($formatted === false) {
            self::log_sms($userid, $phone, '', 'skipped', '', get_string('nonukphone', 'local_twiliosms'));
            return;
        }

        // Build message from template.
        $template = get_config('local_twiliosms', 'messagetemplate');
        if (empty($template)) {
            $template = 'Integer Training - User: {username} Pass: Integer@123 Login: ' . parse_url($CFG->wwwroot, PHP_URL_HOST);
        }

        $message = str_replace(
            ['{firstname}', '{lastname}', '{username}', '{email}'],
            [$user->firstname, $user->lastname, $user->username, $user->email],
            $template
        );

        // Sanitize to GSM-7 and truncate to 160 chars.
        $message = self::sanitize_gsm7($message);

        // Send SMS.
        $result = self::send_sms($formatted, $message);

        if ($result['success']) {
            self::log_sms($userid, $formatted, $message, 'sent', $result['sid'], '');
        } else {
            self::log_sms($userid, $formatted, $message, 'failed', '', $result['error']);
        }
    }

    /**
     * Format a phone number to E.164 UK format.
     *
     * @param string $phone Raw phone input.
     * @return string|false Formatted +44 number, or false if not UK.
     */
    public static function format_uk_phone($phone) {
        // Strip spaces, dashes, parentheses.
        $phone = preg_replace('/[\s\-\(\)]/', '', $phone);

        // 07xxx -> +447xxx
        if (preg_match('/^0[0-9]+$/', $phone)) {
            $phone = '+44' . substr($phone, 1);
        }

        // 7xxx -> +447xxx (bare UK mobile without leading 0).
        if (preg_match('/^7[0-9]+$/', $phone)) {
            $phone = '+44' . $phone;
        }

        // 447xxx -> +447xxx
        if (preg_match('/^44[0-9]+$/', $phone)) {
            $phone = '+' . $phone;
        }

        // Must be +44 to be UK.
        if (strpos($phone, '+44') !== 0) {
            return false;
        }

        // Basic length check: +44 followed by 10 digits = 13 chars.
        if (strlen($phone) < 12 || strlen($phone) > 14) {
            return false;
        }

        return $phone;
    }

    /**
     * Strip non-GSM-7 characters and truncate to 160.
     *
     * @param string $message Raw message.
     * @return string Sanitized message.
     */
    public static function sanitize_gsm7($message) {
        // Allow only GSM-7 basic charset: letters, digits, basic punctuation.
        $message = preg_replace('/[^A-Za-z0-9 .,;:!?\'\"\-\/\(\)\+@&]/', '', $message);

        // Collapse multiple spaces.
        $message = preg_replace('/\s+/', ' ', trim($message));

        // Truncate to 160 characters.
        if (strlen($message) > 160) {
            $message = substr($message, 0, 160);
        }

        return $message;
    }

    /**
     * Send an SMS via Twilio REST API.
     *
     * @param string $to E.164 phone number.
     * @param string $body SMS body (max 160 chars).
     * @return array ['success' => bool, 'sid' => string, 'error' => string]
     */
    public static function send_sms($to, $body) {
        $sid = get_config('local_twiliosms', 'accountsid');
        $token = get_config('local_twiliosms', 'authtoken');
        $from = get_config('local_twiliosms', 'fromnumber');

        if (empty($sid) || empty($token) || empty($from)) {
            return ['success' => false, 'sid' => '', 'error' => 'Twilio credentials not configured'];
        }

        $url = 'https://api.twilio.com/2010-04-01/Accounts/' . $sid . '/Messages.json';

        // Explicitly use '&' separator — Moodle sets arg_separator.output to '&amp;'
        // which breaks POST data for external APIs.
        $postdata = http_build_query([
            'To'   => $to,
            'From' => $from,
            'Body' => $body,
        ], '', '&');

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $postdata,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => $sid . ':' . $token,
            CURLOPT_HTTPAUTH       => CURLAUTH_BASIC,
            CURLOPT_TIMEOUT        => 30,
        ]);

        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlerror = curl_error($ch);
        curl_close($ch);

        if ($curlerror) {
            return ['success' => false, 'sid' => '', 'error' => 'cURL error: ' . $curlerror];
        }

        $data = json_decode($response, true);

        if ($httpcode >= 200 && $httpcode < 300 && !empty($data['sid'])) {
            return ['success' => true, 'sid' => $data['sid'], 'error' => ''];
        }

        $errmsg = $data['message'] ?? ('HTTP ' . $httpcode);
        return ['success' => false, 'sid' => '', 'error' => $errmsg];
    }

    /**
     * Log an SMS attempt to the database.
     *
     * @param int $userid
     * @param string $phone
     * @param string $message
     * @param string $status sent|failed|skipped
     * @param string $twiliosid
     * @param string $error
     */
    public static function log_sms($userid, $phone, $message, $status, $twiliosid, $error) {
        global $DB;

        $record = new \stdClass();
        $record->userid     = $userid;
        $record->phone      = $phone;
        $record->message    = $message;
        $record->status     = $status;
        $record->twilio_sid = $twiliosid;
        $record->error_msg  = $error;
        $record->timecreated = time();

        $DB->insert_record('local_twiliosms_log', $record);
    }
}
