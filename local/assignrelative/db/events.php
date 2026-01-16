<?php
defined('MOODLE_INTERNAL') || die();

$observers = [
    [
        'eventname'   => '\mod_assign\event\course_module_viewed',
        'callback'    => '\local_assignrelative\observer::adjust_dates',
        'includefile' => '/local/assignrelative/classes/observer.php',
        'internal'    => false,
        'priority'    => 9999
    ],
];
