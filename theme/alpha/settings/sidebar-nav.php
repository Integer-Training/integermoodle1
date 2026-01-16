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
$page = new admin_settingpage('theme_alpha_settingssidebar-nav', get_string('settingssidebar-nav', 'theme_alpha'));

$name = 'theme_alpha/isitemonsitehome';
$title = get_string('isitemonsitehome', 'theme_alpha');
$description = get_string('isitemonsitehome_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/possitehome';
$title = get_string('possitehome', 'theme_alpha');
$description = get_string('navposition_desc', 'theme_alpha');
$default = 1;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_alpha/isitemondashboard';
$title = get_string('isitemondashboard', 'theme_alpha');
$description = get_string('isitemondashboard_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/posdashboard';
$title = get_string('posdashboard', 'theme_alpha');
$description = get_string('navposition_desc', 'theme_alpha');
$default = 2;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_alpha/isitemoncalendar';
$title = get_string('isitemoncalendar', 'theme_alpha');
$description = get_string('isitemoncalendar_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/poscalendar';
$title = get_string('poscalendar', 'theme_alpha');
$description = get_string('navposition_desc', 'theme_alpha');
$default = 3;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_alpha/isitemonprivatefiles';
$title = get_string('isitemonprivatefiles', 'theme_alpha');
$description = get_string('isitemonprivatefiles_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/posprivatefiles';
$title = get_string('posprivatefiles', 'theme_alpha');
$description = get_string('navposition_desc', 'theme_alpha');
$default = 4;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_alpha/isitemoncontentbank';
$title = get_string('isitemoncontentbank', 'theme_alpha');
$description = get_string('isitemoncontentbank_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/poscontentbank';
$title = get_string('poscontentbank', 'theme_alpha');
$description = get_string('navposition_desc', 'theme_alpha');
$default = 5;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_alpha/isitemonmycourses';
$title = get_string('isitemonmycourses', 'theme_alpha');
$description = get_string('isitemonmycourses_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/posmycourses';
$title = get_string('posmycourses', 'theme_alpha');
$description = get_string('navposition_desc', 'theme_alpha');
$default = 6;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

// Custom Item #1.
$name = 'theme_alpha/hcustomitem1';
$heading = get_string('hcustomitem1', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('empty_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/iscustomitem1on';
$title = get_string('iscustomitem1on', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/poscustomitem1';
$title = get_string('poscustomitem1', 'theme_alpha');
$description = get_string('navposition_desc', 'theme_alpha');
$default = 7;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_alpha/labelcustomitem1';
$title = get_string('labelcustomitem1', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/iconcustomitem1';
$title = get_string('iconcustomitem1', 'theme_alpha');
$description = get_string('iconcustomitem_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/urlcustomitem1';
$title = get_string('urlcustomitem1', 'theme_alpha');
$description = get_string('urlcustomitem_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// Custom Item #2.
$name = 'theme_alpha/hcustomitem2';
$heading = get_string('hcustomitem2', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('empty_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/iscustomitem2on';
$title = get_string('iscustomitem2on', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/poscustomitem2';
$title = get_string('poscustomitem2', 'theme_alpha');
$description = get_string('navposition_desc', 'theme_alpha');
$default = 8;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_alpha/labelcustomitem2';
$title = get_string('labelcustomitem2', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/iconcustomitem2';
$title = get_string('iconcustomitem2', 'theme_alpha');
$description = get_string('iconcustomitem_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/urlcustomitem2';
$title = get_string('urlcustomitem2', 'theme_alpha');
$description = get_string('urlcustomitem_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// Custom Item #3.
$name = 'theme_alpha/hcustomitem3';
$heading = get_string('hcustomitem3', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('empty_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/iscustomitem3on';
$title = get_string('iscustomitem3on', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/poscustomitem3';
$title = get_string('poscustomitem3', 'theme_alpha');
$description = get_string('navposition_desc', 'theme_alpha');
$default = 9;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_alpha/labelcustomitem3';
$title = get_string('labelcustomitem3', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/iconcustomitem3';
$title = get_string('iconcustomitem3', 'theme_alpha');
$description = get_string('iconcustomitem_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/urlcustomitem3';
$title = get_string('urlcustomitem3', 'theme_alpha');
$description = get_string('iconcustomitem_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// Custom Item #4.
$name = 'theme_alpha/hcustomitem4';
$heading = get_string('hcustomitem4', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('empty_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/iscustomitem4on';
$title = get_string('iscustomitem4on', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/poscustomitem4';
$title = get_string('poscustomitem4', 'theme_alpha');
$description = get_string('navposition_desc', 'theme_alpha');
$default = 10;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_alpha/labelcustomitem4';
$title = get_string('labelcustomitem4', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/iconcustomitem4';
$title = get_string('iconcustomitem4', 'theme_alpha');
$description = get_string('iconcustomitem_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/urlcustomitem4';
$title = get_string('urlcustomitem4', 'theme_alpha');
$description = get_string('urlcustomitem_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// Custom Item #5.
$name = 'theme_alpha/hcustomitem5';
$heading = get_string('hcustomitem5', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('empty_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/iscustomitem5on';
$title = get_string('iscustomitem5on', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/poscustomitem5';
$title = get_string('poscustomitem5', 'theme_alpha');
$description = get_string('navposition_desc', 'theme_alpha');
$default = 11;
$options = array();
for ($i = 1; $i <= 11; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$page->add($setting);

$name = 'theme_alpha/labelcustomitem5';
$title = get_string('labelcustomitem5', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/iconcustomitem5';
$title = get_string('iconcustomitem5', 'theme_alpha');
$description = get_string('iconcustomitem_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/urlcustomitem5';
$title = get_string('urlcustomitem5', 'theme_alpha');
$description = get_string('urlcustomitem_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/customnavitems';
$title = get_string('customnavitems', 'theme_alpha');
$description = get_string('customnavitems_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

// Must add the page after definiting all the settings!
$settings->add($page);
