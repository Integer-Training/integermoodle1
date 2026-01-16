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

$page = new admin_settingpage('theme_alpha_block1', get_string('settingsblock1', 'theme_alpha'));

$name = 'theme_alpha/displayblock1';
$title = get_string('turnon', 'theme_alpha');
$description = get_string('displayblock1_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title .
    '<span class="badge badge-sq badge-dark ml-2">Block #1 (Slider)</span>', $description, $default);
$page->add($setting);

$name = 'theme_alpha/block1fw';
$title = get_string('blockfw', 'theme_alpha');
$description = get_string('blockfw_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block1class';
$title = get_string('additionalclass', 'theme_alpha');
$description = get_string('additionalclass_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block1wrapperalign';
$title = get_string('block1wrapperalign', 'theme_alpha');
$description = get_string('block1wrapperalign_desc', 'theme_alpha');
$default = 1;
$choices = array(0 => 'Left', 1 => 'Middle', 2 => 'Right');
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$page->add($setting);

$name = 'theme_alpha/block1herotitlesize';
$title = get_string('blocktitlesize', 'theme_alpha');
$description = get_string('blocktitlesize_desc', 'theme_alpha');
$default = 1;
$choices = array(0 => 'Normal', 1 => 'Large', 2 => 'Extra Large');
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$page->add($setting);

$name = 'theme_alpha/block1herotitlecolor';
$title = get_string('blocktitlecolor', 'theme_alpha');
$description = get_string('blocktitlecolor_desc', 'theme_alpha');
$default = 0;
$choices = array(0 => 'White', 1 => 'Black', 2 => 'Gradient');
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$page->add($setting);

$name = 'theme_alpha/block1herotitleweight';
$title = get_string('blocktitleweight', 'theme_alpha');
$description = get_string('blocktitleweight_desc', 'theme_alpha');
$default = 1;
$choices = array(0 => 'Normal', 1 => 'Medium', 2 => 'Bold');
$setting = new admin_setting_configselect($name, $title, $description, $default, $choices);
$page->add($setting);

$name = 'theme_alpha/showblock1sliderwrapper';
$title = get_string('showblock1sliderwrapper', 'theme_alpha');
$description = get_string('showblock1sliderwrapper_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block1sliderwrapperbg';
$title = get_string('block1sliderwrapperbg', 'theme_alpha');
$description = get_string('block1sliderwrapperbg_desc', 'theme_alpha');
$setting = new admin_setting_configcolourpicker($name, $title, $description, '');
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

$name = 'theme_alpha/block1sliderinterval';
$title = get_string('sliderinterval', 'theme_alpha');
$description = get_string('sliderinterval_desc', 'theme_alpha');
$default = '6000';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block1count';
$title = get_string('block1count', 'theme_alpha');
$description = get_string('block1count_desc', 'theme_alpha');
$default = 1;
$options = array();
for ($i = 1; $i <= 7; $i++) {
    $options[$i] = $i;
}
$setting = new admin_setting_configselect($name, $title, $description, $default, $options);
$setting->set_updatedcallback('theme_reset_all_caches');
$page->add($setting);

// If we don't have an slide yet, default to the preset.
$slidercount = get_config('theme_alpha', 'block1count');

if (!$slidercount) {
    $slidercount = 1;
}

for ($sliderindex = 1; $sliderindex <= $slidercount; $sliderindex++) {
    $name = 'theme_alpha/hblock1slide' . $sliderindex;
    $heading = get_string('hblock1slide', 'theme_alpha');
    $title = get_string('hblock1slide_desc', 'theme_alpha');
    $setting = new admin_setting_heading($name, '<span class="rui-admin-no">' .
        $sliderindex .
        '</span>' .
        $heading, format_text($title, FORMAT_MARKDOWN));
    $page->add($setting);

    $fileid = 'block1slideimg' . $sliderindex;
    $name = 'theme_alpha/block1slideimg' . $sliderindex;
    $title = get_string('block1slideimg', 'theme_alpha');
    $description = get_string('block1slideimg_desc', 'theme_alpha');
    $opts = array('accepted_types' => array('.png', '.jpg', '.gif', '.webp', '.tiff', '.svg'), 'maxfiles' => 1);
    $setting = new admin_setting_configstoredfile($name, '<span class="rui-admin-no">' .
        $sliderindex .
        '</span>' .
        $title, $description, $fileid, 0, $opts);
    $setting->set_updatedcallback('theme_reset_all_caches');
    $page->add($setting);

    $name = 'theme_alpha/block1slidetitle' . $sliderindex;
    $title = get_string('block1slidetitle', 'theme_alpha');
    $description = get_string('block1slidetitle_desc', 'theme_alpha');
    $setting = new admin_setting_configtext($name, '<span class="rui-admin-no">' .
        $sliderindex .
        '</span>' .
        $title, $description, '', PARAM_TEXT);
    $page->add($setting);

    $name = 'theme_alpha/block1slidecaption' . $sliderindex;
    $title = get_string('block1slidecaption', 'theme_alpha');
    $description = get_string('block1slidecaption_desc', 'theme_alpha');
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
    $setting = new admin_setting_confightmleditor($name, '<span class="rui-admin-no">' .
        $sliderindex .
        '</span>' .
        $title, $description, $default);
    $page->add($setting);

    $name = 'theme_alpha/block1slidecss' . $sliderindex;
    $title = get_string('block1slidecss', 'theme_alpha');
    $description = get_string('block1slidecss_desc', 'theme_alpha');
    $setting = new admin_setting_configtextarea($name, '<span class="rui-admin-no">' .
        $sliderindex .
        '</span>' .
        $title, $description, '', PARAM_TEXT);
    $page->add($setting);
}

$settings->add($page);
