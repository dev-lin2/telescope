<?php

use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;

if (! function_exists('telescope_curl_exec')) {
    /**
     * Execute a curl handle and record the request/response in Telescope.
     * Drop-in replacement for curl_exec().
     *
     * @param  resource  $ch
     * @return string|bool
     */
    function telescope_curl_exec($ch)
    {
        $startTime = microtime(true);

        // Capture info before execution
        $url = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);

        $response = curl_exec($ch);

        $duration = round((microtime(true) - $startTime) * 1000, 2);
        $info = curl_getinfo($ch);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        // Determine HTTP method from curl info
        $method = 'GET';
        if (!empty($info['request_header'])) {
            if (preg_match('/^(\w+)\s/', $info['request_header'], $matches)) {
                $method = strtoupper($matches[1]);
            }
        } elseif ($info['http_code'] > 0) {
            // Fallback: check common curl options patterns
            $method = isset($info['redirect_url']) && $info['redirect_url'] ? 'POST' : 'GET';
        }

        // Try to record in Telescope
        try {
            if (class_exists(Telescope::class) && Telescope::isRecording()) {
                $responseBody = is_string($response) ? $response : '';
                $responseData = json_decode($responseBody, true);

                // Truncate large responses
                $sizeLimit = config('telescope.watchers.' . \Laravel\Telescope\Watchers\ClientRequestWatcher::class . '.size_limit', 64);
                if (mb_strlen($responseBody) / 1000 > $sizeLimit) {
                    $responseData = 'Purged By Telescope (curl response too large)';
                } elseif (is_array($responseData)) {
                    // Keep as decoded JSON
                } elseif (is_string($response) && strlen($response) > 0) {
                    $responseData = mb_substr($responseBody, 0, $sizeLimit * 1000);
                } else {
                    $responseData = $error ?: 'Empty Response';
                }

                $domain = parse_url($url, PHP_URL_HOST) ?: 'unknown';

                Telescope::recordClientRequest(IncomingEntry::make([
                    'method' => $method,
                    'uri' => $url,
                    'headers' => [],
                    'payload' => [],
                    'response_status' => $info['http_code'] ?? 0,
                    'response_headers' => [
                        'content-type' => $info['content_type'] ?? '',
                    ],
                    'response' => $responseData,
                    'duration' => $duration,
                    'curl_error' => $error ?: null,
                    'curl_errno' => $errno ?: null,
                ])->tags(['curl', $domain]));
            }
        } catch (\Throwable $e) {
            // Silently fail - never break the application for logging
        }

        return $response;
    }
}

if (! function_exists('telescope_record_http')) {
    /**
     * Manually record an HTTP client request in Telescope.
     * Use this for edge cases where telescope_curl_exec() can't be used.
     *
     * @param  string  $method
     * @param  string  $url
     * @param  mixed   $payload
     * @param  mixed   $response
     * @param  float   $duration  Duration in milliseconds
     * @param  int     $statusCode
     * @param  array   $headers
     * @return void
     */
    function telescope_record_http($method, $url, $payload = [], $response = null, $duration = 0, $statusCode = 0, $headers = [])
    {
        try {
            if (class_exists(Telescope::class) && Telescope::isRecording()) {
                $domain = parse_url($url, PHP_URL_HOST) ?: 'unknown';

                $responseData = $response;
                if (is_string($response)) {
                    $decoded = json_decode($response, true);
                    if (json_last_error() === JSON_ERROR_NONE) {
                        $responseData = $decoded;
                    }
                }

                Telescope::recordClientRequest(IncomingEntry::make([
                    'method' => strtoupper($method),
                    'uri' => $url,
                    'headers' => $headers,
                    'payload' => is_array($payload) ? $payload : [],
                    'response_status' => $statusCode,
                    'response_headers' => [],
                    'response' => $responseData,
                    'duration' => $duration,
                ])->tags(['curl', $domain]));
            }
        } catch (\Throwable $e) {
            // Silently fail
        }
    }
}
