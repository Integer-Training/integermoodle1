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
 * Library functions for Draft Feedback plugin.
 *
 * @package    local_draftfeedback
 * @copyright  2026 Epearl Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Inject the "Submit Draft for Feedback" button on assignment pages.
 * This is called via the before_footer callback.
 */
function local_draftfeedback_before_footer() {
    global $PAGE, $USER, $OUTPUT;

    // Only on assignment view page.
    if ($PAGE->pagetype !== 'mod-assign-view') {
        return;
    }

    // Check if user is logged in.
    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Get course module.
    $cm = $PAGE->cm;
    if (!$cm || $cm->modname !== 'assign') {
        return;
    }

    // Check capability.
    $context = context_module::instance($cm->id);
    if (!has_capability('local/draftfeedback:submit', $context)) {
        return;
    }

    // Check if already has pending draft.
    if (\local_draftfeedback\manager::has_pending_draft($cm->id, $USER->id)) {
        // Show "Draft Pending" badge instead.
        $html = '
        <script>
        document.addEventListener("DOMContentLoaded", function() {
            var addSubmissionBtn = document.querySelector("[data-action=\\"add-submission\\"]");
            if (!addSubmissionBtn) {
                // Try finding the "Add submission" button by text.
                var buttons = document.querySelectorAll(".btn");
                for (var i = 0; i < buttons.length; i++) {
                    if (buttons[i].textContent.indexOf("Add submission") !== -1) {
                        addSubmissionBtn = buttons[i];
                        break;
                    }
                }
            }

            if (addSubmissionBtn) {
                var badge = document.createElement("span");
                badge.className = "badge badge-warning ml-2";
                badge.innerHTML = "<i class=\"bi bi-hourglass-split\"></i> Draft Pending Feedback";
                badge.style.cssText = "background: #FFF3E0; color: #E65100; padding: 8px 12px; border-radius: 6px; font-size: 13px; margin-left: 10px;";
                addSubmissionBtn.parentNode.insertBefore(badge, addSubmissionBtn.nextSibling);
            }
        });
        </script>';
        echo $html;
        return;
    }

    // Inject button.
    $submiturl = new moodle_url('/local/draftfeedback/submit.php', ['cmid' => $cm->id]);

    $html = '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var addSubmissionBtn = document.querySelector("[data-action=\\"add-submission\\"]");
        if (!addSubmissionBtn) {
            // Try finding the "Add submission" button by text.
            var buttons = document.querySelectorAll(".btn");
            for (var i = 0; i < buttons.length; i++) {
                if (buttons[i].textContent.indexOf("Add submission") !== -1) {
                    addSubmissionBtn = buttons[i];
                    break;
                }
            }
        }

        if (addSubmissionBtn) {
            var draftBtn = document.createElement("a");
            draftBtn.href = "' . $submiturl->out() . '";
            draftBtn.className = "btn btn-secondary ml-2";
            draftBtn.innerHTML = "<i class=\\"bi bi-file-earmark-text\\"></i> Submit Draft for Feedback";
            draftBtn.style.cssText = "background: #9C27B0; color: white; border: none; margin-left: 10px;";
            draftBtn.onmouseover = function() { this.style.background = "#7B1FA2"; };
            draftBtn.onmouseout = function() { this.style.background = "#9C27B0"; };
            addSubmissionBtn.parentNode.insertBefore(draftBtn, addSubmissionBtn.nextSibling);
        }
    });
    </script>';

    echo $html;
}

/**
 * Extend navigation.
 *
 * @param global_navigation $navigation The navigation object.
 */
function local_draftfeedback_extend_navigation(global_navigation $navigation) {
    // Not used with Alpha theme, but kept for compatibility.
}

/**
 * Serve plugin files.
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param context $context the context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool false if file not found, does not return if found
 */
function local_draftfeedback_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    global $DB, $USER;

    if ($filearea !== 'draftfiles') {
        return false;
    }

    require_login($course, false, $cm);

    $draftid = (int)array_shift($args);
    $filename = array_pop($args);

    // Get draft to check permissions.
    $draft = $DB->get_record('local_draftfeedback', ['id' => $draftid], '*', MUST_EXIST);

    // Check view permission.
    $canview = false;
    if ($draft->userid == $USER->id) {
        $canview = has_capability('local/draftfeedback:viewown', $context);
    } else if (has_capability('local/draftfeedback:review', $context)) {
        $canview = true;
    } else if (has_capability('local/draftfeedback:viewall', context_system::instance())) {
        $canview = true;
    }

    if (!$canview) {
        return false;
    }

    $fs = get_file_storage();
    $file = $fs->get_file($context->id, 'local_draftfeedback', $filearea, $draftid, '/', $filename);

    if (!$file) {
        return false;
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}
