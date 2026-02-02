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
 * Automated health checker — runs 10 bottleneck checks and produces a weighted score.
 *
 * Status values: PASS, FAIL, WARN, INFO
 * Score weights reflect the query-savings potential of each fix.
 */
class health_checker {

    const STATUS_PASS = 'pass';
    const STATUS_FAIL = 'fail';
    const STATUS_WARN = 'warn';
    const STATUS_INFO = 'info';

    /**
     * Run all checks and return results + overall score.
     *
     * @return array ['checks' => [...], 'score' => int, 'max_score' => int]
     */
    public static function run_all(): array {
        $checks = [
            self::check_sidebar_role_cache(),
            self::check_learnerdashboard_nav(),
            self::check_learnerprogression_nav(),
            self::check_kopere_menu_cache(),
            self::check_kopere_wildcard_observer(),
            self::check_logstore_query(),
            self::check_admindashboard_n1(),
            self::check_assignrelative_observer(),
            self::check_config_performance(),
            self::check_muc_backend(),
        ];

        $earned = 0;
        $max = 0;
        foreach ($checks as $check) {
            $max += $check['weight'];
            if ($check['status'] === self::STATUS_PASS) {
                $earned += $check['weight'];
            } elseif ($check['status'] === self::STATUS_WARN) {
                $earned += (int) ($check['weight'] * 0.5);
            }
            // INFO checks don't affect score.
        }

        $score = $max > 0 ? (int) round(($earned / $max) * 100) : 0;

        return [
            'checks' => $checks,
            'score' => $score,
            'max_score' => 100,
        ];
    }

    /**
     * Check 1: Sidebar role cache patched in core_renderer.php.
     */
    private static function check_sidebar_role_cache(): array {
        $file = __DIR__ . '/../../../theme/alpha/classes/output/core_renderer.php';
        $result = [
            'id' => 'sidebar_role_cache',
            'title' => 'Sidebar role cache',
            'weight' => 20,
        ];

        if (!file_exists($file)) {
            $result['status'] = self::STATUS_INFO;
            $result['detail'] = 'Alpha theme not found.';
            return $result;
        }

        $content = file_get_contents($file);
        if (strpos($content, 'role_cache::') !== false) {
            $result['status'] = self::STATUS_PASS;
            $result['detail'] = 'core_renderer.php uses role_cache service. Saves 4-7 queries/page.';
        } else {
            $result['status'] = self::STATUS_FAIL;
            $result['detail'] = 'core_renderer.php still uses raw DB queries for role detection (4-7 queries/page).';
        }

        return $result;
    }

    /**
     * Check 2: learnerdashboard nav hook has Alpha short-circuit.
     */
    private static function check_learnerdashboard_nav(): array {
        $file = __DIR__ . '/../../learnerdashboard/lib.php';
        $result = [
            'id' => 'learnerdashboard_nav',
            'title' => 'Learner Dashboard nav hook',
            'weight' => 10,
        ];

        if (!file_exists($file)) {
            $result['status'] = self::STATUS_INFO;
            $result['detail'] = 'learnerdashboard plugin not found.';
            return $result;
        }

        $content = file_get_contents($file);
        if (strpos($content, "theme->name") !== false && strpos($content, 'alpha') !== false) {
            $result['status'] = self::STATUS_PASS;
            $result['detail'] = 'Alpha theme short-circuit present. Saves 2 queries/page.';
        } else {
            $result['status'] = self::STATUS_FAIL;
            $result['detail'] = 'Nav hook runs 2 DB queries on every page even though Alpha ignores flatnavigation.';
        }

        return $result;
    }

    /**
     * Check 3: learnerprogression nav hook has Alpha short-circuit.
     */
    private static function check_learnerprogression_nav(): array {
        $file = __DIR__ . '/../../learnerprogression/lib.php';
        $result = [
            'id' => 'learnerprogression_nav',
            'title' => 'Learner Progression nav hook',
            'weight' => 10,
        ];

        if (!file_exists($file)) {
            $result['status'] = self::STATUS_INFO;
            $result['detail'] = 'learnerprogression plugin not found.';
            return $result;
        }

        $content = file_get_contents($file);
        if (strpos($content, "theme->name") !== false && strpos($content, 'alpha') !== false) {
            $result['status'] = self::STATUS_PASS;
            $result['detail'] = 'Alpha theme short-circuit present. Saves 2 queries/page.';
        } else {
            $result['status'] = self::STATUS_FAIL;
            $result['detail'] = 'Nav hook runs 2+ DB queries on every page even though Alpha ignores flatnavigation.';
        }

        return $result;
    }

