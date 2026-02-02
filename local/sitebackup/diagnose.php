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
 * Diagnostic page for Google Drive connection testing.
 * Tests each HTTP method (Moodle curl, raw cURL, file_get_contents) against
 * Google's token endpoint to identify WAF/firewall issues.
 */

require_once('../../config.php');
require_login();

$context = context_system::instance();
require_capability('local/sitebackup:manage', $context);

$PAGE->set_url(new moodle_url('/local/sitebackup/diagnose.php'));
$PAGE->set_context($context);
$PAGE->set_title('Site Backup - Connection Diagnostics');
$PAGE->set_heading('Site Backup - Connection Diagnostics');
$PAGE->set_pagelayout('standard');

echo $OUTPUT->header();

echo '<div style="max-width:900px; margin:0 auto; font-family:monospace; font-size:13px;">';
echo '<h3 style="margin-bottom:20px;">Google Drive Connection Diagnostics</h3>';

// Check configuration.
$clientid = get_config('local_sitebackup', 'google_client_id');
$secret = get_config('local_sitebackup', 'google_client_secret');
$refreshtoken = get_config('local_sitebackup', 'google_refresh_token');
$accesstoken = get_config('local_sitebackup', 'google_access_token');
$tokenexpires = get_config('local_sitebackup', 'google_token_expires');
$folderid = get_config('local_sitebackup', 'google_drive_folder_id');

echo '<div style="background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:16px; margin-bottom:16px;">';
echo '<h4>1. Configuration Check</h4>';
echo '<table style="width:100%; border-collapse:collapse;">';
$checks = [
    'Client ID' => !empty($clientid) ? 'Set (' . substr($clientid, 0, 20) . '...)' : 'NOT SET',
    'Client Secret' => !empty($secret) ? 'Set (hidden)' : 'NOT SET',
    'Refresh Token' => !empty($refreshtoken) ? 'Set (' . substr($refreshtoken, 0, 15) . '...)' : 'NOT SET',
    'Access Token' => !empty($accesstoken) ? 'Set (expires ' . ($tokenexpires ? userdate($tokenexpires) : 'unknown') . ')' : 'NOT SET',
    'Drive Folder ID' => !empty($folderid) ? $folderid : 'NOT SET (will be auto-created on first upload)',
    'Folder Name' => get_config('local_sitebackup', 'drive_folder_name') ?: 'Moodle-Backups (default)',
];
foreach ($checks as $label => $value) {
    $color = (strpos($value, 'NOT SET') !== false && $label !== 'Drive Folder ID') ? '#dc3545' : '#28a745';
    echo "<tr><td style='padding:4px 8px; font-weight:bold;'>{$label}</td>"
       . "<td style='padding:4px 8px; color:{$color};'>{$value}</td></tr>";
}
echo '</table></div>';

// Check PHP capabilities.
echo '<div style="background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:16px; margin-bottom:16px;">';
echo '<h4>2. PHP Capabilities</h4>';
echo '<table style="width:100%; border-collapse:collapse;">';
$phpchecks = [
    'cURL extension' => function_exists('curl_init') ? 'Available' : 'NOT AVAILABLE',
    'allow_url_fopen' => ini_get('allow_url_fopen') ? 'Enabled' : 'Disabled',
    'Moodle \\curl class' => class_exists('curl') ? 'Available' : 'NOT AVAILABLE',
    'cURL version' => function_exists('curl_version') ? curl_version()['version'] . ' (SSL: ' . curl_version()['ssl_version'] . ')' : 'N/A',
    'PHP version' => phpversion(),
    'Moodle proxy' => !empty($CFG->proxyhost) ? $CFG->proxyhost . ':' . ($CFG->proxyport ?? 0) : 'None configured',
];
foreach ($phpchecks as $label => $value) {
    $color = (strpos($value, 'NOT') !== false || strpos($value, 'Disabled') !== false) ? '#dc3545' : '#28a745';
    echo "<tr><td style='padding:4px 8px; font-weight:bold;'>{$label}</td>"
       . "<td style='padding:4px 8px; color:{$color};'>{$value}</td></tr>";
}
echo '</table></div>';

