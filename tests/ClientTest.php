<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Client;
use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use Langsys\SDK\Tests\Mock\ThrowingHttpClient;
use Langsys\SDK\Html\HtmlParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the main Client class.
 *
 * Note: These tests require mocking the HTTP client, which is done by extending
 * the Client class to inject our mock. For more thorough testing, the Client class
 * could be refactored to accept an HTTP client via dependency injection.
 */
class ClientTest extends TestCase
{
    /**
     * @var array Environment variables to restore after tests
     */
    protected $originalEnv = [];

    protected function setUp(): void
    {
        $this->originalEnv = [
            'LANGSYS_API_KEY' => getenv('LANGSYS_API_KEY'),
            'LANGSYS_PROJECT_ID' => getenv('LANGSYS_PROJECT_ID'),
        ];

        // Clear env vars for clean tests
        putenv('LANGSYS_API_KEY');
        putenv('LANGSYS_PROJECT_ID');
    }

    protected function tearDown(): void
    {
        // Restore original env vars
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $value);
            }
        }
    }

    public function testConstructorWithOptions()
    {
        $client = new Client('test-api-key', 'test-project-id', [
            'cache' => new NullCache(),
        ]);

        $this->assertInstanceOf(Client::class, $client);
        $this->assertEquals('test-project-id', $client->getConfig()->getProjectId());
        $this->assertEquals('test-api-key', $client->getConfig()->getApiKey());
    }

    public function testConstructorWithEnvironmentVariables()
    {
        putenv('LANGSYS_API_KEY=env-api-key');
        putenv('LANGSYS_PROJECT_ID=env-project-id');

        $client = new Client(null, null, [
            'cache' => new NullCache(),
        ]);

        $this->assertEquals('env-api-key', $client->getConfig()->getApiKey());
        $this->assertEquals('env-project-id', $client->getConfig()->getProjectId());
    }

    public function testConstructorMissingApiKey()
    {
        $this->expectException(LangsysException::class);
        $this->expectExceptionMessage('API key is required');

        new Client(null, 'project-id');
    }

    public function testConstructorMissingProjectId()
    {
        $this->expectException(LangsysException::class);
        $this->expectExceptionMessage('Project ID is required');

        new Client('api-key', null);
    }

    public function testGetCacheReturnsCache()
    {
        $cache = new NullCache();
        $client = new Client('api-key', 'project-id', [
            'cache' => $cache,
        ]);

        $this->assertSame($cache, $client->getCache());
    }

    public function testGetConfigReturnsConfig()
    {
        $client = new Client('api-key', 'project-id', [
            'cache' => new NullCache(),
        ]);

        $config = $client->getConfig();

        $this->assertEquals('api-key', $config->getApiKey());
        $this->assertEquals('project-id', $config->getProjectId());
    }

    public function testTranslationsResource()
    {
        $client = new Client('api-key', 'project-id', [
            'cache' => new NullCache(),
        ]);

        $translations = $client->translations();

        $this->assertInstanceOf(\Langsys\SDK\Resources\Translations::class, $translations);
    }

    public function testTranslatableItemsResource()
    {
        $client = new Client('api-key', 'project-id', [
            'cache' => new NullCache(),
        ]);

        $items = $client->translatableItems();

        $this->assertInstanceOf(\Langsys\SDK\Resources\TranslatableItems::class, $items);
    }

    public function testUtilitiesResource()
    {
        $client = new Client('api-key', 'project-id', [
            'cache' => new NullCache(),
        ]);

        $utilities = $client->utilities();

        $this->assertInstanceOf(\Langsys\SDK\Resources\Utilities::class, $utilities);
    }

    public function testClearCacheSpecificLocale()
    {
        $client = new Client('api-key', 'project-id', [
            'cache_driver' => 'none',
        ]);

        // Should not throw
        $result = $client->clearCache('es-es');
        $this->assertTrue($result);
    }

    public function testClearCacheAll()
    {
        $client = new Client('api-key', 'project-id', [
            'cache_driver' => 'none',
        ]);

        // Should not throw
        $result = $client->clearCache();
        $this->assertTrue($result);
    }

    public function testCacheDriverNone()
    {
        $client = new Client('api-key', 'project-id', [
            'cache_driver' => 'none',
        ]);

        $this->assertInstanceOf(NullCache::class, $client->getCache());
    }

    public function testCacheDriverNull()
    {
        $client = new Client('api-key', 'project-id', [
            'cache_driver' => 'null',
        ]);

        $this->assertInstanceOf(NullCache::class, $client->getCache());
    }

    public function testCacheDriverFile()
    {
        $cachePath = sys_get_temp_dir() . '/langsys-test-' . uniqid();

        $client = new Client('api-key', 'project-id', [
            'cache_driver' => 'file',
            'cache_path' => $cachePath,
        ]);

        $this->assertInstanceOf(\Langsys\SDK\Cache\FileCache::class, $client->getCache());

        // Clean up
        if (is_dir($cachePath)) {
            rmdir($cachePath);
        }
    }

    public function testCustomCacheInstance()
    {
        $customCache = new NullCache();

        $client = new Client('api-key', 'project-id', [
            'cache' => $customCache,
        ]);

        $this->assertSame($customCache, $client->getCache());
    }

    // =========================================================================
    // translate() with queuing tests
    // =========================================================================

    public function testTranslateQueuesNewPhrase()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                '__uncategorized__' => [],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // Translate a phrase that doesn't exist
        $result = $client->translate('Hello');

        // Should return original (no translation)
        $this->assertEquals('Hello', $result);

        // Should be queued for registration
        $this->assertTrue($client->hasPendingRegistrations());
        $pending = $client->getPendingPhrases();
        $this->assertArrayHasKey('__uncategorized__::Hello', $pending);
    }

    public function testTranslateDoesNotQueueExistingPhrase()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                '__uncategorized__' => [
                    'Hello' => 'Hola',
                ],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // Translate a phrase that exists
        $result = $client->translate('Hello');

        // Should return translation
        $this->assertEquals('Hola', $result);

        // Should NOT be queued
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testTranslateExistingPhraseWithNullValueFallsBackToSource()
    {
        // The base locale (and not-yet-translated phrases) come back present in
        // the catalog but with a null value. translate() must return the source
        // phrase, not null, and must not queue it (it already exists).
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                'ProductCard' => [
                    'Based on {n} reviews' => null,
                ],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('en-us');

        $this->assertSame(
            'Based on 5 reviews',
            $client->translate('Based on {n} reviews', 'ProductCard', ['n' => 5])
        );
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testTranslateSamePhraseNotQueuedTwice()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                '__uncategorized__' => [],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // Translate same phrase twice
        $client->translate('Hello');
        $client->translate('Hello');

        // Should only be queued once
        $pending = $client->getPendingPhrases();
        $this->assertCount(1, $pending);
    }

    // =========================================================================
    // JS-parity tests: translate(phrase, category?, params?) + interpolation
    // =========================================================================

    public function testTranslateUsesCategoryAsSecondArgument()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                'UI' => ['Save' => 'Guardar'],
                '__uncategorized__' => [],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // Mirrors the JS SDKs: the 2nd positional argument is the category.
        $this->assertEquals('Guardar', $client->translate('Save', 'UI'));
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testTranslateInterpolatesParams()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                'UI' => ['Hello, {name}!' => 'Hola, {name}!'],
                '__uncategorized__' => [],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // t(phrase, category, params): the 3rd argument interpolates the translation.
        $this->assertEquals(
            'Hola, Sarah!',
            $client->translate('Hello, {name}!', 'UI', ['name' => 'Sarah'])
        );
    }

    public function testTranslateInterpolatesFallbackPhrase()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'translations', [
            'data' => ['__uncategorized__' => []],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // Missing phrase: returns the source phrase, still interpolated (JS parity).
        $this->assertEquals('Bye Sarah', $client->translate('Bye {name}', 'UI', ['name' => 'Sarah']));
    }

    public function testTranslateParamsOverloadWithoutCategory()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                '__uncategorized__' => ['Hi {name}' => 'Hola {name}'],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // JS overload t(phrase, params): an array in the category slot = params, no category.
        $this->assertEquals('Hola Mundo', $client->translate('Hi {name}', ['name' => 'Mundo']));
    }

    public function testTranslateRendersIcuPlural()
    {
        if (!class_exists('MessageFormatter')) {
            $this->markTestSkipped('ext-intl not available');
        }

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                'ProductCard' => [
                    'Based on {n} reviews' => '{n, plural, one {Based on # review} other {Based on # reviews}}',
                ],
                '__uncategorized__' => [],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('en-us');

        $this->assertEquals('Based on 1 review', $client->translate('Based on {n} reviews', 'ProductCard', ['n' => 1]));
        $this->assertEquals('Based on 5 reviews', $client->translate('Based on {n} reviews', 'ProductCard', ['n' => 5]));
    }

    public function testLookupContentReturnsContentBlockPhrase()
    {
        $mockHttp = new MockHttpClient();
        $customId = (new HtmlParser())->generateCustomId('__uncategorized__', ['Hello', 'World']);
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                '__uncategorized__' => [
                    $customId => ['Hello' => 'Hola', 'World' => 'Mundo'],
                ],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        $this->assertEquals('Hola', $client->lookupContent('__uncategorized__', $customId, 'Hello'));
        $this->assertNull($client->lookupContent('__uncategorized__', $customId, 'Missing'));
    }

    // =========================================================================
    // translateContentBlock() tests
    // =========================================================================

    public function testTranslateContentBlockWithTranslations()
    {
        $mockHttp = new MockHttpClient();

        // customId must match what the SDK generates for this block
        $customId = (new HtmlParser())->generateCustomId('__uncategorized__', ['Hello', 'World']);

        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                '__uncategorized__' => [
                    $customId => [
                        'Hello' => 'Hola',
                        'World' => 'Mundo',
                    ],
                ],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        $html = '<p>Hello</p><p>World</p>';
        $result = $client->translateContentBlock($html);

        // Should contain translated text
        $this->assertStringContainsString('Hola', $result);
        $this->assertStringContainsString('Mundo', $result);

        // Should NOT be queued (already exists)
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testTranslateContentBlockQueuesNewBlock()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                '__uncategorized__' => [],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        $html = '<p>Hello</p><p>World</p>';
        $result = $client->translateContentBlock($html);

        // Should return original HTML (no translations yet)
        $this->assertEquals($html, $result);

        // Should be queued for registration
        $this->assertTrue($client->hasPendingRegistrations());
        $pending = $client->getPendingContentBlocks();
        $this->assertCount(1, $pending);
    }

    public function testTranslateContentBlockWithCategory()
    {
        $mockHttp = new MockHttpClient();

        // customId must match what the SDK generates for this block
        $customId = (new HtmlParser())->generateCustomId('homepage', ['Hello']);

        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                'homepage' => [
                    $customId => [
                        'Hello' => 'Hola',
                    ],
                ],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        $html = '<p>Hello</p>';
        $result = $client->translateContentBlock($html, 'homepage');

        $this->assertStringContainsString('Hola', $result);
    }

    public function testTranslateContentBlockSameBlockNotQueuedTwice()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                '__uncategorized__' => [],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        $html = '<p>Hello</p>';

        // Translate same block twice
        $client->translateContentBlock($html);
        $client->translateContentBlock($html);

        // Should only be queued once
        $pending = $client->getPendingContentBlocks();
        $this->assertCount(1, $pending);
    }

    // =========================================================================
    // In-memory cache tests
    // =========================================================================

    public function testInMemoryCachePreventsRequeue()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                '__uncategorized__' => [],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // First translate - queues the phrase and adds to memory cache
        $client->translate('Hello');

        // Second translate - should find in memory cache, not re-queue
        $client->translate('Hello');

        // Only one pending phrase
        $this->assertCount(1, $client->getPendingPhrases());
    }

    public function testClearCacheClearsMemoryCache()
    {
        $mockHttp = new MockHttpClient();

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // First call - set response before call
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                '__uncategorized__' => ['Hello' => 'Hola'],
            ],
        ]);
        $client->getTranslations('es-es');

        // Clear cache
        $client->clearCache('es-es');

        // Update response for re-fetch
        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                '__uncategorized__' => ['Hello' => 'Hola Updated'],
            ],
        ]);

        // Next call should fetch from API again
        $translations = $client->getTranslations('es-es');
        $this->assertEquals('Hola Updated', $translations['__uncategorized__']['Hello']);
    }

    // =========================================================================
    // flushPendingRegistrations() tests
    // =========================================================================

    public function testFlushPendingRegistrationsWithPhrases()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', [
            'data' => ['__uncategorized__' => []],
        ]);
        $mockHttp->setResponse('POST', 'translatable-items', [
            'status' => true,
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // Queue some phrases
        $client->translate('Hello');
        $client->translate('World');

        $this->assertTrue($client->hasPendingRegistrations());

        // Flush
        $result = $client->flushPendingRegistrations();

        $this->assertEquals(2, $result['phrases']);
        $this->assertEquals(0, $result['content_blocks']);
        $this->assertTrue($result['success']);
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testFlushPendingRegistrationsWithReadOnlyKey()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'read', 'write_enabled' => false],
        ]);
        $mockHttp->setResponse('GET', 'translations', [
            'data' => ['__uncategorized__' => []],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // Queue a phrase
        $client->translate('Hello');

        // Flush - should silently skip (read-only key)
        $result = $client->flushPendingRegistrations();

        $this->assertEquals(0, $result['phrases']);
        $this->assertFalse($client->hasPendingRegistrations()); // Queue cleared
    }

    public function testClearPendingRegistrations()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'translations', [
            'data' => ['__uncategorized__' => []],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        $client->translate('Hello');
        $this->assertTrue($client->hasPendingRegistrations());

        $client->clearPendingRegistrations();
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testAuthorizeSyncsBatchLimitFromLangsysSettings()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => [
                'key_type' => 'write',
                'write_enabled' => true,
                'langsys_settings' => [
                    'translatable_items' => [
                        'batch_limit' => 50,
                    ],
                ],
            ],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->authorize();

        $this->assertEquals(50, $client->translatableItems()->getBatchLimit());
    }

    public function testAuthorizeFallsBackToDefaultBatchLimit()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->authorize();

        $this->assertEquals(200, $client->translatableItems()->getBatchLimit());
    }

    // =========================================================================
    // Write-gate tests
    // =========================================================================

    public function testCanWriteWithIpWriteKeyThatServerReportsWriteEnabled()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => true],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);

        $this->assertTrue($client->canWrite());
    }

    public function testCannotWriteWithIpWriteKeyThatServerReportsNotWriteEnabled()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => false],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);

        $this->assertFalse($client->canWrite());
    }

    /**
     * write_enabled is authoritative in both directions, on a fresh response.
     *
     * Scope, deliberately narrowed: since the decision is now short-circuited
     * from a cached key_type for 'read' and 'write' keys (so a warm cache costs
     * no round-trip), "the flag overrides key_type" is pinned on the
     * fresh-response path only - which is the only path that can carry a flag,
     * because it is never cached.
     *
     * No server condition produces this combination today - ApiKey::allowsWrite()
     * returns true unconditionally for a WRITE key, and a suspended subscription
     * short-circuits with a 402 before the flag is computed. This pins the
     * direction of trust rather than a live behaviour: if the server ever gains
     * a reason to refuse a write key, the SDK must follow the flag rather than
     * re-derive capability from the key type.
     */
    public function testWriteEnabledOverridesWriteKeyType()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => false],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);

        $this->assertFalse($client->canWrite());
    }

    /**
     * Today's production API does not emit write_enabled at all - it ships on
     * an unmerged backend branch. Failing closed on a missing flag would
     * silently disable registration for every customer, so the SDK falls back
     * to key_type, which on that API is not a lossy proxy but the complete
     * answer: an API without the flag has no ip_write keys and no grants.
     */
    public function testFallsBackToKeyTypeWhenTheApiOmitsWriteEnabled()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write'],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);

        $this->assertTrue($client->canWrite(), 'A write key must keep working against an API that predates the flag');
    }

    public function testFallsBackToKeyTypeForAReadKeyWhenTheApiOmitsWriteEnabled()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'read'],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);

        $this->assertFalse($client->canWrite());
    }

    /**
     * The key-type shortcut must never override a flag the server did send.
     */
    public function testPresentFlagWinsOverKeyTypeOnAFreshResponse()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'read', 'write_enabled' => true],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);

        $this->assertTrue($client->canWrite(), 'A sent flag is authoritative even when key_type would say otherwise');
    }

    // =========================================================================
    // Resolving the decision must not cost a request per render
    // =========================================================================

    /**
     * A read or write key's answer is fully determined by its type, so a warm
     * cache needs no authorization call. Without this, a read-only deployment
     * with unregistered content pays a blocking round-trip on every render,
     * forever - it re-discovers the same items each time by design (GATE-5).
     */
    public function testWarmCacheCostsNoAuthorizationCallForAReadKey()
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'read', 'write_enabled' => false],
        ]);

        // First request warms the shared cache.
        $this->createClientWithMockHttp($mockHttp, $cache)->canWrite();

        // Second request, fresh Client, same cache - as under PHP-FPM.
        $mockHttp->clearRequests();
        $client = $this->createClientWithMockHttp($mockHttp, $cache);

        $this->assertFalse($client->canWrite());
        $this->assertSame([], $mockHttp->getRequests(), 'A warm cache must answer a read key with no HTTP call');

        $cache->clear();
    }

    /**
     * The inverse: ip_write is the one type whose answer varies by caller, so
     * it must still resolve per request even on a warm cache.
     */
    public function testWarmCacheStillResolvesPerRequestForAnIpWriteKey()
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => false],
        ]);
        $this->createClientWithMockHttp($mockHttp, $cache)->canWrite();

        // Same key, next request, now from an allow-listed address.
        $mockHttp->clearRequests();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => true],
        ]);
        $client = $this->createClientWithMockHttp($mockHttp, $cache);

        $this->assertTrue($client->canWrite());
        $this->assertNotEmpty($mockHttp->getRequests(), 'ip_write must not be answered from a cached key_type');

        $cache->clear();
    }

    // =========================================================================
    // The write decision must never outlive the request
    // =========================================================================

    /**
     * write_enabled is derived from the caller's IP, so persisting it applies
     * one caller's answer to every later request sharing the cache.
     */
    public function testWriteDecisionIsNeverWrittenToTheCache()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => true, 'base_locale' => 'en-us'],
        ]);

        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());
        $client = $this->createClientWithMockHttp($mockHttp, $cache);
        $client->authorize();

        $cached = $cache->get('auth_project-id');

        $this->assertIsArray($cached);
        $this->assertArrayNotHasKey('write_enabled', $cached, 'The write decision must not be persisted');
        $this->assertArrayHasKey('key_type', $cached, 'Static project data is still safe to cache');

        $cache->clear();
    }

    /**
     * A cache hit carries no write decision, so the SDK must re-authorize to
     * get one for THIS request rather than defaulting to false (which would
     * silently disable registration for the life of the cache entry).
     */
    public function testCacheHitStillResolvesTheWriteDecisionForThisRequest()
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());

        // Prime the cache the way an earlier, non-write-enabled request would.
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => false, 'base_locale' => 'en-us'],
        ]);
        $this->createClientWithMockHttp($mockHttp, $cache)->authorize();

        // A later request from an allow-listed IP hits that warm cache.
        $mockHttp2 = new MockHttpClient();
        $mockHttp2->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => true, 'base_locale' => 'en-us'],
        ]);
        $client = $this->createClientWithMockHttp($mockHttp2, $cache);

        $this->assertTrue($client->canWrite(), 'A warm cache must not decide the write question for a new request');

        $cache->clear();
    }

    /**
     * Under Octane/Swoole/RoadRunner the Client outlives the request, so the
     * decision has to be resettable between them.
     */
    public function testResetRequestStateClearsTheWriteDecision()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => true],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $this->assertTrue($client->canWrite());

        // Same worker, next request - this one is not allow-listed.
        $client->resetRequestState();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => false],
        ]);

        $this->assertFalse($client->canWrite());
    }

    // =========================================================================
    // An unreachable API must never throw into a render path
    // =========================================================================

    /**
     * translate() sits on every render path. If the SDK's own dependency being
     * down converts a working page into a 500, our outage becomes the
     * customer's outage.
     */
    public function testTranslateReturnsSourceWhenTheApiIsUnreachable()
    {
        $client = $this->createClientWithThrowingHttp();

        $this->assertSame('Hello', $client->translate('Hello'));
    }

    public function testTranslateStillInterpolatesWhenTheApiIsUnreachable()
    {
        $client = $this->createClientWithThrowingHttp();

        $this->assertSame(
            'Based on 5 reviews',
            $client->translate('Based on {n} reviews', 'ProductCard', ['n' => 5])
        );
    }

    /**
     * A failed catalog fetch cannot tell a miss from a hit, so nothing may be
     * queued - guessing would re-register phrases that already exist.
     */
    public function testTranslateQueuesNothingWhenTheApiIsUnreachable()
    {
        $client = $this->createClientWithThrowingHttp();

        $client->translate('Hello');

        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testTranslateContentBlockReturnsSourceHtmlWhenTheApiIsUnreachable()
    {
        $client = $this->createClientWithThrowingHttp();

        $html = '<p>Hello</p><p>World</p>';

        $this->assertSame($html, $client->translateContentBlock($html));
    }

    public function testLookupContentReturnsNullWhenTheApiIsUnreachable()
    {
        $client = $this->createClientWithThrowingHttp();

        $this->assertNull($client->lookupContent('Cat', 'block-id', 'Hello'));
    }

    // =========================================================================
    // A skipped write is not a successful one
    // =========================================================================

    public function testFlushReportsFailureAndCountWhenTheRequestMayNotWrite()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'read', 'write_enabled' => false],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => ['__uncategorized__' => []]]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');
        $client->translate('Hello');
        $client->translate('World');

        $result = $client->flushPendingRegistrations();

        $this->assertFalse($result['success'], 'Discarded work must not be reported as success');
        $this->assertSame(2, $result['skipped']);
        $this->assertSame(0, $result['phrases']);
    }

    public function testFlushReportsSuccessWhenEverythingWasAccepted()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => ['__uncategorized__' => []]]);
        $mockHttp->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');
        $client->translate('Hello');

        $result = $client->flushPendingRegistrations();

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['skipped']);
        $this->assertSame(1, $result['phrases']);
    }

    public function testFlushWithNothingQueuedIsSuccessNotASkip()
    {
        $mockHttp = new MockHttpClient();
        $client = $this->createClientWithMockHttp($mockHttp);

        $result = $client->flushPendingRegistrations();

        $this->assertTrue($result['success']);
        $this->assertSame(0, $result['skipped']);
    }

    // =========================================================================
    // An empty category must not become a second, unreachable namespace
    // =========================================================================

    /**
     * The catalog keys uncategorised items under the sentinel, so a lookup
     * under '' misses forever while registration writes the phrase as
     * uncategorised - the phrase is re-registered on every request and never
     * converges. Mirrors the JS `category || '__uncategorized__'`.
     */
    public function testEmptyCategoryResolvesToTheUncategorizedSentinel()
    {
        $client = $this->clientWithCatalog([
            '__uncategorized__' => ['Hello' => 'Hola'],
        ]);

        $this->assertSame('Hola', $client->translate('Hello', ''));
    }

    public function testEmptyCategoryDoesNotQueueAnAlreadyRegisteredPhrase()
    {
        $client = $this->clientWithCatalog([
            '__uncategorized__' => ['Hello' => 'Hola'],
        ]);

        $client->translate('Hello', '');

        $this->assertFalse(
            $client->hasPendingRegistrations(),
            'A phrase that already exists must not re-register because the category was empty'
        );
    }

    public function testNullCategoryResolvesToTheUncategorizedSentinel()
    {
        $client = $this->clientWithCatalog([
            '__uncategorized__' => ['Hello' => 'Hola'],
        ]);

        $this->assertSame('Hola', $client->translate('Hello', null));
    }

    public function testLookupContentAcceptsAnEmptyCategory()
    {
        $client = $this->clientWithCatalog([
            '__uncategorized__' => ['blk' => ['Hello' => 'Hola']],
        ]);

        $this->assertSame('Hola', $client->lookupContent('', 'blk', 'Hello'));
    }

    // =========================================================================
    // Flush must distinguish work that is gone from work that can be retried
    // =========================================================================

    public function testFlushReportsDroppedWhenTheRequestMayNotWrite()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'read', 'write_enabled' => false],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => ['__uncategorized__' => []]]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');
        $client->translate('Hello');

        $result = $client->flushPendingRegistrations();

        $this->assertSame(1, $result['dropped'], 'Nothing will retry these');
        $this->assertSame(0, $result['retained']);
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testFlushReportsRetainedWhenAuthorizationFails()
    {
        $client = $this->createClientWithMockHttp(new MockHttpClient());
        $client->setLocale('es-es');

        // Queue against a working catalog, then take the API away.
        $reflection = new \ReflectionClass($client);
        $pending = $reflection->getProperty('pendingPhrases');
        $pending->setAccessible(true);
        $pending->setValue($client, ['__uncategorized__::Hello' => ['phrase' => 'Hello', 'category' => '__uncategorized__']]);

        $throwing = new ThrowingHttpClient();
        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $httpProperty->setValue($client, $throwing);

        $result = $client->flushPendingRegistrations();

        $this->assertSame(1, $result['retained'], 'A later flush can still send these');
        $this->assertSame(0, $result['dropped']);
        $this->assertTrue($client->hasPendingRegistrations());
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * A write-enabled client serving a fixed catalog.
     */
    private function clientWithCatalog(array $catalog)
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => $catalog]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        return $client;
    }

    /**
     * Create a client whose every API call fails.
     */
    private function createClientWithThrowingHttp()
    {
        $client = $this->createClientWithMockHttp(new MockHttpClient());
        $client->setLocale('es-es');

        $throwing = new ThrowingHttpClient();
        $reflection = new \ReflectionClass($client);

        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $httpProperty->setValue($client, $throwing);

        foreach (['translations', 'translatableItems'] as $resourceName) {
            $property = $reflection->getProperty($resourceName);
            $property->setAccessible(true);
            $resource = $property->getValue($client);

            $resourceHttp = (new \ReflectionClass($resource))->getProperty('http');
            $resourceHttp->setAccessible(true);
            $resourceHttp->setValue($resource, $throwing);
        }

        return $client;
    }

    /**
     * Create a client with injected mock HTTP client.
     */
    private function createClientWithMockHttp(MockHttpClient $mockHttp, $cache = null)
    {
        $client = new Client('test-api-key', 'project-id', [
            'cache' => $cache !== null ? $cache : new NullCache(),
        ]);

        // Use reflection to inject mock HTTP client
        $reflection = new \ReflectionClass($client);

        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $httpProperty->setValue($client, $mockHttp);

        // Inject into translations resource
        $transProperty = $reflection->getProperty('translations');
        $transProperty->setAccessible(true);
        $translations = $transProperty->getValue($client);

        $transReflection = new \ReflectionClass($translations);
        $transHttpProperty = $transReflection->getProperty('http');
        $transHttpProperty->setAccessible(true);
        $transHttpProperty->setValue($translations, $mockHttp);

        // Inject into translatableItems resource
        $itemsProperty = $reflection->getProperty('translatableItems');
        $itemsProperty->setAccessible(true);
        $items = $itemsProperty->getValue($client);

        $itemsReflection = new \ReflectionClass($items);
        $itemsHttpProperty = $itemsReflection->getProperty('http');
        $itemsHttpProperty->setAccessible(true);
        $itemsHttpProperty->setValue($items, $mockHttp);

        return $client;
    }
}
