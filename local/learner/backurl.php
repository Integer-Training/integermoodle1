<?php

require_once(__DIR__.'/../../config.php');

require_login();

if (empty($SESSION->realuser)) {
    redirect(new moodle_url('/'));
}

$realuser = core_user::get_user($SESSION->realuser, '*', MUST_EXIST);

// Restore user
\core\session\manager::set_user($realuser);

// Cleanup
unset(
    $SESSION->realuser,
    $SESSION->loginascontext,
    $SESSION->loginasreturnurl
);

redirect(new moodle_url('/my/'));
