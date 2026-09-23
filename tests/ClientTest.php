<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Client;
use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use Langsys\SDK\Tests\Mock\ErrorThrowingCache;
use Langsys\SDK\Tests\Mock\ErrorThrowingHttpClient;
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

        $customId = (new HtmlParser())->generateCustomId('homepage', ['Hello', 'World']);

        $mockHttp->setResponse('GET', 'translations', [
            'data' => [
                'homepage' => [
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

        $html = '<p>Hello</p><p>World</p>';

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

    /**
     * The catalog half of the reset. A long-lived worker (Octane, a queue
     * worker) reuses one Client across units of work, so a reset that kept the
     * memory catalog would serve the previous unit's translations until the
     * process died. Ported from the Laravel binding's
     * RequestScopeTest::testTheNextUnitOfWorkReadsTheCatalogAfresh, which was the
     * only test anywhere that turned red when the clearing line was deleted.
     */
    public function testResetRequestStateMakesTheNextUnitReadTheCatalogAfresh()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => ['UI' => ['Save' => 'Guardar']]]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');
        $this->assertSame('Guardar', $client->translate('Save', null, 'UI'), 'Control: this unit must have read the seeded catalog.');

        $mockHttp->setResponse('GET', 'translations', ['data' => ['UI' => ['Save' => 'Salvar']]]);
        $client->resetRequestState();

        $this->assertSame('Salvar', $client->translate('Save', null, 'UI'), "The next unit served the previous unit's catalog.");
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
        $html = '<div><p>Hello there</p><p>Bye</p></div>';
        $legacyId = md5(implode('|', ['', 'Hello there', 'Bye']));

        $client = $this->clientWithCatalog([
            '__uncategorized__' => [$legacyId => ['Hello there' => 'Hola', 'Bye' => 'Adios']],
        ]);

        $this->assertStringContainsString('Hola', $client->translateContentBlock($html));
    }

    public function testUncategorizedLegacyBlockResolvesUnderTheSentinelCategorySlot()
    {
        $html = '<div><p>Hello there</p><p>Bye</p></div>';
        $legacyId = md5(implode('|', ['__uncategorized__', 'Hello there', 'Bye']));

        $client = $this->clientWithCatalog([
            '__uncategorized__' => [$legacyId => ['Hello there' => 'Hola', 'Bye' => 'Adios']],
        ]);

        $this->assertStringContainsString('Hola', $client->translateContentBlock($html));
    }

    /**
     * The legacy fallback, through the client's block resolution, on content the
     * two hashes DISAGREE about.
     *
     * Every diverging row is a single token, and a one-token fragment is a
     * phrase (TOK-6) that never reaches a block lookup through
     * translateContentBlock() - so the rows are driven through
     * resolveContentBlockTranslations(), the resolution both block paths share.
     *
     * Every Client-level legacy test above uses ASCII, where the JS code-unit
     * hash and a UTF-8 byte hash produce the same digest - so all of them pass
     * against a byte-hash stub, and none of them can tell a correct port from a
     * wrong one. The parser-level fixture covers the divergence, but only by
     * calling the hash directly; nothing proved a client actually RESOLVES a
     * block that a JS SDK registered under a non-ASCII id.
     *
     * Ids come from the shared vector file rather than being recomputed here.
     * Recomputing them with the SDK's own helper would make this test agree with
     * whatever the implementation does, including agreeing with it while wrong.
     *
     * @dataProvider nonAsciiLegacyBlockProvider
     */
    public function testNonAsciiLegacyBlockResolvesThroughTheClient($fixtureRow)
    {
        $rows = json_decode(
            file_get_contents(__DIR__ . '/fixtures/legacy-custom-id-reference.json'),
            true
        );

        $row = $rows[$fixtureRow - 1];
        $phrase = $row['tokens'][0];
        $category = $row['category'];

        // The catalog as a JS SDK left it: filed under the pre-fix code-unit id.
        $client = $this->clientWithCatalog([$category => []]);
        $parser = new HtmlParser();

        $this->assertSame(
            [$phrase => 'TRANSLATED'],
            $client->resolveContentBlockTranslations(
                [$row['legacy_custom_id'] => [$phrase => 'TRANSLATED']],
                $category,
                $row['tokens'],
                $parser->generateCustomId($category, $row['tokens']),
                $parser
            ),
            'row ' . $fixtureRow . ' (' . $row['note'] . ') did not resolve through the client'
        );
    }

    public function nonAsciiLegacyBlockProvider()
    {
        return [
            'Cyrillic category and phrase' => [8],
            'Japanese' => [9],
            'Greek' => [10],
            'Hebrew' => [11],
            'Arabic' => [12],
            'astral plane (emoji)' => [13],
        ];
    }

    /**
     * The guard. A legacy id can coincide with an unrelated block - the old
     * form's '|' delimiter is unescaped, so distinct tuples flatten to one
     * string. The phrases decide, not the id, and a mismatch must fail toward
     * "no match" rather than attach someone else's translations.
     */
    public function testLegacyIdResolvingToDifferentContentIsRejected()
    {
        $html = '<div><p>Hello there</p><p>Bye</p></div>';
        $legacyId = md5(implode('|', ['Marketing', 'Hello there', 'Bye']));

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
        $html = '<div><p>Brand new content</p><p>More</p></div>';

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
        $currentId = $parser->generateCustomId('Marketing', ['Brand new content', 'More']);

        // Only legacy ids that DIFFER from the current one can evidence
        // emission. For pure-ASCII content the JS code-unit hash and a UTF-8
        // byte hash agree exactly, so one "legacy" shape is byte-identical to
        // the current id - finding it in the body proves nothing, because it IS
        // the current id. Asserting on the raw list would fail on a correct
        // implementation, which is a test that reports the wrong thing rather
        // than a defect.
        $distinctLegacyIds = array_values(array_filter(
            $parser->legacyCustomIds('Marketing', ['Brand new content', 'More']),
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

    /**
     * The same four seams, failing with an \Error instead of an \Exception.
     *
     * Each of these seams is written `catch (\Throwable)` deliberately, but
     * until now every test drove them with an ApiException - so narrowing any of
     * them back to \Exception left the whole suite green. A seam no test can
     * redden is not a seam that will survive the next edit, and the failures
     * that actually reached production here were \Errors: a TypeError from a
     * wrong-shaped cache hit, a ValueError from a batch limit of zero.
     *
     * @dataProvider errorSeamProvider
     */
    public function testRenderPathsSurviveAnErrorFromTheTransport($call, $expected)
    {
        $client = $this->clientWithUnreachableApi('error');

        $this->assertStringContainsString($expected, $call($client));
    }

    public function errorSeamProvider()
    {
        return [
            // Client.php:719 - translate()'s catalog lookup.
            'translate' => [
                function ($client) { return $client->translate('Hello'); },
                'Hello',
            ],
            // Client.php:1237 - translateContentBlock()'s catalog lookup.
            'translateContentBlock' => [
                function ($client) { return $client->translateContentBlock('<p>Hi</p>'); },
                'Hi',
            ],
            // PageTranslator, via the client.
            'translatePage' => [
                function ($client) { return $client->translatePage('<html><body><p>Hi</p></body></html>'); },
                'Hi',
            ],
        ];
    }

    /**
     * The seam the enumeration above MISSED, and the one that mattered most.
     *
     * `getLocale()` runs at Client.php:708, BEFORE the try block at :717 - so
     * its own fallback to the project's base locale is outside every entry
     * point's catch. When no locale is set and no browser header is present,
     * that fallback calls the API, and an \Error from it escapes translate(),
     * translateContentBlock() and translatePage() alike. The seam at :1142 is
     * the only thing holding it, and nothing exercised it: narrowing it to
     * \Exception left the whole suite green while all three entry points threw.
     *
     * The lesson is not about this line. "All five seams are pinned" was a claim
     * about the five I had enumerated, and I had enumerated them by grepping for
     * the catches I already knew about.
     *
     * @dataProvider errorSeamProvider
     */
    public function testRenderPathsSurviveAnErrorWhileResolvingTheLocale($call, $expected)
    {
        $client = $this->clientWithUnreachableApi('error');

        // No locale set, so getLocale() falls through to the project lookup -
        // which is outside the entry-point try.
        $client->setLocale(null);

        $this->assertStringContainsString($expected, $call($client));
    }

    /**
     * And the post-flush cache clear at Client.php:1790. A flush that succeeded
     * must not be reported as failed because invalidating the catalog afterwards
     * raised an \Error.
     */
    public function testFlushSurvivesAnErrorWhileClearingTheCache()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => ['__uncategorized__' => []]]);
        $mockHttp->setResponse('POST', 'translatable-items', ['status' => true]);

        $cache = new ErrorThrowingCache();
        $client = $this->createClientWithMockHttp($mockHttp, $cache);
        $client->setLocale('es-es');
        $client->translate('Hello');

        $result = $client->flushPendingRegistrations();

        $this->assertTrue($result['success'], 'the registration succeeded; only the cache clear failed');
        $this->assertSame(1, $result['phrases']);
    }

    /**
     * And the two flush seams: Client.php:1726 (the authorize call that gates
     * the flush) and :1769 (the content-block registration call). Registration
     * is best-effort and usually runs from the shutdown handler, where an
     * escaping \Error is a fatal after the response has been sent.
     */
    public function testFlushSurvivesAnErrorFromTheAuthorizeGate()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => ['__uncategorized__' => []]]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');

        // Queue against a HEALTHY API first. Driving this with an unreachable
        // API instead queues nothing - by design, since a failed lookup cannot
        // tell a miss from a hit - and the flush then returns success trivially
        // with an empty queue, asserting nothing. That was this test's first
        // form and it passed for that reason.
        $client->translate('Hello');
        $this->assertTrue($client->hasPendingRegistrations(), 'sanity: there is something to flush');

        // Now break the authorize call that gates the flush, with an \Error.
        $http = (new \ReflectionClass($client))->getProperty('http');
        $http->setAccessible(true);
        $http->setValue($client, new ErrorThrowingHttpClient());

        // Forget the write decision so the flush has to authorize again.
        $writeEnabled = (new \ReflectionClass($client))->getProperty('writeEnabled');
        $writeEnabled->setAccessible(true);
        $writeEnabled->setValue($client, null);

        $result = $client->flushPendingRegistrations();

        $this->assertFalse($result['success']);
        $this->assertSame(1, $result['skipped'], 'the queued phrase is accounted for, not lost silently');
        $this->assertTrue($client->hasPendingRegistrations(), 'and left queued for a later retry');
    }

    public function testFlushSurvivesAnErrorWhileRegisteringContentBlocks()
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write', 'write_enabled' => true],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['data' => ['__uncategorized__' => []]]);

        $client = $this->createClientWithMockHttp($mockHttp);
        $client->setLocale('es-es');
        $client->translateContentBlock('<p>Hi</p><p>There</p>');

        // Only the content-block POST fails, and it fails with an \Error.
        $items = (new \ReflectionClass($client))->getProperty('translatableItems');
        $items->setAccessible(true);
        $resource = $items->getValue($client);

        $http = (new \ReflectionClass($resource))->getProperty('http');
        $http->setAccessible(true);
        $http->setValue($resource, new ErrorThrowingHttpClient());

        $result = $client->flushPendingRegistrations();

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['content_blocks']);
    }

    public function testTranslateContentBlockReturnsSourceHtmlWhenTheApiIsUnreachable()
    {
        $client = $this->clientWithUnreachableApi();
        $html = '<div><p>Hello</p><p>World</p></div>';

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
            //
            // The slice MUST be keyed on the category the renders actually read.
            // These vectors were first written under 'greetings', which no call
            // below passes - so the bad slice was never indexed, the test was
            // 6/6 green against the unfixed code, and a depth-0-only guard left
            // it green too. It asserted nothing at all. Keyed on the sentinel
            // they throw 9/9 without the fix.
            'slice is string'  => [[Client::UNCATEGORIZED => 'a string, not the phrase map']],
            'slice is integer' => [[Client::UNCATEGORIZED => 42]],
            'slice is boolean' => [[Client::UNCATEGORIZED => true]],
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

        // NOT "the cache holds []". That was this assertion's first form and it
        // was asserting the bug: an empty catalog is a valid shape, so caching
        // one blanked translations for the whole TTL. The cache must be left
        // ALONE, exactly as it is on an unreachable API.
        $this->assertNull($cache->get($key), 'a rejected payload must leave the cache untouched');

        $cache->clear();
    }

    /**
     * The regression the fix above introduced, and it was worse than the defect.
     *
     * Rejecting the payload is only half the job: returning `[]` for it made an
     * EMPTY CATALOG the cached value, and an empty catalog is a perfectly valid
     * shape that every later request happily reads. One malformed response
     * therefore blanked translations for the whole TTL - by default an hour, and
     * fleet-wide on a shared Redis. Under a read key nothing ever refetches, so
     * it cannot self-heal at all.
     *
     * Measured before the fix: request 1 gets the bad body, requests 2 and 3 hit
     * a HEALTHY server and still render the source string, issuing zero GETs.
     * That is a regression from the original defect, which was one TypeError on
     * one request and healed on the next.
     *
     * The exception path was already right and is the shape to match: an
     * unreachable API caches nothing, so the next request tries again.
     *
     * @dataProvider malformedServerMapProvider
     */
    public function testARejectedServerMapDoesNotBlankTheCatalogForTheTtl($data)
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());
        $key = 'translations_project-id_es-es';
        $good = ['greetings' => ['Hello' => 'Hola']];

        // Request 1: one malformed body.
        $this->assertSame(
            'Hello',
            $this->clientAgainstServerMap($data, $cache)->translate('Hello', null, 'greetings'),
            'the render degrades rather than throwing'
        );

        $this->assertNull($cache->get($key), 'a rejected payload must leave the cache untouched');

        // Requests 2 and 3: a healthy server, a fresh client, a read key - so
        // nothing but a real fetch can repair the catalog.
        foreach ([2, 3] as $requestNumber) {
            $client = $this->clientAgainstServerMap($good, $cache, $mock);

            $this->assertSame(
                'Hola',
                $client->translate('Hello', null, 'greetings'),
                'request ' . $requestNumber . ' still reads a catalog blanked by one bad response'
            );
        }
    }

    /**
     * Positive control for the assertion above.
     *
     * "The cache is untouched" would also hold if the SDK never cached anything
     * at all, in which case the test would be measuring nothing. Same client,
     * same cache, same call, well-formed body: the entry must appear.
     */
    public function testAWellFormedServerMapStillPopulatesTheCatalog()
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());
        $good = ['greetings' => ['Hello' => 'Hola']];

        $this->assertSame('Hola', $this->clientAgainstServerMap($good, $cache)->translate('Hello', null, 'greetings'));
        $this->assertSame($good, $cache->get('translations_project-id_es-es'), 'the write path must be live');
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
     * A 2xx with no `data` key at all, which used to be waved through.
     *
     * The first version of the fix above carved this shape out and cached `[]`
     * for it, reasoning that "a project with no translations legitimately has an
     * empty catalog". That reasoning was wrong, and checkable: every translations
     * response goes through `ApiResponse::resourceResponse()`, which assigns
     * `$this->simpleResponse['data'] = $data` unconditionally, so an empty
     * catalog serializes WITH the key. The backend has no path that omits it —
     * a 2xx without `data` is always foreign.
     *
     * It is also reachable without anything exotic. `HttpClient::handleResponse()`
     * turns any empty-bodied 2xx into `[]` (needed for 204), and an empty 200
     * from a proxy or load balancer is an ordinary event. That read as "no data",
     * cached `[]`, and blanked translations for the TTL — the exact mode the fix
     * above exists to prevent, re-opened by its own carve-out.
     *
     * @dataProvider absentDataProvider
     */
    public function testAResponseWithNoDataKeyIsTreatedAsMalformed($response)
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());
        $key = 'translations_project-id_es-es';

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'read', 'write_enabled' => false],
        ]);
        $mockHttp->setResponse('GET', 'translations', $response);

        $client = $this->createClientWithMockHttp($mockHttp, $cache);
        $client->setLocale('es-es');

        $this->assertSame('Hello', $client->translate('Hello', null, 'greetings'));
        $this->assertNull($cache->get($key), 'a response with no data key must leave the cache untouched');

        // And the catalog is still repairable: a healthy server on the next
        // request must be able to serve a real translation.
        $this->assertSame(
            'Hola',
            $this->clientAgainstServerMap(['greetings' => ['Hello' => 'Hola']], $cache)
                ->translate('Hello', null, 'greetings'),
            'one bodyless 200 must not blank the catalog for the rest of the TTL'
        );

        $cache->clear();
    }

    /**
     * A failed fetch is not asked again inside its window, per phrase or per
     * request.
     *
     * Nothing about a failure is cacheable - writing a value for it is exactly
     * how one bad response blanks a project for a TTL - but the first fix
     * retried on every call instead, so a 200-phrase page during an incident
     * issued 200 requests against a dependency already in trouble. "The next
     * request tries again" was true; what it hid was that the next CALL tried
     * again.
     *
     * @dataProvider failingTransportProvider
     */
    public function testAFailedCatalogFetchIsNotAskedAgainInsideItsWindow($configure)
    {
        $cache = new FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());

        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'read', 'write_enabled' => false],
        ]);
        $configure($mockHttp);

        $client = $this->createClientWithMockHttp($mockHttp, $cache);
        $client->setLocale('es-es');

        $mockHttp->clearRequests();

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('Hello', $client->translate('Hello', null, 'greetings'));
        }

        $fetches = 0;
        foreach ($mockHttp->getRequests() as $request) {
            if ($request['method'] === 'GET' && strpos($request['endpoint'], 'translations') !== false) {
                $fetches++;
            }
        }

        $this->assertSame(1, $fetches, '5 renders during an incident must not be 5 fetches');
        $this->assertNull(
            $cache->get('translations_project-id_es-es'),
            'and memoizing the failure must still write nothing'
        );

        // The window belongs to the Client, not the request: a long-lived
        // worker's next request inside it does not wait on the failing call.
        $client->resetRequestState();
        $client->translate('Hello', null, 'greetings');

        $fetches = 0;
        foreach ($mockHttp->getRequests() as $request) {
            if ($request['method'] === 'GET' && strpos($request['endpoint'], 'translations') !== false) {
                $fetches++;
            }
        }

        $this->assertSame(1, $fetches, 'resetRequestState() keeps the failure window');

        $cache->clear();
    }

    /**
     * The same memo, when the failure is an \Error rather than an \Exception.
     *
     * Both vectors above fail with a LangsysException, so the memo's own catch
     * could be narrowed to \Exception and they would not notice — leaving an
     * \Error un-memoized and a 200-phrase page back to 200 fetches, in exactly
     * the incident where hammering the dependency hurts most. The mutation that
     * exposed this is the reason the seam list is derived by grepping the file
     * each time rather than carried forward.
     */
    public function testAFailedCatalogFetchIsNotAskedAgainInsideItsWindowForAnErrorToo()
    {
        $counting = new class extends \Langsys\SDK\Http\HttpClient {
            public $calls = 0;

            public function __construct()
            {
                parent::__construct(new \Langsys\SDK\Config([
                    'api_key' => 'test-api-key',
                    'project_id' => 'project-id',
                ]));
            }

            public function get($endpoint, array $params = [])
            {
                if (strpos($endpoint, 'authorize-project') !== false) {
                    return ['data' => ['key_type' => 'read', 'write_enabled' => false]];
                }

                $this->calls++;

                throw new \TypeError('an \Error from the catalog fetch');
            }

            public function post($endpoint, array $data = [])
            {
                throw new \TypeError('an \Error from the catalog fetch');
            }
        };

        $client = $this->createClientWithMockHttp(new MockHttpClient());
        $client->setLocale('es-es');

        $reflection = new \ReflectionClass($client);
        foreach (['http', 'translations', 'translatableItems'] as $target) {
            $property = $reflection->getProperty($target);
            $property->setAccessible(true);

            if ($target === 'http') {
                $property->setValue($client, $counting);
                continue;
            }

            $resource = $property->getValue($client);
            $resourceHttp = (new \ReflectionClass($resource))->getProperty('http');
            $resourceHttp->setAccessible(true);
            $resourceHttp->setValue($resource, $counting);
        }

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame('Hello', $client->translate('Hello', null, 'greetings'));
        }

        $this->assertSame(1, $counting->calls, 'an \Error must be memoized like any other failure');

        $client->resetRequestState();
        $client->translate('Hello', null, 'greetings');

        $this->assertSame(1, $counting->calls, 'and the window outlives the request');
    }

    public function failingTransportProvider()
    {
        return [
            'malformed body' => [function ($mock) {
                $mock->setResponse('GET', 'translations', ['status' => true, 'data' => 'not a map']);
            }],
            'no data key' => [function ($mock) {
                $mock->setResponse('GET', 'translations', ['status' => true]);
            }],
        ];
    }

    public function absentDataProvider()
    {
        return [
            'status only, no data key' => [['status' => true]],
            // What handleResponse() hands back for an empty-bodied 2xx.
            'empty body'               => [[]],
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
     * A client talking to a server that returns $data as the translations map,
     * against a caller-supplied cache so several clients can share one.
     */
    private function clientAgainstServerMap($data, $cache, &$mockHttp = null)
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'read', 'write_enabled' => false],
        ]);
        $mockHttp->setResponse('GET', 'translations', ['status' => true, 'data' => $data]);

        $client = $this->createClientWithMockHttp($mockHttp, $cache);
        $client->setLocale('es-es');

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
    private function clientWithUnreachableApi($failureKind = 'exception')
    {
        $client = $this->createClientWithMockHttp(new MockHttpClient());
        $client->setLocale('es-es');

        $throwing = $failureKind === 'error'
            ? new ErrorThrowingHttpClient()
            : new ThrowingHttpClient();
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

    // =========================================================================
    // MARK-1 — a rendered block carries its resolved id
    // =========================================================================

    /**
     * The point is that identity survives the round trip: once the served HTML
     * carries its own id, a later reader takes it instead of re-deriving it
     * from the text - so a block whose text was edited, or canonicalised
     * differently by another SDK, is still recognised as the same block rather
     * than registered afresh.
     */
    public function testARenderedBlockIsStampedWithItsResolvedId()
    {
        $client = $this->clientWithCatalog(['UI' => []]);
        $parser = new \Langsys\SDK\Html\HtmlParser();
        $expected = $parser->generateCustomId('UI', ['Buy now', 'Later']);

        $rendered = $client->translateContentBlock('<div class="card"><p>Buy now</p><p>Later</p></div>', 'UI');

        $this->assertStringContainsString('data-ls-contentblock="' . $expected . '"', $rendered);
        $this->assertStringContainsString('class="card"', $rendered, 'the host element is otherwise untouched');
    }

    /**
     * Not stamped when there is no single host element.
     *
     * Picking the first child of a multi-root fragment would claim the whole
     * block's id for one of its siblings, and that sibling then reads as having
     * that identity the next time anything parses the page. Silence is the
     * honest outcome.
     */
    public function testAMultiRootBlockIsNotStamped()
    {
        $client = $this->clientWithCatalog(['UI' => []]);

        $rendered = $client->translateContentBlock('<p>One</p><p>Two</p>', 'UI');

        $this->assertStringNotContainsString('data-ls-contentblock', $rendered);
    }

    /**
     * An existing marker is another writer's identity claim and outranks ours -
     * under either spelling, or a JS-rendered host would be restamped with a
     * PHP-derived id on every server render.
     *
     * @dataProvider existingStampProvider
     */
    public function testAnExistingStampIsNeverOverwritten($attribute)
    {
        $client = $this->clientWithCatalog(['UI' => []]);

        $rendered = $client->translateContentBlock(
            '<div ' . $attribute . '="theirs"><p>Buy now</p><p>Later</p></div>',
            'UI'
        );

        $this->assertStringContainsString($attribute . '="theirs"', $rendered);
        $this->assertStringNotContainsString('data-ls-contentblock="' . md5('x'), $rendered);
        $this->assertSame(
            1,
            preg_match_all('/data-l(?:s|angsys)-contentblock=/', $rendered),
            'exactly one identity claim survives'
        );
    }

    public function existingStampProvider()
    {
        return [
            'JS core spelling'  => ['data-ls-contentblock'],
            'this SDK spelling' => ['data-langsys-contentblock'],
        ];
    }

    /**
     * A block whose lookup FAILED is not stamped: we do not know that the id we
     * derived is the one the catalog holds, and stamping a guess publishes an
     * identity claim built on an outage.
     */
    public function testABlockIsNotStampedWhenTheLookupFailed()
    {
        $client = $this->clientWithUnreachableApi();

        $rendered = $client->translateContentBlock('<div><p>Buy now</p><p>Later</p></div>', 'UI');

        $this->assertStringNotContainsString('data-ls-contentblock', $rendered);
    }

    // =========================================================================
    // CID-2 — the sentinel never reaches the hash
    // =========================================================================

    /**
     * '__uncategorized__' is this SDK's LOCAL spelling for "no category". The
     * hash input is the shared one, so the sentinel must be normalised away
     * before hashing or every uncategorised block has a PHP-only id.
     *
     * @dataProvider uncategorizedSpellingProvider
     */
    public function testTheUncategorizedSentinelNeverReachesTheHash($category)
    {
        $parser = new \Langsys\SDK\Html\HtmlParser();

        $this->assertSame(
            $parser->generateCustomId('', ['Buy now']),
            $parser->generateCustomId($category, ['Buy now']),
            'every spelling of "no category" must hash identically to the empty string'
        );
    }

    public function uncategorizedSpellingProvider()
    {
        return [
            'the sentinel' => ['__uncategorized__'],
            'null'         => [null],
            'empty string' => [''],
        ];
    }

    /**
     * Not stamped when the single element has TEXT siblings.
     *
     * `Buy <strong>now</strong>` counted one element and stamped the
     * `<strong>` with the whole fragment's id, while that element's own subtree
     * derives a different one - so the served HTML asserted an identity for a
     * node that does not have it, and any later reader believes the markup.
     * The earlier multi-root test used two ELEMENTS, so it never saw this.
     *
     * @dataProvider textSiblingProvider
     */
    public function testABlockWithTextSiblingsIsNotStamped($html)
    {
        $client = $this->clientWithCatalog(['UI' => []]);

        $rendered = $client->translateContentBlock($html, 'UI');

        $this->assertStringNotContainsString('data-ls-contentblock', $rendered);
    }

    public function textSiblingProvider()
    {
        return [
            'text before the element' => ['Buy <strong>now</strong>'],
            'text after the element'  => ['<strong>Buy</strong> now'],
            'text on both sides'      => ['Buy <strong>it</strong> now'],
        ];
    }

    /**
     * Positive control: the single-element case still stamps, so the guard
     * above did not simply disable stamping.
     */
    public function testASingleElementFragmentStillStamps()
    {
        $client = $this->clientWithCatalog(['UI' => []]);

        $this->assertStringContainsString(
            'data-ls-contentblock=',
            $client->translateContentBlock('<div><p>Buy now</p><p>Later</p></div>', 'UI')
        );
    }

    /**
     * F7: non-breaking padding around a translated run is not silently dropped.
     *
     * The edge checks that decide whether to re-add a leading/trailing space
     * used ASCII `\s`, so a run padded with U+00A0 lost its padding on apply -
     * words ran together in the rendered page. Now measured against the same
     * JavaScript set the collapse uses, so the two cannot drift apart.
     */
    public function testNonBreakingPaddingSurvivesApply()
    {
        // Keyed by the block's custom_id, which is how a content block's
        // translations are actually stored. A flat phrase catalog never
        // resolves here and would make the assertion below measure nothing.
        $parser = new \Langsys\SDK\Html\HtmlParser();
        $blockId = $parser->generateCustomId('UI', ['Buy now', 'Later']);

        $client = $this->clientWithCatalog(['UI' => [$blockId => ['Buy now' => 'Compra ya', 'Later' => 'Luego']]]);

        $rendered = $client->translateContentBlock("<p>\u{00A0}Buy now\u{00A0}</p><p>Later</p>", 'UI');

        $this->assertStringContainsString('Compra ya', $rendered, 'sanity: the run translated');
        $this->assertMatchesRegularExpression(
            '/>[\s\x{00A0}]Compra ya[\s\x{00A0}]</u',
            $rendered,
            'the padding either side must survive, or words run together on the page'
        );
    }

    /**
     * MARK-2, end to end on the page path: a JS-rendered host carrying
     * `data-ls-phrase` must NOT be re-split.
     *
     * A PHP page hosting a JS-rendered component is the ordinary case. If the
     * page walk does not recognise the other SDK's spelling it splits the host
     * at its tag boundaries, registers the pieces as separate phrases, and the
     * component's own single phrase is stranded.
     */
    public function testAJsRenderedHostIsNotReSplitOnThePagePath()
    {
        $client = $this->clientWithCatalog(['__uncategorized__' => []]);
        $client->translatePage('<html><body><p data-ls-phrase>Buy <strong>now</strong></p></body></html>');

        $queued = $this->queuedPhraseTexts($client);

        $this->assertNotContains('Buy', $queued, 'the host was split at the tag boundary');
        $this->assertNotContains('now', $queued, 'the host was split at the tag boundary');
    }

    /**
     * Positive control: without the marker the same markup IS split, so the
     * test above is measuring the marker and not a page path that registers
     * nothing.
     */
    public function testTheSameHostWithoutAMarkerIsSplit()
    {
        $client = $this->clientWithCatalog(['__uncategorized__' => []]);
        $client->translatePage('<html><body><p>Buy <strong>now</strong></p></body></html>');

        $queued = $this->queuedPhraseTexts($client);

        $this->assertNotEmpty($queued, 'the page path must register something, or the marker test is vacuous');
    }

    /**
     * Every phrase text this client has queued, from BOTH queues.
     *
     * A run that gets split lands in the content-block queue as separate
     * phrases, not in the phrase queue - so reading pendingPhrases alone
     * reports "nothing was split" for markup that was split into a block.
     */
    private function queuedPhraseTexts($client)
    {
        $reflection = new \ReflectionClass($client);

        $phrases = $reflection->getProperty('pendingPhrases');
        $phrases->setAccessible(true);
        $blocks = $reflection->getProperty('pendingContentBlocks');
        $blocks->setAccessible(true);

        $texts = [];
        foreach ($phrases->getValue($client) as $item) {
            if (isset($item['phrase'])) {
                $texts[] = $item['phrase'];
            }
        }
        foreach ($blocks->getValue($client) as $block) {
            foreach (isset($block['phrases']) ? $block['phrases'] : [] as $phrase) {
                $texts[] = is_array($phrase) && isset($phrase['phrase']) ? $phrase['phrase'] : $phrase;
            }
        }

        return $texts;
    }

    // =========================================================================
    // Canonicalisation sites that were live but unpinned
    // =========================================================================

    /**
     * The block-path TEXT lookup, placeholder half.
     *
     * `Canonical::phrase()` exists so capture and lookup cannot diverge, but the
     * test for it called the function twice and never reached a lookup site — so
     * the lookup half could be reverted with the suite green, which is exactly
     * the F1 shape the class was introduced to prevent, one commit later.
     *
     * A phrase authored `Hello %name%` is stored `Hello {name}`; the lookup must
     * canonicalise the rendered text the same way or nothing matches.
     */
    public function testABlockAuthoredWithPercentPlaceholdersResolves()
    {
        $parser = new \Langsys\SDK\Html\HtmlParser();
        $id = $parser->generateCustomId('UI', ['Hello {name}', 'Bye']);

        $client = $this->clientWithCatalog(['UI' => [$id => ['Hello {name}' => 'Hola {name}', 'Bye' => 'Adios']]]);

        $this->assertStringContainsString(
            'Hola Sarah',
            $client->translateContentBlock('<p>Hello %name%</p><p>Bye</p>', 'UI', ['name' => 'Sarah'])
        );
    }

    /**
     * The block-path ATTRIBUTE apply. Registration collapses the value, so the
     * apply side must too, or a wrapped alt renders untranslated.
     */
    public function testABlockAttributeWrappedAcrossLinesIsTranslated()
    {
        $parser = new \Langsys\SDK\Html\HtmlParser();
        $id = $parser->generateCustomId('UI', ['A long alt']);

        $client = $this->clientWithCatalog(['UI' => [$id => ['A long alt' => 'Alt traducido']]]);

        $this->assertStringContainsString(
            'alt="Alt traducido"',
            $client->translateContentBlock("<img alt=\"A long\n   alt\">", 'UI')
        );
    }

    /**
     * A trailing newline is not a sibling.
     *
     * Templates emit `"<strong>Buy now</strong>\n"` constantly. Counting raw
     * child nodes made that look like a multi-node fragment, so the ordinary
     * shape silently stopped being stamped when the text-sibling guard landed.
     *
     * @dataProvider insignificantEdgeTextProvider
     */
    public function testWhitespaceOnlyEdgeTextDoesNotBlockStamping($html)
    {
        $client = $this->clientWithCatalog(['UI' => []]);

        $this->assertStringContainsString(
            'data-ls-contentblock=',
            $client->translateContentBlock($html, 'UI')
        );
    }

    public function insignificantEdgeTextProvider()
    {
        return [
            'trailing newline'   => ["<strong>Buy <em>now</em></strong>\n"],
            'leading newline'    => ["\n<strong>Buy <em>now</em></strong>"],
            'both, and indented' => ["\n    <strong>Buy <em>now</em></strong>\n"],
            'non-breaking only'  => ["<strong>Buy <em>now</em></strong>\u{00A0}"],
        ];
    }

    /**
     * Control: REAL text beside the element still blocks stamping, which is the
     * case the guard exists for.
     */
    public function testRealTextStillBlocksStamping()
    {
        $client = $this->clientWithCatalog(['UI' => []]);

        $this->assertStringNotContainsString(
            'data-ls-contentblock',
            $client->translateContentBlock('Buy <strong>now</strong>', 'UI')
        );
    }
}