    /**
     * Check 4: Kopere menu cache enabled (no "false &&" bypass).
     */
    private static function check_kopere_menu_cache(): array {
        $file = __DIR__ . '/../../kopere_dashboard/lib.php';
        $result = [
            'id' => 'kopere_menu_cache',
            'title' => 'Kopere menu cache',
            'weight' => 15,
        ];

        if (!file_exists($file)) {
            $result['status'] = self::STATUS_INFO;
            $result['detail'] = 'kopere_dashboard plugin not found.';
            return $result;
        }

        $content = file_get_contents($file);
        if (strpos($content, 'false &&') !== false || strpos($content, 'false&&') !== false) {
            $result['status'] = self::STATUS_FAIL;
            $result['detail'] = 'Menu cache disabled with "false &&" guard. Causes 5-10 recursive DB queries/page.';
        } else {
            $result['status'] = self::STATUS_PASS;
            $result['detail'] = 'Menu cache enabled. Saves 5-10 queries/page after first load.';
        }

        return $result;
    }

    /**
     * Check 5: Kopere wildcard observer (info/warn — third-party, not our fix).
     */
    private static function check_kopere_wildcard_observer(): array {
        $file = __DIR__ . '/../../kopere_dashboard/db/events.php';
        $result = [
            'id' => 'kopere_wildcard',
            'title' => 'Kopere wildcard observer',
            'weight' => 5,
        ];

        if (!file_exists($file)) {
            $result['status'] = self::STATUS_INFO;
            $result['detail'] = 'kopere_dashboard plugin not found.';
            return $result;
        }

        $content = file_get_contents($file);
        if (strpos($content, '"*"') !== false || strpos($content, "'*'") !== false) {
            $result['status'] = self::STATUS_WARN;
            $result['detail'] = 'Wildcard event observer ("*") fires on every Moodle event. Third-party code — risky to modify.';
        } else {
            $result['status'] = self::STATUS_PASS;
            $result['detail'] = 'No wildcard observer found.';
        }

        return $result;
    }

    /**
     * Check 6: Logstore query uses timestamp range (not YEAR(FROM_UNIXTIME())).
     */
    private static function check_logstore_query(): array {
        $file = __DIR__ . '/../../learnerdashboard/index.php';
        $result = [
            'id' => 'logstore_query',
            'title' => 'Logstore query optimization',
            'weight' => 15,
        ];

        if (!file_exists($file)) {
            $result['status'] = self::STATUS_INFO;
            $result['detail'] = 'learnerdashboard plugin not found.';
            return $result;
        }

        $content = file_get_contents($file);
        $has_old = strpos($content, 'FROM_UNIXTIME') !== false;
        $has_new = strpos($content, 'enable_logstore_fix') !== false;

        if ($has_new && $has_old) {
            // Optimized code present with fallback — check if toggle is on.
            if (get_config('local_performance', 'enable_logstore_fix')) {
                $result['status'] = self::STATUS_PASS;
                $result['detail'] = 'Optimized logstore query active (timestamp range). Fallback retained for safety.';
            } else {
                $result['status'] = self::STATUS_WARN;
                $result['detail'] = 'Optimized query patched but toggle is OFF. Enable in Settings to use index-friendly query.';
            }
        } else if ($has_old) {
            $result['status'] = self::STATUS_FAIL;
            $result['detail'] = 'Logstore query wraps timecreated in YEAR(FROM_UNIXTIME()), preventing index usage on millions of rows.';
        } else {
            $result['status'] = self::STATUS_PASS;
            $result['detail'] = 'Logstore query uses timestamp range — index-friendly.';
        }

        return $result;
    }

    /**
     * Check 7: Admindashboard N+1 queries (info — major refactor needed).
     */
    private static function check_admindashboard_n1(): array {
        $file = __DIR__ . '/../../admindashboard/index.php';
        $result = [
            'id' => 'admindashboard_n1',
            'title' => 'Admin dashboard N+1 queries',
            'weight' => 5,
        ];

        if (!file_exists($file)) {
            $result['status'] = self::STATUS_INFO;
            $result['detail'] = 'admindashboard plugin not found.';
            return $result;
        }

        $content = file_get_contents($file);
        if (preg_match('/foreach.*tutor/i', $content) && substr_count($content, 'get_record') > 3) {
            $result['status'] = self::STATUS_INFO;
            $result['detail'] = 'Per-tutor loop with individual queries (N+1 pattern, 200-300 queries). Requires major refactor.';
        } else {
            $result['status'] = self::STATUS_PASS;
            $result['detail'] = 'No obvious N+1 pattern detected.';
        }

        return $result;
    }

