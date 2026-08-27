<?php
/**
 * Opace AI Hub Library - HTTP Client
 * 
 * Abstraction layer for HTTP communication with AI providers
 * Handles common functionality like headers, timeouts, and error handling
 * 
 * @package AI_Core
 * @version 1.0.16
 */

namespace AICore\Http;

class HttpClient {
    
    /**
     * Default timeout for requests (seconds)
     */
    const DEFAULT_TIMEOUT = 120;
    
    /**
     * Send POST request to API endpoint
     * 
     * @param string $url API endpoint URL
     * @param array $data Request payload
     * @param array $headers HTTP headers
     * @param int $timeout Request timeout in seconds
     * @return array Response data
     * @throws \Exception On HTTP errors or invalid responses
     */
    public static function post(string $url, array $data, array $headers = [], int $timeout = self::DEFAULT_TIMEOUT): array {
        
        // Prepare request arguments for wp_remote_post
        $args = [
            'method' => 'POST',
            'timeout' => $timeout,
            'headers' => array_merge([
                'Content-Type' => 'application/json',
                'User-Agent' => 'AI-Scribe/' . self::getVersion()
            ], $headers),
            'body' => json_encode($data),
            'sslverify' => true
        ];
        
        // Send request using WordPress HTTP API
        $response = wp_remote_post($url, $args);
        if (self::isConnectionTimeout($response)) {
            // Requests has a separate 10-second connection timeout even when
            // WordPress's overall request timeout is much longer. A connect
            // timeout means no HTTP request reached the provider, so one retry
            // is safe and cannot duplicate a billable generation.
            $response = wp_remote_post($url, $args);
        }
        
        // Check for WordPress HTTP errors
        if (is_wp_error($response)) {
            throw new \Exception(\esc_html('HTTP Request failed: ' . $response->get_error_message()));
        }
        
        // Get response code and body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Check for HTTP error codes
        if ($response_code < 200 || $response_code >= 300) {
            $description = self::describeErrorBody($response_body);
            if (self::isAuthFailure($response_code, $response_body)) {
                $description = 'The API key was rejected by the provider. Check the key on the Opace AI Hub settings screen. (' . $description . ')';
            }
            throw new \Exception(\esc_html("HTTP {$response_code}: " . $description));
        }
        
        // Decode JSON response
        $decoded_response = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(\esc_html('Invalid JSON response: ' . json_last_error_msg()));
        }
        
        return $decoded_response;
    }
    
    /**
     * Send GET request to API endpoint
     *
     * @param string $url API endpoint URL
     * @param array $params Query parameters
     * @param array $headers HTTP headers
     * @param int $timeout Request timeout in seconds
     * @return array Response data
     * @throws \Exception On HTTP errors or invalid responses
     */
    public static function get(string $url, array $params = [], array $headers = [], int $timeout = self::DEFAULT_TIMEOUT): array {
        
        // Prepare request arguments for wp_remote_get
        $args = [
            'timeout' => $timeout,
            'headers' => array_merge([
                'User-Agent' => 'AI-Scribe/' . self::getVersion()
            ], $headers),
            'sslverify' => true
        ];
        
        // Send request using WordPress HTTP API
        $response = wp_remote_get($url, $args);
        if (self::isConnectionTimeout($response)) {
            $response = wp_remote_get($url, $args);
        }
        
        // Check for WordPress HTTP errors
        if (is_wp_error($response)) {
            throw new \Exception(\esc_html('HTTP Request failed: ' . $response->get_error_message()));
        }
        
        // Get response code and body
        $response_code = wp_remote_retrieve_response_code($response);
        $response_body = wp_remote_retrieve_body($response);
        
        // Check for HTTP error codes
        if ($response_code < 200 || $response_code >= 300) {
            $description = self::describeErrorBody($response_body);
            if (self::isAuthFailure($response_code, $response_body)) {
                $description = 'The API key was rejected by the provider. Check the key on the Opace AI Hub settings screen. (' . $description . ')';
            }
            throw new \Exception(\esc_html("HTTP {$response_code}: " . $description));
        }
        
        // Decode JSON response
        $decoded_response = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Exception(\esc_html('Invalid JSON response: ' . json_last_error_msg()));
        }
        
        return $decoded_response;
    }
    
    /**
     * Whether Requests failed before an HTTP connection was established.
     *
     * Do not retry generic operation/read timeouts: the provider may already
     * have received those requests. Only the explicit connection-timeout
     * wording is safe to repeat.
     *
     * @param mixed $response WordPress HTTP response.
     * @return bool
     */
    private static function isConnectionTimeout($response): bool {
        return is_wp_error($response)
            && false !== stripos($response->get_error_message(), 'connection timed out');
    }

    /**
     * Is this error response an authentication failure?
     *
     * A rejected key deserves a message about the key, not about whatever
     * field the provider happened to complain about. OpenAI and Anthropic
     * answer 401, but Google rejects a bad key with a plain
     * 400 INVALID_ARGUMENT whose only tells are the API_KEY_INVALID reason
     * and the "API key not valid" message, so a 400 is inspected for those
     * markers rather than trusted as a payload problem.
     *
     * @param int    $code Response status code.
     * @param string $body Raw response body.
     * @return bool
     */
    private static function isAuthFailure(int $code, string $body): bool {
        if ($code === 401 || $code === 403) {
            return true;
        }

        if ($code !== 400) {
            return false;
        }

        return (bool) preg_match(
            '/API_KEY_INVALID|API key not valid|invalid[ _-]api[ _-]key|authentication[ _-]error|invalid x-api-key/i',
            $body
        );
    }

    /**
     * Render a provider error body as a single readable line.
     *
     * Every provider Opace AI Hub talks to nests the human-readable cause under
     * an "error" object, and OpenAI additionally names the offending field
     * in error.param with a machine code in error.code. Those two carry the
     * whole story for a rejected parameter, so they are kept rather than
     * discarded — a bare status code leaves the user with nothing to act on.
     *
     * @param string $body Raw response body.
     * @return string
     */
    private static function describeErrorBody(string $body): string {
        $decoded = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($decoded['error'])) {
            return $body === '' ? 'no response body' : substr($body, 0, 300);
        }

        $error = $decoded['error'];

        if (!\is_array($error)) {
            return (string) $error;
        }

        $message = $error['message'] ?? substr($body, 0, 300);

        $detail = [];
        if (!empty($error['param'])) {
            $detail[] = 'parameter: ' . $error['param'];
        }
        if (!empty($error['code']) && \is_string($error['code'])) {
            $detail[] = 'code: ' . $error['code'];
        }

        return $detail ? $message . ' [' . implode(', ', $detail) . ']' : $message;
    }

    /**
     * Get Opace AI Hub library version
     * 
     * @return string Version string
     */
    private static function getVersion(): string {
        return defined('AI_CORE_VERSION') ? AI_CORE_VERSION : '1.0.16';
    }
    
    /**
     * Validate URL format
     * 
     * @param string $url URL to validate
     * @return bool True if URL is valid
     */
    public static function isValidUrl(string $url): bool {
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }
    
    /**
     * Build query string from array
     * 
     * @param array $params Query parameters
     * @return string Query string
     */
    public static function buildQueryString(array $params): string {
        return http_build_query($params);
    }
}
