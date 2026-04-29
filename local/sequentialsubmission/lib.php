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
 * Hooks for Sequential Submission plugin.
 *
 * Two hooks:
 *   before_http_headers — server-side enforcement: on mod-assign-editsubmission
 *                         pages, redirect locked learners away BEFORE the form
 *                         can be submitted. Safe to call redirect() from here.
 *   before_footer       — UI layer: on mod-assign-view pages (the read-only
 *                         assignment detail), inject a banner + disable the
 *                         Submit button for locked learners.
 *
 * @package    local_sequentialsubmission
 * @copyright  2026 Integer Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use local_sequentialsubmission\lock_checker;

/**
 * Server-side enforcement — redirect locked learners off the submission form.
 *
 * Fires before any HTTP headers are sent. Safe to call redirect() from here.
 */
function local_sequentialsubmission_before_http_headers() {
    global $PAGE, $USER, $CFG;

    // Guest / not logged in → nothing to lock.
    if (empty($USER->id) || isguestuser()) {
        return;
    }

    // Only intercept the assignment submission edit page.
    // Moodle sets pagetype to "mod-assign-{action}" in mod/assign/locallib.php.
    if (empty($PAGE->pagetype) || $PAGE->pagetype !== 'mod-assign-editsubmission') {
        return;
    }

    // Admin / impersonator / bypass-capable → short-circuit fast.
    if (lock_checker::user_should_bypass($USER->id)) {
        return;
    }

    // Kill switch.
    if (!lock_checker::is_enabled()) {
        return;
    }

    // Resolve the target course module.
    $cmid = 0;
    if (!empty($PAGE->cm) && !empty($PAGE->cm->id)) {
        $cmid = (int) $PAGE->cm->id;
    } else {
        $cmid = (int) optional_param('id', 0, PARAM_INT);
    }
    if ($cmid <= 0) {
        return;
    }

    $check = lock_checker::check($USER->id, $cmid);
    if (!$check->locked) {
        return;
    }

    // Log the block.
    lock_checker::log_event($USER->id, 'block', [
        'blocked_cmid' => $cmid,
        'blocking_cmid' => $check->blocking_cmid,
        'courseid' => !empty($PAGE->cm->course) ? (int) $PAGE->cm->course : null,
        'reason' => $check->reason,
        'notes' => 'Redirect from editsubmission page',
    ]);

    // Redirect back to the assignment view with a notification the learner can read.
    $viewurl = new moodle_url('/mod/assign/view.php', ['id' => $cmid]);
    $msg = local_sequentialsubmission_format_banner_plaintext($check);
    redirect($viewurl, $msg, null, \core\output\notification::NOTIFY_ERROR);
}

/**
 * UI layer — inject a banner + disable the Submit button on the read-only
 * assignment view page when the learner is locked.
 */
function local_sequentialsubmission_before_footer() {
    global $PAGE, $USER, $OUTPUT;

    if (empty($USER->id) || isguestuser()) {
        return '';
    }
    if (empty($PAGE->pagetype) || $PAGE->pagetype !== 'mod-assign-view') {
        return '';
    }
    if (lock_checker::user_should_bypass($USER->id)) {
        return '';
    }
    if (!lock_checker::is_enabled()) {
        return '';
    }

    $cmid = 0;
    if (!empty($PAGE->cm) && !empty($PAGE->cm->id)) {
        $cmid = (int) $PAGE->cm->id;
    } else {
        $cmid = (int) optional_param('id', 0, PARAM_INT);
    }
    if ($cmid <= 0) {
        return '';
    }

    $check = lock_checker::check($USER->id, $cmid);
    if (!$check->locked) {
        return '';
    }

    // Build banner HTML and the JS that disables the Submit button.
    $bannerhtml = local_sequentialsubmission_format_banner_html($check);
    $tooltip = get_string('submit_disabled_tooltip', 'local_sequentialsubmission');

    // Inject via appended HTML + JS. Matches the pattern used by local/draftfeedback.
    $html = <<<HTML
<style>
.ss-banner {
    margin: 16px 0;
    padding: 16px 20px;
    border-radius: 8px;
    background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
    border-left: 4px solid #e17055;
    color: #5a3a1a;
    font-family: 'Inter', system-ui, sans-serif;
    font-size: 14.5px;
    line-height: 1.55;
    box-shadow: 0 2px 8px rgba(0,0,0,0.06);
}
.ss-banner strong { color: #2d1b0e; }
.ss-banner .ss-icon { font-size: 18px; margin-right: 8px; }
.ss-banner .ss-cta { display: inline-block; margin-top: 10px; padding: 6px 14px; background: #3a5ba0; color: #fff; text-decoration: none; border-radius: 6px; font-weight: 500; font-size: 13px; }
.ss-banner .ss-cta:hover { background: #2d477f; color: #fff; }
.ss-locked-disabled { opacity: 0.5; pointer-events: none; cursor: not-allowed !important; }
</style>
<div class="ss-banner" id="ss-lock-banner">{$bannerhtml}</div>
<script>
(function() {
    function disableSubmitButtons() {
        // Target the submission-action buttons on mod-assign-view.
        var selectors = [
            'a[data-action="add-submission"]',
            'a[data-action="edit-submission"]',
            'input[type="submit"][name="addsubmission"]',
            'input[type="submit"][name="editsubmission"]',
            'button[data-action="add-submission"]',
            'button[data-action="edit-submission"]'
        ];
        selectors.forEach(function(sel) {
            document.querySelectorAll(sel).forEach(function(el) {
                el.classList.add('ss-locked-disabled');
                el.setAttribute('title', {$this_tooltip_js});
                el.setAttribute('aria-disabled', 'true');
                el.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); });
            });
        });
        // Fallback: any button/link whose text contains "Add submission" or "Edit submission".
        var texttargets = ['Add submission', 'Edit submission', 'Add a new attempt'];
        document.querySelectorAll('a, button, input[type=submit]').forEach(function(el) {
            var label = (el.innerText || el.value || '').trim();
            if (texttargets.indexOf(label) !== -1) {
                el.classList.add('ss-locked-disabled');
                el.setAttribute('title', {$this_tooltip_js});
                el.setAttribute('aria-disabled', 'true');
                el.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); });
            }
        });
    }
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', disableSubmitButtons);
    } else {
        disableSubmitButtons();
    }
})();
</script>
HTML;

    // Swap the JS-encoded tooltip in (avoid php heredoc variable collision with JS strings).
    $tooltipjson = json_encode($tooltip);
    $html = str_replace('{$this_tooltip_js}', $tooltipjson, $html);

    return $html;
}

