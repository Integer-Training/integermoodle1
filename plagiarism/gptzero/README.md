# GPTZero Plugin for Moodle #

GPTZero AI Detection Plugin is an easy to use and intuitive integration that seamlessly scans student submissions directly within Moodle to detect the use of AI-generated content. As educators grade assignments, they can effortlessly review each submission with our plugin. The GPTZero plugin provides detailed reports indicating the likelihood of AI involvement in text generation, plagiarism likelihood, writing analysis, and writing feedback.

## Supported Modules ##
- Assignment
- Forum

## Installing via uploaded ZIP file ##

1. Log in to your Moodle site as an admin and go to _Site administration >
   Plugins > Install plugins_.
2. Upload the ZIP file with the plugin code.
3. Check the plugin validation report and finish the installation.

## Installing manually ##

The plugin can be also installed by putting the contents of this directory to

    {your/moodle/dirroot}/plagiarism/gptzero

Afterwards, log in to your Moodle site as an admin and go to _Site administration >
Notifications_ to complete the installation.

Alternatively, you can run

    $ php admin/cli/upgrade.php

to complete the installation from the command line.

## License ##

2024 GPTZero <team@gptzero.me>

This program is free software: you can redistribute it and/or modify it under
the terms of the GNU General Public License as published by the Free Software
Foundation, either version 3 of the License, or (at your option) any later
version.

This program is distributed in the hope that it will be useful, but WITHOUT ANY
WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
PARTICULAR PURPOSE.  See the GNU General Public License for more details.

You should have received a copy of the GNU General Public License along with
this program.  If not, see <https://www.gnu.org/licenses/>.

## Dev Docs ##

***lib.php***

plagiarism_plugin_gptzero - class that inherits the plagiarism_plugin class from Moodle. This allows us to implement hooks and surface results within the Moodle interface without breaking any other functionality.

&emsp;get_settings - checks if “Enable GPTZero” is enabled in admin settings. Return false if not enabled, or returns an array of relevant settings.

&emsp;is_gptzero_used - checks if GPTZero plugin is enabled in a course module. Returns type boolean.

&emsp;get_links - inherited function from plagiarism_plugin which allows us to surface AI detection results. We use HTML to form the button and surfaced AI detection results which uses information from the `plagiarism_gptzero_files` table on Moodle-side.

&emsp;get_scan_url - retrieves the scanurl item associated with a specific submission from the `plagiarism_gptzero_files`.

&emsp;get_file_results - inherited function from plagiarism_plugin which allows us to get an analysis status (1 is analyzed or 0 if not), predicted class, and class probability with an associated file.

&emsp;handle_onlinetext - collects required user information associated with the submitter, calls submit_text from api.php, and stores information returned from api call in `plagiarism_gptzero_files` table.

&emsp;update_plagiarism_file - similar to handle_onlinetext but in the context of file uploads. Collects required user information associated with the submitter, calls submit_file from api.php, and stores information returned from api call in `plagiarism_gptzero_files` table.

&emsp;print_disclosure - inherited function from plagiarism_plugin that allows a disclosure statement to be printed notifying users what will happen with their submission.

&emsp;handle_grading_page_view - handles the logic to display notification if user does not have a GPTZero account. Because this function is called in get_links and get_links is called for *n* assignments, handle_grading_page_view checks if notification was already displayed and will only call has_gptzero_account in api.php is it hasn’t been cached already.

gptzero_handle_event - large function that handles events such as assignment file uploads, forum file uploads, and other events observed in classes/observer.php. Typically redirects for file uploads vs online text upload.

plagiarism_gptzero_is_plugin_configured - checks if GPTZero plugin is configured (is API key set and is `Enable {course module}` enabled) in admin settings

plagiarism_gptzero_coursemodule_standard_elements - displays “GPTZero Settings” in Course Module settings.

plagiarism_gptzero_coursemodule_edit_post_actions - saves settings from Course Module settings and calls the create_assignment from api.php. Stores course module settings and api response in `plagiarism_gptzero_config` table.

***classes***

&emsp;***api.php*** (file that handles all api calls to GPTZero endpoints)

&emsp;&emsp;api - class that contains different GPTZero requests

&emsp;&emsp;&emsp;$apiurl - hardcoded url for GPTZero endpoint

&emsp;&emsp;&emsp;$apikey - API Key from admin settings

&emsp;&emsp;&emsp;submit_file - forms cURL request for file submissions to /v3/moodle/submit with x-api-key in the header. Called on file submissions and returns AI detection results.

&emsp;&emsp;&emsp;submit_text - forms cURL request for online text submissions to /v3/moodle/submit with x-api-key in the header. Called on online text submissions and returns AI detection results.

&emsp;&emsp;&emsp;create_assignment - forms cURL request to /v3/moodle/deep-linking with x-api-key in the header. Called on assignment creation and returns GPTZero assignment id (treated like a foreign key).

&emsp;&emsp;&emsp;has_gptzero_account - forms cURL request to /v3/moodle/launch with x-api-key in the header. Called on grading page and returns if user has GPTZero account.

&emsp;***observer.php -*** has all event observers for file uploads from course modules like assign, forum, workshop and redirects to according function (usually gptzero_handle_event in lib.php)

***db***

&emsp;***access.php*** - sets permissions for db changes based on roles

&emsp;***events.php*** - events and their associated callback functions

&emsp;***install.xml*** - db table structure for `plagiarism_gptzero_files` and `plagiarism_gptzero_config`

***lang/en***

&emsp;***plagiarism_gptzero.php*** - var strings for English

***plagiarism_form.php*** - adds elements for admin settings page.

***settings.php*** - saves admin settings page using set_config with appropriate naming fields.

***version.php*** - contains plugin information which are used during the plugin installation and upgrade process

***phpunit.xml*** - xml configuration file for php
