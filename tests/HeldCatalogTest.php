<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Client;
use Langsys\SDK\Exception\ApiException;
use Langsys\SDK\Exception\AuthenticationException;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * CACHE-2: a catalog already held keeps serving. A catalog in the persisted
 * cache renders on every read path while authorization is refused or the
 * API cannot be reached, and the failure never replaces it.
 */
class HeldCatalogTest extends TestCase
{
    /** @var string */
    private $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/langsys-held-catalog-' . getmypid() . '-' . mt_rand();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->dir);
    }

    public function refusalProvider()
    {
        return [
            'authorization refused' => [new AuthenticationException('Invalid API key', 401)],
            'API unreachable' => [new ApiException('Could not connect', 0)],
        ];
    }

    /**
     * @dataProvider refusalProvider
     */
    public function testACachedCatalogKeepsServingWhenTheApiRefuses(\Exception $refusal): void
    {
        $catalog = ['UI' => ['Hello' => 'Hola', 'Read our {m0o}terms{m0c}' => 'Lee {m0o}los términos{m0c}']];
        $cache = new FileCache($this->dir);
        $cache->set('translations_project-id_es-es', $catalog, 3600);

        $http = new class extends MockHttpClient {
            /** @var \Exception */
            public $refusal;

            public function get($endpoint, array $params = [])
            {
                parent::get($endpoint, $params);
                throw $this->refusal;
            }

            public function post($endpoint, array $data = [])
            {
                parent::post($endpoint, $data);
                throw $this->refusal;
            }
        };
        $http->refusal = $refusal;

        $client = new Client('test-api-key', 'project-id', ['cache' => $cache, 'error_log' => false]);
        $reflection = new \ReflectionClass($client);
        foreach (['http', 'translations', 'translatableItems'] as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            if ($property === 'http') {
                $prop->setValue($client, $http);
                continue;
            }
            $resource = $prop->getValue($client);
            $inner = (new \ReflectionClass($resource))->getProperty('http');
            $inner->setAccessible(true);
            $inner->setValue($resource, $http);
        }
        $client->setLocale('es-es');

        $this->assertSame('Hola', $client->translate('Hello', null, 'UI'));
        $this->assertSame('Lee <a href="/terms">los términos</a>', $client->translateRich('Read our <a href="/terms">terms</a>', null, 'UI')['html']);
        $this->assertSame('<p>Hola</p>', $client->translateContentBlock('<p>Hello</p>', 'UI'));
        $this->assertStringContainsString('<p>Hola</p>', $client->translatePage('<html><body><p>Hello</p></body></html>', 'UI'));

        $this->assertNotSame([], $http->getRequests(), 'the API was asked and refused');
        $this->assertSame($catalog, $cache->get('translations_project-id_es-es'), 'the failure never replaces what is held');
    }
}
