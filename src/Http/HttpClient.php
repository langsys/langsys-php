<?php

namespace Langsys\SDK\Http;

use Langsys\SDK\Config;
use Langsys\SDK\Exception\ApiException;
use Langsys\SDK\Exception\AuthenticationException;
use Langsys\SDK\Exception\ValidationException;
use Langsys\SDK\Log\LoggerInterface;
use Langsys\SDK\Log\NullLogger;

/**
 * cURL-based HTTP client for the Langsys API.
 * Compatible with PHP 5.6 through 8.4+.
 */
class HttpClient
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var int
     */
    protected $timeout = 30;

    /**
     * Create a new HttpClient instance.
     *
     * @param Config $config
     * @param LoggerInterface $logger
     */
    public function __construct(Config $config, $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger !== null ? $logger : new NullLogger();
    }

    /**
     * Set the request timeout.
     *
     * @param int $timeout
     * @return $this
     */
    public function setTimeout($timeout)
    {
        $this->timeout = (int) $timeout;
        return $this;
    }

    /**
     * Make a GET request.
     *
     * @param string $endpoint
     * @param array $params
     * @return array
     * @throws ApiException
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function get($endpoint, array $params = [])
    {
        $url = $this->buildUrl($endpoint, $params);
        return $this->request('GET', $url);
    }

    /**
     * Make a POST request.
     *
     * @param string $endpoint
     * @param array $data
     * @return array
     * @throws ApiException
     * @throws AuthenticationException
     * @throws ValidationException
     */
    public function post($endpoint, array $data = [])
    {
        $url = $this->buildUrl($endpoint);
        return $this->request('POST', $url, $data);
    }

    /**
     * Build the full URL for an endpoint.
     *
     * @param string $endpoint
     * @param array $params
     * @return string
     */
    protected function buildUrl($endpoint, array $params = [])
    {
        $url = $this->config->getApiUrl() . '/' . ltrim($endpoint, '/');

        if (!empty($params)) {
            $url .= '?' . http_build_query($params);
        }

        return $url;
    }

    /**
     * Get the default headers for requests.
     *
     * @return array
     */
    protected function getHeaders()
    {
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json',
        ];

        if ($this->config->hasApiKey()) {
            $headers[] = 'X-Authorization: ' . $this->config->getApiKey();
        }

        return $headers;
    }

    /**
     * Execute an HTTP request.
     *
     * @param string $method
     * @param string $url
     * @param array|null $data
     * @return array
     * @throws ApiException
     * @throws AuthenticationException
     * @throws ValidationException
     */
    protected function request($method, $url, $data = null)
    {
        $startTime = microtime(true);

        // Encode POST body and track size
        $postBody = null;
        $postBodySize = 0;
        if ($method === 'POST' && $data !== null) {
            $postBody = json_encode($data);
            $postBodySize = strlen($postBody);
        }

        $logContext = [
            'method' => $method,
            'url' => $url,
        ];
        if ($postBodySize > 0) {
            $logContext['request_body_size'] = $postBodySize;
        }

        $this->logger->debug('API request starting', $logContext);

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->getHeaders());
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($postBody !== null) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
            }
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        $errno = curl_errno($ch);

        curl_close($ch);

        $durationMs = round((microtime(true) - $startTime) * 1000, 2);

        if ($errno) {
            $errorContext = [
                'method' => $method,
                'url' => $url,
                'error' => $error,
                'errno' => $errno,
                'http_code' => $httpCode,
                'duration_ms' => $durationMs,
            ];
            if ($postBody !== null) {
                $errorContext['payload'] = $postBody;
            }
            $this->logger->error('API request failed', $errorContext);
            throw new ApiException('cURL error: ' . $error, $errno);
        }

        // Log based on status code
        if ($httpCode >= 400) {
            $errorContext = [
                'method' => $method,
                'url' => $url,
                'status_code' => $httpCode,
                'duration_ms' => $durationMs,
                'response_body' => $this->truncateResponseBody($response),
            ];
            if ($postBody !== null) {
                $errorContext['payload'] = $postBody;
            }
            $this->logger->error('API request error', $errorContext);
        } elseif ($httpCode >= 300) {
            $this->logger->warning('API request redirect', [
                'method' => $method,
                'url' => $url,
                'status_code' => $httpCode,
                'duration_ms' => $durationMs,
            ]);
        } else {
            $this->logger->info('API request completed', [
                'method' => $method,
                'url' => $url,
                'status_code' => $httpCode,
                'duration_ms' => $durationMs,
            ]);
        }

        return $this->handleResponse($response, $httpCode);
    }

    /**
     * Truncate response body for logging.
     *
     * @param string $body
     * @param int $maxLength
     * @return string
     */
    protected function truncateResponseBody($body, $maxLength = 1000)
    {
        if ($body === false || $body === null) {
            return '';
        }
        if (strlen($body) <= $maxLength) {
            return $body;
        }
        return substr($body, 0, $maxLength) . '... (truncated)';
    }

    /**
     * Handle the API response.
     *
     * @param string $response
     * @param int $httpCode
     * @return array
     * @throws ApiException
     * @throws AuthenticationException
     * @throws ValidationException
     */
    protected function handleResponse($response, $httpCode)
    {
        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ApiException(
                'Failed to parse JSON response: ' . json_last_error_msg(),
                $httpCode
            );
        }

        if ($httpCode === 401) {
            $message = isset($data['error']) ? $data['error'] : 'Unauthorized';
            throw new AuthenticationException($message, $data);
        }

        if ($httpCode === 422) {
            $message = isset($data['error']) ? $data['error'] : 'Validation failed';
            $errors = isset($data['errors']) ? $data['errors'] : [];
            throw new ValidationException($message, $errors, $data);
        }

        if ($httpCode >= 400) {
            $message = isset($data['error']) ? $data['error'] : 'API error';
            throw new ApiException($message, $httpCode, $data);
        }

        return $data;
    }
}
