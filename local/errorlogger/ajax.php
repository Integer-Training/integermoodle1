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
 * AJAX endpoint for server-side DataTable processing.
 *
 * @package    local_errorlogger
 * @copyright  2026 Epearl Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define('AJAX_SCRIPT', true);

require_once('../../config.php');
require_login();
require_sesskey();

$context = context_system::instance();
require_capability('local/errorlogger:view', $context);

global $DB;

// DataTable server-side parameters.
$draw   = required_param('draw', PARAM_INT);
$start  = optional_param('start', 0, PARAM_INT);
$length = optional_param('length', 25, PARAM_INT);

// Custom filters.
$filtertype     = optional_param('filter_type', '', PARAM_ALPHANUMEXT);
$filterseverity = optional_param('filter_severity', '', PARAM_RAW);
$filterdatefrom = optional_param('filter_date_from', '', PARAM_TEXT);
$filterdateto   = optional_param('filter_date_to', '', PARAM_TEXT);
$ordercolumn    = optional_param('order_column', 'timecreated', PARAM_ALPHA);
$orderdir       = optional_param('order_dir', 'desc', PARAM_ALPHA);

// Build WHERE clause with parameterized queries.
$conditions = [];
$params = [];

if (!empty($filtertype)) {
    $conditions[] = 'l.type = :ftype';
    $params['ftype'] = $filtertype;
}

if ($filterseverity !== '' && (int) $filterseverity > 0) {
    $conditions[] = 'l.severity = :fseverity';
    $params['fseverity'] = (int) $filterseverity;
}

if (!empty($filterdatefrom)) {
    $ts = strtotime($filterdatefrom);
    if ($ts) {
        $conditions[] = 'l.timecreated >= :datefrom';
        $params['datefrom'] = $ts;
    }
}

if (!empty($filterdateto)) {
    $ts = strtotime($filterdateto . ' 23:59:59');
    if ($ts) {
        $conditions[] = 'l.timecreated <= :dateto';
        $params['dateto'] = $ts;
    }
}

// DataTable global search.
$searchvalue = optional_param_array('search', [], PARAM_RAW);
$searchtext = $searchvalue['value'] ?? '';
if (!empty($searchtext)) {
    $conditions[] = '(' . $DB->sql_like('l.message', ':searchmsg', false) .
                    ' OR ' . $DB->sql_like('l.component', ':searchcomp', false) . ')';
    $params['searchmsg'] = '%' . $DB->sql_like_escape($searchtext) . '%';
    $params['searchcomp'] = '%' . $DB->sql_like_escape($searchtext) . '%';
}

$where = $conditions ? implode(' AND ', $conditions) : '1=1';

// Count total and filtered records.
$totalrecords = $DB->count_records('local_errorlogger_logs');
$filteredrecords = $DB->count_records_sql(
    "SELECT COUNT(*) FROM {local_errorlogger_logs} l WHERE {$where}",
    $params
);

// Whitelist sortable columns to prevent SQL injection.
$sortcolumns = [
    'timecreated' => 'l.timecreated',
    'severity'    => 'l.severity',
    'type'        => 'l.type',
    'component'   => 'l.component',
    'message'     => 'l.message',
];
$sort = $sortcolumns[$ordercolumn] ?? 'l.timecreated';
$dir = (strtolower($orderdir) === 'asc') ? 'ASC' : 'DESC';

// Fetch records.
$sql = "SELECT l.id, l.type, l.severity, l.component, l.message,
               l.url, l.userid, l.ipaddress, l.timecreated
          FROM {local_errorlogger_logs} l
         WHERE {$where}
         ORDER BY {$sort} {$dir}";
$records = $DB->get_records_sql($sql, $params, $start, $length);

/**
 * Generate a plain-English explanation for an error log entry.
 *
 * @param string $type      error, exception, warning, notice, task_failure, conversion_failure
 * @param int    $severity  1=critical, 2=warning, 3=notice, 4=debug
 * @param string $message   Raw error message
 * @param string $component Detected component
 * @return string
 */
