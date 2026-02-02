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

$string['pluginname'] = 'Performance Optimizer';
$string['performance:view'] = 'View performance dashboard';
$string['dashboard_title'] = 'Performance Health Dashboard';
$string['settings_heading'] = 'Optimization Toggles';
$string['settings_heading_desc'] = 'Enable or disable specific performance optimizations. Changes take effect after purging caches.';
$string['enable_role_cache'] = 'Enable role cache service';
$string['enable_role_cache_desc'] = 'Cache user role lookups (admin/teacher/student) in the session, eliminating repeated DB queries on every page load. Saves 4-7 queries per page.';
$string['enable_nav_shortcircuit'] = 'Short-circuit nav hooks for Alpha theme';
$string['enable_nav_shortcircuit_desc'] = 'Skip role DB queries in learnerdashboard and learnerprogression lib.php when Alpha theme is active (these nav hooks are ignored by Alpha). Saves 4 queries per page.';
$string['enable_logstore_fix'] = 'Optimized logstore query';
$string['enable_logstore_fix_desc'] = 'Use timestamp range instead of YEAR(FROM_UNIXTIME()) in the learner dashboard hours query, enabling index usage on logstore_standard_log.';
$string['dashboard_link'] = 'Health Dashboard';
$string['dashboard_link_desc'] = 'View the <a href="{$a}">Performance Health Dashboard</a> for bottleneck diagnostics and health score.';
