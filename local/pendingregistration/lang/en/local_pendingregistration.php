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
 * Language strings.
 *
 * @package   local_pendingregistration
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Pending Registration';
$string['pendingregistration'] = 'Pending Registration';
$string['pendingregistration:view'] = 'View pending registration page';
$string['apikey'] = 'Pearl LMS API Key';
$string['apikey_desc'] = 'The x-api-key header value for Pearl LMS API.';
$string['apitoken'] = 'Pearl LMS Auth Token';
$string['apitoken_desc'] = 'The Bearer token for Pearl LMS API Authorization header.';
$string['apiurl'] = 'Pearl LMS API URL';
$string['apiurl_desc'] = 'Base URL for the Pearl LMS payment API endpoint.';
$string['sync_learners'] = 'Sync learner payment data from Pearl LMS';
$string['syncsuccess'] = 'Sync completed: {$a->synced} learners synced, {$a->skipped} skipped.';
$string['syncerror'] = 'Sync error: {$a}';
$string['syncnow'] = 'Sync Now';
$string['lastsynced'] = 'Last synced';
$string['norecords'] = 'No learners pending registration.';
