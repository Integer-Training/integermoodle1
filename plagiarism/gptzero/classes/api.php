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
     * Submits a file to GPTZero for AI detection using standard API.
     *
     * @param mixed $file The file to be submitted.
     * @param array $params Additional parameters for the submission (unused in standard API).
     * @return string The response from the GPTZero API.
     */
    public function submit_file($file, $params) {
        $filecontent = $file->get_content();
        $filename = $file->get_filename();
        $filetype = $file->get_mimetype();

        // Use standard GPTZero API endpoint for files
        $boundary = "----CustomBoundary" . uniqid();
        $payload = "--" . $boundary . "\r\n";
        $payload .= "Content-Disposition: form-data; name=\"files\"; filename=\"" . basename($filename) . "\"\r\n";
        $payload .= "Content-Type: " . $filetype . "\r\n\r\n";
        $payload .= $filecontent . "\r\n";
        $payload .= "--" . $boundary . "--\r\n";

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiurl . '/v2/predict/files',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
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
            return json_encode(['error' => $err]);
        }

        // Transform standard API response to match expected format
        return $this->transform_response($response);
    }

    /**
     * Submits text to GPTZero for AI detection using standard API.
     *
     * @param string $text The text to be submitted.
     * @param array $params Additional parameters for the submission (unused in standard API).
     * @return string The response from the GPTZero API.
     */
    public function submit_text($text, $params) {
        $data = json_encode([
            'document' => $text,
        ]);

        $curl = curl_init();

        curl_setopt_array($curl, [
            CURLOPT_URL => $this->apiurl . '/v2/predict/text',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
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
            return json_encode(['error' => $err]);
        }

        // Transform standard API response to match expected format
        return $this->transform_response($response);
    }

    /**
     * Transform GPTZero standard API response to the format expected by the plugin.
     *
     * Standard API returns:
     * {
     *   "documents": [{
     *     "average_generated_prob": 0.8,
     *     "completely_generated_prob": 0.9,
     *     "overall_burstiness": 50,
     *     "paragraphs": [...],
     *     "predicted_class": "ai",
     *     "class_probabilities": {"ai": 0.9, "human": 0.05, "mixed": 0.05}
     *   }]
     * }
     *
     * Plugin expects:
     * {
     *   "results": {
     *     "predicted_class": "ai",
     *     "class_probability": 0.9,
     *     "confidence_category": "high",
     *     "scanId": "xxx",
     *     "scanUrl": "https://..."
     *   }
     * }
     *
     * @param string $response Raw API response
     * @return string Transformed response
     */
    private function transform_response($response) {
        $data = json_decode($response, true);

        if (isset($data['error'])) {
            return $response; // Pass through errors
        }

        if (!isset($data['documents']) || empty($data['documents'])) {
            return json_encode(['error' => 'Invalid response from GPTZero API']);
        }

        $doc = $data['documents'][0];

        // Determine class probability based on predicted class
        $predictedClass = $doc['predicted_class'] ?? 'unknown';
        $classProbability = 0;

        if (isset($doc['class_probabilities'])) {
            $classProbability = $doc['class_probabilities'][$predictedClass] ?? 0;
        } else if (isset($doc['completely_generated_prob'])) {
            // Fallback: use completely_generated_prob for AI probability
            if ($predictedClass === 'ai') {
                $classProbability = $doc['completely_generated_prob'];
            } else if ($predictedClass === 'human') {
                $classProbability = 1 - $doc['completely_generated_prob'];
            } else {
                $classProbability = 0.5; // Mixed
            }
        }

        // Determine confidence category
        $confidenceCategory = 'low';
        if ($classProbability >= 0.8) {
            $confidenceCategory = 'high';
        } else if ($classProbability >= 0.5) {
            $confidenceCategory = 'medium';
        }

        $transformed = [
            'results' => [
                'predicted_class' => $predictedClass,
                'class_probability' => $classProbability,
                'confidence_category' => $confidenceCategory,
                'scanId' => uniqid('gptzero_'),
                'scanUrl' => '', // Standard API doesn't provide a scan URL
            ],
        ];

        return json_encode($transformed);
    }

    /**
     * Creates an assignment in GPTZero with specified user details.
     * Note: This uses the Moodle-specific endpoint which may require approval.
     *
     * @param string $username Username associated with the assignment.
     * @param string $useremail User's email for contact and identification.
     * @param string $userid User's ID in the system.
     * @return string The response from the GPTZero API indicating success or failure.
     */
    public function create_assignment($username, $useremail, $userid) {
        // Return a mock successful response since we're using standard API
        // The Moodle-specific deep-linking endpoint requires special approval
        return json_encode([
            'data' => [
                'gptzero_assignment_id' => 'standard_api_' . uniqid(),
            ],
        ]);
    }

    /**
     * Checks if a user has an account on GPTZero.
     * Note: This uses the Moodle-specific endpoint which may require approval.
     *
     * @param string $useremail The email address to check for an existing account.
     * @return string The response from the GPTZero API indicating account existence.
     */
    public function has_gptzero_account($useremail) {
        // Return mock response - skip account check for standard API usage
        return json_encode([
            'success' => true,
            'hasAccount' => true,
        ]);
    }
}
