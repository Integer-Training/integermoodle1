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
 * @copyright 2021 Marcin Czaja (https://rosea.io)
 * @license   Commercial https://themeforest.net/licenses
 *
 */


defined('MOODLE_INTERNAL') || die();

$page = new admin_settingpage('theme_alpha_block3', get_string('settingsblock3', 'theme_alpha'));

$name = 'theme_alpha/displayblock3';
$title = get_string('turnon', 'theme_alpha');
$description = get_string('displayblock3_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title .
    '<span class="badge badge-sq badge-dark ml-2">Block #3 (Hero Image)</span>', $description, $default);
$page->add($setting);

$name = 'theme_alpha/block3fw';
$title = get_string('blockfw', 'theme_alpha');
$description = get_string('blockfw_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block3class';
$title = get_string('additionalclass', 'theme_alpha');
$description = get_string('additionalclass_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block3wrapperalign';
$title = get_string('block3wrapperalign', 'theme_alpha');
$description = get_string('block3wrapperalign_desc', 'theme_alpha');
$default = 1;
$choices = array(0 => 'Left', 1 => 'Middle', 2 => 'Right');
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$page->add($setting);

$name = 'theme_alpha/showblock3wrapper';
$title = get_string('showblock3wrapper', 'theme_alpha');
$description = get_string('showblock3wrapper_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block3wrapperbg';
$title = get_string('block3wrapperbg', 'theme_alpha');
$description = get_string('block3wrapperbg_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/block3introcontent';
$title = get_string('blockintrocontent', 'theme_alpha');
$description = get_string('blockintrocontent_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block3herotitle';
$title = get_string('block3herotitle', 'theme_alpha');
$description = get_string('block3herotitle_desc', 'theme_alpha');
$setting = new admin_setting_configtextarea($name, $title, $description, '', PARAM_TEXT);
$page->add($setting);

$name = 'theme_alpha/block3herosubheading';
$title = get_string('block3herosubheading', 'theme_alpha');
$description = get_string('block3herosubheading_desc', 'theme_alpha');
$setting = new admin_setting_configtextarea($name, $title, $description, '', PARAM_TEXT);
$page->add($setting);

$name = 'theme_alpha/block3herotitlesize';
$title = get_string('blocktitlesize', 'theme_alpha');
$description = get_string('blocktitlesize_desc', 'theme_alpha');
$default = 1;
$choices = array(0 => 'Normal', 1 => 'Large', 2 => 'Extra Large');
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$page->add($setting);

$name = 'theme_alpha/block3herotitlecolor';
$title = get_string('blocktitlecolor', 'theme_alpha');
$description = get_string('blocktitlecolor_desc', 'theme_alpha');
$default = 0;
$choices = array(0 => 'White', 1 => 'Black', 2 => 'Gradient');
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$page->add($setting);

$name = 'theme_alpha/block3herotitleweight';
$title = get_string('blocktitleweight', 'theme_alpha');
$description = get_string('blocktitleweight_desc', 'theme_alpha');
$default = 1;
$choices = array(0 => 'Normal', 1 => 'Medium', 2 => 'Bold');
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$page->add($setting);

$name = 'theme_alpha/block3herocaption';
$title = get_string('block3herocaption', 'theme_alpha');
$description = get_string('block3herocaption_desc', 'theme_alpha');
$default = '<div class="rui-hero-desc">
<p>The Alpha 2 is dedicated only to Moodle 4.0 and later. For Moodle 3.9 - 3.11 there is Alpha 1.15</p>
<p class="mt-3 small">Need help with theme customization?<br>Or do you want to report a bug?</p>
</div>
<div class="rui-hero-btns mt-3 2-100">
<a href="https://1.envato.market/3PgWgB" target="_blank" class="btn btn-lg btn-primary my-1">Get this theme!</a>
</div>
<div class="rui-hero-btns mt-3 w-100">
<a href="https://rosea.io/alpha-theme/" target="_blank" class="btn btn-sm btn-light my-1 mx-0">Alpha 1.15 for Moodle 3.9 - 3.11</a>
</div>';
$setting = new admin_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block3img';
$title = get_string('block3img', 'theme_alpha');
$description = get_string('block3img_desc', 'theme_alpha');
$opts = array('accepted_types' => array('.jpg, .png, .gif'), 'maxfiles' => 1);
$setting = new admin_setting_configstoredfile($name, $title, $description, 'block3img', 0, $opts);
$page->add($setting);

$name = 'theme_alpha/block3htmlcontent';
$title = get_string('blockhtmlcontent', 'theme_alpha');
$description = get_string('blockhtmlcontent_desc', 'theme_alpha');
$default = '';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block3footercontent';
$title = get_string('blockfootercontent', 'theme_alpha');
$description = get_string('blockfootercontent_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
