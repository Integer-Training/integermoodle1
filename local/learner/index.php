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
 * phpcs:disable moodle.Files.RequireLogin.Missing
 *
 * index file
 *
 * introduced 23/05/17 17:59
 *
 * @package   local_kopere_dashboard
 * @copyright 2017 Eduardo Kraus {@link http:// eduardokraus.com}
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/learner:view', $context);

$PAGE->set_url(new moodle_url('/local/learner/index.php'));
$PAGE->set_context($context);
$PAGE->set_title('Learners');

$PAGE->requires->css('/local/learner/styles.css');
$PAGE->requires->jquery();
//$PAGE->requires->js_call_amd('local_learnermanagement/main', 'init');

//$output = $PAGE->get_renderer('local_learner');
echo $OUTPUT->header();
echo html_writer::tag('h3','Learner Management');
// Renderable object (templatable)
$mainpage = new \local_learner\output\main_page();
// get data array for template
$data = $mainpage->export_for_template($OUTPUT);
echo $OUTPUT->footer();
echo '<style>
        .wrapper-course {
            margin-top:-30px;
            padding: 0px 10px !important;
        }
      </style>';