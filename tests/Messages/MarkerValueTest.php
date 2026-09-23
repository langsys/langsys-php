<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Messages\ServerMessage;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use Langsys\SDK\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;

/**
 * MSG-3 / MSG-11 at render: a marker whose value is itself a phrase in the
 * project's catalog carries translatable text, and is warned once per template
 * and marker. A value the catalog does not hold - an order code, raw input - is
 * the accepted case.
 */
class MarkerValueTest extends TestCase
{
    const TEMPLATE = 'The order is {status}.';

    /**
     * @var SpyLogger
     */
    private $logger;

    private function client()
    {
        $http = new MockHttpClient();
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'read', 'write_enabled' => false]]);
        $http->setResponse('GET', 'translations', ['data' => [
            'Errors' => [self::TEMPLATE => 'El pedido está {status}.', 'At least {min} characters.' => 'Al menos {min} caracteres.'],
            // '12' is page text too: a number in a marker stays the accepted case.
            'UI' => ['shipped' => 'enviado', '12' => '12'],
        ]]);

        $this->logger = new SpyLogger();
        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'logger' => $this->logger]);
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

    private function message($status)
    {
        return new ServerMessage('order_status', 'The order is ' . $status . '.', self::TEMPLATE, ['status' => $status]);
    }

    private function reports(): array
    {
        return array_values(array_filter($this->logger->entries, function ($entry) {
            return $entry['level'] === 'warning' && strpos($entry['message'], 'translatable text in a marker') !== false;
        }));
    }

    public function testAStatusTheAppAlsoTranslatesIsReportedOnce(): void
    {
        $client = $this->client();

        foreach ([1, 2, 3] as $unused) {
            $this->assertSame('El pedido está shipped.', $client->translateMessage($this->message('shipped')));
        }

        $reports = $this->reports();
        $this->assertCount(1, $reports, 'once per template and marker');
        $this->assertSame(['template' => self::TEMPLATE, 'marker' => 'status', 'value' => 'shipped'], $reports[0]['context']);
    }

    public function testEmittingTheMessageReportsItToo(): void
    {
        $client = $this->client();
        $client->emitMessage($this->message('shipped'));

        $this->assertCount(1, $this->reports());
    }

    /**
     * @dataProvider acceptedValueProvider
     */
    public function testANonTranslatableValueIsTheAcceptedCase($template, array $params): void
    {
        $client = $this->client();
        $client->translateMessage(new ServerMessage('x', 'x', $template, $params));
        $client->emitMessage(new ServerMessage('x', 'x', $template, $params));

        $this->assertSame([], $this->reports());
    }

    public function acceptedValueProvider(): array
    {
        return [
            'an order code' => [self::TEMPLATE, ['status' => 'A-1042']],
            'a number' => ['At least {min} characters.', ['min' => 12]],
            'a numeric string' => ['At least {min} characters.', ['min' => '12']],
        ];
    }
}
