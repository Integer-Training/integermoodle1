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

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Error Logger';
$string['enabled'] = 'Enable Error Logger';
$string['enabled_desc'] = 'Capture PHP errors, exceptions, and debugging messages into the error log.';
$string['retention_days'] = 'Log retention period';
$string['retention_days_desc'] = 'Automatically delete log entries older than this many days.';
$string['min_severity'] = 'Minimum severity to capture';
$string['min_severity_desc'] = 'Only capture errors at or above this severity level.';
$string['capture_backtraces'] = 'Capture backtraces';
$string['capture_backtraces_desc'] = 'Include stack traces in error details. Useful for debugging but increases storage.';
$string['max_logs_per_minute'] = 'Max logs per minute';
$string['max_logs_per_minute_desc'] = 'Rate limit: maximum number of errors to log per minute. Prevents log flooding.';
$string['viewlogs'] = 'View Error Logs';
$string['logdetail'] = 'Log Entry Detail';
$string['severity'] = 'Severity';
$string['type'] = 'Type';
$string['message'] = 'Message';
$string['component'] = 'Component';
$string['user'] = 'User';
$string['time'] = 'Time';
$string['details'] = 'Details';
$string['actions'] = 'Actions';
$string['all'] = 'All';
$string['severity_all'] = 'All severities';
$string['severity_warnings'] = 'Warnings and above';
$string['severity_errors'] = 'Errors only';
$string['severity_critical'] = 'Critical';
$string['severity_warning'] = 'Warning';
$string['severity_notice'] = 'Notice';
$string['severity_debug'] = 'Debug';
$string['last24h'] = 'Last 24 hours';
$string['datefrom'] = 'From date';
$string['dateto'] = 'To date';
$string['applyfilters'] = 'Apply Filters';
$string['clearall'] = 'Clear All Logs';
$string['confirmclear'] = 'Are you sure you want to delete ALL error log entries? This cannot be undone.';
$string['logs_cleared'] = 'All error logs have been cleared.';
$string['back'] = 'Back to Logs';
$string['task_cleanup'] = 'Error Logger: clean up old logs';
$string['task_aggregate'] = 'Error Logger: aggregate task and conversion failures';
$string['errorlogger:view'] = 'View error logs';
$string['explanation'] = 'Explanation';
$string['nologs'] = 'No error logs found.';
