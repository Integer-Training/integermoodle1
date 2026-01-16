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
global $PAGE, $OUTPUT;

// Variables.
$block23introtitle = format_text(theme_alpha_get_setting('block23introtitle'), FORMAT_HTML, array('noclean' => true));
$block23introcontent = format_text(theme_alpha_get_setting('block23introcontent'), FORMAT_HTML, array('noclean' => true));
$block23html = format_text(theme_alpha_get_setting('block23htmlcontent'), FORMAT_HTML, array('noclean' => true));
$block23footer = format_text(theme_alpha_get_setting('block23footercontent'), FORMAT_HTML, array('noclean' => true));
$block23class = theme_alpha_get_setting('block23class');

$block23html1 = format_text(theme_alpha_get_setting('fpcustomcategoryblockhtml1'), FORMAT_HTML, array('noclean' => true));
$block23html2 = format_text(theme_alpha_get_setting('fpcustomcategoryblockhtml2'), FORMAT_HTML, array('noclean' => true));
$block23html3 = format_text(theme_alpha_get_setting('fpcustomcategoryblockhtml3'), FORMAT_HTML, array('noclean' => true));

echo '<!-- Start Block 23 -->';
echo '<div id="fbblock23" class="wrapper-xl rui-fp-block--23 ' .
    $block23class .
    ' s-courses-list row no-gutters justify-content-sm-center justify-content-lg-start">';

if (!empty($block23introtitle) || !empty($block23introcontent)) {
    echo '<div class="wrapper-md">';
}
if (!empty($block23introtitle)) {
    echo '<h3 class="rui-block-title">' .
        $block23introtitle .
        '</h3>';
}
if (!empty($block23introcontent)) {
    echo '<div class="rui-block-desc">' .
        $block23introcontent .
        '</div>';
}
if (!empty($block23introtitle) || !empty($block23introcontent)) {
    echo '</div>';
}

echo '<div class="col-12 row no-gutters justify-content-between p-0">';
if (!empty($block23html1)) {
    echo '<div class="col-sm-12 col-md-3 text-left mt-0">';
    echo $block23html1;
    echo '</div>';
}
if (!empty($block23html2)) {
    echo '<div class="col-sm-12 col-md-3 text-left mt-sm-5 mt-md-0">';
    echo $block23html2;
    echo '</div>';
}
if (!empty($block23html3)) {
    echo '<div class="col-sm-12 col-md-3 text-left mt-sm-5 mt-md-0">';
    echo $block23html3;
    echo '</div>';
}
echo '</div>';

echo $block23html;

if (!empty($block23footer)) {
    echo '<div class="rui-block-footer wrapper-fw">' . $block23footer . '</div>';
}

echo '</div>';
if (theme_alpha_get_setting("displayhrblock23") == '1') {
    echo '<hr class="rui-block-hr" />';
}
echo '<!-- End Block 11 -->';
