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
 * Manual sync trigger — fetches latest data from Pearl LMS API.
 *
 * @package   local_pendingregistration
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();
require_capability('local/pendingregistration:view', context_system::instance());
require_sesskey();

$manager = new \local_pendingregistration\sync_manager();
$result = $manager->sync_all();

$msg = get_string('syncsuccess', 'local_pendingregistration', (object) $result);
redirect(
    new moodle_url('/local/pendingregistration/index.php'),
    $msg,
    null,
    \core\output\notification::NOTIFY_SUCCESS
);
