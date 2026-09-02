<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Client;
use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use Langsys\SDK\Tests\Mock\ThrowingHttpClient;
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
            'data' => ['key_type' => 'write'],
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
    // translateContentBlock() tests
    // =========================================================================

    public function testTranslateContentBlockWithTranslations()
    {
        $mockHttp = new MockHttpClient();

        // Derive the id rather than hardcoding a hash, so the test survives a
        // change to the hashing scheme (it must stay in sync with the JS SDKs).
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
            'data' => ['key_type' => 'write'],
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
            'data' => ['key_type' => 'write'],
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
            'data' => ['key_type' => 'read'],
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
        // The SERVER's shape, nested under translatable_items. Verified against
        // a live authorize-project response and LangsysSettingsResource:
        //   {"langsys_settings":{"translatable_items":{"batch_limit":200}}}
        //
        // This fixture previously used a FLAT langsys_settings.batch_limit,
        // which is the shape the implementation read - so the test asserted the
        // SDK agreed with itself rather than with the server, and stayed green
        // while the server-provided limit was silently ignored.
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => [
                'key_type' => 'write',
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
            'data' => ['key_type' => 'write'],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->authorize();

        $this->assertEquals(200, $client->translatableItems()->getBatchLimit());
    }

    // =========================================================================
    // A null catalog value is "registered, not yet translated"
    // =========================================================================

    public function testTranslateReturnsSourcePhraseWhenCatalogValueIsNull()
    {
        $client = $this->clientWithCatalog(['ProductCard' => ['Based on {n} reviews' => null]]);

        $this->assertSame(
            'Based on {n} reviews',
            $client->translate('Based on {n} reviews', 'es-es', 'ProductCard')
        );
    }

    public function testTranslateInterpolatesSourcePhraseWhenCatalogValueIsNull()
    {
        $client = $this->clientWithCatalog(['ProductCard' => ['Based on {n} reviews' => null]]);

        $this->assertSame(
            'Based on 5 reviews',
            $client->translate('Based on {n} reviews', 'es-es', 'ProductCard', null, ['n' => 5])
        );
    }

    public function testTranslateDoesNotQueuePhraseWithNullCatalogValue()
    {
        $client = $this->clientWithCatalog(['ProductCard' => ['Based on {n} reviews' => null]]);
        $client->translate('Based on {n} reviews', 'es-es', 'ProductCard');

        $this->assertFalse($client->hasPendingRegistrations());
    }

    // =========================================================================
    // Write decision
    // =========================================================================

    public function testCanWriteWithIpWriteKeyThatServerReportsWriteEnabled()
    {
        $client = $this->clientWithAuth(['key_type' => 'ip_write', 'write_enabled' => true]);
        $this->assertTrue($client->canWrite());
    }

    public function testCannotWriteWithIpWriteKeyThatServerReportsNotWriteEnabled()
    {
        $client = $this->clientWithAuth(['key_type' => 'ip_write', 'write_enabled' => false]);
        $this->assertFalse($client->canWrite());
    }

    /**
     * Today's production API does not emit write_enabled at all. Failing closed
     * on a missing flag would silently disable registration for every customer,
     * so the SDK falls back to key_type - which on that API is not a lossy proxy
     * but the complete answer, since it has no ip_write keys and no grants.
     */
    public function testFallsBackToKeyTypeWhenTheApiOmitsWriteEnabled()
    {
        $client = $this->clientWithAuth(['key_type' => 'write']);
        $this->assertTrue($client->canWrite(), 'A write key must keep working against an API that predates the flag');
    }

    public function testFallsBackToKeyTypeForAReadKeyWhenTheApiOmitsWriteEnabled()
    {
        $client = $this->clientWithAuth(['key_type' => 'read']);
        $this->assertFalse($client->canWrite());
    }

    public function testPresentFlagWinsOverKeyTypeOnAFreshResponse()
    {
        $client = $this->clientWithAuth(['key_type' => 'read', 'write_enabled' => true]);
        $this->assertTrue($client->canWrite(), 'A sent flag is authoritative even when key_type would say otherwise');
    }

    public function testWriteDecisionIsNeverWrittenToTheCache()
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => true, 'base_locale' => 'en-us'],
        ]);
        $this->createClientWithMockHttp($mockHttp, $cache)->authorize();

        $cached = $cache->get('auth_project-id_' . substr(hash('sha256', 'test-api-key'), 0, 12));

        $this->assertIsArray($cached);
        $this->assertArrayNotHasKey('write_enabled', $cached, 'The write decision must not be persisted');
        $this->assertArrayHasKey('key_type', $cached, 'Static project data is still safe to cache');

        $cache->clear();
    }

    /**
     * A read or write key's answer is fully determined by its type, so a warm
     * cache needs no authorization call. Without this a read-only deployment
     * with unregistered content pays a blocking round-trip on every render.
     */
    public function testWarmCacheCostsNoAuthorizationCallForAReadKey()
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'read', 'write_enabled' => false],
        ]);
        $this->createClientWithMockHttp($mockHttp, $cache)->canWrite();

        $mockHttp->clearRequests();
        $client = $this->createClientWithMockHttp($mockHttp, $cache);

        $this->assertFalse($client->canWrite());
        $this->assertSame([], $mockHttp->getRequests(), 'A warm cache must answer a read key with no HTTP call');

        $cache->clear();
    }

    public function testWarmCacheStillResolvesPerRequestForAnIpWriteKey()
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => false],
        ]);
        $this->createClientWithMockHttp($mockHttp, $cache)->canWrite();

        $mockHttp->clearRequests();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => true],
        ]);
        $client = $this->createClientWithMockHttp($mockHttp, $cache);

        $this->assertTrue($client->canWrite());
        $this->assertNotEmpty($mockHttp->getRequests(), 'ip_write must not be answered from a cached key_type');

        $cache->clear();
    }

    public function testResetRequestStateClearsTheWriteDecision()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => true],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $this->assertTrue($client->canWrite());

        $client->resetRequestState();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'ip_write', 'write_enabled' => false],
        ]);

        $this->assertFalse($client->canWrite());
    }

    // =========================================================================
    // An empty category must not become a second, unreachable namespace
    // =========================================================================

    public function testEmptyCategoryResolvesToTheUncategorizedSentinel()
    {
        $client = $this->clientWithCatalog(['__uncategorized__' => ['Hello' => 'Hola']]);

        $this->assertSame('Hola', $client->translate('Hello', 'es-es', ''));
    }

    public function testEmptyCategoryDoesNotQueueAnAlreadyRegisteredPhrase()
    {
        $client = $this->clientWithCatalog(['__uncategorized__' => ['Hello' => 'Hola']]);
        $client->translate('Hello', 'es-es', '');

        $this->assertFalse(
            $client->hasPendingRegistrations(),
            'An existing phrase must not re-register because the category was empty'
        );
    }

    public function testNullCategoryResolvesToTheUncategorizedSentinel()
    {
        $client = $this->clientWithCatalog(['__uncategorized__' => ['Hello' => 'Hola']]);

        $this->assertSame('Hola', $client->translate('Hello', 'es-es', null));
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
        $this->assertFalse($result['success'], 'Discarded work must not be reported as success');
        $this->assertFalse($client->hasPendingRegistrations());
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

    // =========================================================================
    // Legacy (pipe-form) content-block lookup fallback
    // =========================================================================

    /**
     * Content registered before the JSON-form id change is filed under
     * md5(implode('|', [category, ...phrases])). Without a fallback it resolves
     * to nothing, and the block is re-registered under the new id - stranding
     * the translations on the old one. THIS is the incident.
     */
    public function testContentBlockResolvesUnderItsLegacyPipeFormId()
    {
        $html = '<div><h2>Welcome to our shop</h2><p>Free delivery</p></div>';
        $legacyId = md5(implode('|', ['Marketing', 'Welcome to our shop', 'Free delivery']));

        $client = $this->clientWithCatalog([
            'Marketing' => [
                $legacyId => [
                    'Welcome to our shop' => 'Bienvenido a nuestra tienda',
                    'Free delivery' => 'Envio gratis',
                ],
            ],
        ]);

        $result = $client->translateContentBlock($html, 'Marketing');

        $this->assertStringContainsString('Bienvenido a nuestra tienda', $result);
        $this->assertStringContainsString('Envio gratis', $result);
    }

    /**
     * The anti-stranding half, and the reason the fallback works at all: a block
     * served from a legacy id must NOT be queued. Registering it would create a
     * second block under the new id and leave the translations behind on the old
     * one - producing the very damage the fallback exists to prevent.
     */
    public function testLegacyResolvedContentBlockIsNotQueuedForRegistration()
    {
        $html = '<div><h2>Welcome to our shop</h2><p>Free delivery</p></div>';
        $legacyId = md5(implode('|', ['Marketing', 'Welcome to our shop', 'Free delivery']));

        $client = $this->clientWithCatalog([
            'Marketing' => [
                $legacyId => [
                    'Welcome to our shop' => 'Bienvenido a nuestra tienda',
                    'Free delivery' => 'Envio gratis',
                ],
            ],
        ]);

        $client->translateContentBlock($html, 'Marketing');

        $this->assertFalse(
            $client->hasPendingRegistrations(),
            'Re-registering a legacy block is what strands its translations'
        );
    }

    /**
     * The old code disagreed with itself about the category slot - one path sent
     * the sentinel literally, another omitted it - so an uncategorised block
     * exists under either spelling. Both must resolve.
     */
    public function testUncategorizedLegacyBlockResolvesUnderTheEmptyCategorySlot()
    {
        $html = '<div><p>Hello there</p></div>';
        $legacyId = md5(implode('|', ['', 'Hello there']));

        $client = $this->clientWithCatalog([
            '__uncategorized__' => [$legacyId => ['Hello there' => 'Hola']],
        ]);

        $this->assertStringContainsString('Hola', $client->translateContentBlock($html));
    }

    public function testUncategorizedLegacyBlockResolvesUnderTheSentinelCategorySlot()
    {
        $html = '<div><p>Hello there</p></div>';
        $legacyId = md5(implode('|', ['__uncategorized__', 'Hello there']));

        $client = $this->clientWithCatalog([
            '__uncategorized__' => [$legacyId => ['Hello there' => 'Hola']],
        ]);

        $this->assertStringContainsString('Hola', $client->translateContentBlock($html));
    }

    /**
     * The guard. A legacy id can coincide with an unrelated block - the old
     * form's '|' delimiter is unescaped, so distinct tuples flatten to one
     * string. The phrases decide, not the id, and a mismatch must fail toward
     * "no match" rather than attach someone else's translations.
     */
    public function testLegacyIdResolvingToDifferentContentIsRejected()
    {
        $html = '<div><p>Hello there</p></div>';
        $legacyId = md5(implode('|', ['Marketing', 'Hello there']));

        $client = $this->clientWithCatalog([
            'Marketing' => [
                $legacyId => ['Some entirely other phrase' => 'Otra frase'],
            ],
        ]);

        $result = $client->translateContentBlock($html, 'Marketing');

        $this->assertStringNotContainsString('Otra frase', $result);
        $this->assertStringContainsString('Hello there', $result);
        $this->assertTrue($client->hasPendingRegistrations(), 'A rejected match is a genuine miss');
    }

    /**
     * A legacy id must never be emitted - it is a read path only.
     */
    public function testLegacyIdIsNeverSentToTheApi()
    {
        $html = '<div><p>Brand new content</p></div>';

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => []]);
        $mockHttp->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');
        $client->translateContentBlock($html, 'Marketing');
        $client->flushPendingRegistrations();

        $parser = new HtmlParser();
        $currentId = $parser->generateCustomId('Marketing', ['Brand new content']);

        // Only legacy ids that DIFFER from the current one can evidence
        // emission. For pure-ASCII content the JS code-unit hash and a UTF-8
        // byte hash agree exactly, so one "legacy" shape is byte-identical to
        // the current id - finding it in the body proves nothing, because it IS
        // the current id. Asserting on the raw list would fail on a correct
        // implementation, which is a test that reports the wrong thing rather
        // than a defect.
        $distinctLegacyIds = array_values(array_filter(
            $parser->legacyCustomIds('Marketing', ['Brand new content']),
            function ($id) use ($currentId) { return $id !== $currentId; }
        ));

        $this->assertNotEmpty($distinctLegacyIds, 'sanity: at least one legacy shape must differ');

        foreach ($mockHttp->getRequests() as $request) {
            if ($request['method'] !== 'POST') {
                continue;
            }
            $body = json_encode($request['data']);
            foreach ($distinctLegacyIds as $legacyId) {
                $this->assertStringNotContainsString($legacyId, $body, 'Legacy ids are lookup-only');
            }
        }
    }

    // =========================================================================
    // An unreachable API must never throw into a render path
    // =========================================================================

    /**
     * translate() sits on every render path. If the SDK's own dependency being
     * down turns a working page into a 500, our outage becomes the customer's.
     */
    public function testTranslateReturnsSourceWhenTheApiIsUnreachable()
    {
        $client = $this->clientWithUnreachableApi();

        $this->assertSame('Hello', $client->translate('Hello'));
    }

    public function testTranslateStillInterpolatesWhenTheApiIsUnreachable()
    {
        $client = $this->clientWithUnreachableApi();

        $this->assertSame(
            'Based on 5 reviews',
            $client->translate('Based on {n} reviews', 'es-es', 'ProductCard', null, ['n' => 5])
        );
    }

    /**
     * A failed catalog fetch cannot tell a miss from a hit, so nothing may be
     * queued - registering on a guess turns every outage into a write storm.
     */
    public function testTranslateQueuesNothingWhenTheApiIsUnreachable()
    {
        $client = $this->clientWithUnreachableApi();
        $client->translate('Hello');

        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testTranslateContentBlockReturnsSourceHtmlWhenTheApiIsUnreachable()
    {
        $client = $this->clientWithUnreachableApi();
        $html = '<div><p>Hello</p></div>';

        $this->assertStringContainsString('Hello', $client->translateContentBlock($html));
    }

    // =========================================================================
    // The sentinel is a local key, never a wire value
    // =========================================================================

    public function testUncategorizedPhrasesDoNotSendTheSentinelOnTheWire()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => []]);
        $mockHttp->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');
        $client->translate('Hello');
        $client->flushPendingRegistrations();

        foreach ($mockHttp->getRequests() as $request) {
            if ($request['method'] !== 'POST') {
                continue;
            }
            foreach ($request['data']['translatable_items'] as $item) {
                $this->assertNotSame(
                    '__uncategorized__',
                    isset($item['category']) ? $item['category'] : null,
                    'The sentinel keys the local catalog; the API expects an absent category'
                );
            }
        }
    }

    /**
     * write_enabled is authoritative in BOTH directions, on a fresh response.
     *
     * No server condition produces this combination today - a WRITE key always
     * allows, and a suspended subscription short-circuits before the flag is
     * computed. This pins the direction of trust, not a live behaviour. Scope is
     * the fresh-response path, since read/write keys are answered from the
     * cached key_type and a cache entry never carries the flag.
     */
    public function testWriteEnabledFalseOverridesAWriteKeyType()
    {
        $client = $this->clientWithAuth(['key_type' => 'write', 'write_enabled' => false]);

        $this->assertFalse($client->canWrite());
    }

    // =========================================================================
    // REG-9 — the server's batch limit must actually apply
    // =========================================================================

    /**
     * The limit is nested at langsys_settings.translatable_items.batch_limit.
     * Read one level short it never applied, so the SDK kept its own default and
     * would send oversized batches the server REJECTS if the limit is lowered.
     */
    public function testChunksToTheServerAdvertisedBatchLimit()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => [
                'key_type' => 'write',
                'write_enabled' => true,
                'langsys_settings' => ['translatable_items' => ['batch_limit' => 3]],
            ],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => ['__uncategorized__' => []]]);
        $mockHttp->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');
        foreach (range(1, 7) as $i) {
            $client->translate('Phrase ' . $i);
        }

        $mockHttp->clearRequests();
        $client->flushPendingRegistrations();

        $sizes = [];
        foreach ($mockHttp->getRequests() as $request) {
            if ($request['method'] === 'POST') {
                $sizes[] = count($request['data']['translatable_items']);
            }
        }

        $this->assertSame([3, 3, 1], $sizes, '7 phrases at a limit of 3 must chunk 3/3/1');
        $this->assertSame(3, $client->translatableItems()->getBatchLimit());
    }

    /**
     * The regression the fix above introduced.
     *
     * Reading the limit for the first time also meant HONOURING a bad one. A
     * project whose settings carry 0 (an unset column, a misconfigured project)
     * reached array_chunk(), which raises a ValueError on a non-positive
     * length - and this normally runs from the shutdown handler, where an
     * uncaught Error is a fatal AFTER the response has been sent. Before the
     * REG-9 fix the value was never read, so it was harmless; afterwards it
     * took the request down.
     *
     * @dataProvider nonPositiveBatchLimitProvider
     */
    public function testNonPositiveServerBatchLimitIsIgnored($limit)
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => [
                'key_type' => 'write',
                'write_enabled' => true,
                'langsys_settings' => ['translatable_items' => ['batch_limit' => $limit]],
            ],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => ['__uncategorized__' => []]]);
        $mockHttp->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');
        $client->translate('Hello');

        $mockHttp->clearRequests();
        $result = $client->flushPendingRegistrations();

        $this->assertTrue($result['success'], 'a bad setting must not fail the flush');
        $this->assertSame(1, $result['phrases']);
        $this->assertSame(
            200,
            $client->translatableItems()->getBatchLimit(),
            'a non-positive limit is ignored in favour of the default'
        );
    }

    public function nonPositiveBatchLimitProvider()
    {
        return [
            'zero'     => [0],
            'negative' => [-1],
        ];
    }

    /**
     * And the flush survives an \Error, not merely an \Exception.
     *
     * The catches were \Exception-only, so the ValueError above unwound
     * straight through them. Registration is best-effort by design and mostly
     * runs after the response is sent; every failure has to land in $result
     * instead of escaping. Asserted with a TypeError, which is an \Error and
     * so invisible to the old catches.
     */
    public function testFlushSurvivesAnErrorNotJustAnException()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => ['__uncategorized__' => []]]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');
        $client->translate('Hello');

        // Swap in a transport that raises an \Error rather than an \Exception.
        $items = (new \ReflectionClass($client))->getProperty('translatableItems');
        $items->setAccessible(true);
        $resource = $items->getValue($client);

        $http = (new \ReflectionClass($resource))->getProperty('http');
        $http->setAccessible(true);
        $http->setValue($resource, new class {
            public function post($path, array $data = [])
            {
                throw new \TypeError('an \Error, not an \Exception');
            }
        });

        $result = $client->flushPendingRegistrations();

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['phrases']);
        $this->assertSame(1, $result['retained'], 'the phrase stays queued for a later retry');
    }

    // =========================================================================
    // CACHE-1 — the auth answer depends on WHICH key asked
    // =========================================================================

    /**
     * Shadow pair, direction 1: a read key must not inherit a write key's cached
     * capability. The default cache is a shared temp directory, so a host running
     * a read key for rendering and a write key for a sync job shares this entry.
     */
    public function testReadKeyDoesNotInheritAWriteKeysCachedCapability()
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());

        $this->clientForKey('write-key', 'write', true, $cache)->canWrite();

        $this->assertFalse(
            $this->clientForKey('read-key', 'read', false, $cache)->canWrite(),
            'a read key inheriting write capability would attempt registrations it may not make'
        );

        $cache->clear();
    }

    /**
     * Direction 2, the quiet one: a write key must not inherit a read key's
     * cached capability, which would silently disable discovery.
     */
    public function testWriteKeyDoesNotInheritAReadKeysCachedCapability()
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());

        $this->clientForKey('read-key', 'read', false, $cache)->canWrite();

        $this->assertTrue(
            $this->clientForKey('write-key', 'write', true, $cache)->canWrite(),
            'a write key inheriting read capability would stop discovering, silently'
        );

        $cache->clear();
    }

    public function testAuthCacheKeyCarriesNoRawKeyMaterial()
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());
        $this->clientForKey('super-secret-key', 'write', true, $cache)->canWrite();

        $method = new \ReflectionMethod(Client::class, 'authCacheKey');
        $method->setAccessible(true);
        $key = $method->invoke($this->clientForKey('super-secret-key', 'write', true, $cache));

        $this->assertStringNotContainsString('super-secret-key', $key, 'cache keys land on shared filesystems');
        $this->assertStringContainsString('project-id', $key);

        $cache->clear();
    }

    // =========================================================================
    // WIRE-4 — a malformed cache entry must not reach the render
    // =========================================================================

    /**
     * A shared cache sees truncated writes, key collisions and other versions'
     * formats. A wrong-SHAPED hit reached the catalog lookup and raised a
     * TypeError out of translate() - a 500 on a customer page, from the cache the
     * SDK itself relies on.
     *
     * @dataProvider malformedCacheEntryProvider
     */
    public function testMalformedCacheEntryDoesNotReachTheRender($poison)
    {
        $client = $this->clientWithPoisonedCache($poison, $cache);

        $this->assertSame('Hello', $client->translate('Hello'));
        $this->assertStringContainsString('Hi', $client->translateContentBlock('<p>Hi</p>'));
        $this->assertStringContainsString('Hi', $client->translatePage('<html><body><p>Hi</p></body></html>'));

        $cache->clear();
    }

    public function malformedCacheEntryProvider()
    {
        return [
            // Depth 0 - the whole entry is the wrong type.
            'string'  => ['a string, not the category map'],
            'integer' => [42],
            'boolean' => [true],

            // Depth 1 - a map of SCALAR slices. This is the shape a depth-0
            // is_array() check waves through: it looks like a catalog until
            // something indexes into a category, which every render does. It
            // reached all three entry points as a TypeError.
            'slice is string'  => [['greetings' => 'a string, not the phrase map']],
            'slice is integer' => [['greetings' => 42]],
            'slice is boolean' => [['greetings' => true]],
        ];
    }

    /**
     * And the entry is DISCARDED, not merely survived. Degrading on every call
     * while the poisoned entry sits there for the rest of its TTL is the lesser
     * fix; the next request must be able to repopulate from the API.
     *
     * @dataProvider malformedCacheEntryProvider
     */
    public function testMalformedCacheEntryIsInvalidatedRatherThanEndured($poison)
    {
        $client = $this->clientWithPoisonedCache($poison, $cache);
        $key = 'translations_project-id_es-es';

        $this->assertSame($poison, $cache->get($key), 'sanity: the poison is present');

        $client->translate('Hello');

        // The poison is gone. What sits there now is the repopulated catalog -
        // deleting the bad entry lets the very next fetch write a good one, which
        // is the point: the alternative is degrading on every call for the rest
        // of the entry's TTL.
        $this->assertIsArray($cache->get($key), 'the entry must be replaced, not merely tolerated');
        $this->assertNotSame($poison, $cache->get($key));

        $cache->clear();
    }

    /**
     * The other half of WIRE-4, and the more embarrassing one: the SDK was
     * MANUFACTURING the poison it guards against.
     *
     * getTranslations() wrote whatever the API returned straight into the shared
     * cache. A malformed response - a proxy error page parsed as JSON, a partial
     * body, a server bug - was cached verbatim and then re-read by every request
     * for the rest of the TTL. Guarding the read alone leaves the SDK poisoning
     * its own cache and merely surviving it afterwards.
     *
     * @dataProvider malformedServerMapProvider
     */
    public function testMalformedServerMapIsNeverWrittenToTheCache($data)
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());
        $key = 'translations_project-id_es-es';

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['status' => true, 'data' => $data]);

        $client = $this->createClientWithMockHttp($mockHttp, $cache);
        $client->setLocale('es-es');

        $this->assertSame('Hello', $client->translate('Hello'), 'the render degrades rather than throwing');

        $cached = $cache->get($key);
        $this->assertIsArray($cached, 'the malformed payload must never be written');
        $this->assertSame([], $cached);

        $cache->clear();
    }

    public function malformedServerMapProvider()
    {
        return [
            'data is a string'  => ['not a map'],
            'data is an int'    => [42],
            'slice is a string' => [['greetings' => 'not the phrase map']],
            'slice is an int'   => [['greetings' => 7]],
        ];
    }

    /**
     * Positive control for the test above.
     *
     * The assertion there - "the cache holds []" - is only meaningful if the
     * write path is actually REACHED and would otherwise have stored the
     * payload. Same client, same cache, same call; the only difference is a
     * well-formed response. If this fails, the test above proves nothing,
     * because nothing was ever going to be written.
     */
    public function testWellFormedServerMapIsWrittenToTheCache()
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());
        $key = 'translations_project-id_es-es';

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', [
            'status' => true,
            'data' => ['greetings' => ['Hello' => 'Hola']],
        ]);

        $client = $this->createClientWithMockHttp($mockHttp, $cache);
        $client->setLocale('es-es');

        $this->assertSame('Hola', $client->translate('Hello', null, 'greetings'));
        $this->assertSame(
            ['greetings' => ['Hello' => 'Hola']],
            $cache->get($key),
            'the write path must be live, or the malformed-map test is vacuous'
        );

        $cache->clear();
    }

    // =========================================================================
    // Helper methods
    // =========================================================================

    /**
     * A client for one API key against a shared cache.
     */
    private function clientForKey($apiKey, $keyType, $writeEnabled, $cache)
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => $keyType, 'write_enabled' => $writeEnabled, 'base_locale' => 'en-us'],
        ]);

        $client = new Client($apiKey, 'project-id', ['cache' => $cache]);
        $reflection = new \ReflectionClass($client);

        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $httpProperty->setValue($client, $mockHttp);

        foreach (['translations', 'translatableItems'] as $resourceName) {
            $property = $reflection->getProperty($resourceName);
            $property->setAccessible(true);
            $resource = $property->getValue($client);

            $resourceHttp = (new \ReflectionClass($resource))->getProperty('http');
            $resourceHttp->setAccessible(true);
            $resourceHttp->setValue($resource, $mockHttp);
        }

        return $client;
    }

    /**
     * A client whose translations cache holds a value of the wrong shape.
     */
    private function clientWithPoisonedCache($poison, &$cache)
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());
        $cache->set('translations_project-id_es-es', $poison);

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['status' => true, 'data' => []]);

        $client = $this->createClientWithMockHttp($mockHttp, $cache);
        $client->setLocale('es-es');

        return $client;
    }

    /**
     * A client whose every API call fails.
     */
    private function clientWithUnreachableApi()
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
     * A client whose authorization response is exactly $data.
     */
    private function clientWithAuth(array $data)
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', ['data' => $data]);

        return $this->createClientWithMockHttp($mockHttp);
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
