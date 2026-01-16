<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/learner:view' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW
        ]
    ],
];