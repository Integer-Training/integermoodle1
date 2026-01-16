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

$page = new admin_settingpage('theme_alpha_block6', get_string('settingsblock6', 'theme_alpha'));

$name = 'theme_alpha/displayblock6';
$title = get_string('turnon', 'theme_alpha');
$description = get_string('displayblock6_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title .
    '<span class="badge badge-sq badge-dark ml-2">Block #6</span>', $description, $default);
$page->add($setting);

$name = 'theme_alpha/displayhrblock6';
$title = get_string('displayblockhr', 'theme_alpha');
$description = get_string('displayblockhr_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block6class';
$title = get_string('additionalclass', 'theme_alpha');
$description = get_string('additionalclass_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block6introtitle';
$title = get_string('blockintrotitle', 'theme_alpha');
$description = get_string('blockintrotitle_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block6introcontent';
$title = get_string('blockintrocontent', 'theme_alpha');
$description = get_string('blockintrocontent_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block6htmlcontent';
$title = get_string('blockhtmlcontent', 'theme_alpha');
$description = get_string('blockhtmlcontent_desc', 'theme_alpha');
$default = '<!-- Start - Block - Testimonials #2 -->
<div class="wrapper-fw rui-block-testimonials-2">
<div class="rui-card-testimonials-grid">
<!-- Start item -->
<div class="rui-block-testimonials-item d-flex justify-content-center text-center">
<div class="rui-block-testimonials--quote">"Everything you need and nothing you do not"</div>
<span class="rui-block-testimonials--author">Ryan Cook</span>
<span class="rui-block-testimonials--additional mb-3">CEO | SoSimple</span>
<img src="https://assets.rosea.io/alpha/demo/logo180x90.png" alt="" width="100" height="34" class="">
</div>
<!-- End item -->
<!-- Start item -->
<div class="rui-block-testimonials-item d-flex justify-content-center text-center">
<div class="rui-block-testimonials--quote">“I could not imagine using anything else!”</div>
<span class="rui-block-testimonials--author">Tim Smith</span>
<span class="rui-block-testimonials--additional mb-3">UX Designer | Clean Simple</span>
<img src="hhttps://assets.rosea.io/alpha/demo/logo180x90.png" alt="" width="100" height="34" class="">
</div>
<!-- End item -->
<!-- Start item -->
<div class="rui-block-testimonials-item d-flex justify-content-center text-center">
<div class="rui-block-testimonials--quote">"One of the best Moodle Theme I have ever used”</div>
<span class="rui-block-testimonials--author">Anna van Diesel</span>
<span class="rui-block-testimonials--additional mb-3">Senior Product Designer | ABC Design</span>
<img src="https://assets.rosea.io/alpha/demo/logo180x90.png" alt="" width="100" height="25" class="">
</div>
<!-- End item -->
</div>
</div>
<!-- End - Block - Testimonials #2 -->';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block6footercontent';
$title = get_string('blockfootercontent', 'theme_alpha');
$description = get_string('blockfootercontent_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
