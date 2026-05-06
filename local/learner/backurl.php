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
 * Cancel "Login As" — restore the original admin/tutor session.
 *
 * Moodle's \core\session\manager stores the real user/session inside
 * $_SESSION['REALUSER'] / $_SESSION['REALSESSION'] (uppercase), set up
 * by ::loginas(). The previous implementation looked at
 * $SESSION->realuser (lowercase property) which was never populated
 * by ::loginas(), so this page silently redirected with no effect.
 *
 * @package   local_learner
 */

require_once(__DIR__ . '/../../config.php');

require_login();

// If the session isn't currently impersonating, just go home.
if (!\core\session\manager::is_loggedinas()) {
    redirect(new moodle_url('/my/'));
}

// Restore the cloned-away session.
if (isset($_SESSION['REALSESSION'])) {
    $GLOBALS['SESSION'] = clone($_SESSION['REALSESSION']);
    $_SESSION['SESSION'] =& $GLOBALS['SESSION'];
    unset($_SESSION['REALSESSION']);
}

// Restore the real user.
if (isset($_SESSION['REALUSER'])) {
    $GLOBALS['USER'] = clone($_SESSION['REALUSER']);
    $_SESSION['USER'] =& $GLOBALS['USER'];
    unset($_SESSION['REALUSER']);
}

// Lift any leftover lowercase legacy keys from the previous custom
// implementation, in case someone still has them in their session.
unset(
    $SESSION->realuser,
    $SESSION->loginascontext,
    $SESSION->loginasreturnurl
);

redirect(new moodle_url('/my/'));
