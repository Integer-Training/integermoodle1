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

namespace plagiarism_gptzero;

/**
 * Functions to communicate with GPTZero endpoints
 *
 * @package    plagiarism_gptzero
 * @copyright  2024 GPTZero <team@gptzero.me>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class api {
    /** @var string $apikey API key used for authentication with GPTZero endpoints */
    private $apikey;

    /** @var string $apiurl URL used for sending requests to GPTZero endpoints */
    private $apiurl = 'https://api.gptzero.me';

    /**
     * Constructs the API client, initializing with API key from the configuration.
     */
    public function __construct() {
        $this->apikey = get_config('plagiarism_gptzero', 'gptzero_apikey');
    }

    /**
     * Submits a file to GPTZero for AI detection.
     *
     * @param mixed $file The file to be submitted.
     * @param array $params Additional parameters for the submission.
     * @return string The response from the GPTZero API.
     */
    public function submit_file($file, $params) {
        $filecontent = $file->get_content();
        $filename = $file->get_filename();
        $filetype = $file->get_mimetype();

        $boundary = "----CustomBoundary123456789";
        $payload = "--" . $boundary . "\r\n";
        $payload .= "Content-Disposition: form-data; name=\"file\"; filename=\"" . basename($filename) . "\"\r\n";
        $payload .= "Content-Type: " . $filetype . "\r\n\r\n";
        $payload .= $filecontent . "\r\n";

        foreach ($params as $key => $value) {
            $payload .= "--" . $boundary . "\r\n";
            $payload .= "Content-Disposition: form-data; name=\"" . $key . "\"\r\n\r\n";
            $payload .= $value . "\r\n";
        }
        $payload .= "--" . $boundary . "--\r\n";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiurl . '/v3/moodle/submit',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: multipart/form-data; boundary=" . $boundary,
                "x-api-key: {$this->apikey}",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            debugging("cURL Error #:" . $err, DEBUG_DEVELOPER);
        } else {
            debugging("Response received: " . $response, DEBUG_DEVELOPER);
        }

        return $response;
    }

    /**
     * Submits text to GPTZero for AI detection.
     *
     * @param string $text The text to be submitted.
     * @param array $params Additional parameters for the submission.
     * @return string The response from the GPTZero API.
     */
    public function submit_text($text, $params) {
        $boundary = "----CustomBoundary123456789";
        $payload = "--" . $boundary . "\r\n";
        $payload .= "Content-Disposition: form-data; name=\"text\"\r\n\r\n";
        $payload .= $text . "\r\n";

        foreach ($params as $key => $value) {
            $payload .= "--" . $boundary . "\r\n";
            $payload .= "Content-Disposition: form-data; name=\"" . $key . "\"\r\n\r\n";
            $payload .= $value . "\r\n";
        }
        $payload .= "--" . $boundary . "--\r\n";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiurl . '/v3/moodle/submit',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: multipart/form-data; boundary=" . $boundary,
                "x-api-key: {$this->apikey}",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);

        if ($err) {
            debugging("cURL Error #:" . $err, DEBUG_DEVELOPER);
        } else {
            debugging("Response received: " . $response, DEBUG_DEVELOPER);
        }

        return $response;
    }

    /**
     * Creates an assignment in GPTZero with specified user details.
     *
     * @param string $username Username associated with the assignment.
     * @param string $useremail User's email for contact and identification.
     * @param string $userid User's ID in the system.
     * @return string The response from the GPTZero API indicating success or failure.
     */
    public function create_assignment($username, $useremail, $userid) {
        $data = json_encode([
            'userName' => $username,
            'userEmail' => $useremail,
            'userId' => $userid,
        ]);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiurl . "/v3/moodle/deep-linking",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: application/json",
                "x-api-key: {$this->apikey}",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            debugging("cURL Error #:" . $err, DEBUG_DEVELOPER);
        } else {
            debugging("Assignment creation response: " . $response, DEBUG_DEVELOPER);
        }

        return $response;
    }

    /**
     * Checks if a user has an account on GPTZero.
     *
     * @param string $useremail The email address to check for an existing account.
     * @return string The response from the GPTZero API indicating account existence.
     */
    public function has_gptzero_account($useremail) {
        $data = json_encode([
            'userEmail' => $useremail,
        ]);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiurl . "/v3/moodle/launch",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => $data,
            CURLOPT_HTTPHEADER => [
                "Accept: application/json",
                "Content-Type: application/json",
                "x-api-key: {$this->apikey}",
            ],
        ]);

        $response = curl_exec($curl);
        $err = curl_error($curl);

        curl_close($curl);

        if ($err) {
            debugging("cURL Error #:" . $err, DEBUG_DEVELOPER);
        } else {
            debugging("GPTZero Account API Response: " . $response, DEBUG_DEVELOPER);
        }

        return $response;
    }
}
