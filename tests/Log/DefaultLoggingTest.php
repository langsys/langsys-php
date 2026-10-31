<?php

namespace Langsys\SDK\Tests\Log;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * REG-10's "always log": with no log file configured, a failed registration
 * still reaches PHP's error log - warnings and errors only - unless the app
 * turns that off.
 */
class DefaultLoggingTest extends TestCase
{
    /**
     * @var string
     */
    private $errorLog;

    /**
     * @var string|false
     */
    private $originalErrorLog;

    protected function setUp(): void
    {
        $this->errorLog = tempnam(sys_get_temp_dir(), 'langsys-errlog-');
        $this->originalErrorLog = ini_get('error_log');
        ini_set('error_log', $this->errorLog);
    }

    protected function tearDown(): void
    {
        ini_set('error_log', $this->originalErrorLog === false ? '' : $this->originalErrorLog);
        @unlink($this->errorLog);
    }

    private function client(array $options = [], array $authorize = ['key_type' => 'write', 'write_enabled' => true])
    {
        $http = new class extends MockHttpClient {
            public function post($endpoint, array $data = [])
            {
                parent::post($endpoint, $data);
                throw new LangsysException('The API refused the send');
            }
        };
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => $authorize]);
        $http->setResponse('GET', 'translations', ['data' => ['UI' => []]]);

        $client = new Client('test-api-key', 'project-id', array_merge(['cache' => new NullCache(), 'warn_runtime_requirements' => false], $options));
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

        return $client;
    }

    private function written(): string
    {
        return (string) file_get_contents($this->errorLog);
    }

    public function testAFailedSendReachesTheErrorLogWithNoLoggerConfigured(): void
    {
        $client = $this->client();
        $client->translate('Save', null, 'UI');
        $client->flushPendingRegistrations();

        $this->assertStringContainsString('[langsys] ERROR: Failed to register phrases', $this->written());
    }

    public function testNothingBelowWarningIsWritten(): void
    {
        $client = $this->client();
        $client->translate('Save', null, 'UI');

        $this->assertStringNotContainsString('queued for registration', $this->written(), 'debug stays out of the error log');
        $this->assertStringNotContainsString('Project authorized', $this->written(), 'info stays out of the error log');
    }

    public function testAKeyThatMayNotWriteDoesNotLogEveryRequest(): void
    {
        $client = $this->client([], ['key_type' => 'read', 'write_enabled' => false]);
        $client->translate('Save', null, 'UI');
        $client->flushPendingRegistrations();

        $this->assertSame('', $this->written());
    }

    public function testTheErrorLogCanBeTurnedOff(): void
    {
        $client = $this->client(['error_log' => false]);
        $client->translate('Save', null, 'UI');
        $client->flushPendingRegistrations();

        $this->assertSame('', $this->written());
    }

    public function testAnUnusableLogDirectoryFallsBackToTheErrorLog(): void
    {
        $blocker = tempnam(sys_get_temp_dir(), 'langsys-notadir-');
        $client = $this->client(['log_path' => $blocker . '/sub/langsys.log']);
        $client->translate('Save', null, 'UI');
        $client->flushPendingRegistrations();
        @unlink($blocker);

        $this->assertStringContainsString('Failed to register phrases', $this->written());
    }
}
