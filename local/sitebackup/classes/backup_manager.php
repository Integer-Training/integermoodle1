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

namespace local_sitebackup;

defined('MOODLE_INTERNAL') || die();

/**
 * Core backup orchestrator.
 * Creates database dumps, zips themes/plugins/config/moodledata, bundles into a master ZIP,
 * uploads to Google Drive, and logs the result.
 */
class backup_manager {

    /** @var string Timestamp string for this backup run */
    private string $timestamp;

    /** @var string Temp directory for this backup */
    private string $tempdir;

    /** @var array List of what was included */
    private array $contents = [];

    /** @var int Log record ID */
    private int $logid;

    /**
     * Run a full backup.
     * @param bool $skipdrive If true, skip Google Drive upload (local only)
     * @return bool True on success
     */
    public function run(bool $skipdrive = false): bool {
        global $DB, $CFG;

        // Ensure logs table exists (handles edge case where install.xml didn't run).
        $dbman = $DB->get_manager();
        $table = new \xmldb_table('local_sitebackup_logs');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('filename', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, null);
            $table->add_field('filesize', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('drive_file_id', XMLDB_TYPE_CHAR, '255', null, null, null, null);
            $table->add_field('drive_link', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'in_progress');
            $table->add_field('error_message', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('contents', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('duration', XMLDB_TYPE_INTEGER, '10', null, null, null, '0');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('idx_status', XMLDB_INDEX_NOTUNIQUE, ['status']);
            $table->add_index('idx_timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
            $dbman->create_table($table);
        }

        $start = time();
        $this->timestamp = date('Y-m-d_H-i-s');
        $filename = 'sitebackup_' . $this->timestamp . '.zip';

        // Create log record.
        $this->logid = $DB->insert_record('local_sitebackup_logs', (object) [
            'filename'    => $filename,
            'filesize'    => 0,
            'status'      => 'in_progress',
            'contents'    => '',
            'timecreated' => $start,
        ]);

        try {
            // Create temp directory.
            $this->tempdir = $CFG->dataroot . '/temp/sitebackup_' . $this->timestamp;
            if (!mkdir($this->tempdir, 0777, true)) {
                throw new \moodle_exception('backupfailed', 'local_sitebackup', '',
                    'Cannot create temp directory');
            }

            // Step 1: Database dump.
            if (get_config('local_sitebackup', 'include_database') !== '0') {
                $this->dump_database();
                $this->contents[] = 'database';
            }
            $this->db_keepalive();

            // Step 2: Themes.
            if (get_config('local_sitebackup', 'include_themes') !== '0') {
                $this->zip_themes();
                $this->contents[] = 'themes';
            }
            $this->db_keepalive();

            // Step 3: Local plugins.
            if (get_config('local_sitebackup', 'include_plugins') !== '0') {
                $this->zip_plugins();
                $this->contents[] = 'plugins';
            }
            $this->db_keepalive();

            // Step 4: Config.
            if (get_config('local_sitebackup', 'include_config') !== '0') {
                $this->copy_config();
                $this->contents[] = 'config';
            }
            $this->db_keepalive();

            // Step 5: Moodledata (uploaded files).
            if (get_config('local_sitebackup', 'include_moodledata') === '1') {
                $this->zip_moodledata();
                $this->contents[] = 'moodledata';
            }
            $this->db_keepalive();

            // Step 6: Bundle everything into master ZIP.
            $masterzip = $CFG->dataroot . '/temp/' . $filename;
            $this->create_master_zip($masterzip);
            $this->db_keepalive();

            // Step 7: Move master ZIP to permanent local storage.
            $storagedir = $CFG->dataroot . '/sitebackup';
            if (!is_dir($storagedir)) {
                mkdir($storagedir, 0777, true);
            }
            $localpath = $storagedir . '/' . $filename;
            rename($masterzip, $localpath);
            $filesize = filesize($localpath);

            // Step 8: Upload to Google Drive (if connected and not skipped).
            $driveid = '';
            $drivelink = '';
            if (!$skipdrive && google_drive::is_connected()) {
                $this->db_keepalive();
                $result = google_drive::upload_file($localpath, $filename);
                $driveid = $result['id'];
                $drivelink = $result['link'];

                // Apply retention policy.
                google_drive::apply_retention();
            }

            // Step 9: Update log — keepalive first to prevent "gone away".
            $this->db_keepalive();
            $duration = time() - $start;
            $DB->update_record('local_sitebackup_logs', (object) [
                'id'            => $this->logid,
                'filesize'      => $filesize,
                'drive_file_id' => $driveid,
                'drive_link'    => $drivelink,
                'status'        => 'success',
                'contents'      => json_encode($this->contents),
                'duration'      => $duration,
            ]);

            // Step 10: Cleanup temp files (not the permanent backup).
            $this->cleanup();

            return true;

        } catch (\Exception $e) {
            // Log failure — keepalive first since connection may have dropped.
            $this->db_keepalive();
            $duration = time() - $start;
            try {
                $DB->update_record('local_sitebackup_logs', (object) [
                    'id'            => $this->logid,
                    'status'        => 'failed',
                    'error_message' => $e->getMessage(),
                    'contents'      => json_encode($this->contents),
                    'duration'      => $duration,
                ]);
            } catch (\Exception $dbe) {
                // If DB is truly gone, we can't log — just continue.
                mtrace('Could not update backup log: ' . $dbe->getMessage());
            }

            // Cleanup temp files.
            $this->cleanup();

            // Re-throw so the caller gets the actual error details.
            throw $e;
        }
    }

    /**
     * Dump the database using mysqldump or PHP fallback.
     */
    private function dump_database(): void {
        global $CFG;

        $dumpfile = $this->tempdir . '/database.sql';

        // Try mysqldump first.
        if ($this->try_mysqldump($dumpfile)) {
            return;
        }

        // Fallback: PHP-based SQL export.
        $this->php_dump($dumpfile);
    }

    /**
     * Attempt database dump via mysqldump CLI tool.
     * @param string $dumpfile Output file path
     * @return bool True if successful
     */
    private function try_mysqldump(string $dumpfile): bool {
        global $CFG;

        // Check if shell_exec is available.
        if (!function_exists('shell_exec') || !is_callable('shell_exec')) {
            return false;
        }

        // Check if mysqldump exists.
        $which = shell_exec('which mysqldump 2>/dev/null');
        if (empty(trim($which ?? ''))) {
            return false;
        }

        $host = escapeshellarg($CFG->dbhost);
        $user = escapeshellarg($CFG->dbuser);
        $pass = $CFG->dbpass;
        $name = escapeshellarg($CFG->dbname);
        $file = escapeshellarg($dumpfile);

        // Build command — password via environment variable for security.
        $cmd = "MYSQL_PWD=" . escapeshellarg($pass)
             . " mysqldump --single-transaction --routines --triggers"
             . " -h {$host} -u {$user} {$name} > {$file} 2>&1";

        shell_exec($cmd);

        // Verify the dump file was created and has content.
        if (file_exists($dumpfile) && filesize($dumpfile) > 100) {
            return true;
        }

        // mysqldump failed, remove any partial file.
        if (file_exists($dumpfile)) {
            unlink($dumpfile);
        }
        return false;
    }

    /**
     * PHP-based SQL dump as fallback when mysqldump is not available.
     * Exports all tables as CREATE TABLE + INSERT statements.
     * @param string $dumpfile Output file path
     */
    private function php_dump(string $dumpfile): void {
        global $DB, $CFG;

        $handle = fopen($dumpfile, 'w');
        if (!$handle) {
            throw new \moodle_exception('backupfailed', 'local_sitebackup', '',
                'Cannot write database dump file');
        }

        fwrite($handle, "-- Moodle Site Backup Database Dump\n");
        fwrite($handle, "-- Generated: " . date('Y-m-d H:i:s') . "\n");
        fwrite($handle, "-- Database: {$CFG->dbname}\n\n");
        fwrite($handle, "SET NAMES utf8mb4;\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        // Get all tables.
        $tables = $DB->get_records_sql("SHOW TABLES");
        $dbname = $CFG->dbname;

        // SHOW TABLES returns rows with a column named 'Tables_in_{dbname}'.
        $colname = 'tables_in_' . strtolower($dbname);

        foreach ($tables as $row) {
            $rowarray = (array) $row;
            $table = reset($rowarray);

            // Get CREATE TABLE statement.
            $create = $DB->get_record_sql("SHOW CREATE TABLE `{$table}`");
            $createarray = (array) $create;
            $createstmt = end($createarray);

            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");
            fwrite($handle, $createstmt . ";\n\n");

            // Export data in batches.
            $offset = 0;
            $batchsize = 1000;

            while (true) {
                $rows = $DB->get_records_sql(
                    "SELECT * FROM `{$table}` LIMIT {$batchsize} OFFSET {$offset}"
                );

                if (empty($rows)) {
                    break;
                }

                foreach ($rows as $datarow) {
                    $values = [];
                    foreach ((array) $datarow as $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = "'" . addslashes((string) $value) . "'";
                        }
                    }
                    fwrite($handle, "INSERT INTO `{$table}` VALUES (" . implode(',', $values) . ");\n");
                }

                $offset += $batchsize;
            }

            fwrite($handle, "\n");
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($handle);
    }

    /**
     * Zip theme directories.
     */
    private function zip_themes(): void {
        global $CFG;

        $themedir = $CFG->dirroot . '/theme';
        $zipfile = $this->tempdir . '/themes.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipfile, \ZipArchive::CREATE) !== true) {
            throw new \moodle_exception('backupfailed', 'local_sitebackup', '',
                'Cannot create themes.zip');
        }

        // Zip custom themes (non-core). Core themes that ship with Moodle
        // don't need backing up. We focus on: alpha, adaptable, moove, almondb, nice, stream.
        $customthemes = ['alpha', 'adaptable', 'moove', 'almondb', 'nice', 'stream'];
        foreach ($customthemes as $theme) {
            $path = $themedir . '/' . $theme;
            if (is_dir($path)) {
                $this->add_directory_to_zip($zip, $path, 'theme/' . $theme);
            }
        }

        $zip->close();

        // If the zip is empty (no custom themes found), remove it.
        if (filesize($zipfile) < 22) {
            unlink($zipfile);
        }
    }

    /**
     * Zip local plugin directories.
     */
    private function zip_plugins(): void {
        global $CFG;

        $plugindir = $CFG->dirroot . '/local';
        $zipfile = $this->tempdir . '/plugins.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipfile, \ZipArchive::CREATE) !== true) {
            throw new \moodle_exception('backupfailed', 'local_sitebackup', '',
                'Cannot create plugins.zip');
        }

        $dirs = scandir($plugindir);
        foreach ($dirs as $dir) {
            if ($dir === '.' || $dir === '..') {
                continue;
            }
            $path = $plugindir . '/' . $dir;
            if (is_dir($path)) {
                $this->add_directory_to_zip($zip, $path, 'local/' . $dir);
            }
        }

        $zip->close();
    }

    /**
     * Copy config.php.
     */
    private function copy_config(): void {
        global $CFG;
        $src = $CFG->dirroot . '/config.php';
        $dst = $this->tempdir . '/config.php';
        if (file_exists($src)) {
            copy($src, $dst);
        }
    }

    /**
     * Zip moodledata/filedir (uploaded files).
     */
    private function zip_moodledata(): void {
        global $CFG;

        $filedir = $CFG->dataroot . '/filedir';
        if (!is_dir($filedir)) {
            return;
        }

        $zipfile = $this->tempdir . '/moodledata_filedir.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipfile, \ZipArchive::CREATE) !== true) {
            throw new \moodle_exception('backupfailed', 'local_sitebackup', '',
                'Cannot create moodledata_filedir.zip');
        }

        $this->add_directory_to_zip($zip, $filedir, 'filedir');
        $zip->close();
    }

    /**
     * Create the master ZIP containing all backup components.
     * @param string $outputpath Path for the master ZIP
     */
    private function create_master_zip(string $outputpath): void {
        $zip = new \ZipArchive();
        if ($zip->open($outputpath, \ZipArchive::CREATE) !== true) {
            throw new \moodle_exception('backupfailed', 'local_sitebackup', '',
                'Cannot create master backup ZIP');
        }

        // Add manifest.
        $manifest = [
            'timestamp'  => $this->timestamp,
            'contents'   => $this->contents,
            'moodle_ver' => get_config('', 'version'),
            'site_url'   => $GLOBALS['CFG']->wwwroot ?? '',
        ];
        $zip->addFromString('manifest.json', json_encode($manifest, JSON_PRETTY_PRINT));

        // Add each file from the temp directory.
        $files = scandir($this->tempdir);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filepath = $this->tempdir . '/' . $file;
            if (is_file($filepath)) {
                $zip->addFile($filepath, $file);
            }
        }

        $zip->close();
    }

