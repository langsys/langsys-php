<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Exception\ConfigurationException;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Messages\ServerMessage;
use Langsys\SDK\Snapshot\Snapshot;
use Langsys\SDK\Sync\SyncPlan;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use Langsys\SDK\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;

/**
 * A client built without an API key or project id (WIRE-4).
 *
 * It is unconfigured, not refused: an app's tests, CI, a fresh clone and an
 * image build run without credentials, and whatever builds the client must not
 * go down with it. Every read renders source text through the path a catalog
 * that failed to load takes, nothing is fetched or queued, one warning per
 * process names what is missing, and an explicit write throws.
 * `require_credentials` moves the exception to first use.
 *
 * Every client here points at a closed local port, so a request that escaped
 * would fail rather than reach a real API.
 */
class UnconfiguredClientTest extends TestCase
{
    /**
     * @var MockHttpClient
     */
    private $http;

    /**
     * @var \ReflectionProperty
     */
    private $warned;

    /**
     * @var array
     */
    private $env = [];

    /**
     * @var string|null
     */
    private $dir;

    protected function setUp(): void
    {
        foreach (['LANGSYS_API_KEY', 'LANGSYS_PROJECT_ID'] as $name) {
            $this->env[$name] = getenv($name);
            putenv($name);
        }

        $this->warned = new \ReflectionProperty(Client::class, 'unconfiguredWarned');
        $this->warned->setAccessible(true);
        $this->warned->setValue(null, false);
    }

