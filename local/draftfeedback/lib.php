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
 * @copyright  2026 Integer Training
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

    // Check if learner has any draft for this assignment.
    $draft = \local_draftfeedback\manager::get_learner_draft($cm->id, $USER->id);

    if ($draft) {
        if ($draft->status === 'reviewed') {
            // Feedback received - show "View Feedback" button.
            $viewurl = new moodle_url('/local/draftfeedback/view.php', ['id' => $draft->id]);
            $html = '
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                var addSubmissionBtn = document.querySelector("[data-action=\\"add-submission\\"]");
                if (!addSubmissionBtn) {
                    var buttons = document.querySelectorAll(".btn");
                    for (var i = 0; i < buttons.length; i++) {
                        if (buttons[i].textContent.indexOf("Add submission") !== -1 ||
                            buttons[i].textContent.indexOf("Edit submission") !== -1) {
                            addSubmissionBtn = buttons[i];
                            break;
                        }
                    }
                }

                if (addSubmissionBtn) {
                    var feedbackBtn = document.createElement("a");
                    feedbackBtn.href = "' . $viewurl->out() . '";
                    feedbackBtn.className = "btn ml-2";
                    feedbackBtn.innerHTML = "<i class=\\"bi bi-chat-square-text\\"></i> View Draft Feedback";
                    feedbackBtn.style.cssText = "background: #4CAF50; color: white; border: none; margin-left: 10px;";
                    feedbackBtn.onmouseover = function() { this.style.background = "#45a049"; };
                    feedbackBtn.onmouseout = function() { this.style.background = "#4CAF50"; };
                    addSubmissionBtn.parentNode.insertBefore(feedbackBtn, addSubmissionBtn.nextSibling);
                }
            });
            </script>';
            echo $html;
        } else {
            // Draft pending - show status badge.
            $viewurl = new moodle_url('/local/draftfeedback/view.php', ['id' => $draft->id]);
            $html = '
            <script>
            document.addEventListener("DOMContentLoaded", function() {
                var addSubmissionBtn = document.querySelector("[data-action=\\"add-submission\\"]");
                if (!addSubmissionBtn) {
                    var buttons = document.querySelectorAll(".btn");
                    for (var i = 0; i < buttons.length; i++) {
                        if (buttons[i].textContent.indexOf("Add submission") !== -1 ||
                            buttons[i].textContent.indexOf("Edit submission") !== -1) {
                            addSubmissionBtn = buttons[i];
                            break;
                        }
                    }
                }

                if (addSubmissionBtn) {
                    var statusLink = document.createElement("a");
                    statusLink.href = "' . $viewurl->out() . '";
                    statusLink.className = "ml-2";
                    statusLink.innerHTML = "<i class=\\"bi bi-hourglass-split\\"></i> Draft Awaiting Feedback";
                    statusLink.style.cssText = "background: #FFF3E0; color: #E65100; padding: 8px 16px; border-radius: 6px; font-size: 13px; margin-left: 10px; text-decoration: none; display: inline-block;";
                    statusLink.onmouseover = function() { this.style.background = "#FFE0B2"; };
                    statusLink.onmouseout = function() { this.style.background = "#FFF3E0"; };
                    addSubmissionBtn.parentNode.insertBefore(statusLink, addSubmissionBtn.nextSibling);
                }
            });
            </script>';
            echo $html;
        }
        return;
    }

    // No draft - show "Submit Draft for Feedback" button.
    $submiturl = new moodle_url('/local/draftfeedback/submit.php', ['cmid' => $cm->id]);

    $html = '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var addSubmissionBtn = document.querySelector("[data-action=\\"add-submission\\"]");
        if (!addSubmissionBtn) {
            var buttons = document.querySelectorAll(".btn");
            for (var i = 0; i < buttons.length; i++) {
                if (buttons[i].textContent.indexOf("Add submission") !== -1 ||
                    buttons[i].textContent.indexOf("Edit submission") !== -1) {
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

    if ($filearea !== 'draftfiles' && $filearea !== 'feedbackfiles') {
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
