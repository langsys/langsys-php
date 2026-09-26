<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Messages\MessageCatalog;
use Langsys\SDK\Messages\MessageCatalogCommand;
use Langsys\SDK\Messages\MessageSource;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

class ListedSource implements MessageSource
{
    private $templates;
    private $problems;

    public function __construct(array $templates, array $problems = [])
    {
        $this->templates = $templates;
        $this->problems = $problems;
    }

    public function collect(MessageCatalog $catalog)
    {
        foreach ($this->templates as $template) {
            $catalog->add($template, 'SampleApp\\SignupRequest');
        }

        foreach ($this->problems as $problem) {
            call_user_func_array([$catalog, 'problem'], $problem);
        }
    }
}

/**
 * MSG-7: the build-time command lists every template, fails the build naming
 * each message it cannot list, and registers idempotently.
 */
class MessageCatalogCommandTest extends TestCase
{
    const TEMPLATES = ['The name is required.', 'The password must be at least {min} characters.'];

    /** @var MockHttpClient */
    private $http;

    private $out;
    private $err;

    protected function setUp(): void
    {
        $this->out = fopen('php://memory', 'w+');
        $this->err = fopen('php://memory', 'w+');
        $this->http = new MockHttpClient();
    }

    private function client(array $catalog, array $auth = ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us'])
    {
        $this->http->setResponse('GET', 'authorize-project/project-id', ['data' => $auth]);
        $this->http->setResponse('GET', 'translations', ['data' => $catalog]);
        $this->http->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache()]);
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

    private function read($stream)
    {
        rewind($stream);

        return stream_get_contents($stream);
    }

    private function registered()
    {
        $items = [];

        foreach ($this->http->getRequests() as $request) {
            if ($request['method'] === 'POST') {
                foreach ($request['data']['translatable_items'] as $item) {
                    $items[] = [$item['phrase'], $item['category']];
                }
            }
        }

        return $items;
    }

    public function testListingPrintsTheCountAndExitsZero(): void
    {
        $code = MessageCatalogCommand::run([new ListedSource(self::TEMPLATES)], null, [], $this->out, $this->err);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('2 message templates', $this->read($this->out));
        $this->assertSame('', $this->read($this->err));
    }

    public function testVerboseListsEachTemplateWithItsSource(): void
    {
        MessageCatalogCommand::run([new ListedSource(self::TEMPLATES)], null, ['verbose' => true], $this->out, $this->err);

        $this->assertStringContainsString('The name is required.  SampleApp\\SignupRequest', $this->read($this->out));
    }

    /**
     * MSG-7: a message that cannot be listed is reported with an actionable
     * line and the command exits zero - it registers when first sent (MSG-8) -
     * unless the app asks for a strict build.
     */
    public function testAMessageThatCannotBeListedIsReportedAndFailsOnlyUnderStrict(): void
    {
        $source = new ListedSource(self::TEMPLATES, [['SampleApp\\SignupRequest', 'uses a closure rule, which declares no template', 'replace it with a rule that declares its template', 'terms']]);

        $code = MessageCatalogCommand::run([$source], null, [], $this->out, $this->err);
        $err = $this->read($this->err);

        $this->assertSame(0, $code);
        $this->assertStringContainsString('✗ SampleApp\\SignupRequest.terms: uses a closure rule, which declares no template — replace it with a rule that declares its template', $err);
        $this->assertStringContainsString('1 message cannot be registered ahead of time', $err);

        $this->assertSame(1, MessageCatalogCommand::run([$source], null, ['strict' => true], $this->out, $this->err));
    }

    public function testRegisterFilesEveryTemplateUnderTheMessagesCategory(): void
    {
        $code = MessageCatalogCommand::run([new ListedSource(self::TEMPLATES)], $this->client(['Errors' => []]), ['register' => true], $this->out, $this->err);

        $this->assertSame(0, $code, $this->read($this->err));
        $this->assertSame([[self::TEMPLATES[0], 'Errors'], [self::TEMPLATES[1], 'Errors']], $this->registered());
        $this->assertStringContainsString('Registered 2 templates under "Errors"', $this->read($this->out));
    }