// Test outbound POST to Google token endpoint (with dummy data to trigger a known error).
echo '<div style="background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:16px; margin-bottom:16px;">';
echo '<h4>3. Outbound POST Test (to Google Token Endpoint)</h4>';
echo '<p style="color:#666; font-size:12px;">Sends a test POST with dummy grant_type to check if the server '
   . 'can deliver POST bodies to Google. Expected response: an error mentioning "unsupported_grant_type" '
   . '(this confirms the POST body was received). If it says "Invalid grant_type:" (empty), the hosting '
   . 'firewall is stripping POST bodies.</p>';

$testbody = http_build_query([
    'grant_type'    => 'diagnostic_test',
    'client_id'     => 'test',
    'client_secret' => 'test',
]);
$tokenurl = 'https://oauth2.googleapis.com/token';

// Test Method A: Moodle \curl.
echo '<div style="margin:12px 0; padding:12px; background:#fff; border:1px solid #ddd; border-radius:4px;">';
echo '<strong>Method A: Moodle \\curl class</strong><br>';
try {
    require_once($CFG->libdir . '/filelib.php');
    $curl = new \curl();
    $curl->setHeader([
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($testbody),
    ]);
    $response = $curl->post($tokenurl, $testbody, ['CURLOPT_TIMEOUT' => 15]);
    $info = $curl->get_info();
    $httpcode = $info['http_code'] ?? 0;
    $error = $curl->error;

    $data = json_decode($response, true);
    $googlerror = $data['error'] ?? 'no error field';
    $googledesc = $data['error_description'] ?? 'no description';

    echo "HTTP Code: <strong>{$httpcode}</strong><br>";
    echo "Google error: <strong>{$googlerror}</strong><br>";
    echo "Google description: <strong>{$googledesc}</strong><br>";
    echo "cURL error: " . ($error ?: 'none') . "<br>";

    if (strpos($googlerror, 'unsupported_grant_type') !== false ||
        strpos($googledesc, 'unsupported_grant_type') !== false) {
        echo '<span style="color:#28a745; font-weight:bold;">PASS - POST body received by Google correctly.</span>';
    } else if (strpos($googlerror, 'invalid_grant') !== false ||
               strpos($googledesc, 'Invalid grant_type:') !== false) {
        echo '<span style="color:#dc3545; font-weight:bold;">FAIL - POST body appears to be stripped '
           . '(Google received empty grant_type). Your hosting firewall/WAF is blocking outbound POST data.</span>';
    } else {
        echo '<span style="color:#ffc107; font-weight:bold;">UNCLEAR - Unexpected response. Raw: '
           . htmlspecialchars(substr($response, 0, 300)) . '</span>';
    }
} catch (\Exception $e) {
    echo '<span style="color:#dc3545;">Exception: ' . htmlspecialchars($e->getMessage()) . '</span>';
}
echo '</div>';

// Test Method B: Raw cURL.
echo '<div style="margin:12px 0; padding:12px; background:#fff; border:1px solid #ddd; border-radius:4px;">';
echo '<strong>Method B: Raw cURL (php curl_init)</strong><br>';
if (function_exists('curl_init')) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $tokenurl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $testbody);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'Content-Length: ' . strlen($testbody),
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    $googlerror = $data['error'] ?? 'no error field';
    $googledesc = $data['error_description'] ?? 'no description';

    echo "HTTP Code: <strong>{$httpcode}</strong><br>";
    echo "Google error: <strong>{$googlerror}</strong><br>";
    echo "Google description: <strong>{$googledesc}</strong><br>";
    echo "cURL error: " . ($error ?: 'none') . "<br>";

    if (strpos($googlerror, 'unsupported_grant_type') !== false ||
        strpos($googledesc, 'unsupported_grant_type') !== false) {
        echo '<span style="color:#28a745; font-weight:bold;">PASS - POST body received by Google correctly.</span>';
    } else if (strpos($googlerror, 'invalid_grant') !== false ||
               strpos($googledesc, 'Invalid grant_type:') !== false) {
        echo '<span style="color:#dc3545; font-weight:bold;">FAIL - POST body stripped by hosting firewall.</span>';
    } else {
        echo '<span style="color:#ffc107; font-weight:bold;">UNCLEAR - Raw: '
           . htmlspecialchars(substr($response, 0, 300)) . '</span>';
    }
} else {
    echo '<span style="color:#dc3545;">cURL extension not available.</span>';
}
echo '</div>';

