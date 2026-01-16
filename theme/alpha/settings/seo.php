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

$page = new admin_settingpage('theme_alpha_seo', get_string('seosettings', 'theme_alpha'));

// Favicon setting.
$name = 'theme_alpha/favicon';
$title = get_string('favicon', 'theme_alpha');
$description = get_string('favicon_desc', 'theme_alpha');
$opts = array('accepted_types' => array('.ico'), 'maxfiles' => 1);
$setting = new admin_setting_configstoredfile($name, $title, $description, 'favicon', 0, $opts);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/favicon16';
$title = get_string('favicon16', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$opts = array('accepted_types' => array('.png'), 'maxfiles' => 1);
$setting = new admin_setting_configstoredfile($name, $title, $description, 'favicon16', 0, $opts);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/favicon32';
$title = get_string('favicon32', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$opts = array('accepted_types' => array('.png'), 'maxfiles' => 1);
$setting = new admin_setting_configstoredfile($name, $title, $description, 'favicon32', 0, $opts);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/faviconsafaritab';
$title = get_string('faviconsafaritab', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$opts = array('accepted_types' => array('.svg'), 'maxfiles' => 1);
$setting = new admin_setting_configstoredfile($name, $title, $description, 'faviconsafaritab', 0, $opts);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/faviconsafaritabcolor';
$title = get_string('faviconsafaritabcolor', 'theme_alpha');
$description = get_string('empty_desc', 'theme_alpha');
$default = '#000000';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);


// Apple Touch Icon.
$name = 'theme_alpha/seoappletouchicon';
$title = get_string('seoappletouchicon', 'theme_alpha');
$description = get_string('seoappletouchicon_desc', 'theme_alpha');
$opts = array('accepted_types' => array('.png'), 'maxfiles' => 1);
$setting = new admin_setting_configstoredfile($name, $title, $description, 'seoappletouchicon');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/seometadesc';
$title = get_string('seometadesc', 'theme_alpha');
$description = get_string('seometadesc_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/seothemecolor';
$title = get_string('seothemecolor', 'theme_alpha');
$description = get_string('seothemecolor_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
