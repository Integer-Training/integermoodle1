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
 * Admin settings page for Sequential Submission.
 *
 * @package   local_sequentialsubmission
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage(
        'local_sequentialsubmission',
        get_string('settings', 'local_sequentialsubmission')
    );

    // Kill switch.
    $settings->add(new admin_setting_configcheckbox(
        'local_sequentialsubmission/enable_lock',
        get_string('enable_lock', 'local_sequentialsubmission'),
        get_string('enable_lock_desc', 'local_sequentialsubmission'),
        1
    ));

    // Stuck-learner SLA (days).
    $settings->add(new admin_setting_configtext(
        'local_sequentialsubmission/sla_days',
        get_string('sla_days', 'local_sequentialsubmission'),
        get_string('sla_days_desc', 'local_sequentialsubmission'),
        7,
        PARAM_INT,
        5
    ));

    // Force-unlock TTL (hours).
    $settings->add(new admin_setting_configtext(
        'local_sequentialsubmission/force_unlock_ttl_hours',
        get_string('force_unlock_ttl_hours', 'local_sequentialsubmission'),
        get_string('force_unlock_ttl_hours_desc', 'local_sequentialsubmission'),
        24,
        PARAM_INT,
        5
    ));

    // Per-course disable list.
    $settings->add(new admin_setting_configtext(
        'local_sequentialsubmission/per_course_disable',
        get_string('per_course_disable', 'local_sequentialsubmission'),
        get_string('per_course_disable_desc', 'local_sequentialsubmission'),
        '',
        PARAM_TEXT,
        60
    ));

    // Exempt patterns.
    $settings->add(new admin_setting_configtextarea(
        'local_sequentialsubmission/exempt_patterns',
        get_string('exempt_patterns', 'local_sequentialsubmission'),
        get_string('exempt_patterns_desc', 'local_sequentialsubmission'),
        '', // Default patterns (IAG, ID Proof, Case Stud) are baked into lock_checker::DEFAULT_EXEMPT_PATTERNS.
        PARAM_TEXT
    ));

    // Link to the manage page.
    $manageurl = new moodle_url('/local/sequentialsubmission/index.php');
    $settings->add(new admin_setting_heading(
        'local_sequentialsubmission/manageheading',
        get_string('managelocks', 'local_sequentialsubmission'),
        '<a href="' . $manageurl->out() . '" class="btn btn-primary">' .
        get_string('managelocks', 'local_sequentialsubmission') .
        '</a>'
    ));

    $ADMIN->add('localplugins', $settings);
}
