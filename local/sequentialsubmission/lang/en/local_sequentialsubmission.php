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
 * Language strings for Sequential Submission plugin.
 *
 * @package    local_sequentialsubmission
 * @copyright  2026 Integer Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin.
$string['pluginname'] = 'Sequential Submission';
$string['pluginname_short'] = 'Submission Locks';

// Capabilities.
$string['sequentialsubmission:manage'] = 'Manage submission locks (view dashboard, force-unlock)';
$string['sequentialsubmission:bypass'] = 'Bypass the submission lock';

// Page titles.
$string['managelocks'] = 'Submission Locks';
$string['activelocks'] = 'Active Submission Locks';
$string['auditlog'] = 'Audit Log';
$string['orderpreview'] = 'Unit Order Preview';
$string['forceunlock'] = 'Force Unlock';

// Settings.
$string['settings'] = 'Sequential Submission settings';
$string['enable_lock'] = 'Enable sequential submission lock';
$string['enable_lock_desc'] = 'Master switch. Turn OFF to disable all lock enforcement immediately. Banners and redirects stop on the next page load. No data is modified.';
$string['sla_days'] = 'Stuck-learner SLA (days)';
$string['sla_days_desc'] = 'Number of days a submission can be pending before the learner is flagged as stuck and admins are alerted.';
$string['per_course_disable'] = 'Courses exempt from the rule';
$string['per_course_disable_desc'] = 'Comma-separated course IDs where the sequential lock should NOT apply. Learners in these courses use standard Moodle rules only.';
$string['exempt_patterns'] = 'Exempt assignment name patterns';
$string['exempt_patterns_desc'] = 'One pattern per line. Assignments whose name contains any of these (case-insensitive) are exempt from the lock. Defaults cover IAG, ID Proof, and Case Studies.';
$string['force_unlock_ttl_hours'] = 'Force-unlock TTL (hours)';
$string['force_unlock_ttl_hours_desc'] = 'How long a force-unlock stays active before expiring. Clears automatically when the blocking submission is marked — whichever comes first.';

// Banners shown to learners on locked assignments.
$string['banner_locked_4b'] = '🔒 This assignment is locked. You already have another assignment awaiting marking ({$a->blocking_name} in {$a->blocking_course}, submitted {$a->days_waiting} day(s) ago). Only one assignment can be submitted at a time — your tutor will mark it shortly.';
$string['banner_locked_1a'] = '🔒 This assignment is locked. You must first resubmit your referred work: {$a->blocking_name}. Once that is marked Passed, your other assignments will unlock.';
$string['banner_locked_2a'] = '🔒 This assignment is locked. You must first complete {$a->blocking_name} (currently {$a->blocking_status}). Assignments must be completed in order.';
$string['banner_cta_back'] = 'Back to my dashboard';
$string['submit_disabled_tooltip'] = 'You must finish your previous assignment first.';

// Redirect notifications.
$string['notify_blocked_redirect'] = 'Submission blocked: {$a}';

// Admin dashboard.
$string['heading_active_locks'] = 'Active Submission Locks';
$string['heading_stuck_learners'] = 'Stuck Learners (pending > {$a} days)';
$string['heading_audit_log'] = 'Recent Activity';
$string['col_learner'] = 'Learner';
$string['col_tutor'] = 'Assigned Tutor';
$string['col_course'] = 'Course';
$string['col_pending_assignment'] = 'Pending Assignment';
$string['col_days_waiting'] = 'Days Waiting';
$string['col_status'] = 'Status';
$string['col_action'] = 'Action';
$string['col_time'] = 'When';
$string['col_event'] = 'Event';
$string['col_details'] = 'Details';
$string['status_awaiting'] = 'Awaiting Marking';
$string['status_referred'] = 'Referred';
$string['unassigned_tutor'] = 'Unassigned';