function local_errorlogger_explain(string $type, int $severity, string $message, string $component): string {
    $msg = strtolower($message);

    // Task and conversion failures.
    if ($type === 'task_failure') {
        return 'A scheduled background task did not complete successfully.';
    }
    if ($type === 'conversion_failure') {
        return 'A file could not be converted to the required format.';
    }

    // File-system operations.
    if (str_contains($msg, 'unlink(') || str_contains($msg, 'unlink:')) {
        return 'A temporary file could not be deleted — it was likely already removed.';
    }
    if (str_contains($msg, 'mkdir(') || str_contains($msg, 'mkdir:')) {
        return 'The system could not create a folder — check file permissions.';
    }
    if (str_contains($msg, 'fopen(') || str_contains($msg, 'file_get_contents(') || str_contains($msg, 'file_put_contents(')) {
        return 'A file could not be opened or written — check file permissions and disk space.';
    }
    if (str_contains($msg, 'permission denied')) {
        return 'The server does not have permission to access a file or folder.';
    }
    if (str_contains($msg, 'no such file or directory')) {
        return 'A file or folder the system expected to find does not exist.';
    }
    if (str_contains($msg, 'disk quota') || str_contains($msg, 'no space left')) {
        return 'The server is running low on disk space.';
    }

    // Database.
    if (str_contains($msg, 'deadlock') || str_contains($msg, 'lock wait timeout')) {
        return 'Two database operations collided — usually resolves on its own.';
    }
    if (str_contains($msg, 'duplicate entry')) {
        return 'A database record already exists and could not be inserted again.';
    }
    if (str_contains($msg, 'table') && (str_contains($msg, 'doesn\'t exist') || str_contains($msg, 'does not exist'))) {
        return 'A required database table is missing — a plugin may need reinstalling.';
    }

    // Memory / timeout.
    if (str_contains($msg, 'allowed memory size') || str_contains($msg, 'out of memory')) {
        return 'The server ran out of memory while processing a request.';
    }
    if (str_contains($msg, 'maximum execution time') || str_contains($msg, 'timed out')) {
        return 'A process took too long and was stopped by the server.';
    }

    // Session / auth.
    if (str_contains($msg, 'session') && (str_contains($msg, 'expired') || str_contains($msg, 'invalid'))) {
        return 'A user\'s session expired — they need to log in again.';
    }

    // Common coding errors.
    if (str_contains($msg, 'undefined variable') || str_contains($msg, 'undefined index') || str_contains($msg, 'undefined array key')) {
        return 'The code tried to use a value that hasn\'t been set — a minor coding issue.';
    }
    if (str_contains($msg, 'class') && str_contains($msg, 'not found')) {
        return 'A required PHP class could not be found — a plugin may be missing or broken.';
    }
    if (str_contains($msg, 'call to undefined function')) {
        return 'The code called a function that doesn\'t exist — a plugin may need updating.';
    }
    if (str_contains($msg, 'call to a member function') && str_contains($msg, 'on null')) {
        return 'The code expected an object but got nothing — usually a missing record or config.';
    }
    if (str_contains($msg, 'type error') || str_contains($msg, 'typeerror')) {
        return 'A function received the wrong type of data — a coding issue.';
    }

    // cURL / external services.
    if (str_contains($msg, 'curl') || str_contains($msg, 'could not resolve host')) {
        return 'The server could not connect to an external service.';
    }
    if (str_contains($msg, 'ssl') || str_contains($msg, 'certificate')) {
        return 'There is a problem with a secure (SSL) connection to an external service.';
    }

    // Generic fallbacks by type.
    if ($type === 'exception') {
        return 'An unexpected error occurred that stopped a process from completing.';
    }
    if ($type === 'error') {
        return $severity === 1
            ? 'A serious error occurred — the page or task could not finish.'
            : 'A PHP error occurred during processing.';
    }
    if ($type === 'warning') {
        return 'Something unexpected happened, but the page continued to load.';
    }
    if ($type === 'notice') {
        return 'A minor issue was detected — usually not visible to users.';
    }

    return 'An issue was recorded in the system logs.';
}

// Format response.
$severitynames   = [1 => 'Critical', 2 => 'Warning', 3 => 'Notice', 4 => 'Debug'];
$severityclasses = [1 => 'danger', 2 => 'warning', 3 => 'info', 4 => 'secondary'];

$data = [];
foreach ($records as $r) {
    $username = '';
    if ($r->userid) {
        $user = $DB->get_record('user', ['id' => $r->userid], 'id, firstname, lastname', IGNORE_MISSING);
        if ($user) {
            $username = fullname($user);
        }
    }

    $data[] = [
        'id'          => (int) $r->id,
        'time'        => userdate($r->timecreated, '%d %b %Y %H:%M:%S'),
        'severity'    => $severitynames[$r->severity] ?? 'Unknown',
        'sev_class'   => $severityclasses[$r->severity] ?? 'secondary',
        'type'        => $r->type,
        'message'     => shorten_text($r->message, 120),
        'explanation' => local_errorlogger_explain($r->type, $r->severity, $r->message, $r->component),
        'component'   => $r->component ?: '-',
        'user'        => $username ?: '-',
        'ip'          => $r->ipaddress ?: '',
    ];
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'draw'            => $draw,
    'recordsTotal'    => (int) $totalrecords,
    'recordsFiltered' => (int) $filteredrecords,
    'data'            => $data,
]);
die();
