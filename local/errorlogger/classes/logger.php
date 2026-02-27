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

namespace local_errorlogger;

/**
 * Core logging class — writes error entries to the database.
 *
 * @package    local_errorlogger
 * @copyright  2026 Integer Training
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class logger {

    /**
     * Log an error entry to the database.
     *
     * @param array $data Keys: type, severity, message, component, details
     */
    public static function log(array $data): void {
        global $DB, $USER;

        // Safety: don't log during install or if DB not available.
        if (!$DB || during_initial_install()) {
            return;
        }

        // Verify our table exists (first-run safety).
        try {
            $dbman = $DB->get_manager();
            if (!$dbman->table_exists('local_errorlogger_logs')) {
                return;
            }
        } catch (\Throwable $e) {
            return;
        }

        $record = new \stdClass();
        $record->type        = substr($data['type'] ?? 'error', 0, 30);
        $record->severity    = (int) ($data['severity'] ?? 3);
        $record->component   = substr($data['component'] ?? '', 0, 255);
        $record->message     = substr($data['message'] ?? '', 0, 10000);
        $record->details     = $data['details'] ?? '';
        $record->url         = self::get_current_url();
        $record->userid      = (!empty($USER->id) && $USER->id > 0) ? (int) $USER->id : null;
        $record->ipaddress   = self::get_ip();
        $record->timecreated = time();

        try {
            $DB->insert_record('local_errorlogger_logs', $record, false);
        } catch (\Throwable $e) {
            // Silently fail — never let logging break the site.
        }
    }

    /**
     * Get the current page URL safely.
     *
     * @return string
     */
    private static function get_current_url(): string {
        try {
            $url = qualified_me();
            return $url ? substr((string) $url, 0, 2000) : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    /**
     * Get the client IP address safely.
     *
     * @return string
     */
    private static function get_ip(): string {
        try {
            return getremoteaddr('');
        } catch (\Throwable $e) {
            return '';
        }
    }
}
