<?php

namespace Langsys\SDK\Tests\Mock;

use Langsys\SDK\Config;
use Langsys\SDK\Exception\ApiException;
use Langsys\SDK\Http\HttpClient;

/**
 * An HTTP client standing in for the API being unreachable.
 *
 * Used to prove the SDK degrades rather than throwing into a caller's render
 * path when its own dependency is down.
 */
class ThrowingHttpClient extends HttpClient
{
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

    public function get($endpoint, array $params = [])
    {
        throw new ApiException('cURL error: Could not resolve host', 6);
    }

    public function post($endpoint, array $data = [])
    {
        throw new ApiException('cURL error: Could not resolve host', 6);
    }
}
