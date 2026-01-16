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

$page = new admin_settingpage('theme_alpha_block18', get_string('settingsblock18', 'theme_alpha'));

$name = 'theme_alpha/displayblock18';
$title = get_string('turnon', 'theme_alpha');
$description = get_string('displayblock18_desc', 'theme_alpha');
$default = 0;
$setting = new admin_setting_configcheckbox($name, $title .
    '<span class="badge badge-sq badge-dark ml-2">Block #18</span>', $description, $default);
$page->add($setting);

$name = 'theme_alpha/displayhrblock18';
$title = get_string('displayblockhr', 'theme_alpha');
$description = get_string('displayblockhr_desc', 'theme_alpha');
$default = 1;
$setting = new admin_setting_configcheckbox($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block18class';
$title = get_string('additionalclass', 'theme_alpha');
$description = get_string('additionalclass_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtext($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block18introtitle';
$title = get_string('blockintrotitle', 'theme_alpha');
$description = get_string('blockintrotitle_desc', 'theme_alpha');
$default = '';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block18introcontent';
$title = get_string('blockintrocontent', 'theme_alpha');
$description = get_string('blockintrocontent_desc', 'theme_alpha');
$default = '<div class="wrapper-md mb-5"><h3 class="rui-block-title">Team</h3>
<div class="rui-block-desc text-center">Lorem ipsum dolar set...</div></div>';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block18htmlcontent';
$title = get_string('blockhtmlcontent', 'theme_alpha');
$description = get_string('block18htmlcontent_desc', 'theme_alpha');
$default = '<!-- Start - Block - Team #3 -->
<div class="wrapper-md rui-block-team-3">
<div class="row">
<!-- Start item -->
<div class="rui-block-team-item text-left col-md-4">
<img src="https://assets.rosea.io/alpha/demo/team.jpg" alt="Team #1" width="300" height="300" class="img-fluid">
<h4 class="lead-4 mt-3 mb-0">Christa McAuliffe</h4>
<div class="rui-block-text--3 rui-block-text--light">CEO</div>
<div class="rui-block-text--2 mt-2">Multidisciplinary. Drinks too much coffee. Likes sentence fragments.</div>
</div>
<!-- End item -->

<!-- Start item -->
<div class="rui-block-team-item text-left col-md-4">
<img src="https://assets.rosea.io/alpha/demo/team.jpg" alt="ku" width="300" height="300" class="img-fluid">
<h4 class="lead-4 mt-3 mb-0">Helen Keller</h4>
<div class="rui-block-text--3 rui-block-text--light">Senior Vice President Services</div>
<div class="rui-block-text--2 mt-2">Multidisciplinary. Drinks too much coffee. Likes sentence fragments.</div>
</div>
<!-- End item -->

<!-- Start item -->
<div class="rui-block-team-item text-left col-md-4">
<img src="https://assets.rosea.io/alpha/demo/team.jpg" alt="team #3" width="300" height="300" class="img-fluid">
<h4 class="lead-4 mt-3 mb-0">Mark Twain</h4>
<div class="rui-block-text--3 rui-block-text--light">Senior Vice President Machine Learning and AI Strategy</div>
<div class="rui-block-text--2 mt-2">Multidisciplinary. Drinks too much coffee. Likes sentence fragments.</div>
</div>
<!-- End item -->
</div><!-- End row -->
</div>
<!-- End - Block - Team #3 -->';
$setting = new alpha_setting_confightmleditor($name, $title, $description, $default);
$page->add($setting);

$name = 'theme_alpha/block18footercontent';
$title = get_string('blockfootercontent', 'theme_alpha');
$description = get_string('blockfootercontent_desc', 'theme_alpha');
$default = '<hr class="hr-small" />
          <div class="wrapper-md mt-5 text-center">
              <h4 class="lead-4">Join our team!</h4>
              <div class="rui-block-text--2">Help us on our quest to make good software even better.</div>
              <a href="#" class="btn btn-link">See courrent openings</a>
          </div>';
$setting = new admin_setting_configtextarea($name, $title, $description, $default);
$page->add($setting);

$settings->add($page);
