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

/**
 * "Login As" entry point used by the custom learner/tutor management UI.
 *
 * Wraps Moodle's standard \core\session\manager::loginas() which handles:
 *   - Cloning the current session into $_SESSION['REALSESSION']
 *   - Storing the real user in $_SESSION['REALUSER']
 *   - Marking the new $USER with realuser + loginascontext properties
 *   - Reloading enrolment state for the target user
 *   - Triggering the \core\event\user_loggedinas event
 *
 * The previous implementation called \core\session\manager::set_user()
 * directly — that only switches $USER for the current request and the
 * impersonation state was lost on the next page load (admin would be
 * silently restored, making the redirect appear to "do nothing").
 *
 * @package   local_learner
 */

require_once(__DIR__ . '/../../config.php');

$targetuserid = required_param('id', PARAM_INT);

require_login();

$systemcontext = context_system::instance();
require_capability('moodle/user:loginas', $systemcontext);

// No-op if you click "Login As" on yourself.
if ($targetuserid == $USER->id) {
    redirect(new moodle_url('/my/'));
}

// Don't allow a session to nest impersonations — bail back to the
// existing impersonated session if one is already active.
if (\core\session\manager::is_loggedinas()) {
    redirect(new moodle_url('/my/'));
}

// Moodle's official "log in as" routine. This is the only way to make
// impersonation persist across redirects and respect Moodle's
// capability/enrolment state.
\core\session\manager::loginas($targetuserid, $systemcontext);

redirect(new moodle_url('/my/'));
