<?php

namespace Langsys\SDK\Tests\Support;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Tests\Mock\MockHttpClient;

/**
 * A Client whose every HTTP call lands on one MockHttpClient, with a write key
 * and an en-us base locale unless a test says otherwise.
 */
trait BuildsMockClient
{
    /** @var MockHttpClient */
    protected $http;

    protected function mockClient(array $catalog, array $options = [], array $auth = null, MockHttpClient $http = null)
    {
        $this->http = $http ?: new MockHttpClient();
        $this->http->setResponse('GET', 'authorize-project/project-id', [
            'data' => $auth !== null ? $auth : ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us'],
        ]);
        $this->http->setResponse('GET', 'translations', ['data' => $catalog]);
        $this->http->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = new Client('test-api-key', 'project-id', array_merge(['cache' => new NullCache()], $options));
        $reflection = new \ReflectionClass($client);

        $property = $reflection->getProperty('http');
        $property->setAccessible(true);
        $property->setValue($client, $this->http);

        foreach (['translations', 'translatableItems'] as $name) {
            $resource = $reflection->getProperty($name);
            $resource->setAccessible(true);
            $object = $resource->getValue($client);
            $inner = (new \ReflectionClass($object))->getProperty('http');
            $inner->setAccessible(true);
            $inner->setValue($object, $this->http);
        }

        return $client;
    }

    /**
     * Locales sent on the catalog GETs, in order.
     *
     * @return string[]
     */
    protected function catalogLocalesRequested()
    {
        $locales = [];

        foreach ($this->http->getRequests() as $request) {
            if ($request['method'] === 'GET' && strpos($request['endpoint'], 'translations') === 0) {
                $locales[] = isset($request['params']['locale']) ? $request['params']['locale'] : null;
            }
        }

        return $locales;
    }

    /**
     * What was registered, as [kind, text-or-phrases] pairs.
     *
     * @return array
     */
    protected function registered()
    {
        $items = [];

        foreach ($this->http->getRequests() as $request) {
            if ($request['method'] !== 'POST') {
                continue;
            }

            foreach ($request['data']['translatable_items'] as $item) {
                $items[] = isset($item['phrases'])
                    ? ['block', array_column($item['phrases'], 'phrase')]
                    : ['phrase', $item['phrase']];
            }
        }

        return $items;
    }

    protected static function body($html)
    {
        return preg_match('#<body>(.*)</body>#s', $html, $match) ? trim($match[1]) : trim($html);
    }
}
