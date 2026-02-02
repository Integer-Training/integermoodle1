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

$string['pluginname'] = 'Site Backup Manager';
$string['sitebackup:manage'] = 'Manage site backups';

// Dashboard.
$string['runbackupnow'] = 'Run Backup Now';
$string['localbackup'] = 'Local Backup';
$string['drivebackup'] = 'Backup + Drive';
$string['confirmbackuplocal'] = 'Run a local backup now? The ZIP will be available for download once complete.';
$string['confirmbackupdrive'] = 'Run a backup and upload to Google Drive? This may take longer.';
$string['backuptimedout'] = 'Backup timed out (exceeded 30 minutes). The local ZIP file may still be available for download.';
$string['backuphistory'] = 'Backup History';
$string['lastbackup'] = 'Last Backup';
$string['googledrive'] = 'Google Drive';
$string['nextscheduled'] = 'Next Scheduled';
$string['connected'] = 'Connected';
$string['notconnected'] = 'Not Connected';
$string['connectdrive'] = 'Connect Google Drive';
$string['disconnectdrive'] = 'Disconnect Google Drive';
$string['nobackupsyet'] = 'No backups have been created yet.';
$string['confirmbackup'] = 'Are you sure you want to run a full site backup now? This may take several minutes.';
$string['confirmdisconnect'] = 'Are you sure you want to disconnect Google Drive? Future backups will not be uploaded.';
$string['backupstarted'] = 'Backup started successfully.';
$string['backupcomplete'] = 'Backup completed successfully.';
$string['backupfailed'] = 'Backup failed.';
$string['driveconnected'] = 'Google Drive connected successfully.';
$string['drivedisconnected'] = 'Google Drive disconnected.';
$string['driveerror'] = 'Google Drive connection error: {$a}';
$string['configuredrive'] = 'Configure Google Drive credentials in plugin settings first.';

// Table columns.
$string['date'] = 'Date';
$string['filename'] = 'Filename';
$string['filesize'] = 'Size';
$string['status'] = 'Status';
$string['duration'] = 'Duration';
$string['actions'] = 'Actions';
$string['contents'] = 'Contents';
$string['openindrive'] = 'Open in Drive';
$string['deletebackup'] = 'Delete';
$string['confirmdelete'] = 'Are you sure you want to delete this backup from Google Drive?';

// Status labels.
$string['status_success'] = 'Success';
$string['status_failed'] = 'Failed';
$string['status_in_progress'] = 'In Progress';

// Settings.
$string['settings'] = 'Settings';
$string['google_client_id'] = 'Google Client ID';
$string['google_client_id_desc'] = 'OAuth 2.0 Client ID from Google Cloud Console.';
$string['google_client_secret'] = 'Google Client Secret';
$string['google_client_secret_desc'] = 'OAuth 2.0 Client Secret from Google Cloud Console.';
$string['drive_folder_name'] = 'Drive Folder Name';
$string['drive_folder_name_desc'] = 'Name of the folder on Google Drive where backups are stored.';
$string['retention_count'] = 'Retention Count';
$string['retention_count_desc'] = 'Number of recent backups to keep. Older backups are automatically deleted from Drive.';
$string['include_database'] = 'Include Database';
$string['include_database_desc'] = 'Include a full database dump (all courses, grades, marking results, users, enrollments, and all Moodle data).';
$string['include_themes'] = 'Include Themes';
$string['include_themes_desc'] = 'Include custom theme files in the backup.';
$string['include_plugins'] = 'Include Plugins';
$string['include_plugins_desc'] = 'Include local plugin files in the backup.';
$string['include_config'] = 'Include Config';
$string['include_config_desc'] = 'Include config.php in the backup.';
$string['include_moodledata'] = 'Include Uploaded Files';
$string['include_moodledata_desc'] = 'Include moodledata/filedir (all uploaded course files, assignment submissions, resources). Warning: this can be very large.';

// Task.
$string['taskbackup'] = 'Scheduled site backup';

// Misc.
$string['never'] = 'Never';
$string['ago'] = '{$a} ago';
$string['seconds'] = '{$a}s';
$string['na'] = 'N/A';
$string['back'] = 'Back';
$string['notconfigured'] = 'Google Drive credentials not configured.';
$string['drivenotconnected'] = 'Google Drive not connected. Backup saved locally only.';
$string['download'] = 'Download';
$string['filenotfound'] = 'Backup file not found on server. It may have been removed.';
$string['deleted'] = 'Backup deleted successfully.';
$string['deletefailed'] = 'Failed to delete backup.';
$string['backupcontents_db'] = 'Database (courses, grades, marking, users)';
$string['backupcontents_themes'] = 'Themes';
$string['backupcontents_plugins'] = 'Local Plugins';
$string['backupcontents_config'] = 'Config';
$string['backupcontents_moodledata'] = 'Uploaded Files (filedir)';
