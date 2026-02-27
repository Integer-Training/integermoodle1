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
 * Library functions and callbacks for local_news.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Extend navigation with news links.
 *
 * Note: This works with standard Moodle themes but Alpha theme ignores it.
 * For Alpha theme, the sidebar is hardcoded in core_renderer.php.
 *
 * @param global_navigation $nav Global navigation object
 */
function local_news_extend_navigation(global_navigation $nav) {
    global $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    $context = context_system::instance();

    // Add view news link for all users.
    if (has_capability('local/news:view', $context)) {
        $newsnode = $nav->add(
            get_string('news', 'local_news'),
            new moodle_url('/local/news/index.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_news_view',
            new pix_icon('i/news', get_string('news', 'local_news'))
        );
        $newsnode->showinflatnavigation = true;
    }

    // Add management link for admins.
    if (has_capability('local/news:manage', $context)) {
        $managenode = $nav->add(
            get_string('newsmanagement', 'local_news'),
            new moodle_url('/local/news/manage.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_news_manage',
            new pix_icon('i/settings', get_string('newsmanagement', 'local_news'))
        );
        $managenode->showinflatnavigation = true;
    }
}

/**
 * Called before page footer - inject popup script if needed.
 */
function local_news_before_footer() {
    global $SESSION, $PAGE, $USER;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    // Check if we have unread important news to show.
    if (!empty($SESSION->local_news_unread)) {
        $newsids = $SESSION->local_news_unread;
        unset($SESSION->local_news_unread); // Only show once per session.

        // Include the AMD module to show popups.
        $PAGE->requires->js_call_amd('local_news/popup', 'init', [$newsids]);
    }
}

/**
 * Get sidebar widget HTML for Alpha theme.
 *
 * This function is called from theme/alpha/classes/output/core_renderer.php
 * to render the news sidebar widget.
 *
 * @return string HTML for sidebar widget
 */
function local_news_get_sidebar_widget_html(): string {
    global $USER, $OUTPUT, $PAGE;

    if (!isloggedin() || isguestuser()) {
        return '';
    }

    $context = context_system::instance();
    if (!has_capability('local/news:view', $context)) {
        return '';
    }

    // Get recent news items.
    $news = \local_news\manager::get_recent_for_widget(5);
    if (empty($news)) {
        return '';
    }

    // Add read status and format dates.
    foreach ($news as $item) {
        $item->is_unread = !\local_news\manager::is_read_by_user($item->id, $USER->id);
        $item->formatted_date = userdate($item->publishdate, get_string('dateformat', 'local_news'));
        $item->category_label = \local_news\manager::get_category_label($item->category);
        $item->view_url = (new moodle_url('/local/news/view.php', ['id' => $item->id]))->out();
    }

    $unreadcount = \local_news\manager::count_unread_for_user($USER->id);

    // Build the widget HTML.
    $html = '<li class="rui-sidebar-nav-item news-widget-container">';
    $html .= '<div class="news-widget">';

    // Header with badge.
    $html .= '<div class="news-widget-header">';
    $html .= '<span class="news-widget-title"><i class="bi bi-megaphone me-2"></i>' . get_string('recentnews', 'local_news') . '</span>';
    if ($unreadcount > 0) {
        $html .= '<span class="news-widget-badge">' . $unreadcount . '</span>';
    }
    $html .= '</div>';

    // News items.
    $html .= '<ul class="news-widget-list">';
    foreach ($news as $item) {
        $unreadclass = $item->is_unread ? 'news-item-unread' : '';
        $importanticon = $item->important ? '<i class="bi bi-exclamation-triangle-fill text-danger me-1"></i>' : '';

        $html .= '<li class="news-widget-item ' . $unreadclass . '">';
        $html .= '<a href="' . $item->view_url . '" class="news-widget-link">';
        if ($item->is_unread) {
            $html .= '<span class="news-unread-dot"></span>';
        }
        $html .= $importanticon . '<span class="news-item-title">' . format_string($item->title) . '</span>';
        $html .= '<span class="news-item-meta">' . $item->formatted_date . ' &bull; ' . $item->category_label . '</span>';
        $html .= '</a>';
        $html .= '</li>';
    }
    $html .= '</ul>';

    // View all link.
    $viewallurl = new moodle_url('/local/news/index.php');
    $html .= '<div class="news-widget-footer">';
    $html .= '<a href="' . $viewallurl->out() . '" class="news-widget-viewall">' . get_string('viewall', 'local_news') . ' <i class="bi bi-arrow-right"></i></a>';
    $html .= '</div>';

    $html .= '</div>';
    $html .= '</li>';

    return $html;
}

/**
 * Get admin sidebar link HTML for Alpha theme.
 *
 * @return string HTML for admin sidebar link
 */
function local_news_get_admin_sidebar_link(): string {
    global $USER;

    if (!is_siteadmin($USER)) {
        return '';
    }

    $url = new moodle_url('/local/news/manage.php');

    $html = '<li class="rui-sidebar-nav-item">';
    $html .= '<a href="' . $url->out() . '" class="rui-sidebar-nav-item-link">';
    $html .= '<span class="rui-sidebar-nav-icon"><i class="fa-solid fa-bullhorn"></i></span>';
    $html .= '<span class="rui-sidebar-nav-text">' . get_string('newsmanagement', 'local_news') . '</span>';
    $html .= '</a>';
    $html .= '</li>';

    return $html;
}
