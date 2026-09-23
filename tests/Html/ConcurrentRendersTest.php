<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Client;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * SRV-2: concurrent requests in different locales never observe each other's
 * catalog.
 *
 * The renders are interleaved, not run in turn: each one suspends inside its
 * catalog fetch, so both are in flight at once in one process - the shape a
 * coroutine runtime produces. Each request has its own Client, as a request
 * scope gives it, and both share one persistent cache, since a cache is the
 * process-wide state a request-scoped catalog could still leak through.
 *
 * @requires PHP >= 8.1
 */
class ConcurrentRendersTest extends TestCase
{
    /**
     * @var string
     */
    private $cacheDir;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir() . '/langsys-srv2-' . bin2hex(random_bytes(6));
    }

    protected function tearDown(): void
    {
        foreach (glob($this->cacheDir . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->cacheDir);
    }

    public function testTwoInterleavedRendersEachServeOnlyTheirOwnLocale(): void
    {
        $events = [];
        $cache = new FileCache($this->cacheDir);

        $page = '<html><head><title>Welcome</title></head><body><p>Hello</p><p>Goodbye</p></body></html>';
        $catalogs = [
            'it-it' => ['__uncategorized__' => ['Welcome' => 'Benvenuto', 'Hello' => 'Ciao', 'Goodbye' => 'Arrivederci']],
            'de-de' => ['__uncategorized__' => ['Welcome' => 'Willkommen', 'Hello' => 'Hallo', 'Goodbye' => 'Auf Wiedersehen']],
        ];

        $render = function ($locale) use ($cache, $catalogs, $page, &$events) {
            return new \Fiber(function () use ($locale, $cache, $catalogs, $page, &$events) {
                $client = $this->clientSuspendingOnFetch($cache, $catalogs, $events);
                $client->setLocale($locale);

                return $client->translatePage($page);
            });
        };

        $it = $render('it-it');
        $de = $render('de-de');

        $it->start();
        $de->start();

        // The later request finishes first, then the earlier one resumes.
        $de->resume();
        $it->resume();

        // Harness control: both fetches were in flight before either returned,
        // so the renders really overlapped. Run in turn, this would read
        // fetch, return, fetch, return and the test would prove nothing.
        $this->assertSame(['fetch it-it', 'fetch de-de', 'return de-de', 'return it-it'], $events);

        $italian = $it->getReturn();
        $german = $de->getReturn();

        foreach (['Benvenuto', 'Ciao', 'Arrivederci', 'lang="it-it"'] as $expected) {
            $this->assertStringContainsString($expected, $italian);
        }
        foreach (['Benvenuto', 'Ciao', 'Arrivederci', 'lang="it-it"'] as $foreign) {
            $this->assertStringNotContainsString($foreign, $german, 'the German render carries Italian');
        }

        foreach (['Willkommen', 'Hallo', 'Auf Wiedersehen', 'lang="de-de"'] as $expected) {
            $this->assertStringContainsString($expected, $german);
        }
        foreach (['Willkommen', 'Hallo', 'Auf Wiedersehen', 'lang="de-de"'] as $foreign) {
            $this->assertStringNotContainsString($foreign, $italian, 'the Italian render carries German');
        }

        // The shared cache now holds both catalogs, the Italian one written
        // last. A later German request is served from it without a fetch, and
        // it is still German.
        $later = new \Fiber(function () use ($cache, $catalogs, $page, &$events) {
            $client = $this->clientSuspendingOnFetch($cache, $catalogs, $events);
            $client->setLocale('de-de');

            return $client->translatePage($page);
        });
        $later->start();

        $this->assertTrue($later->isTerminated(), 'the later request fetched instead of reading the shared cache');
        $this->assertStringContainsString('Hallo', $later->getReturn());
        $this->assertStringNotContainsString('Ciao', $later->getReturn());
    }

    /**
     * A Client whose catalog fetch suspends the running fiber and answers with
     * the catalog for the locale it asked for.
     */
    private function clientSuspendingOnFetch($cache, array $catalogs, array &$events)
    {
        $http = new class ($catalogs, $events) extends MockHttpClient {
            private $catalogs;
            private $events;

            public function __construct(array $catalogs, array &$events)
            {
                parent::__construct();
                $this->catalogs = $catalogs;
                $this->events = &$events;
            }

            public function get($endpoint, array $params = [])
            {
                if ($endpoint !== 'translations') {
                    return parent::get($endpoint, $params);
                }

                $locale = $params['locale'];
                $this->events[] = 'fetch ' . $locale;
                \Fiber::suspend();
                $this->events[] = 'return ' . $locale;

                return ['status' => true, 'data' => $this->catalogs[$locale]];
            }
        };

        $http->setResponse('GET', 'authorize-project/test-project-id', [
            'status' => true,
            'data' => ['key_type' => 'read', 'write_enabled' => false, 'base_locale' => 'en-us'],
        ]);

        $client = new Client('test-api-key', 'test-project-id', ['cache' => $cache]);

        $reflection = new \ReflectionClass($client);
        foreach (['http' => $client, 'translations' => null, 'translatableItems' => null] as $property => $unused) {
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

        return $client;
    }
}
