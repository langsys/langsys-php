<?php

namespace Langsys\SDK\Tests\Mock;

use Langsys\SDK\Config;
use Langsys\SDK\Http\HttpClient;

/**
 * An HTTP client that fails with an \Error rather than an \Exception.
 *
 * Every degradation seam in this SDK catches \Throwable on purpose: an
 * \Exception-only catch is an enumeration of the failures we thought of, and
 * the ones that actually reached production were \Errors - a TypeError from a
 * wrong-shaped cache hit, a ValueError from a bad batch limit. But a seam
 * written as \Throwable and only ever exercised with an \Exception is not
 * PROVEN to be \Throwable: narrowing it back leaves the suite green, so nothing
 * stops the next edit from narrowing it.
 *
 * This is the double that makes those seams falsifiable. A TypeError is used
 * because it is what the real incidents raised.
 */
class ErrorThrowingHttpClient extends HttpClient
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
        throw new \TypeError('an \Error, not an \Exception, from ' . $endpoint);
    }

    public function post($endpoint, array $data = [])
    {
        throw new \TypeError('an \Error, not an \Exception, from ' . $endpoint);
    }
}
