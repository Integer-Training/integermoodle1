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

$page = new admin_settingpage('theme_alpha_settingssidebar', get_string('settingssidebar', 'theme_alpha'));

$name = 'theme_alpha/mycoursesontop';
$title = get_string('mycoursesontop', 'theme_alpha');
$description = get_string('mycoursesontop_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/hcustomsidebarlogo';
$heading = get_string('hcustomsidebarlogo', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading,
    format_text(get_string('hcustomsidebarlogo_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/customsidebarlogo';
$title = get_string('customsidebarlogo', 'theme_alpha');
$description = get_string('customsidebarlogo_desc', 'theme_alpha');
$opts = array('accepted_types' => array('.png', '.jpg', '.svg', 'gif'));
$setting = new admin_setting_configstoredfile($name, $title, $description, 'customsidebarlogo', 0, $opts);
$page->add($setting);

$name = 'theme_alpha/customsidebardmlogo';
$title = get_string('customsidebardmlogo', 'theme_alpha');
$description = get_string('customsidebardmlogo_desc', 'theme_alpha');
$opts = array('accepted_types' => array('.png', '.jpg', '.svg', 'gif'));
$setting = new admin_setting_configstoredfile($name, $title, $description, 'customsidebardmlogo', 0, $opts);
$page->add($setting);

$name = 'theme_alpha/hcustomcontent';
$heading = get_string('hcustomcontent', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading,
    format_text(get_string('hcustomcontent_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/customstcontent';
$title = get_string('customstcontent', 'theme_alpha');
$description = get_string('customstcontent_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/customsmcontent';
$title = get_string('customsmcontent', 'theme_alpha');
$description = get_string('customsmcontent_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/customsfcontent';
$title = get_string('customsfcontent', 'theme_alpha');
$description = get_string('customsfcontent_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/hcourseindexdrawer';
$heading = get_string('hcourseindexdrawer', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading,
    format_text(get_string('hcourseindexdrawer_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/customcitcontent';
$title = get_string('customcitcontent', 'theme_alpha');
$description = get_string('customcitcontent_desc', 'theme_alpha', $a);
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/customcibcontent';
$title = get_string('customcibcontent', 'theme_alpha');
$description = get_string('customcibcontent_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

// Label.
$name = 'theme_alpha/hturnoffsidebar';
$heading = get_string('hturnoffsidebar', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading,
    format_text(get_string('hturnoffsidebar_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/labelsidebaropened';
$title = get_string('labelsidebaropened', 'theme_alpha');
$description = get_string('labelsidebaropened_desc', 'theme_alpha', $a);
$default = 'Open the sidebar';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/labelsidebarclosed';
$title = get_string('labelsidebarclosed', 'theme_alpha');
$description = get_string('labelsidebarclosed_desc', 'theme_alpha', $a);
$default = 'Close the sidebar';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/hturnoffsidebar';
$heading = get_string('hturnoffsidebar', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading,
    format_text(get_string('hturnoffsidebar_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/turnoffsidebarfp';
$title = get_string('turnoffsidebarfp', 'theme_alpha');
$description = get_string('turnoffsidebarfp_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/turnoffsidebardashboard';
$title = get_string('turnoffsidebardashboard', 'theme_alpha');
$description = get_string('turnoffsidebardashboard_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/turnoffsidebarcourse';
$title = get_string('turnoffsidebarcourse', 'theme_alpha');
$description = get_string('turnoffsidebarcourse_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/turnoffsidebarincourse';
$title = get_string('turnoffsidebarincourse', 'theme_alpha');
$description = get_string('turnoffsidebarincourse_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/turnoffsidebarreport';
$title = get_string('turnoffsidebarreport', 'theme_alpha');
$description = get_string('turnoffsidebarreport_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/turnoffsidebarstandard';
$title = get_string('turnoffsidebarstandard', 'theme_alpha');
$description = get_string('turnoffsidebarstandard_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/turnoffsidebaradmin';
$title = get_string('turnoffsidebaradmin', 'theme_alpha');
$description = get_string('turnoffsidebaradmin_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/hmycoursesbtn';
$heading = get_string('hmycoursesbtn', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading,
    format_text(get_string('hmycoursesbtn_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/showmycoursesbox';
$title = get_string('showmycoursesbox', 'theme_alpha');
$description = get_string('showmycoursesbox_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/hidedetails';
$title = get_string('hidedetails', 'theme_alpha');
$description = get_string('hidedetails_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/stringmycourses';
$title = get_string('stringmycourses', 'theme_alpha');
$description = get_string('stringmycourses_desc', 'theme_alpha');
$default = 'My Courses';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/stringnocourses';
$title = get_string('stringnocourses', 'theme_alpha');
$description = get_string('stringnocourses_desc', 'theme_alpha');
$default = 'You are not enrolled in any courses.';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/stringshowhidden';
$title = get_string('stringshowhidden', 'theme_alpha');
$description = get_string('stringshowhidden_desc', 'theme_alpha');
$default = 'Show hidden courses';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/stringshowonlyinprogress';
$title = get_string('stringshowonlyinprogress', 'theme_alpha');
$description = get_string('stringshowonlyinprogress_desc', 'theme_alpha');
$default = 'Only courses in progress';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/stringdetails';
$title = get_string('stringdetails', 'theme_alpha');
$description = get_string('stringdetails_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/stringallcourses';
$title = get_string('stringallcourses', 'theme_alpha');
$description = get_string('stringallcourses_desc', 'theme_alpha');
$default = '<span>List of all available courses</span>
<svg width="18"
height="18"
fill="none"
viewBox="0
0
24
24"><path
stroke="currentColor"
stroke-linecap="round"
stroke-linejoin="round"
stroke-width="1.5"
d="M13.75 6.75L19.25 12L13.75 17.25"></path>
<path
stroke="currentColor"
stroke-linecap="round"
stroke-linejoin="round"
stroke-width="1.5"
d="M19 12H4.75"></path></svg>';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/stringnocourses';
$title = get_string('stringnocourses', 'theme_alpha');
$description = get_string('stringnocourses_desc', 'theme_alpha');
$default = 'You are not enrolled in any courses.';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/mycourseswrapperheight';
$title = get_string('mycourseswrapperheight', 'theme_alpha');
$description = get_string('mycourseswrapperheight_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/hsidebarcolors';
$heading = get_string('hsidebarcolors', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading,
    format_text(get_string('hsidebarcolors_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/colordrawerbg';
$title = get_string('colordrawerbg', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/colordrawertext';
$title = get_string('colordrawertext', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/colordrawernavcontainer';
$title = get_string('colordrawernavcontainer', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/colordrawernavbtnicon';
$title = get_string('colordrawernavbtnicon', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/colordrawernavbtniconh';
$title = get_string('colordrawernavbtniconh', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/colordrawernavbtntext';
$title = get_string('colordrawernavbtntext', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/colordrawernavbtntexth';
$title = get_string('colordrawernavbtntexth', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/colordrawernavbtnbgh';
$title = get_string('colordrawernavbtnbgh', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/colordrawernavbtntextlight';
$title = get_string('colordrawernavbtntextlight', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/colordrawerscrollbar';
$title = get_string('colordrawerscrollbar', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/colordrawerlink';
$title = get_string('colordrawerlink', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/colordrawerlinkh';
$title = get_string('colordrawerlinkh', 'theme_alpha');
$description = get_string('color_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// Must add the page after definiting all the settings!
$settings->add($page);
