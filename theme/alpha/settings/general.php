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
 *
 * @package   theme_alpha
 * @copyright 2022 - 2023 Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 *
 */

defined('MOODLE_INTERNAL') || die();

$page = new admin_settingpage('theme_alpha_general', get_string('generalsettings', 'theme_alpha'));

$name = 'theme_alpha/hintro';
$heading = get_string('hintro', 'theme_alpha', $a);
$setting = new alpha_setting_specialsettingheading($name, $heading,
    format_text(get_string('hintro_desc', 'theme_alpha', $a), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/darkmodefirst';
$title = get_string('darkmodefirst', 'theme_alpha');
$description = get_string('darkmodefirst_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/darkmodetheme';
$title = get_string('darkmodetheme', 'theme_alpha');
$description = get_string('darkmodetheme_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/sdarkmode';
$title = get_string('sdarkmode', 'theme_alpha');
$description = get_string('sdarkmode_desc', 'theme_alpha');
$default = 'Dark Mode';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/slightmode';
$title = get_string('slightmode', 'theme_alpha');
$description = get_string('slightmode_desc', 'theme_alpha');
$default = 'Light Mode';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/themeauthor';
$title = get_string('themeauthor', 'theme_alpha');
$description = get_string('themeauthor_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/backtotop';
$title = get_string('backtotop', 'theme_alpha');
$description = get_string('backtotop_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Video Size
$name = 'theme_alpha/forcefwvideo';
$title = get_string('forcefwvideo', 'theme_alpha');
$description = get_string('forcefwvideo_desc', 'theme_alpha', $a);
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Show hint for switched role.
$name = 'theme_alpha/showswitchedroleincourse';
$title = get_string('showswitchedroleincoursesetting', 'theme_alpha');
$description = get_string('showswitchedroleincoursesetting_desc', 'theme_alpha', $a);
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Show hint in hidden courses.
$name = 'theme_alpha/showhintcoursehidden';
$title = get_string('showhintcoursehiddensetting', 'theme_alpha');
$description = get_string('showhintcoursehiddensetting_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Show hint guest for access.
$name = 'theme_alpha/showhintcourseguestaccess';
$title = get_string('showhintcoursguestaccesssetting', 'theme_alpha');
$description = get_string('showhintcourseguestaccesssetting_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Show hint for self enrolment without enrolment key.
$name = 'theme_alpha/showhintcourseselfenrol';
$title = get_string('showhintcourseselfenrolsetting', 'theme_alpha');
$description = get_string('showhintcourseselfenrolsetting_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

// Unaddable blocks.
// Blocks to be excluded when this theme is enabled in the "Add a block" list: Administration, Navigation, Courses and
// Section links.
$default = 'navigation,settings,course_list,section_links';
$setting = new admin_setting_configtext(
    'theme_alpha/unaddableblocks',
    get_string('unaddableblocks', 'theme_alpha'),
    get_string('unaddableblocks_desc', 'theme_alpha'),
    $default,
    PARAM_TEXT
);
$page->add($setting);

// Google analytics block.
$name = 'theme_alpha/googleanalytics';
$title = get_string('googleanalytics', 'theme_alpha');
$description = get_string('googleanalyticsdesc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$settings->add($page);
