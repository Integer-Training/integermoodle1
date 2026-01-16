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
 * Course activities overview page.
 *
 * @package    core_course
 * @copyright  2025 Ferran Recio <ferran@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../config.php');
require_once('lib.php');
require_once($CFG->libdir . '/completionlib.php');

$courseid = required_param('id', PARAM_INT);
// The expand param is just a quick way of expanding a specific activity type so it
// does not require javascript to expand it. It is mostly used to accelerate behats.
$expand = optional_param_array('expand', [], PARAM_ALPHANUM);

$PAGE->set_url('/course/overview.php', ['id' => $courseid]);

$course = get_course($courseid);

$context = context_course::instance($course->id, MUST_EXIST);

require_login($course);
require_capability('moodle/course:viewoverview', $context);

// Trigger event, course information viewed.
$event = \core\event\course_overview_viewed::create(['context' => $context]);
$event->add_record_snapshot('course', $course);
$event->trigger();

$format = course_get_format($course);
$renderer = $format->get_renderer($PAGE);
$overviewpageclass = $format->get_output_classname('overview\\overviewpage');
/** @var core_courseformat\output\local\overview\overviewpage $overview */
$overview = new $overviewpageclass($course, $expand);
//print_object($overview);die;

$PAGE->set_pagelayout('incourse');

$PAGE->set_title(get_string('overview_page_title', 'course', $course->fullname));
$PAGE->set_heading($course->fullname);
include_course_ajax($course);

echo $renderer->header();

echo $renderer->heading(get_string('activities'), 2, 'h4');
echo $renderer->paragraph(get_string('overview_info', 'course'));

echo $renderer->render($overview);
//


$teachers = local_get_teacher_courses();
if($teachers){
    $url = new moodle_url('/local/learner/sessions.php',['id'=>$courseid]);
    $url1 = new moodle_url('/local/learner/duedates.php',['id'=>$courseid]);
	echo html_writer::start_tag('div');
    echo '<h3>'.html_writer::link($url,'Track Units').'<h3>';
    echo html_writer::start_tag('div');
    echo '<br>';
    echo html_writer::start_tag('div');
    echo '<h3>'.html_writer::link($url1,'Due Dates').'<h3>';
    echo html_writer::start_tag('div');
}

echo $renderer->footer();
//
function local_get_teacher_courses(){
    global $DB,$COURSE,$USER;
    // Get context levels for courses.
    $contextlevel = CONTEXT_COURSE;

    // Find teacher roles (editingteacher, teacher, etc.)
    $teacherroles = $DB->get_records_sql(
        "SELECT id FROM {role} WHERE shortname IN ('editingteacher', 'teacher')"
    );

    if (empty($teacherroles)) {
        return [];
    }

    $roleids = array_keys($teacherroles);
    list($insql, $params) = $DB->get_in_or_equal($roleids, SQL_PARAMS_NAMED);

    // Query to get all courses where user has teacher roles.
    $sql = "SELECT c.id, c.fullname, c.shortname, c.startdate, c.enddate
              FROM {course} c
              JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = :contextlevel
              JOIN {role_assignments} ra ON ra.contextid = ctx.id
             WHERE ra.userid = :userid AND ra.roleid $insql
          ORDER BY c.fullname ASC";

    $params['contextlevel'] = $contextlevel;
    $params['userid'] = $USER->id;

    return $DB->get_records_sql($sql, $params);
}

echo '<style>
      #hvp_overview .alert-info {
            display: none;
        }
     </style>';