// Force-unlock modal.
$string['forceunlock_title'] = 'Force unlock {$a}?';
$string['forceunlock_prompt'] = 'This learner will be granted a temporary bypass so they can submit their next unit while the stuck submission remains in the marking queue.';
$string['forceunlock_reason_label'] = 'Reason (required, for audit)';
$string['forceunlock_confirm'] = 'Grant temporary bypass';
$string['forceunlock_success'] = 'Bypass granted for {$a->learner}. Expires {$a->expires}.';
$string['forceunlock_reason_required'] = 'A reason is required.';

// Grandfather notification (sent once, from tutor or admin).
$string['msg_grandfather_subject'] = 'Important update — one assignment at a time';
$string['msg_grandfather_body'] = 'Hi {$a->firstname},

From today, Integer Training has a new rule: you can only have ONE assignment awaiting marking at a time. Once your tutor marks it, your next assignment unlocks automatically.

Why:
• Submitting everything at once means if there is an issue, everything goes back for resubmission
• You get focused feedback on one piece of work at a time
• Your tutor can turn things around faster

What this means for you right now:
• You have {$a->pending_count} assignment(s) already submitted that are awaiting marking. These will be graded as normal, in unit order.
• Until those are Passed, you cannot submit new ones.
• If an assignment is Referred, please resubmit that one before any others.

Questions? Message your tutor or email student.support@integertraining.com.

— {$a->tutorname}';

// Mobile revert notification.
$string['msg_mobilerevert_subject'] = 'Your submission was returned to draft';
$string['msg_mobilerevert_body'] = 'Hi {$a->firstname},

Your submission for "{$a->assignmentname}" was returned to draft status because you already had another assignment awaiting marking. Please wait for your tutor to mark your previous submission, then you will be able to submit this one.

You can still edit and re-save your draft. It will be submitted automatically once you become eligible.

— Integer Training';

// Stuck alert to admin.
$string['msg_stuck_subject'] = '{$a->count} learner(s) stuck > {$a->days} days';
$string['msg_stuck_body'] = 'The following learners have had a submission awaiting marking for more than {$a->days} days:

{$a->learner_list}

Review at: {$a->admin_url}';

// Force-unlock notification to learner.
$string['msg_forceunlocked_subject'] = 'Temporary unlock granted';
$string['msg_forceunlocked_body'] = 'Hi {$a->firstname},

An administrator has granted you a temporary bypass on the sequential submission rule. You can submit your next assignment immediately. The bypass expires {$a->expires} or as soon as your stuck submission is marked, whichever comes first.

— Integer Training';

// Order preview page.
$string['orderpreview_intro'] = 'This page shows the unit order the plugin will enforce for each course. The order comes from the drag-drop arrangement of activities on each course home page. If any course looks wrong, fix the course page OR add it to the exempt list in plugin settings.';
$string['orderpreview_col_pos'] = 'Position';
$string['orderpreview_col_assignment'] = 'Assignment';
$string['orderpreview_col_exempt'] = 'Exempt?';
$string['orderpreview_exempt_yes'] = 'Yes';
$string['orderpreview_exempt_no'] = 'No';
$string['orderpreview_noassignments'] = 'No assignments in this course.';

// Misc.
$string['none'] = 'None';
$string['privacy:metadata:local_ss_log'] = 'Audit log of lock events and force-unlocks.';
$string['privacy:metadata:local_ss_log:userid'] = 'The learner the event relates to.';
$string['privacy:metadata:local_ss_log:action'] = 'The kind of event that occurred.';
$string['privacy:metadata:local_ss_log:timecreated'] = 'When the event occurred.';
$string['privacy:metadata:local_ss_bypass'] = 'Temporary bypasses granted by administrators.';
$string['privacy:metadata:local_ss_bypass:userid'] = 'The learner the bypass applies to.';
$string['privacy:metadata:local_ss_bypass:reason'] = 'Admin-provided reason for the bypass.';
$string['privacy:metadata:local_ss_bypass:timecreated'] = 'When the bypass was granted.';
