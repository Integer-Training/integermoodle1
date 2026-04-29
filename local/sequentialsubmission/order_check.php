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
 * Order preview — shows the unit order the plugin will enforce for each course.
 * Safety valve to spot courses whose drag-drop order doesn't match the intended
 * unit progression BEFORE the rule goes live.
 *
 * @package   local_sequentialsubmission
 * @copyright 2026 Integer Training
 */

require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sequentialsubmission:manage', $context);

use local_sequentialsubmission\lock_checker;

$PAGE->set_url(new moodle_url('/local/sequentialsubmission/order_check.php'));
$PAGE->set_context($context);
$PAGE->set_title(get_string('orderpreview', 'local_sequentialsubmission'));
$PAGE->set_heading(get_string('orderpreview', 'local_sequentialsubmission'));
$PAGE->set_pagelayout('admin');

echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">';
echo $OUTPUT->header();

// Get all visible courses that have at least one assignment.
$courses = $DB->get_records_sql(
    "SELECT DISTINCT c.id, c.fullname, c.shortname
     FROM {course} c
     JOIN {assign} a ON a.course = c.id
     WHERE c.visible = 1 AND c.id > 1
     ORDER BY c.fullname"
);

$exempt_patterns = lock_checker::get_exempt_patterns();
$disabled_courses = lock_checker::get_disabled_courses();

?>
<style>
.oc-shell { max-width: 1200px; margin: 0 auto; padding: 16px 24px; font-family: 'Inter', system-ui, sans-serif; color: #1a2238; }
.oc-header { background: linear-gradient(135deg, #1a2238 0%, #3a5ba0 100%); color: #fff; border-radius: 12px; padding: 24px 28px; margin-bottom: 24px; }
.oc-header h1 { font-family: 'Libre Baskerville', Georgia, serif; font-size: 26px; margin: 0 0 8px 0; color: #fff; }
.oc-header p { margin: 0; color: rgba(255,255,255,0.85); font-size: 13.5px; }
.oc-course { background: #fff; border-radius: 10px; padding: 22px 26px; margin-bottom: 16px; box-shadow: 0 2px 6px rgba(0,0,0,0.04); }
.oc-course h3 { font-family: 'Libre Baskerville', serif; font-size: 17px; margin: 0 0 12px 0; color: #1a2238; display: flex; align-items: center; gap: 10px; }
.oc-course h3 .oc-disabled-pill { background: #ff7675; color: #fff; padding: 2px 10px; border-radius: 12px; font-size: 11px; font-weight: 600; font-family: 'Inter', sans-serif; text-transform: uppercase; letter-spacing: 0.05em; }
.oc-table { width: 100%; border-collapse: collapse; font-size: 13.5px; }
.oc-table th { background: #f8fafc; padding: 8px 12px; text-align: left; font-size: 11px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.05em; border-bottom: 1px solid #e5e7eb; }
.oc-table td { padding: 9px 12px; border-bottom: 1px solid #f1f5f9; }
.oc-table tr:last-child td { border-bottom: none; }
.oc-exempt { color: #94a3b8; background: #f8fafc; }
.oc-pill-yes { background: #fef3c7; color: #92400e; padding: 2px 8px; border-radius: 10px; font-size: 11px; font-weight: 600; }
.oc-pill-no { color: #64748b; font-size: 12px; }
.oc-none { color: #94a3b8; font-style: italic; padding: 12px; }
</style>

<div class="oc-shell">
    <div class="oc-header">
        <h1><i class="bi bi-list-ol"></i> <?php echo s(get_string('orderpreview', 'local_sequentialsubmission')); ?></h1>
        <p><?php echo s(get_string('orderpreview_intro', 'local_sequentialsubmission')); ?></p>
    </div>

    <?php foreach ($courses as $course):
        $is_disabled = in_array((int) $course->id, $disabled_courses, true);
        // Get all assignments in this course with their ordering.
        $assigns = $DB->get_records_sql(
            "SELECT cm.id AS cmid, cm.section AS sectionid, a.name, cs.section AS section_number, cs.sequence
             FROM {course_modules} cm
             JOIN {course_sections} cs ON cs.id = cm.section
             JOIN {modules} m ON m.id = cm.module AND m.name = 'assign'
             JOIN {assign} a ON a.id = cm.instance
             WHERE cm.course = :courseid AND cm.visible = 1
             ORDER BY cs.section ASC",
            ['courseid' => $course->id]
        );

        // Sort by position_in_sequence for display consistency.
        $rows = [];
        foreach ($assigns as $a) {
            $pos = lock_checker::position_in_sequence($a->section_number, $a->sequence, $a->cmid);
            $rows[] = [
                'cmid' => (int) $a->cmid,
                'pos' => $pos,
                'name' => $a->name,
                'section' => (int) $a->section_number,
                'is_exempt' => lock_checker::is_assignment_exempt($a->name),
            ];
        }
        usort($rows, function($a, $b) { return $a['pos'] <=> $b['pos']; });
    ?>
        <div class="oc-course">
            <h3>
                <i class="bi bi-folder"></i>
                <?php echo s($course->fullname); ?>
                <span style="color:#94a3b8; font-weight: 400; font-size: 12px;">(ID <?php echo (int) $course->id; ?>)</span>
                <?php if ($is_disabled): ?>
                    <span class="oc-disabled-pill">Exempt course</span>
                <?php endif; ?>
            </h3>
            <?php if (empty($rows)): ?>
                <div class="oc-none"><?php echo s(get_string('orderpreview_noassignments', 'local_sequentialsubmission')); ?></div>
            <?php else: ?>
                <table class="oc-table">
                    <thead><tr>
                        <th style="width:80px;"><?php echo s(get_string('orderpreview_col_pos', 'local_sequentialsubmission')); ?></th>
                        <th>Section</th>
                        <th><?php echo s(get_string('orderpreview_col_assignment', 'local_sequentialsubmission')); ?></th>
                        <th style="width:100px;"><?php echo s(get_string('orderpreview_col_exempt', 'local_sequentialsubmission')); ?></th>
                    </tr></thead>
                    <tbody>
                    <?php foreach ($rows as $i => $r): ?>
                        <tr<?php if ($r['is_exempt']): ?> class="oc-exempt"<?php endif; ?>>
                            <td><strong><?php echo $i + 1; ?></strong></td>
                            <td><?php echo $r['section']; ?></td>
                            <td><?php echo s($r['name']); ?></td>
                            <td>
                                <?php if ($r['is_exempt']): ?>
                                    <span class="oc-pill-yes"><?php echo s(get_string('orderpreview_exempt_yes', 'local_sequentialsubmission')); ?></span>
                                <?php else: ?>
                                    <span class="oc-pill-no"><?php echo s(get_string('orderpreview_exempt_no', 'local_sequentialsubmission')); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
</div>

<?php echo $OUTPUT->footer(); ?>