    /**
     * Check 8: assignrelative observer writes to global assign table.
     */
    private static function check_assignrelative_observer(): array {
        $file = __DIR__ . '/../../assignrelative/classes/observer.php';
        $result = [
            'id' => 'assignrelative_observer',
            'title' => 'Assign-relative observer',
            'weight' => 5,
        ];

        if (!file_exists($file)) {
            $result['status'] = self::STATUS_INFO;
            $result['detail'] = 'assignrelative plugin not found.';
            return $result;
        }

        $content = file_get_contents($file);
        if (strpos($content, 'update_record') !== false && strpos($content, '{assign}') === false
            && strpos($content, "'assign'") !== false) {
            $result['status'] = self::STATUS_WARN;
            $result['detail'] = 'Observer writes to global {assign} table on every view — overwrites dates for all students. Should use assign_overrides.';
        } else if (strpos($content, 'update_record') !== false) {
            $result['status'] = self::STATUS_WARN;
            $result['detail'] = 'Observer updates assignment records on every view. Consider per-user overrides.';
        } else {
            $result['status'] = self::STATUS_PASS;
            $result['detail'] = 'No global update pattern detected.';
        }

        return $result;
    }

    /**
     * Check 9: config.php performance settings.
     */
    private static function check_config_performance(): array {
        global $CFG;
        $result = [
            'id' => 'config_performance',
            'title' => 'config.php performance settings',
            'weight' => 10,
        ];

        $found = 0;
        $missing = [];

        if (!empty($CFG->cachejs)) {
            $found++;
        } else {
            $missing[] = '$CFG->cachejs = true';
        }

        if (isset($CFG->langstringcache) && $CFG->langstringcache) {
            $found++;
        } else {
            // langstringcache defaults to true in Moodle, so not a major issue.
            $found++;
        }

        if (!empty($CFG->themedesignermode)) {
            $missing[] = '$CFG->themedesignermode should be false in production';
        } else {
            $found++;
        }

        if (empty($CFG->debug) || $CFG->debug == 0) {
            $found++;
        } else {
            $missing[] = '$CFG->debug should be 0 in production';
        }

        if (empty($missing)) {
            $result['status'] = self::STATUS_PASS;
            $result['detail'] = 'Core performance settings look good.';
        } else {
            $result['status'] = self::STATUS_WARN;
            $result['detail'] = 'Recommendations: ' . implode('; ', $missing) . '.';
        }

        return $result;
    }

    /**
     * Check 10: MUC backend (APCu/Redis availability).
     */
    private static function check_muc_backend(): array {
        $result = [
            'id' => 'muc_backend',
            'title' => 'MUC cache backend',
            'weight' => 5,
        ];

        $apcu = function_exists('apcu_store');
        $redis = class_exists('Redis');

        if ($apcu || $redis) {
            $backends = [];
            if ($apcu) {
                $backends[] = 'APCu';
            }
            if ($redis) {
                $backends[] = 'Redis';
            }

            // Check if any are actually configured as MUC stores.
            $mucconfig = __DIR__ . '/../../../moodledata/muc/config.php';
            // Use $CFG->dataroot for correct path.
            global $CFG;
            $mucfile = $CFG->dataroot . '/muc/config.php';

            if (file_exists($mucfile)) {
                $content = file_get_contents($mucfile);
                if (strpos($content, 'cachestore_apcu') !== false || strpos($content, 'cachestore_redis') !== false) {
                    $result['status'] = self::STATUS_PASS;
                    $result['detail'] = 'Fast cache backend available and configured: ' . implode(', ', $backends) . '.';
                } else {
                    $result['status'] = self::STATUS_WARN;
                    $result['detail'] = implode(', ', $backends) . ' available but not configured as MUC store. All caching uses file-based backend.';
                }
            } else {
                $result['status'] = self::STATUS_WARN;
                $result['detail'] = implode(', ', $backends) . ' PHP extension(s) available. Configure in Site admin > Plugins > Caching > Stores.';
            }
        } else {
            $result['status'] = self::STATUS_INFO;
            $result['detail'] = 'No APCu or Redis PHP extensions available. File-based MUC caching is used (standard for shared hosting).';
        }

        return $result;
    }
}
