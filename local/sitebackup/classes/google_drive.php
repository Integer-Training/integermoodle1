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

require_once($GLOBALS['CFG']->libdir . '/filelib.php');

/**
 * Google Drive REST API v3 client using Moodle's \curl class.
 * Handles OAuth 2.0 token management, file upload, list, and delete.
 */
class google_drive {

    /** @var string Google OAuth 2.0 auth endpoint */
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    /** @var string Google OAuth 2.0 token endpoint */
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** @var string Google Drive API v3 base URL */
    private const DRIVE_API = 'https://www.googleapis.com/drive/v3';

    /** @var string Google Drive API v3 upload URL */
    private const UPLOAD_API = 'https://www.googleapis.com/upload/drive/v3';

    /** @var string OAuth scope — only access files created by this app */
    private const SCOPE = 'https://www.googleapis.com/auth/drive.file';

    /**
     * Check if Google Drive credentials are configured.
     * @return bool
     */
    public static function is_configured(): bool {
        $clientid = get_config('local_sitebackup', 'google_client_id');
        $secret = get_config('local_sitebackup', 'google_client_secret');
        return !empty($clientid) && !empty($secret);
    }

    /**
     * Check if Google Drive is connected (has refresh token).
     * @return bool
     */
    public static function is_connected(): bool {
        $token = get_config('local_sitebackup', 'google_refresh_token');
        return !empty($token);
    }

    /**
     * Get the OAuth 2.0 authorization URL for the consent screen.
     * @return string
     */
    public static function get_auth_url(): string {
        global $CFG;

        $clientid = get_config('local_sitebackup', 'google_client_id');
        $redirect = $CFG->wwwroot . '/local/sitebackup/authorize.php';

        $params = [
            'client_id'     => $clientid,
            'redirect_uri'  => $redirect,
            'response_type' => 'code',
            'scope'         => self::SCOPE,
            'access_type'   => 'offline',
            'prompt'        => 'consent',
        ];

        return self::AUTH_URL . '?' . http_build_query($params);
    }

    /**
     * Exchange authorization code for access + refresh tokens.
     * @param string $code Authorization code from Google
     * @return bool True on success
     * @throws \Exception on failure
     */
    public static function exchange_code(string $code): bool {
        global $CFG;

        $clientid = get_config('local_sitebackup', 'google_client_id');
        $secret = get_config('local_sitebackup', 'google_client_secret');
        $redirect = $CFG->wwwroot . '/local/sitebackup/authorize.php';

        $postdata = [
            'grant_type'    => 'authorization_code',
            'code'          => $code,
            'client_id'     => $clientid,
            'client_secret' => $secret,
            'redirect_uri'  => $redirect,
        ];

        $response = self::token_request($postdata);

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            $error = $data['error_description'] ?? $data['error'] ?? 'Unknown error';
            $raw = substr($response, 0, 200);
            throw new \Exception($error . ' [Raw: ' . $raw . ']');
        }

        set_config('google_access_token', $data['access_token'], 'local_sitebackup');
        set_config('google_token_expires', time() + ($data['expires_in'] ?? 3600), 'local_sitebackup');

        if (!empty($data['refresh_token'])) {
            set_config('google_refresh_token', $data['refresh_token'], 'local_sitebackup');
        }