    public function testASecondRunRegistersNothingNew(): void
    {
        $listed = array_fill_keys(self::TEMPLATES, null);

        $code = MessageCatalogCommand::run([new ListedSource(self::TEMPLATES)], $this->client(['Errors' => $listed]), ['register' => true], $this->out, $this->err);

        $this->assertSame(0, $code, $this->read($this->err));
        $this->assertSame([], $this->registered());
        $this->assertStringContainsString('nothing new to register', $this->read($this->out));
    }

    public function testAReportedMessageDoesNotHoldBackTheListedOnes(): void
    {
        $source = new ListedSource(self::TEMPLATES, [['SampleApp\\SignupRequest', 'has no label', 'add it to attributes()', 'cc_number']]);

        $code = MessageCatalogCommand::run([$source], $this->client(['Errors' => []]), ['register' => true], $this->out, $this->err);

        $this->assertSame(0, $code);
        $this->assertEqualsCanonicalizing(self::TEMPLATES, array_column($this->registered(), 0));
    }

    public function testStrictRegistersNothingWhenAMessageCannotBeListed(): void
    {
        $source = new ListedSource(self::TEMPLATES, [['SampleApp\\SignupRequest', 'has no label', 'add it to attributes()', 'cc_number']]);

        $code = MessageCatalogCommand::run([$source], $this->client(['Errors' => []]), ['register' => true, 'strict' => true], $this->out, $this->err);

        $this->assertSame(1, $code);
        $this->assertSame([], $this->registered());
    }

    public function testCollectAndRegisterWorkWithoutStreams(): void
    {
        $catalog = MessageCatalogCommand::collect([new ListedSource(self::TEMPLATES)]);

        $this->assertInstanceOf(MessageCatalog::class, $catalog);
        $this->assertCount(2, $catalog->templates());

        $result = MessageCatalogCommand::register($catalog, $this->client(['Errors' => [self::TEMPLATES[0] => null]]));

        $this->assertSame(['registered' => 1, 'skipped' => 1], $result);
        $this->assertSame([[self::TEMPLATES[1], 'Errors']], $this->registered());
    }

    public function testRegisterWithoutAClientFails(): void
    {
        $this->assertSame(1, MessageCatalogCommand::run([new ListedSource(self::TEMPLATES)], null, ['register' => true], $this->out, $this->err));
        $this->assertStringContainsString('client', $this->read($this->err));
    }

    public function testRegisterWithAKeyThatCannotWriteFailsAndSendsNothing(): void
    {
        $client = $this->client(['Errors' => []], ['key_type' => 'read', 'write_enabled' => false, 'base_locale' => 'en-us']);

        $this->assertSame(1, MessageCatalogCommand::run([new ListedSource(self::TEMPLATES)], $client, ['register' => true], $this->out, $this->err));
        $this->assertSame([], $this->registered());
        $this->assertStringContainsString('write', $this->read($this->err));
    }

    public function testTheCliExitsNonZeroOnAProblemOnlyUnderStrict(): void
    {
        $bin = dirname(__DIR__, 2) . '/bin/langsys-messages';
        $withProblem = escapeshellarg(dirname(__DIR__) . '/fixtures/messages/with-problem.php');

        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' --config=' . escapeshellarg(dirname(__DIR__) . '/fixtures/messages/clean.php') . ' 2>&1', $cleanOut, $cleanCode);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' --config=' . $withProblem . ' 2>&1', $badOut, $badCode);
        exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' --strict --config=' . $withProblem . ' 2>&1', $strictOut, $strictCode);

        $this->assertSame(0, $cleanCode, implode("\n", $cleanOut));
        $this->assertStringContainsString('1 message templates', implode("\n", $cleanOut));
        $this->assertSame(0, $badCode, implode("\n", $badOut));
        $this->assertStringContainsString('✗ SampleApp\\Errors\\QuotaError: uses the marker {limit} but has no $limit property to fill it', implode("\n", $badOut));
        $this->assertSame(1, $strictCode, implode("\n", $strictOut));
    }
}