// Test Method C: file_get_contents.
echo '<div style="margin:12px 0; padding:12px; background:#fff; border:1px solid #ddd; border-radius:4px;">';
echo '<strong>Method C: file_get_contents (PHP stream)</strong><br>';
if (ini_get('allow_url_fopen')) {
    $context = stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => "Content-Type: application/x-www-form-urlencoded\r\n"
                       . "Content-Length: " . strlen($testbody) . "\r\n",
            'content' => $testbody,
            'timeout' => 15,
        ],
        'ssl' => [
            'verify_peer'      => true,
            'verify_peer_name' => true,
        ],
    ]);
    $response = @file_get_contents($tokenurl, false, $context);

    if ($response !== false) {
        $data = json_decode($response, true);
        $googlerror = $data['error'] ?? 'no error field';
        $googledesc = $data['error_description'] ?? 'no description';

        echo "Google error: <strong>{$googlerror}</strong><br>";
        echo "Google description: <strong>{$googledesc}</strong><br>";

        if (strpos($googlerror, 'unsupported_grant_type') !== false ||
            strpos($googledesc, 'unsupported_grant_type') !== false) {
            echo '<span style="color:#28a745; font-weight:bold;">PASS - POST body received correctly.</span>';
        } else if (strpos($googlerror, 'invalid_grant') !== false ||
                   strpos($googledesc, 'Invalid grant_type:') !== false) {
            echo '<span style="color:#dc3545; font-weight:bold;">FAIL - POST body stripped.</span>';
        } else {
            echo '<span style="color:#ffc107; font-weight:bold;">UNCLEAR - Raw: '
               . htmlspecialchars(substr($response, 0, 300)) . '</span>';
        }
    } else {
        echo '<span style="color:#dc3545;">Request failed (returned false). '
           . 'The server may be blocking outbound HTTPS requests via file_get_contents.</span>';
    }
} else {
    echo '<span style="color:#666;">allow_url_fopen is disabled.</span>';
}
echo '</div>';
echo '</div>';

// If we have a refresh token, offer to test actual token refresh.
if (!empty($refreshtoken)) {
    $action = optional_param('action', '', PARAM_ALPHA);
    if ($action === 'testrefresh' && confirm_sesskey()) {
        echo '<div style="background:#f8f9fa; border:1px solid #dee2e6; border-radius:8px; padding:16px; margin-bottom:16px;">';
        echo '<h4>4. Live Token Refresh Test</h4>';
        try {
            // This will use the updated token_request() with Moodle curl.
            $result = \local_sitebackup\google_drive::is_connected();
            echo '<p>is_connected(): <strong>' . ($result ? 'true' : 'false') . '</strong></p>';

            // Try to list files (this triggers token refresh if needed).
            $files = \local_sitebackup\google_drive::list_files();
            echo '<p style="color:#28a745; font-weight:bold;">Token refresh + Drive API call SUCCEEDED.</p>';
            echo '<p>Files in backup folder: <strong>' . count($files) . '</strong></p>';
            if (!empty($files)) {
                echo '<ul>';
                foreach (array_slice($files, 0, 5) as $file) {
                    echo '<li>' . htmlspecialchars($file['name'] ?? 'unnamed')
                       . ' (' . ($file['size'] ?? '?') . ' bytes)</li>';
                }
                if (count($files) > 5) {
                    echo '<li>... and ' . (count($files) - 5) . ' more</li>';
                }
                echo '</ul>';
            }
        } catch (\Exception $e) {
            echo '<p style="color:#dc3545; font-weight:bold;">FAILED: '
               . htmlspecialchars($e->getMessage()) . '</p>';
        }
        echo '</div>';
    } else {
        $testurl = new moodle_url('/local/sitebackup/diagnose.php', [
            'action' => 'testrefresh',
            'sesskey' => sesskey(),
        ]);
        echo '<div style="margin:16px 0;">';
        echo '<a href="' . $testurl->out(false) . '" class="btn btn-primary">'
           . 'Test Live Token Refresh + Drive API</a>';
        echo ' <span style="color:#666; font-size:12px;">(Uses your stored refresh token to actually '
           . 'call Google Drive API)</span>';
        echo '</div>';
    }
}

$backurl = new moodle_url('/local/sitebackup/view.php');
echo '<div style="margin-top:24px;">';
echo '<a href="' . $backurl->out(false) . '" class="btn btn-secondary">Back to Backup Manager</a>';
echo '</div>';
echo '</div>';

echo $OUTPUT->footer();
