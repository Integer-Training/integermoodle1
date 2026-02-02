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
 * Google OAuth 2.0 callback endpoint.
 * Google redirects here with ?code=... after user grants consent.
 */

require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sitebackup:manage', $context);

$code = optional_param('code', '', PARAM_RAW);
$error = optional_param('error', '', PARAM_RAW);

$redirecturl = new moodle_url('/local/sitebackup/view.php');

if (!empty($error)) {
    redirect($redirecturl,
        get_string('driveerror', 'local_sitebackup', $error),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

if (empty($code)) {
    redirect($redirecturl,
        get_string('driveerror', 'local_sitebackup', 'No authorization code received'),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}

try {
    \local_sitebackup\google_drive::exchange_code($code);
    redirect($redirecturl,
        get_string('driveconnected', 'local_sitebackup'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
} catch (\Exception $e) {
    redirect($redirecturl,
        $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