/**
 * Format the full banner HTML based on the lock reason.
 *
 * @param object $check Result of lock_checker::check()
 * @return string
 */
function local_sequentialsubmission_format_banner_html($check) {
    global $CFG;

    $a = (object) [
        'blocking_name' => format_string($check->blocking_name ?? ''),
        'blocking_course' => format_string($check->blocking_course ?? ''),
        'blocking_status' => $check->blocking_status ?? '',
        'days_waiting' => (int) ($check->days_waiting ?? 0),
    ];

    switch ($check->reason) {
        case 'rule_1a_refer':
            $text = get_string('banner_locked_1a', 'local_sequentialsubmission', $a);
            break;
        case 'rule_2a_order':
            $text = get_string('banner_locked_2a', 'local_sequentialsubmission', $a);
            break;
        case 'rule_4b_pending':
        default:
            $text = get_string('banner_locked_4b', 'local_sequentialsubmission', $a);
            break;
    }

    // Learner dashboard as a friendly "back" link.
    $backurl = new moodle_url('/local/learnerdashboard/index.php');
    $cta = get_string('banner_cta_back', 'local_sequentialsubmission');

    return '<span class="ss-icon">⚠️</span>' . s($text)
        . '<br><a class="ss-cta" href="' . $backurl->out(false) . '">' . s($cta) . '</a>';
}

/**
 * Plaintext version of the banner, for redirect() notifications.
 *
 * @param object $check
 * @return string
 */
function local_sequentialsubmission_format_banner_plaintext($check) {
    $a = (object) [
        'blocking_name' => $check->blocking_name ?? '',
        'blocking_course' => $check->blocking_course ?? '',
        'blocking_status' => $check->blocking_status ?? '',
        'days_waiting' => (int) ($check->days_waiting ?? 0),
    ];
    switch ($check->reason) {
        case 'rule_1a_refer':
            return get_string('banner_locked_1a', 'local_sequentialsubmission', $a);
        case 'rule_2a_order':
            return get_string('banner_locked_2a', 'local_sequentialsubmission', $a);
        case 'rule_4b_pending':
        default:
            return get_string('banner_locked_4b', 'local_sequentialsubmission', $a);
    }
}

/**
 * Navigation hook for non-Alpha themes — Alpha sidebar is handled directly
 * in theme/alpha/classes/output/core_renderer.php::mainsidebarmenu().
 *
 * @param global_navigation $navigation
 */
function local_sequentialsubmission_extend_navigation(global_navigation $navigation) {
    global $USER;

    if (!is_siteadmin($USER)) {
        return;
    }
    $url = new moodle_url('/local/sequentialsubmission/index.php');
    $node = $navigation->add(
        get_string('managelocks', 'local_sequentialsubmission'),
        $url,
        \navigation_node::TYPE_CUSTOM,
        null,
        'local_sequentialsubmission',
        new \pix_icon('i/lock', '')
    );
    $node->showinflatnavigation = true;
}
