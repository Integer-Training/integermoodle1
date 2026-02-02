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

require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/performance:view', $context);

$PAGE->set_url(new moodle_url('/local/performance/index.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('dashboard_title', 'local_performance'));
$PAGE->set_heading(get_string('dashboard_title', 'local_performance'));
$PAGE->requires->css('/local/performance/styles.css');

// Run all health checks.
$result = \local_performance\health_checker::run_all();

// Build template context.
$checks = [];
foreach ($result['checks'] as $check) {
    $check['is_pass'] = ($check['status'] === 'pass');
    $check['is_fail'] = ($check['status'] === 'fail');
    $check['is_warn'] = ($check['status'] === 'warn');
    $check['is_info'] = ($check['status'] === 'info');
    $check['status_label'] = strtoupper($check['status']);
    $checks[] = $check;
}

$score = $result['score'];
$scoreclass = 'perf-score-low';
if ($score >= 80) {
    $scoreclass = 'perf-score-high';
} else if ($score >= 50) {
    $scoreclass = 'perf-score-mid';
}

// Count statuses.
$pass_count = 0;
$fail_count = 0;
$warn_count = 0;
$info_count = 0;
foreach ($checks as $c) {
    if ($c['is_pass']) {
        $pass_count++;
    } else if ($c['is_fail']) {
        $fail_count++;
    } else if ($c['is_warn']) {
        $warn_count++;
    } else {
        $info_count++;
    }
}

// Settings.
$settings_url = new moodle_url('/admin/settings.php', ['section' => 'local_performance']);

$templatecontext = [
    'score' => $score,
    'scoreclass' => $scoreclass,
    'checks' => $checks,
    'pass_count' => $pass_count,
    'fail_count' => $fail_count,
    'warn_count' => $warn_count,
    'info_count' => $info_count,
    'total_checks' => count($checks),
    'settings_url' => $settings_url->out(false),
    'role_cache_enabled' => (bool) get_config('local_performance', 'enable_role_cache'),
    'nav_shortcircuit_enabled' => (bool) get_config('local_performance', 'enable_nav_shortcircuit'),
    'logstore_fix_enabled' => (bool) get_config('local_performance', 'enable_logstore_fix'),
];

echo $OUTPUT->header();
echo $OUTPUT->render_from_template('local_performance/dashboard', $templatecontext);
echo $OUTPUT->footer();
