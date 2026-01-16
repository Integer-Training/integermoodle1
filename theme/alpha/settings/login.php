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

// Login settings.
$page = new admin_settingpage('theme_alpha_settingslogin', get_string('settingslogin', 'theme_alpha'));

$name = 'theme_alpha/hlogin';
$heading = get_string('hlogin', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hlogin_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/setloginlayout';
$title = get_string('setloginlayout', 'theme_alpha');
$description = get_string('setloginlayout_desc', 'theme_alpha');
$options = [];
$options[1] = get_string('loginlayout1', 'theme_alpha');
$options[2] = get_string('loginlayout2', 'theme_alpha');
$options[3] = get_string('loginlayout3', 'theme_alpha');
$options[4] = get_string('loginlayout4', 'theme_alpha');
$options[5] = get_string('loginlayout5', 'theme_alpha');
$setting = new admin_setting_configselect($name, $title, $description, 1, $options);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/loginidprovtop';
$title = get_string('loginidprovtop', 'theme_alpha');
$description = get_string('loginidprovtop_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/customloginlogo';
$title = get_string('customloginlogo', 'theme_alpha');
$description = get_string('customloginlogo_desc', 'theme_alpha');
$opts = array('accepted_types' => array('.png', '.jpg', '.gif', '.svg'));
$setting = new admin_setting_configstoredfile($name, $title, $description, 'customloginlogo', 0, $opts);
$page->add($setting);

$name = 'theme_alpha/customlogindmlogo';
$title = get_string('customlogindmlogo', 'theme_alpha');
$description = get_string('customlogindmlogo_desc', 'theme_alpha');
$opts = array('accepted_types' => array('.png', '.jpg', '.gif', '.svg'));
$setting = new admin_setting_configstoredfile($name, $title, $description, 'customlogindmlogo', 0, $opts);
$page->add($setting);

$name = 'theme_alpha/loginlogooutside';
$title = get_string('loginlogooutside', 'theme_alpha');
$description = get_string('loginlogooutside_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/customsignupoutside';
$title = get_string('customsignupoutside', 'theme_alpha');
$description = get_string('customsignupoutside_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/loginbg';
$title = get_string('loginbg', 'theme_alpha');
$description = get_string('loginbg_desc', 'theme_alpha');
$opts = array('accepted_types' => array('.png', '.jpg', '.gif', '.webp', '.tiff', '.svg'));
$setting = new admin_setting_configstoredfile($name, $title, $description, 'loginbg', 0, $opts);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/loginbgcolor';
$title = get_string('loginbgcolor', 'theme_alpha');
$description = get_string('loginbgcolor_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/hideforgotpassword';
$title = get_string('hideforgotpassword', 'theme_alpha');
$description = get_string('hideforgotpassword_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/logininfobox';
$title = get_string('logininfobox', 'theme_alpha');
$description = get_string('logininfobox_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/loginintrotext';
$title = get_string('loginintrotext', 'theme_alpha');
$description = get_string('loginintrotext_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/loginhtmlcontent1';
$title = get_string('loginhtmlcontent1', 'theme_alpha');
$description = get_string('loginhtmlcontent1_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/loginhtmlcontent2';
$title = get_string('loginhtmlcontent2', 'theme_alpha');
$description = get_string('loginhtmlcontent2_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/loginhtmlblockbottom';
$title = get_string('loginhtmlblockbottom', 'theme_alpha');
$description = get_string('loginhtmlblockbottom_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/loginhtmlcontent3';
$title = get_string('loginhtmlcontent3', 'theme_alpha');
$description = get_string('loginhtmlcontent3_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/loginfootercontent';
$title = get_string('loginfootercontent', 'theme_alpha');
$description = get_string('loginfootercontent_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/logincustomfooterhtml';
$title = get_string('logincustomfooterhtml', 'theme_alpha');
$description = get_string('logincustomfooterhtml_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/stringca';
$title = get_string('stringca', 'theme_alpha');
$description = get_string('stringca_desc', 'theme_alpha');
$default = 'Don\'t have an account?';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/stringbacktologin';
$title = get_string('stringbacktologin', 'theme_alpha');
$description = get_string('stringbacktologin_desc', 'theme_alpha');
$default = 'Already have an account?';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/hsignup';
$heading = get_string('hsignup', 'theme_alpha');
$setting = new admin_setting_heading($name, $heading, format_text(get_string('hsignup_desc', 'theme_alpha'), FORMAT_MARKDOWN));
$page->add($setting);

$name = 'theme_alpha/signupintrotext';
$title = get_string('signupintrotext', 'theme_alpha');
$description = get_string('signupintrotext_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/signuptext';
$title = get_string('signuptext', 'theme_alpha');
$description = get_string('signuptext_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
