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
 * Language strings for local_news.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

// Plugin name.
$string['pluginname'] = 'News & Updates';
$string['news'] = 'News & Updates';

// Capabilities.
$string['news:manage'] = 'Manage news items';
$string['news:view'] = 'View news items';
$string['news:viewstats'] = 'View read statistics';

// Categories.
$string['category_update'] = 'Update';
$string['category_announcement'] = 'Announcement';
$string['category_maintenance'] = 'Maintenance';
$string['category_policy_change'] = 'Policy Change';
$string['category_help'] = 'Choose the appropriate category for your news item:

**Update** - Platform feature updates, new functionality, or improvements to existing features.

**Announcement** - General news, events, or important information that doesn\'t fit other categories.

**Maintenance** - Scheduled downtime, system maintenance, or technical work affecting platform availability.

**Policy Change** - Changes to terms of service, privacy policies, or compliance updates that may require acknowledgement.';

// Page titles.
$string['newsmanagement'] = 'News Management';
$string['createnews'] = 'Create News Item';
$string['editnews'] = 'Edit News Item';
$string['viewnews'] = 'View News';
$string['readstatistics'] = 'Read Statistics';

// Form labels.
$string['title'] = 'Title';
$string['content'] = 'Content';
$string['category'] = 'Category';
$string['important'] = 'Important';
$string['important_help'] = 'Important news will show as a popup on login and send email notifications to all users.';
$string['requiresacknowledgement'] = 'Requires Acknowledgement';
$string['requiresacknowledgement_help'] = 'Users must click "I Acknowledge" to confirm they have read this news item.';
$string['published'] = 'Published';
$string['draft'] = 'Draft';
$string['publishdate'] = 'Publish Date';
$string['publishdate_help'] = 'The news item will become visible from this date. Use future dates to schedule news.';
$string['expirydate'] = 'Expiry Date';
$string['expirydate_help'] = 'The news item will automatically hide after this date. Leave empty for no expiry.';
$string['publishingoptions'] = 'Publishing Options';
$string['status'] = 'Status';

// Actions.
$string['addnews'] = 'Add News Item';
$string['edit'] = 'Edit';
$string['delete'] = 'Delete';
$string['viewstats'] = 'View Statistics';
$string['acknowledge'] = 'I Acknowledge';
$string['acknowledgeconfirm'] = 'I Acknowledge - I Have Read This';
$string['markasread'] = 'Mark as Read';
$string['viewall'] = 'View All News';
$string['readmore'] = 'Read More';
$string['backtonews'] = 'Back to News';
$string['saveasdraft'] = 'Save as Draft';
$string['publish'] = 'Publish';
$string['cancel'] = 'Cancel';
$string['exportcsv'] = 'Export CSV';

// Status labels.
$string['unread'] = 'Unread';
$string['read'] = 'Read';
$string['acknowledged'] = 'Acknowledged';
$string['pendingacknowledgement'] = 'Pending Acknowledgement';
$string['importantnotice'] = 'Important Notice';

// Statistics.
$string['totalusers'] = 'Total Users';
$string['readcount'] = 'Read';
$string['acknowledgedcount'] = 'Acknowledged';
$string['userdetails'] = 'User Details';
$string['readat'] = 'Read At';
$string['acknowledgedat'] = 'Acknowledged At';
$string['notread'] = 'Not Read';
$string['notacknowledged'] = 'Not Acknowledged';
$string['overview'] = 'Overview';

// Table headers.
$string['views'] = 'Views';
$string['actions'] = 'Actions';

// Messages.
$string['newsdeleted'] = 'News item deleted successfully.';
$string['newssaved'] = 'News item saved successfully.';
$string['newspublished'] = 'News item published successfully.';
$string['nonews'] = 'No news items to display.';
$string['confirmdeletenews'] = 'Are you sure you want to delete this news item? This action cannot be undone.';
$string['acknowledgementrecorded'] = 'Your acknowledgement has been recorded.';
$string['alreadyacknowledged'] = 'You have already acknowledged this news item.';

// Sidebar widget.
$string['recentnews'] = 'Recent News';
$string['nounreadnews'] = 'No unread news';

// Notifications.
$string['messageprovider:importantnews'] = 'Important news notifications';
$string['notification_subject'] = 'Important News: {$a->title}';
$string['notification_body'] = 'There is an important update you should be aware of:

{$a->title}

{$a->content}

Please log in to acknowledge this news item.

{$a->url}';
$string['notification_html'] = '<p>There is an important update you should be aware of:</p>
<h3>{$a->title}</h3>
{$a->content}
<p><a href="{$a->url}">Click here to view and acknowledge this news item</a></p>';

// Scheduled task.
$string['task_send_notifications'] = 'Send important news notifications';

// Errors.
$string['error_titlerequired'] = 'Title is required.';
$string['error_contentrequired'] = 'Content is required.';
$string['error_newsnotfound'] = 'News item not found.';
$string['error_nopermission'] = 'You do not have permission to perform this action.';

// Date format.
$string['dateformat'] = '%d %b %Y';
$string['datetimeformat'] = '%d %b %Y at %H:%M';

// Misc.
$string['postedby'] = 'Posted by {$a->name} on {$a->date}';
$string['requiresack_notice'] = 'This notice requires acknowledgement.';
$string['youacknowledged'] = 'You acknowledged this on: {$a}';
$string['stayinformed'] = 'Stay informed about platform changes';
$string['filterby'] = 'Filter by';
$string['allcategories'] = 'All Categories';
