//
// This file is part of alpha theme for moodle
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
//
//
// Alpha main JS file
//
// @package    theme_alpha
// @copyright  Copyright © 2021 onwards Marcin Czaja Rosea Themes
//
// @license    Commercial

/* jshint ignore:start */
define(['jquery', 'core/aria', 'core_user/repository'], function ($, setUserPreference, UserRepository) {
    "use strict"; // ...jshint ;_; !!!

    return {
        init: function () {
            $(document).ready(function ($) {
                var trigger = $(document.getElementById("darkModeBtn"));
                var preference = trigger.attr('data-preference');

                $('#darkModeBtn').click(function () {
                    if ($('body').hasClass('theme-dark')) {
                        $('body').removeClass('theme-dark');
                        $('html').removeClass('dark-mode');
                        trigger.attr('aria-checked', 'false');
                        UserRepository.setUserPreference(preference, false);
                    } else {
                        $('body').addClass('theme-dark');
                        $('html').addClass('dark-mode');
                        trigger.attr('aria-checked', 'true');
                        UserRepository.setUserPreference(preference, true);
                    }
                });

                var trigger2 = $(document.getElementById("myCoursesBtn"));
                var preference2 = trigger2.attr('data-preference');

                $('#myCoursesBtn').click(function () {
                    if ($('body').hasClass('mycourses-on')) {
                        $('body').removeClass('mycourses-on');
                        trigger2.attr('aria-checked', 'false');
                        UserRepository.setUserPreference(preference2, false);
                    } else {
                        $('body').addClass('mycourses-on');
                        trigger2.attr('aria-checked', 'true');
                        UserRepository.setUserPreference(preference2, true);
                    }
                });

                var trigger3 = $(document.getElementById("myCoursesHidden"));
                var preference3 = trigger3.attr('data-preference');

                $('#myCoursesHidden').click(function () {
                    if ($('body').hasClass('mycourses-hidden-on')) {
                        $('body').removeClass('mycourses-hidden-on');
                        trigger3.attr('aria-checked', 'false');
                        UserRepository.setUserPreference(preference3, false);
                    } else {
                        $('body').addClass('mycourses-hidden-on');
                        trigger3.attr('aria-checked', 'true');
                        UserRepository.setUserPreference(preference3, true);
                    }
                });

                var trigger4 = $(document.getElementById("myCoursesInprogress"));
                var preference4 = trigger4.attr('data-preference');

                $('#myCoursesInprogress').click(function () {
                    if ($('body').hasClass('mycourses-inprogress-on')) {
                        $('body').removeClass('mycourses-inprogress-on');
                        trigger4.attr('aria-checked', 'false');
                        UserRepository.setUserPreference(preference4, false);
                    } else {
                        $('body').addClass('mycourses-inprogress-on');
                        trigger4.attr('aria-checked', 'true');
                        UserRepository.setUserPreference(preference4, true);
                    }
                });

                $('.btn-close-drawer--left').click(function () {
                    $('body').removeClass('drawer-courseindex--open');
                    $('body').removeClass('drawer-open-index--open');
                });

                if ($(window).width() <= 1399) {
                    $('.drawertoggle').click(function () {
                        $('body').removeClass('drawer-courseindex--open');
                        $('body').removeClass('drawer-open-index--open');
                    });
                }

                $('.drawer-right-toggle').click(function () {
                    if (screen.width <= 1399) {
                        $('body').removeClass('drawer-courseindex--open');
                        $('body').removeClass('drawer-open-index--open');
                    }
                });

                $('.btn-drawer--left').click(function () {
                    $('body').addClass('drawer-courseindex--open');
                    $('body').addClass('drawer-open-index--open');
                });

                $('#mobileNav, .rui-mobile-nav-btn-close').click(function () {
                    $('#topBar').toggleClass('opened');
                });

                // $( ".editing .editmode-switch-form" ).on( "mouseover", function() {
                //     $('body').addClass('rui-edit-areas');
                // });

                // $( ".editing .editmode-switch-form" ).on( "mouseout", function() {
                //     $('body').removeClass('rui-edit-areas');
                // });

                $("#showBlockArea").on("click", function () {
                    $('body').toggleClass('rui-edit-areas');
                    $('#showBlockArea').toggleClass('active');
                });

                // Remove unnecessary string "..." from default moodle blocks
                // $('.block .footer').each(function () {
                //     var text = $(this).html();
                //     $(this).html(text.replace('...', ''));
                // });

                $('.rui-nav--admin .nav-link').each(function () {
                    var text = $(this).html();
                    $(this).html(text.replace('(', '<span class="mt-1 small d-block">'));
                });
                $('.rui-nav--admin .nav-link').each(function () {
                    var text = $(this).html();
                    $(this).html(text.replace(')', '</span>'));
                });

                function courseCardInfo() {
                    
                    $(".rui-course-info").on('click', function () {
                        var courseSummaryBox = $(this).parent().parent().find('.rui-course-card-text');
                        
                        if(courseSummaryBox.hasClass("show")) {
                            courseSummaryBox.removeClass("show");
                            $(this).find('.icon--close').addClass("hidden");
                            $(this).find('.icon--open').removeClass("hidden");
                        } else {
                            courseSummaryBox.addClass("show");
                            $(this).find('.icon--close').removeClass("hidden");
                            $(this).find('.icon--open').addClass("hidden");
                        }

                    });

                }
                
                courseCardInfo();

            });

        }
    };
});
/* jshint ignore:end */
