<?php

namespace Langsys\SDK\Tests\Mock;

use Langsys\SDK\Config;
use Langsys\SDK\Http\HttpClient;

/**
 * Mock HTTP client for testing without making real API calls.
 */
class MockHttpClient extends HttpClient
{
    /**
     * @var array Queued responses keyed by "METHOD:endpoint"
     */
    protected $responses = [];

    /**
     * @var array Default responses for any endpoint
     */
    protected $defaultResponses = [];

    /**
     * @var array Recorded requests
     */
    protected $requests = [];

    /**
     * Create a mock HTTP client.
     *
     * @param Config|null $config
     */
    public function __construct($config = null)
    {
        if ($config === null) {
            $config = new Config([
                'api_key' => 'test-api-key',
                'project_id' => 'test-project-id',
            ]);
        }
        parent::__construct($config);
    }

    /**
     * Queue a response for a specific endpoint.
     *
     * @param string $method HTTP method (GET, POST)
     * @param string $endpoint Endpoint pattern (can include wildcards)
     * @param array $response Response data
     * @return $this
     */
    public function setResponse($method, $endpoint, array $response)
    {
        $key = strtoupper($method) . ':' . $endpoint;
        $this->responses[$key] = $response;
        return $this;
    }

    /**
     * Set a default response for any unmatched endpoint.
     *
     * @param string $method HTTP method
     * @param array $response Response data
     * @return $this
     */
    public function setDefaultResponse($method, array $response)
    {
        $this->defaultResponses[strtoupper($method)] = $response;
        return $this;
    }

    /**
     * Get all recorded requests.
     *
     * @return array
     */
    public function getRequests()
    {
        return $this->requests;
    }

    /**
     * Get the last request made.
     *
     * @return array|null
     */
    public function getLastRequest()
    {
        if (empty($this->requests)) {
            return null;
        }
        return $this->requests[count($this->requests) - 1];
    }

    /**
     * Clear all recorded requests.
     *
     * @return $this
     */
    public function clearRequests()
    {
        $this->requests = [];
        return $this;
    }

    /**
     * Override GET request to return mocked response.
     *
     * @param string $endpoint
     * @param array $params
     * @return array
     */
    public function get($endpoint, array $params = [])
    {
        $this->requests[] = [
            'method' => 'GET',
            'endpoint' => $endpoint,
            'params' => $params,
        ];

        return $this->findResponse('GET', $endpoint);
    }

    /**
     * Override POST request to return mocked response.
     *
     * @param string $endpoint
     * @param array $data
     * @return array
     */
    public function post($endpoint, array $data = [])
    {
        $this->requests[] = [
            'method' => 'POST',
            'endpoint' => $endpoint,
            'data' => $data,
        ];

        return $this->findResponse('POST', $endpoint);
    }

    /**
     * Find a matching response for the endpoint.
     *
     * @param string $method
     * @param string $endpoint
     * @return array
     */
    protected function findResponse($method, $endpoint)
    {
        $method = strtoupper($method);

        // Try exact match first
        $key = $method . ':' . $endpoint;
        if (isset($this->responses[$key])) {
            return $this->responses[$key];
        }

        // Try pattern matching (for endpoints with IDs)
        foreach ($this->responses as $pattern => $response) {
            if (strpos($pattern, $method . ':') !== 0) {
                continue;
            }

            $patternEndpoint = substr($pattern, strlen($method) + 1);

            // Convert pattern to regex (replace * with .*)
            $regex = '/^' . str_replace(['/', '*'], ['\/', '.*'], $patternEndpoint) . '$/';
            if (preg_match($regex, $endpoint)) {
                return $response;
            }
        }

        // Return default response for method if set
        if (isset($this->defaultResponses[$method])) {
            return $this->defaultResponses[$method];
        }

        // Return empty success response
        return ['status' => true, 'data' => []];
    }
}