    /**
     * Recursively add a directory to a ZipArchive.
     * @param \ZipArchive $zip ZipArchive instance
     * @param string $directory Path to directory
     * @param string $prefix Prefix inside the ZIP
     */
    private function add_directory_to_zip(\ZipArchive $zip, string $directory, string $prefix): void {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            $subpath = $prefix . '/' . $iterator->getSubPathname();
            if ($item->isDir()) {
                $zip->addEmptyDir($subpath);
            } else {
                // Skip very large individual files (>200MB) and .git directories.
                if (strpos($subpath, '/.git/') !== false) {
                    continue;
                }
                if ($item->getSize() > 200 * 1024 * 1024) {
                    continue;
                }
                $zip->addFile($item->getPathname(), $subpath);
            }
        }
    }

    /**
     * Send a lightweight query to keep the MySQL connection alive.
     * Shared hosting often has short wait_timeout (30–60s).
     * This prevents "MySQL server has gone away" during long file operations.
     */
    private function db_keepalive(): void {
        global $DB;
        try {
            $DB->count_records_sql("SELECT 1");
        } catch (\Exception $e) {
            // Connection already lost — nothing to do here.
            // The keepalive calls between steps prevent this from happening.
        }
    }

    /**
     * Clean up temp directory and optionally the master ZIP.
     * @param string|null $masterzip Path to master ZIP to remove
     */
    private function cleanup(?string $masterzip = null): void {
        if (!empty($this->tempdir) && is_dir($this->tempdir)) {
            $this->remove_directory($this->tempdir);
        }
        if ($masterzip && file_exists($masterzip)) {
            unlink($masterzip);
        }
    }

    /**
     * Recursively remove a directory.
     * @param string $dir Directory path
     */
    private function remove_directory(string $dir): void {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                $this->remove_directory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
