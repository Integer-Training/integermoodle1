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
 * Email open tracking pixel endpoint.
 *
 * Returns a 1x1 transparent GIF and logs the first open with IP + timestamp.
 * No login required — this is hit from email clients.
 *
 * @package   local_commlogs
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

// @codingStandardsIgnoreLine
define('NO_MOODLE_COOKIES', true);
require_once(__DIR__ . '/../../config.php');

$token = optional_param('t', '', PARAM_ALPHANUM);

if (!empty($token)) {
    $record = $DB->get_record('local_commlogs_tracking', ['token' => $token]);
    if ($record && !$record->opened) {
        $ip = getremoteaddr();
        $DB->update_record('local_commlogs_tracking', (object)[
            'id'        => $record->id,
            'opened'    => 1,
            'opened_at' => time(),
            'opened_ip' => $ip,
        ]);
    }
}

// Serve 1x1 transparent GIF.
header('Content-Type: image/gif');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
// Minimal 1x1 transparent GIF (43 bytes).
echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
