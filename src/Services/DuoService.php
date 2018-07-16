<?php

namespace Laramie\Services;

use Exception;
use GuzzleHttp\Client;

class DuoService
{
    private $integrationKey;
    private $secretKey;
    private $apiHostname;

    private $serviceAvailable = true;

    /**
     * Create a new DuoService.
     *
     * @param string $integrationKey
     * @param string $integrationKey
     * @param string $integrationKey
     *
     * @return DuoService
     */
    public function __construct($integrationKey, $secretKey, $apiHostname)
    {
        $this->integrationKey = $integrationKey;
        $this->secretKey = $secretKey;
        $this->apiHostname = $apiHostname;
        $this->client = new Client([
            'base_uri' => 'https://'.$apiHostname,
        ]);

        //$serviceAvailable = object_get($this->ping(), 'stat') == 'OK';
        //if (!$serviceAvailable) {
            //throw new Exception('Duo service currently not available');
        //}

        //$duoSettingsOk = object_get($this->checkSettings(), 'stat') == 'OK';
        //if (!$serviceAvailable) {
            //throw new Exception('Issue with DUO settings');
        //}
    }

    /**
     * Check to see if the service is available.
     *
     * @return \Illuminate\Http\Response
     */
    protected function ping()
    {
        return $this->getResponseData($this->client->request('GET', '/auth/v2/ping'));
    }

    /**
     * Check to see if the credentials provided successfully connect to the Duo API.
     *
     * @return \Illuminate\Http\Response
     */
    protected function checkSettings()
    {
        return $this->makeAuthorizedRequest('/auth/v2/check', [], 'GET');
    }

    /**
     * Check to see if the credentials provided successfully connect to the Duo API.
     *
     * @param string $username Username you would like to enroll in Duo
     *
     * @return \Illuminate\Http\Response
     */
    public function register($username)
    {
        return $this->makeAuthorizedRequest('/auth/v2/enroll');
    }

    public function preAuthenitcate($duoUserId)
    {
        return $this->makeAuthorizedRequest('/auth/v2/preauth', ['user_id' => $duoUserId]);
    }

    public function authenticate($duoUserId, $passcode)
    {
        $params = ['user_id' => $duoUserId, 'device' => 'auto'];

        if (trim(strtolower($passcode)) == 'push') {
            $params['factor'] = 'push';
        } else {
            $params['factor'] = 'passcode';
            $params['passcode'] = $passcode;
        }

        return $this->makeAuthorizedRequest('/auth/v2/auth', $params);
    }

    private function makeAuthorizedRequest($path, $params = [], $method = 'POST')
    {
        $headers = $this->getAuthHeaders($path, $params, $method);

        $data = ['headers' => $headers];

        if (count($params)) {
            $data['form_params'] = $params;
        }

        return $this->getResponseData($this->client->request($method, $path, $data));
    }

    private function getResponseData($response)
    {
        return json_decode($response->getBody());
    }

    private function getAuthHeaders($path, $params = [], $method = 'POST')
    {
        $date = \Carbon\Carbon::now()->toRfc2822String(); // The current time, formatted as RFC 2822. This must be the same string as the "Date" header.

        $signature = sprintf(
            "%s\n%s\n%s\n%s\n%s",
            $date,
            strtoupper($method), // The HTTP method (uppercase)
            strtolower($this->apiHostname), // Your API hostname (lowercase)
            $path, // The specific API method's path
            $params = collect($params) // See note below
                ->map(function ($item, $key) {
                    return sprintf('%s=%s', rawurlencode($key), rawurlencode($item));
                })
                ->sort()
                ->values()
                ->implode('&')
        );

        return [
            'Date' => $date,
            'Authorization' => 'Basic '.base64_encode(sprintf('%s:%s', $this->integrationKey, hash_hmac('sha1', $signature, $this->secretKey))),
        ];

        // Regarding `$params`:
        // The URL-encoded list of key=value pairs, lexicographically sorted by key.
        // These come from the request parameters (the URL query string for GET and DELETE
        // requests or the request body for POST requests). If the request does not have
        // any parameters one must still include a blank line in the string that is
        // signed. Do not encode unreserved characters. Use upper-case hexadecimal digits
        // A through F in escape sequences.
    }
}
