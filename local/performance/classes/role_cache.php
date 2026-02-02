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

namespace local_performance;

defined('MOODLE_INTERNAL') || die();

/**
 * 3-tier cached role service.
 *
 * Replaces 4-7 scattered DB queries per page with a single query, cached in:
 *   1. Static memory  (free — current PHP request)
 *   2. MUC session     (persists across page loads, no external deps)
 *   3. DB fallback     (one query, then cached in both upper tiers)
 *
 * Usage:
 *   \local_performance\role_cache::is_admin();   // current user
 *   \local_performance\role_cache::is_teacher(42); // specific user
 */
class role_cache {

    /** @var array Static per-request cache: userid => string[] of role shortnames. */
    private static $cache = [];

    /**
     * Check if a user is a site admin or has the manager role.
     *
     * @param int|null $userid  Defaults to current user.
     * @return bool
     */
    public static function is_admin(?int $userid = null): bool {
        global $USER;
        $userid = $userid ?? (int) $USER->id;

        // is_siteadmin() checks $CFG->siteadmins — fast, no DB query.
        if (is_siteadmin($userid)) {
            return true;
        }

        $roles = self::get_roles($userid);
        return in_array('manager', $roles, true);
    }

    /**
     * Check if a user has the non-editing teacher role.
     *
     * @param int|null $userid  Defaults to current user.
     * @return bool
     */
    public static function is_teacher(?int $userid = null): bool {
        $roles = self::get_roles($userid);
        return in_array('teacher', $roles, true);
    }

    /**
     * Check if a user has the editing teacher role.
     *
     * @param int|null $userid  Defaults to current user.
     * @return bool
     */
    public static function is_editing_teacher(?int $userid = null): bool {
        $roles = self::get_roles($userid);
        return in_array('editingteacher', $roles, true);
    }

    /**
     * Check if a user has the student role.
     *
     * @param int|null $userid  Defaults to current user.
     * @return bool
     */
    public static function is_student(?int $userid = null): bool {
        $roles = self::get_roles($userid);
        return in_array('student', $roles, true);
    }

    /**
     * Check if a user has any of the given role shortnames.
     *
     * @param array    $shortnames  e.g. ['teacher', 'editingteacher']
     * @param int|null $userid      Defaults to current user.
     * @return bool
     */
    public static function has_any_role(array $shortnames, ?int $userid = null): bool {
        $roles = self::get_roles($userid);
        return !empty(array_intersect($shortnames, $roles));
    }

    /**
     * Get all role shortnames for a user.
     *
     * Tier 1: static memory (same request).
     * Tier 2: MUC session cache (across page loads).
     * Tier 3: Single DB query (then stored in tiers 1 + 2).
     *
     * @param int|null $userid  Defaults to current user.
     * @return string[] Array of role shortnames (e.g. ['student', 'teacher']).
     */
    public static function get_roles(?int $userid = null): array {
        global $USER, $DB;
        $userid = $userid ?? (int) $USER->id;

        if ($userid < 1) {
            return [];
        }

        // Tier 1: Static memory.
        if (isset(self::$cache[$userid])) {
            return self::$cache[$userid];
        }

        // Tier 2: MUC session cache.
        $muckey = 'u' . $userid;
        try {
            $muc = \cache::make('local_performance', 'user_roles');
            $cached = $muc->get($muckey);
            if ($cached !== false) {
                // Stored as comma-separated string for simpledata compatibility.
                $roles = $cached === '' ? [] : explode(',', $cached);
                self::$cache[$userid] = $roles;
                return $roles;
            }
        } catch (\Exception $e) {
            // MUC unavailable (plugin not yet installed, upgrade in progress, etc.).
            $muc = null;
        }

        // Tier 3: DB query — single query replaces 4-7 scattered queries.
        $sql = "SELECT DISTINCT r.shortname
                  FROM {role_assignments} ra
                  JOIN {role} r ON r.id = ra.roleid
                 WHERE ra.userid = :userid";
        $records = $DB->get_records_sql($sql, ['userid' => $userid]);
        $roles = array_keys($records);

        // Store in both upper tiers.
        self::$cache[$userid] = $roles;
        if ($muc) {
            $muc->set($muckey, implode(',', $roles));
        }

        return $roles;
    }

    /**
     * Invalidate cached roles for a user (call after role changes).
     *
     * @param int|null $userid  Defaults to current user.
     */
    public static function invalidate(?int $userid = null): void {
        global $USER;
        $userid = $userid ?? (int) $USER->id;

        unset(self::$cache[$userid]);

        $muckey = 'u' . $userid;
        try {
            $muc = \cache::make('local_performance', 'user_roles');
            $muc->delete($muckey);
        } catch (\Exception $e) {
            // Ignore — cache not available.
        }
    }

    /**
     * Invalidate all cached roles (call during upgrade/purge).
     */
    public static function invalidate_all(): void {
        self::$cache = [];

        try {
            $muc = \cache::make('local_performance', 'user_roles');
            $muc->purge();
        } catch (\Exception $e) {
            // Ignore.
        }
    }
}
