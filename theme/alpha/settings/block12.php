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

$page = new admin_settingpage('theme_alpha_block12', get_string('settingsblock12', 'theme_alpha'));

$name = 'theme_alpha/displayblock12';
$title = get_string('turnon', 'theme_alpha');
$description = get_string('displayblock12_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title .
    '<span class="badge badge-sq badge-dark ml-2">Block #12</span>', $description, $default);
$page->add($setting);

$name = 'theme_alpha/displayhrblock12';
$title = get_string('displayblockhr', 'theme_alpha');
$description = get_string('displayblockhr_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block12class';
$title = get_string('additionalclass', 'theme_alpha');
$description = get_string('additionalclass_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block12introtitle';
$title = get_string('blockintrotitle', 'theme_alpha');
$description = get_string('blockintrotitle_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block12introcontent';
$title = get_string('blockintrocontent', 'theme_alpha');
$description = get_string('blockintrocontent_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block12htmlcontent';
$title = get_string('blockhtmlcontent', 'theme_alpha');
$description = get_string('blockhtmlcontent_desc', 'theme_alpha');
$default = '<div class="row">
          <!-- Start item -->
          <div class="col-12 col-md-3 px-3 my-3 my-lg-0 text-center">
              <div class="display-3 mb-2">$99<sup class="text-muted">/ $3k</sup></div>
              <p class="text-light">Standard / Extended License</p>
          </div>
          <!-- End item -->
          <!-- Start item -->
          <div class="col-12 col-md-3 px-3 my-3 my-lg-0 text-center">
              <div class="display-3 mb-2">Free</div>
              <p class="text-light">Updates and bug fixes</p>
          </div>
          <!-- End item -->
          <!-- Start item -->
          <div class="col-12 col-md-3 px-3 my-3 my-lg-0 text-center">
              <div class="display-3 mb-2">1900+</div>
              <p class="text-light">Trusted users</p>
          </div>
          <!-- End item -->
          <!-- Start item -->
          <div class="col-12 col-md-3 px-3 my-3 my-lg-0 text-center">
              <div class="display-3 mb-2">Moodle</div>
              <p class="text-light">Compatible with Moodle 3.11 and later e.g Moodle 4.0</p>
          </div>
          <!-- End item -->
      </div>';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block12footercontent';
$title = get_string('blockfootercontent', 'theme_alpha');
$description = get_string('blockfootercontent_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
