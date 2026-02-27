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
 * Event observer for local_news.
 *
 * @package   local_news
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_news;

defined('MOODLE_INTERNAL') || die();

/**
 * Observer class to handle events.
 */
class observer {

    /**
     * Handle user login event - check for unread important news.
     *
     * @param \core\event\user_loggedin $event The event object
     */
    public static function user_loggedin(\core\event\user_loggedin $event) {
        global $SESSION;

        $userid = (int) $event->userid;

        // Get unread important news requiring acknowledgement.
        $unread = manager::get_unread_important_for_user($userid);

        if (!empty($unread)) {
            // Store news IDs in session - the before_footer hook will show the popup.
            $SESSION->local_news_unread = array_keys($unread);
        }
    }
}