    protected function tearDown(): void
    {
        // Back to the quiet state the bootstrap set.
        $this->warned->setValue(null, true);

        foreach ($this->env as $name => $value) {
            putenv($value === false ? $name : $name . '=' . $value);
        }

        if ($this->dir !== null && is_dir($this->dir)) {
            $paths = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::CHILD_FIRST
            );
            foreach ($paths as $path) {
                $path->isDir() ? @rmdir($path->getPathname()) : @unlink($path->getPathname());
            }
            @rmdir($this->dir);
        }
    }

    /**
     * A client whose every HTTP route records into $this->http.
     */
    private function client($apiKey = null, $projectId = null, array $options = [])
    {
        $client = new Client($apiKey, $projectId, array_merge([
            'api_url' => 'http://127.0.0.1:9',
            'cache' => new NullCache(),
            'warn_runtime_requirements' => false,
        ], $options));

        $this->http = new MockHttpClient();
        $reflection = new \ReflectionClass($client);

        $property = $reflection->getProperty('http');
        $property->setAccessible(true);
        $property->setValue($client, $this->http);

        foreach (['translations', 'translatableItems', 'utilities'] as $name) {
            $resource = $reflection->getProperty($name);
            $resource->setAccessible(true);
            $object = $resource->getValue($client);
            $inner = (new \ReflectionClass($object))->getProperty('http');
            $inner->setAccessible(true);
            $inner->setValue($object, $this->http);
        }

        return $client;
    }

    // ---------------------------------------------------------------
    // The state
    // ---------------------------------------------------------------

    public function testMissingCredentialsBuildAnUnconfiguredClient()
    {
        $this->assertFalse($this->client()->isConfigured());
    }

    public function testAClientWithBothIsConfigured()
    {
        $this->assertTrue($this->client('api-key', 'project-id')->isConfigured());
    }

    public function testEitherOneMissingIsUnconfigured()
    {
        $this->assertFalse($this->client('api-key', null)->isConfigured());
        $this->assertFalse($this->client(null, 'project-id')->isConfigured());
        $this->assertFalse($this->client('', '')->isConfigured());
    }

    public function testTheEnvironmentStillConfiguresIt()
    {
        putenv('LANGSYS_API_KEY=env-key');
        putenv('LANGSYS_PROJECT_ID=env-project');

        $this->assertTrue($this->client()->isConfigured());
    }

    public function testItCannotWrite()
    {
        $this->assertFalse($this->client()->canWrite());
        $this->assertSame([], $this->http->getRequests());
    }

    // ---------------------------------------------------------------
    // The warning
    // ---------------------------------------------------------------

    public function testItWarnsOncePerProcessNamingWhatIsMissing()
    {
        $logger = new SpyLogger();

        $this->client(null, null, ['logger' => $logger]);
        $this->client(null, null, ['logger' => $logger]);

        $warnings = $logger->messagesAt('warning');
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('not configured', $warnings[0]);
        $this->assertStringContainsString('LANGSYS_API_KEY', $warnings[0]);
        $this->assertStringContainsString('LANGSYS_PROJECT_ID', $warnings[0]);
    }

    public function testTheWarningNamesOnlyWhatIsMissing()
    {
        $logger = new SpyLogger();

        $this->client('api-key', null, ['logger' => $logger]);

        $warnings = $logger->messagesAt('warning');
        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('LANGSYS_PROJECT_ID', $warnings[0]);
        $this->assertStringNotContainsString('LANGSYS_API_KEY', $warnings[0]);
    }

    public function testAConfiguredClientDoesNotWarn()
    {
        $logger = new SpyLogger();

        $this->client('api-key', 'project-id', ['logger' => $logger]);

        $this->assertSame([], $logger->messagesAt('warning'));
    }

    /**
     * The warning is the one report: a read that degrades because the client
     * has no credentials logs nothing, on any path.
     */
    public function testNoReadLogsAnError()
    {
        $logger = new SpyLogger();
        $client = $this->client(null, null, ['logger' => $logger]);

        $client->translate('Hello', 'es-es');
        $client->translate('Hello');
        $client->translateRich('Read our <a href="/terms">terms</a>', 'es-es');
        $client->setLocale('es-es');
        $client->translateContentBlock('<p>Hello</p>', 'UI');
        $client->translateContentBlock('<div><p>One</p><p>Two</p></div>', 'UI');
        $client->translateContentBlock('<section><div data-ls-contentblock="block-id"><p>One</p><p>Two</p></div><p>Other</p></section>', 'UI');
        $client->translatePage('<html><body><div><p>One</p><p>Two</p></div></body></html>');
        $client->translateMessage(['message' => 'The email is required.', 'template' => 'The {field} is required.', 'params' => ['field' => 'email']]);

        $this->assertSame([], $logger->messagesAt('error'));
        $this->assertCount(1, $logger->messagesAt('warning'));
    }

    /**
     * FRM-3's once-per-process notice that translate() had no catalog is kept
     * for a client that has one to lose: the warning said why this one has none.
     */
    public function testTheNoCatalogNoticeIsLeftForAClientThatHasOne()
    {
        $noticed = new \ReflectionProperty(Client::class, 'catalogUnavailableNoticed');
        $noticed->setAccessible(true);
        $before = $noticed->getValue();
        $noticed->setValue(null, false);

        try {
            $logger = new SpyLogger();
            $this->client(null, null, ['logger' => $logger])->translate('Hello', 'es-es');

            $this->assertSame([], array_values(array_filter($logger->messagesAt('debug'), function ($message) {
                return strpos($message, 'No catalog to consult') !== false;
            })));
            $this->assertFalse($noticed->getValue());
        } finally {
            $noticed->setValue(null, $before);
        }
    }

    // ---------------------------------------------------------------
    // Reads render source text, and nothing is fetched or queued
    // ---------------------------------------------------------------

    public function testTranslateRendersTheSourceWithItsPlaceholdersFilled()
    {
        $client = $this->client();

        $this->assertSame('Hello, Sarah!', $client->translate('Hello, {name}!', 'es-es', 'Greetings', null, ['name' => 'Sarah']));
        $this->assertSame('1 item', $client->translate('{n, plural, one {# item} other {# items}}', 'es-es', 'UI', null, ['n' => 1]));
        $this->assertSame('Checkout', $client->translate('Checkout', 'fr-fr'));
        $this->assertSame('Hello, Sarah!', $client->translate('Hello, {name}!', null, null, null, ['name' => 'Sarah']));
    }

    public function testEveryReadPathRendersSourceAndSendsNothing()
    {
        $client = $this->client();
        $client->setLocale('es-es');

        $this->assertSame(['text' => 'Hi Sarah', 'from' => 'source'], $client->resolve('Hi {name}', null, 'UI', ['name' => 'Sarah']));
        $this->assertSame('Read our <a href="/terms">terms</a>, Sarah', $client->translateRich('Read our <a href="/terms">terms</a>, {name}', null, 'UI', ['name' => 'Sarah'])['html']);
        $this->assertStringContainsString('<p>Hi Sarah</p>', $client->translateContentBlock('<div><p>Hi {name}</p><p>Two</p></div>', 'UI', ['name' => 'Sarah']));
        $this->assertStringContainsString('<p>Page text</p>', $client->translatePage('<html><body><p>Page text</p></body></html>'));

        $entry = ['message' => 'The email is required.', 'template' => 'The {field} is required.', 'params' => ['field' => 'email']];
        $this->assertSame('The email is required.', $client->translateMessage($entry));
        $message = ServerMessage::fromArray($entry);
        $this->assertSame($message, $client->emitMessage($message));

        $this->assertSame([], $this->http->getRequests(), 'an unconfigured client never calls the API');
        $this->assertFalse($client->hasPendingRegistrations());
    }

    /**
     * Not an empty catalog: an empty catalog is a value, and a miss decided
     * against it would be queued.
     */
    public function testReadingTheCatalogThrowsAsAFailedFetchDoes()
    {
        $client = $this->client();

        try {
            $client->getTranslations('es-es');
            $this->fail('an unconfigured client has no catalog to return');
        } catch (ConfigurationException $e) {
            $this->assertStringContainsString('not configured', $e->getMessage());
        }

        $this->assertSame([], $this->http->getRequests());
    }

    /**
     * What an unconfigured client renders never depends on what another
     * process left in a shared cache.
     */
    public function testNoCacheIsRead()
    {
        $this->dir = sys_get_temp_dir() . '/langsys-unconfigured-' . bin2hex(random_bytes(4));
        $cache = new FileCache($this->dir);
        $cache->set('translations_project-id_es-es', ['UI' => ['Hello' => 'Hola']]);

        $client = $this->client(null, 'project-id', ['cache' => $cache]);

        $this->assertSame('Hello', $client->translate('Hello', 'es-es', 'UI'));
    }

    public function testRuntimeRegistrationOnStillQueuesNothing()
    {
        $client = $this->client(null, null, ['runtime_registration' => true]);
        $client->setLocale('es-es');

        $client->translate('Brand new phrase');
        $client->translateContentBlock('<div><p>Brand new</p><p>Block</p></div>', 'UI');

        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testQueuingDirectlyQueuesNothing()
    {
        $client = $this->client();

        $client->queuePhraseForRegistration('Hello', 'UI');
        $client->queueContentBlockForRegistration('<div><p>One</p><p>Two</p></div>', 'UI', 'block-id', ['One', 'Two']);

        $this->assertFalse($client->hasPendingRegistrations());
    }

    /**
     * The flush reports the skip as for any catalog that could not be read
     * (REG-10), and sends nothing.
     */
    public function testAFlushSendsNothingAndSaysTheCatalogWasUnavailable()
    {
        $client = $this->client();
        $client->setLocale('es-es');
        $client->translate('Hello');

        $result = $client->flushPendingRegistrations();

        $this->assertFalse($result['success']);
        $this->assertSame('catalog_unavailable', $result['reason']);
        $this->assertSame(0, $result['phrases']);
        $this->assertSame([], $this->http->getRequests());
    }

    /**
     * FRM-3's chain runs past the catalog it cannot read: the miss fallback
     * answers.
     */
    public function testTheMissFallbackStillAnswers()
    {
        $client = $this->client();
        $client->useMissFallback(function ($phrase) {
            return $phrase === 'Welcome back' ? 'Bienvenido de nuevo' : null;
        });

        $this->assertSame(['text' => 'Bienvenido de nuevo', 'from' => 'fallback'], $client->resolve('Welcome back', 'es-es'));
    }

    /**
     * A snapshot the app seeds answers, as it does with no network (SNAP-2).
     */
    public function testASeededSnapshotStillAnswers()
    {
        $payload = [
            'project_id' => 'project-id',
            'generated_at' => '2026-09-25T00:00:00Z',
            'base_locale' => 'en-us',
            'locales' => ['es-es'],
            'categories' => ['UI'],
            'catalog' => ['es-es' => ['UI' => [
                'Save' => 'Guardar',
                (new HtmlParser())->generateCustomId('UI', ['One', 'Two']) => ['One' => 'Uno', 'Two' => 'Dos'],
            ]]],
        ];
        $document = ['format' => Snapshot::FORMAT, 'version' => Snapshot::VERSION] + $payload + ['checksum' => 'sha256:' . hash('sha256', Snapshot::canonical($payload))];

        $client = $this->client(null, 'project-id', ['snapshot' => Snapshot::load(json_encode($document))]);
        $client->setLocale('es-es');

        $this->assertSame('Guardar', $client->translate('Save', null, 'UI'));
        $this->assertStringContainsString('<p>Uno</p>', $client->translateContentBlock('<div><p>One</p><p>Two</p></div>', 'UI'));
        $this->assertSame('Unknown', $client->translate('Unknown', null, 'UI'));
        $this->assertSame([], $this->http->getRequests());
    }

    /**
     * MIG-2's lookup reads local files, so a key still renders the value its
     * source file holds.
     */
    public function testTheMigrationModeStillResolvesKeysFromLocalFiles()
    {
        $this->dir = sys_get_temp_dir() . '/langsys-unconfigured-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/lang', 0777, true);
        file_put_contents($this->dir . '/lang/en.json', json_encode([
            'checkout' => ['submit' => 'Place order', 'greeting' => 'Hello {{name}}'],
            'cart' => ['items_one' => '{{count}} item', 'items_other' => '{{count}} items'],
        ]));

        $client = $this->client(null, null, ['migration' => ['files' => [['path' => $this->dir . '/lang/en.json', 'format' => 'i18next']]]]);
        $client->setLocale('es-es');

        $this->assertSame('Place order', $client->translate('checkout.submit'));
        $this->assertSame('Hello Sarah', $client->translate('checkout.greeting', null, null, null, ['name' => 'Sarah']));
        $this->assertSame('1 item', $client->translate('cart.items', null, null, null, ['count' => 1]));
        $this->assertSame([], $this->http->getRequests());
        $this->assertFalse($client->hasPendingRegistrations());
    }

    // ---------------------------------------------------------------
    // Writes throw
    // ---------------------------------------------------------------

    public function writes()
    {
        return [
            'registerPhrases' => ['register phrases', function (Client $client) {
                $client->registerPhrases(['Checkout']);
            }],
            'registerContentBlock' => ['register a content block', function (Client $client) {
                $client->registerContentBlock('<p>Hi</p>');
            }],
            'sync' => ['sync', function (Client $client) {
                $client->sync([['phrase' => 'Checkout', 'category' => 'UI']], 'es-es');
            }],
            'planSync' => ['plan a sync', function (Client $client) {
                $client->planSync([]);
            }],
            'applySync' => ['apply a sync', function (Client $client) {
                $client->applySync(new SyncPlan([], [], []));
            }],
            'importLegacyTranslations' => ['import translations', function (Client $client) {
                $client->importLegacyTranslations(['es-es' => ['files' => []]]);
            }],
        ];
    }

    /**
     * @dataProvider writes
     */
    public function testAWriteThrowsSayingTheClientIsNotConfigured($action, callable $write)
    {
        $client = $this->client();

        try {
            $write($client);
            $this->fail('an explicit write must refuse');
        } catch (ConfigurationException $e) {
            $this->assertStringStartsWith('Cannot ' . $action . ': the client is not configured', $e->getMessage());
            $this->assertStringContainsString('LANGSYS_API_KEY', $e->getMessage());
        }

        $this->assertSame([], $this->http->getRequests());
    }

    /**
     * A resource reached through the client sends nothing either: the HTTP
     * client refuses before any connection is made.
     */
    public function testTheResourcesSendNothing()
    {
        $client = new Client(null, null, [
            'api_url' => 'http://127.0.0.1:9',
            'cache' => new NullCache(),
            'warn_runtime_requirements' => false,
        ]);

        $this->expectException(ConfigurationException::class);

        $client->translations()->getFlat('es-es');
    }

    // ---------------------------------------------------------------
    // require_credentials: the exception at first use
    // ---------------------------------------------------------------

    public function reads()
    {
        return [
            'translate' => [function (Client $client) {
                $client->translate('Hello', 'es-es');
            }],
            'resolve' => [function (Client $client) {
                $client->resolve('Hello', 'es-es');
            }],
            'translateRich' => [function (Client $client) {
                $client->translateRich('Read our <a href="/terms">terms</a>', 'es-es');
            }],
            'translateContentBlock' => [function (Client $client) {
                $client->translateContentBlock('<div><p>One</p><p>Two</p></div>', 'UI');
            }],
            'translatePage' => [function (Client $client) {
                $client->setLocale('es-es');
                $client->translatePage('<html><body><p>Hello</p></body></html>');
            }],
            'translateMessage' => [function (Client $client) {
                $client->translateMessage(['message' => 'The email is required.', 'template' => 'The {field} is required.']);
            }],
            'emitMessage' => [function (Client $client) {
                $client->emitMessage(ServerMessage::fromArray(['message' => 'Saved.', 'template' => 'Saved.']));
            }],
            'resolveRequestLocale' => [function (Client $client) {
                $client->resolveRequestLocale([], ['send_vary' => false]);
            }],
            'getTranslations' => [function (Client $client) {
                $client->getTranslations('es-es');
            }],
            'authorize' => [function (Client $client) {
                $client->authorize();
            }],
        ];
    }

    /**
     * @dataProvider reads
     */
    public function testWithRequireCredentialsEveryReadThrowsAtFirstUse(callable $read)
    {
        $client = $this->client(null, 'project-id', ['require_credentials' => true]);
        // A set locale, so no read reaches the request-locale resolver's own
        // guard before its own.
        $client->setLocale('es-es');

        try {
            $read($client);
            $this->fail('require_credentials must surface the missing key at first use');
        } catch (ConfigurationException $e) {
            $this->assertSame('API key is required. Set LANGSYS_API_KEY environment variable or pass it to the constructor.', $e->getMessage());
        }

        $this->assertSame([], $this->http->getRequests());
    }

    public function testWithRequireCredentialsTheClientStillBuildsAndDoesNotWarn()
    {
        $logger = new SpyLogger();

        $client = $this->client('api-key', null, ['require_credentials' => true, 'logger' => $logger]);

        $this->assertFalse($client->isConfigured());
        $this->assertSame([], $logger->messagesAt('warning'));

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('Project ID is required');

        $client->translate('Hello', 'es-es');
    }

    public function testWithRequireCredentialsAWriteThrowsTheSameException()
    {
        $client = $this->client(null, 'project-id', ['require_credentials' => true]);

        $this->expectException(ConfigurationException::class);
        $this->expectExceptionMessage('API key is required');

        $client->registerPhrases(['Checkout']);
    }

    /**
     * Read the way an environment string reads: "false" is off.
     */
    public function testRequireCredentialsReadsAStringAsABoolean()
    {
        $this->assertSame('Hello', $this->client(null, null, ['require_credentials' => 'false'])->translate('Hello', 'es-es'));

        $this->expectException(ConfigurationException::class);

        $this->client(null, null, ['require_credentials' => 'true'])->translate('Hello', 'es-es');
    }

    public function testAConfiguredClientIgnoresRequireCredentials()
    {
        $client = $this->client('api-key', 'project-id', ['require_credentials' => true]);
        $this->http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'read', 'write_enabled' => false, 'base_locale' => 'en-us']]);
        $this->http->setResponse('GET', 'translations', ['data' => ['UI' => ['Hello' => 'Hola']]]);

        $this->assertSame('Hola', $client->translate('Hello', 'es-es', 'UI'));
    }
}
