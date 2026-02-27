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
 * Language strings for local_twiliosms.
 *
 * @package   local_twiliosms
 * @copyright 2025 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Twilio SMS';
$string['enabled'] = 'Enable SMS notifications';
$string['enabled_desc'] = 'Send an SMS with login credentials when a new user is registered.';
$string['accountsid'] = 'Twilio Account SID';
$string['accountsid_desc'] = 'Your Twilio Account SID from the Twilio Console.';
$string['authtoken'] = 'Twilio Auth Token';
$string['authtoken_desc'] = 'Your Twilio Auth Token (kept secret).';
$string['fromnumber'] = 'From phone number';
$string['fromnumber_desc'] = 'Your Twilio phone number in E.164 format (e.g. +447307280358).';
$string['messagetemplate'] = 'SMS message template';
$string['messagetemplate_desc'] = 'Message sent to new users. Placeholders: {firstname}, {lastname}, {username}. Max 160 characters (GSM-7). No emojis or special characters.';
$string['smslogs'] = 'SMS Logs';
$string['smslogs_desc'] = 'View all SMS send attempts.';
$string['viewsmslogs'] = 'View SMS Logs';
$string['loguser'] = 'User';
$string['logphone'] = 'Phone';
$string['logmessage'] = 'Message';
$string['logstatus'] = 'Status';
$string['logtwiliosid'] = 'Twilio SID';
$string['logerror'] = 'Error';
$string['logtime'] = 'Time';
$string['statussent'] = 'Sent';
$string['statusfailed'] = 'Failed';
$string['statusskipped'] = 'Skipped';
$string['nophone'] = 'No phone number provided';
$string['nonukphone'] = 'Non-UK phone number';
$string['charlimitwarning'] = 'Message exceeds 160 characters and will be truncated.';
$string['nolosfound'] = 'No SMS logs found.';
