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
 * Pearl LMS API client.
 *
 * @package   local_pendingregistration
 * @copyright 2026 Integer Training
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_pendingregistration;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/filelib.php');

class api_client {

    /** @var string API base URL */
    private $apiurl;
    /** @var string API key */
    private $apikey;
    /** @var string Bearer token */
    private $apitoken;

    public function __construct() {
        $this->apiurl = get_config('local_pendingregistration', 'apiurl');
        $this->apikey = get_config('local_pendingregistration', 'apikey');
        $this->apitoken = get_config('local_pendingregistration', 'apitoken');
    }

    /**
     * Check if API credentials are configured.
     * @return bool
     */
    public function is_configured(): bool {
        return !empty($this->apiurl) && !empty($this->apikey) && !empty($this->apitoken);
    }

    /**
     * Look up a learner by email from the Pearl LMS API.
     *
     * @param string $email
     * @return object|null Parsed learner data or null on failure/not found.
     */
    public function get_learner(string $email): ?object {
        if (!$this->is_configured()) {
            return null;
        }

        $url = $this->apiurl . '?email=' . urlencode($email);

        $curl = new \curl();
        $curl->setHeader([
            'x-api-key: ' . $this->apikey,
            'Authorization: Bearer ' . $this->apitoken,
            'Content-Type: application/json',
        ]);

        $response = $curl->get($url);
        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if ($httpcode !== 200 || empty($response)) {
            return null;
        }

        $data = json_decode($response);
        if (!$data || empty($data->success) || empty($data->learner)) {
            return null;
        }

        return $data->learner;
    }

    /**
     * Check if a learner meets the pending registration criteria.
     *
     * @param object $learner API learner object.
     * @return bool
     */
    public function meets_criteria(object $learner): bool {
        // Must have payment data.
        if (empty($learner->payment) || empty($learner->stripe)) {
            return false;
        }

        // paid_to_date > 500.
        $paid = (float) ($learner->payment->paid_to_date ?? 0);
        if ($paid <= 500) {
            return false;
        }

        // subscription_status is "active" or "trialing".
        $substatus = strtolower($learner->stripe->subscription_status ?? '');
        if (!in_array($substatus, ['active', 'trialing'])) {
            return false;
        }

        // Status is "Active".
        $status = $learner->status ?? '';
        if (strcasecmp($status, 'Active') !== 0) {
            return false;
        }

        return true;
    }
}
