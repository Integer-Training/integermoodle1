<?php

require_once(__DIR__.'/../../config.php');

$targetuserid = required_param('id', PARAM_INT);

require_login();
require_capability('moodle/user:loginas', context_system::instance());

$targetuser = core_user::get_user($targetuserid, '*', MUST_EXIST);

// Store original user
if (empty($SESSION->realuser)) {
    $SESSION->realuser = $USER->id;
    $SESSION->loginascontext = context_system::instance()->id;
    $SESSION->loginasreturnurl = new moodle_url('/my/');
}

// Switch user
\core\session\manager::set_user($targetuser);

// Redirect
redirect(new moodle_url('/my/'));
