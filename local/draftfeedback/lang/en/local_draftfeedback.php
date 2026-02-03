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
 * Language strings for Draft Feedback plugin.
 *
 * @package    local_draftfeedback
 * @copyright  2026 Epearl Academy
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'Draft Feedback';
$string['draftfeedback'] = 'Draft Feedback';

// Capabilities.
$string['draftfeedback:submit'] = 'Submit draft for feedback';
$string['draftfeedback:viewown'] = 'View own draft feedback';
$string['draftfeedback:review'] = 'Review and provide feedback on drafts';
$string['draftfeedback:viewall'] = 'View all draft feedback requests';

// Page titles.
$string['submitdraft'] = 'Submit Draft for Feedback';
$string['draftlist'] = 'Drafts Awaiting Feedback';
$string['viewdraft'] = 'View Draft';
$string['providefeedback'] = 'Provide Feedback';
$string['aireport'] = 'AI Detection Report';

// Buttons and actions.
$string['submitdraftforfeedback'] = 'Submit Draft for Feedback';
$string['submitdraft_help'] = 'Submit your work as a draft for tutor feedback before making your final submission. Your tutor will review it and provide comments to help you improve.';
$string['savefeedback'] = 'Save Feedback';
$string['viewfeedback'] = 'View Feedback';
$string['editdraft'] = 'Edit Draft';
$string['runaicheck'] = 'Run AI Check';
$string['viewaireport'] = 'View AI Report';

// Status labels.
$string['status_pending'] = 'Awaiting Feedback';
$string['status_reviewed'] = 'Feedback Provided';
$string['status_revised'] = 'Revised';

// Messages.
$string['draftsubmitted'] = 'Your draft has been submitted for feedback. Your tutor will review it soon.';
$string['feedbacksaved'] = 'Feedback has been saved and the learner has been notified.';
$string['nodrafts'] = 'No drafts awaiting feedback.';
$string['nofileordraft'] = 'Please upload a file or enter text before submitting your draft.';
$string['alreadypending'] = 'You already have a draft pending feedback for this assignment.';

// Table headers.
$string['learner'] = 'Learner';
$string['assignment'] = 'Assignment';
$string['course'] = 'Course';
$string['submitted'] = 'Submitted';
$string['status'] = 'Status';
$string['airesult'] = 'AI Check';
$string['actions'] = 'Actions';

// Dashboard cards.
$string['awaitingdraftfeedback'] = 'Awaiting Draft Feedback';
$string['draftspendingfeedback'] = 'Drafts pending review';

// Form labels.
$string['draftcontent'] = 'Draft Content';
$string['uploadfile'] = 'Upload File';
$string['onlinetext'] = 'Online Text';
$string['feedbacktext'] = 'Your Feedback';
$string['feedbacktext_help'] = 'Provide constructive feedback to help the learner improve their work before final submission.';

// Notifications.
$string['messageprovider:draftfeedback'] = 'Draft feedback notifications';
$string['notifylearner_subject'] = 'Feedback received on your draft';
$string['notifylearner_body'] = 'Your tutor has provided feedback on your draft for "{$a->assignment}" in {$a->course}. Please review the feedback and make any necessary revisions before submitting your final work.';
$string['notifytutor_subject'] = 'New draft submitted for feedback';
$string['notifytutor_body'] = '{$a->learner} has submitted a draft for feedback on "{$a->assignment}" in {$a->course}. Please review it at your earliest convenience.';

// Errors.
$string['invalidcmid'] = 'Invalid course module ID';
$string['invaliddraftid'] = 'Invalid draft ID';
$string['nopermission'] = 'You do not have permission to perform this action';
$string['assignmentonly'] = 'Draft feedback is only available for assignments';
$string['noaicheckdata'] = 'No AI check data available for this draft';
