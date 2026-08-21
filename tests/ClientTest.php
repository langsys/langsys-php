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
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => [
                'key_type' => 'write',
                'langsys_settings' => [
                    'batch_limit' => 50,
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

        $cached = $cache->get('auth_project-id');

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
        $legacyIds = $parser->legacyCustomIds('Marketing', ['Brand new content']);

        foreach ($mockHttp->getRequests() as $request) {
            if ($request['method'] !== 'POST') {
                continue;
            }
            $body = json_encode($request['data']);
            foreach ($legacyIds as $legacyId) {
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
    // Helper methods
    // =========================================================================

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
