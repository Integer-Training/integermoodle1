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
 * Scheduled task definitions for Sequential Submission plugin.
 *
 * @package    local_sequentialsubmission
 * @copyright  2026 Integer Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$tasks = [
    // Daily scan for learners stuck beyond the SLA threshold.
    [
        'classname' => 'local_sequentialsubmission\\task\\stuck_learner_scan',
        'blocking'  => 0,
        'minute'    => '15',
        'hour'      => '6',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
    // Hourly sweep to expire consumed bypasses.
    [
        'classname' => 'local_sequentialsubmission\\task\\expire_bypasses',
        'blocking'  => 0,
        'minute'    => '45',
        'hour'      => '*',
        'day'       => '*',
        'dayofweek' => '*',
        'month'     => '*',
    ],
];