        return true;
    }

    /**
     * Disconnect Google Drive (remove stored tokens).
     */
    public static function disconnect(): void {
        unset_config('google_access_token', 'local_sitebackup');
        unset_config('google_refresh_token', 'local_sitebackup');
        unset_config('google_token_expires', 'local_sitebackup');
        unset_config('google_drive_folder_id', 'local_sitebackup');
    }

    /**
     * Get a valid access token, refreshing if necessary.
     * @return string Access token
     * @throws \Exception if refresh fails
     */
    private static function get_access_token(): string {
        $token = get_config('local_sitebackup', 'google_access_token');
        $expires = get_config('local_sitebackup', 'google_token_expires');

        // Refresh if expired or expiring within 60 seconds.
        if (empty($token) || empty($expires) || time() > ($expires - 60)) {
            $token = self::refresh_token();
        }

        return $token;
    }

    /**
     * Refresh the access token using the stored refresh token.
     * @return string New access token
     * @throws \Exception if refresh fails
     */
    private static function refresh_token(): string {
        $refreshtoken = get_config('local_sitebackup', 'google_refresh_token');
        if (empty($refreshtoken)) {
            throw new \Exception('No refresh token stored. Please reconnect Google Drive.');
        }

        $clientid = get_config('local_sitebackup', 'google_client_id');
        $secret = get_config('local_sitebackup', 'google_client_secret');

        $postdata = [
            'grant_type'    => 'refresh_token',
            'client_id'     => $clientid,
            'client_secret' => $secret,
            'refresh_token' => $refreshtoken,
        ];

        $response = self::token_request($postdata);

        $data = json_decode($response, true);
        if (empty($data['access_token'])) {
            $error = $data['error_description'] ?? $data['error'] ?? 'Token refresh failed';
            throw new \Exception($error);
        }

        set_config('google_access_token', $data['access_token'], 'local_sitebackup');
        set_config('google_token_expires', time() + ($data['expires_in'] ?? 3600), 'local_sitebackup');

        return $data['access_token'];
    }

    /**
     * Get or create the backup folder on Google Drive.
     * @return string Folder ID
     */
    private static function get_or_create_folder(): string {
        // Check cached folder ID.
        $folderid = get_config('local_sitebackup', 'google_drive_folder_id');
        if (!empty($folderid)) {
            // Verify folder still exists.
            try {
                $token = self::get_access_token();
                $curl = new \curl();
                $curl->setHeader(['Authorization: Bearer ' . $token]);
                $response = $curl->get(self::DRIVE_API . '/files/' . $folderid . '?fields=id,trashed');

                $data = json_decode($response, true);
                if (!empty($data['id']) && empty($data['trashed'])) {
                    return $folderid;
                }
            } catch (\Exception $e) {
                // Folder may have been deleted, create a new one.
            }
        }

        // Create folder.
        $foldername = get_config('local_sitebackup', 'drive_folder_name') ?: 'Moodle-Backups';
        $token = self::get_access_token();

        $metadata = json_encode([
            'name'     => $foldername,
            'mimeType' => 'application/vnd.google-apps.folder',
        ]);

        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ]);
        $response = $curl->post(self::DRIVE_API . '/files', $metadata);

        $data = json_decode($response, true);
        if (empty($data['id'])) {
            throw new \Exception('Failed to create Drive folder');
        }

        set_config('google_drive_folder_id', $data['id'], 'local_sitebackup');
        return $data['id'];
    }

    /**
     * Upload a file to Google Drive.
     * @param string $filepath Local path to the file
     * @param string $filename Display name on Drive
     * @return array ['id' => file_id, 'link' => web_view_link]
     */
    public static function upload_file(string $filepath, string $filename): array {
        $token = self::get_access_token();
        $folderid = self::get_or_create_folder();
        $filesize = filesize($filepath);

        // Use resumable upload for files > 5MB, multipart for smaller.
        if ($filesize > 5 * 1024 * 1024) {
            return self::upload_resumable($filepath, $filename, $folderid, $token);
        }

        return self::upload_multipart($filepath, $filename, $folderid, $token);
    }

    /**
     * Multipart upload for smaller files.
     */
    private static function upload_multipart(string $filepath, string $filename,
                                              string $folderid, string $token): array {
        $boundary = 'sitebackup_' . uniqid();
        $metadata = json_encode([
            'name'    => $filename,
            'parents' => [$folderid],
        ]);

        $filecontent = file_get_contents($filepath);

        $body = "--{$boundary}\r\n"
              . "Content-Type: application/json; charset=UTF-8\r\n\r\n"
              . $metadata . "\r\n"
              . "--{$boundary}\r\n"
              . "Content-Type: application/zip\r\n"
              . "Content-Transfer-Encoding: base64\r\n\r\n"
              . base64_encode($filecontent) . "\r\n"
              . "--{$boundary}--";

        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $token,
            'Content-Type: multipart/related; boundary=' . $boundary,
        ]);
        $response = $curl->post(
            self::UPLOAD_API . '/files?uploadType=multipart&fields=id,webViewLink',
            $body
        );

        $data = json_decode($response, true);
        if (empty($data['id'])) {
            throw new \Exception('Upload failed: ' . ($data['error']['message'] ?? 'Unknown error'));
        }

        return [
            'id'   => $data['id'],
            'link' => $data['webViewLink'] ?? '',
        ];
    }

    /**
     * Resumable upload for larger files — uploads in 5MB chunks.
     */
    private static function upload_resumable(string $filepath, string $filename,
                                              string $folderid, string $token): array {
        $filesize = filesize($filepath);
        $metadata = json_encode([
            'name'    => $filename,
            'parents' => [$folderid],
        ]);

        // Step 1: Initiate resumable session.
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => self::UPLOAD_API . '/files?uploadType=resumable&fields=id,webViewLink',
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $metadata,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER         => true,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $token,
                'Content-Type: application/json; charset=UTF-8',
                'X-Upload-Content-Type: application/zip',
                'X-Upload-Content-Length: ' . $filesize,
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);
        $response = curl_exec($ch);
        $headersize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headers = substr($response, 0, $headersize);
        curl_close($ch);

        // Extract upload URI from Location header.
        if (!preg_match('/Location:\s*(.+)/i', $headers, $matches)) {
            throw new \Exception('Failed to initiate resumable upload');
        }
        $uploaduri = trim($matches[1]);

        // Step 2: Upload in chunks.
        $chunksize = 5 * 1024 * 1024; // 5MB chunks.
        $handle = fopen($filepath, 'rb');
        $offset = 0;

        while ($offset < $filesize) {
            $chunk = fread($handle, $chunksize);
            $chunklen = strlen($chunk);
            $end = $offset + $chunklen - 1;

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => $uploaduri,
                CURLOPT_CUSTOMREQUEST  => 'PUT',
                CURLOPT_POSTFIELDS     => $chunk,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_HTTPHEADER     => [
                    'Content-Length: ' . $chunklen,
                    'Content-Range: bytes ' . $offset . '-' . $end . '/' . $filesize,
                ],
                CURLOPT_TIMEOUT        => 300,
            ]);
            $result = curl_exec($ch);
            $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $offset += $chunklen;

            // 200 or 201 means upload complete, 308 means more chunks needed.
            if ($httpcode === 200 || $httpcode === 201) {
                fclose($handle);
                $data = json_decode($result, true);
                return [
                    'id'   => $data['id'] ?? '',
                    'link' => $data['webViewLink'] ?? '',
                ];
            }

            if ($httpcode !== 308) {
                fclose($handle);
                throw new \Exception('Chunk upload failed with HTTP ' . $httpcode);
            }
        }

        fclose($handle);
        throw new \Exception('Resumable upload did not complete');
    }

    /**
     * List files in the backup folder.
     * @return array Array of file objects with id, name, size, createdTime, webViewLink
     */
    public static function list_files(): array {
        $folderid = get_config('local_sitebackup', 'google_drive_folder_id');
        if (empty($folderid)) {
            return [];
        }

        $token = self::get_access_token();
        $query = "'{$folderid}' in parents and trashed = false";
        $fields = 'files(id,name,size,createdTime,webViewLink)';
        $orderby = 'createdTime desc';

        $url = self::DRIVE_API . '/files?' . http_build_query([
            'q'       => $query,
            'fields'  => $fields,
            'orderBy' => $orderby,
            'pageSize' => 100,
        ]);

        $curl = new \curl();
        $curl->setHeader(['Authorization: Bearer ' . $token]);
        $response = $curl->get($url);
        $data = json_decode($response, true);

        return $data['files'] ?? [];
    }

    /**
     * Delete a file from Google Drive.
     * @param string $fileid Drive file ID
     * @return bool
     */
    public static function delete_file(string $fileid): bool {
        $token = self::get_access_token();

        $curl = new \curl();
        $curl->setHeader(['Authorization: Bearer ' . $token]);
        $curl->delete(self::DRIVE_API . '/files/' . $fileid);

        $info = $curl->get_info();
        $httpcode = $info['http_code'] ?? 0;

        return $httpcode === 204 || $httpcode === 200;
    }

    /**
     * Make a POST request to Google's token endpoint.
     * Tries multiple methods to handle different server configurations.
     * @param array $params POST parameters
     * @return string Raw response body
     */
    private static function token_request(array $params): string {
        $body = '';
        foreach ($params as $key => $value) {
            if ($body !== '') {
                $body .= '&';
            }
            $body .= rawurlencode($key) . '=' . rawurlencode($value);
        }

        // Method 1: PHP stream context (most reliable on shared hosting).
        if (ini_get('allow_url_fopen')) {
            $context = stream_context_create([
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                              . "Content-Length: " . strlen($body) . "\r\n",
                    'content' => $body,
                    'timeout' => 30,
                ],
                'ssl' => [
                    'verify_peer'      => true,
                    'verify_peer_name' => true,
                ],
            ]);
            $response = @file_get_contents(self::TOKEN_URL, false, $context);
            if ($response !== false) {
                return $response;
            }
        }

        // Method 2: Raw cURL with explicit settings.
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, self::TOKEN_URL);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded',
                'Content-Length: ' . strlen($body),
            ]);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            $response = curl_exec($ch);
            $error = curl_error($ch);
            curl_close($ch);

            if ($response !== false) {
                return $response;
            }
            throw new \Exception('cURL error: ' . $error);
        }

        throw new \Exception('No HTTP client available (cURL and file_get_contents both unavailable)');
    }

    /**
     * Apply retention policy — delete oldest files beyond the retention count.
     */
    public static function apply_retention(): void {
        $retention = (int) get_config('local_sitebackup', 'retention_count') ?: 10;
        $files = self::list_files();

        if (count($files) <= $retention) {
            return;
        }

        // Files are already sorted newest first. Delete from position $retention onwards.
        $todelete = array_slice($files, $retention);
        foreach ($todelete as $file) {
            self::delete_file($file['id']);
        }
    }
}
