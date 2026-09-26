<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Config;
use Langsys\SDK\Messages\ServerMessage;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

class FailingCatalogHttpClient extends MockHttpClient
{
    public function get($endpoint, array $params = [])
    {
        if (strpos($endpoint, 'translations') === 0) {
            parent::get($endpoint, $params);
            throw new \Langsys\SDK\Exception\ApiException('Server error', 500);
        }

        return parent::get($endpoint, $params);
    }
}

/**
 * MSG-5 / MSG-6 / MSG-8 on the client: render a server message from its
 * template, fall back to its message, file it under one category, and register
 * a template the catalog lacks after the response.
 */
class ClientMessagesTest extends TestCase
{
    const TEMPLATE = 'The password must be at least {min} characters.';

    /** @var MockHttpClient */
    private $http;

    private function client(array $catalog, array $options = [], array $auth = ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us'], MockHttpClient $http = null)
    {
        $this->http = $http ?: new MockHttpClient();
        $this->http->setResponse('GET', 'authorize-project/project-id', ['data' => $auth]);
        $this->http->setResponse('GET', 'translations', ['data' => $catalog]);
        $this->http->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = new Client('test-api-key', 'project-id', array_merge(['cache' => new NullCache()], $options));
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

    private function entry(array $params = ['min' => 12])
    {
        return ServerMessage::make('too_short', self::TEMPLATE, $params, 'password');
    }

    public function testMessagesUseTheErrorsCategoryUnlessConfigured(): void
    {
        $this->assertSame('Errors', (new Config([]))->getMessagesCategory());
        $this->assertSame('Validation', (new Config(['messages_category' => 'Validation']))->getMessagesCategory());
        $this->assertSame('Errors', $this->client([])->getConfig()->getMessagesCategory());
    }

    public function testATranslatedTemplateRendersWithItsParams(): void
    {
        $client = $this->client(['Errors' => [self::TEMPLATE => 'La contraseña debe tener al menos {min} caracteres.']]);
        $client->setLocale('es-es');

        $this->assertSame('La contraseña debe tener al menos 12 caracteres.', $client->translateMessage($this->entry()));
    }

    public function testAnUntranslatedTemplateFallsBackToTheServersMessage(): void
    {
        // de-DE would format 1234567 as 1.234.567 if the template were filled
        // here; the fallback is the message the server already filled.
        $entry = ServerMessage::make('too_long', 'The name must not be longer than {max} characters.', ['max' => 1234567], 'name');

        foreach ([[], ['Errors' => []], ['Errors' => ['The name must not be longer than {max} characters.' => null]], ['Errors' => ['The name must not be longer than {max} characters.' => '']]] as $catalog) {
            $client = $this->client($catalog);
            $client->setLocale('de-de');

            $this->assertSame('The name must not be longer than 1234567 characters.', $client->translateMessage($entry));
        }
    }

    public function testWithNoTranslationEveryCanonicalEntryRendersItsMessageByteForByte(): void
    {
        $canonical = [
            ServerMessage::make('project_limit_reached', "Your plan's project limit is {limit}.", ['limit' => 3]),
            ServerMessage::make('validation_failed', 'The request failed validation.'),
            ServerMessage::make('too_short', self::TEMPLATE, ['min' => 12], 'password'),
            ServerMessage::make('mismatch', 'The password confirmation does not match.', [], 'password'),
            ServerMessage::make('probe_quota_used', 'You have used {used} of your {limit} probes.', ['used' => 7, 'limit' => 10]),
            ServerMessage::make('too_long', 'The name must not be longer than {max} characters.', ['max' => 1234567], 'name'),
            ServerMessage::make('too_small', 'The amount must be at least {amount}.', ['amount' => 12.5], 'amount'),
        ];

        foreach (['es-es', 'de-de', null] as $locale) {
            $client = $this->client(['Errors' => []]);
            if ($locale !== null) {
                $client->setLocale($locale);
            }

            foreach ($canonical as $entry) {
                $this->assertSame($entry->getMessage(), $client->translateMessage($entry), ($locale ?: 'no locale') . ': ' . $entry->getTemplate());
            }
        }
    }

    public function testRenderingAndEmittingTheSameTemplateQueuesItOnce(): void
    {
        $client = $this->client(['Errors' => []]);
        $client->setLocale('es-es');

        $client->translateMessage($this->entry());
        $client->emitMessage($this->entry(['min' => 8]));

        $this->assertCount(1, $client->getPendingPhrases());
        $client->flushPendingRegistrations();
        $this->assertSame([[self::TEMPLATE, 'Errors']], $this->registered());
    }

    public function testAFailedLookupFallsBackToTheMessageWithoutThrowing(): void
    {
        $client = $this->client(['Errors' => [self::TEMPLATE => 'traducido']], [], ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us'], new FailingCatalogHttpClient());
        $client->setLocale('es-es');

        $this->assertSame('The password must be at least 12 characters.', $client->translateMessage($this->entry()));
    }

    public function testTheMessageIsNeverTheLookupKey(): void
    {
        $client = $this->client(['Errors' => ['The password must be at least 12 characters.' => 'WRONG']]);
        $client->setLocale('es-es');

        $this->assertSame('The password must be at least 12 characters.', $client->translateMessage($this->entry()));
        $this->assertNotContains('The password must be at least 12 characters.', array_column($client->getPendingPhrases(), 'phrase'));
    }

    public function testAPluralTranslationRendersThroughIcuFromACountParam(): void
    {
        $template = 'You have {count} probes left.';
        $client = $this->client(['Errors' => [$template => '{count, plural, one {Te queda # sonda.} other {Te quedan # sondas.}}']]);
        $client->setLocale('es-es');

        $this->assertSame('Te queda 1 sonda.', $client->translateMessage(ServerMessage::make('probes_left', $template, ['count' => 1])));
        $this->assertSame('Te quedan 3 sondas.', $client->translateMessage(ServerMessage::make('probes_left', $template, ['count' => 3])));
    }

    public function testATemplateIsOnlyFoundUnderTheConfiguredCategory(): void
    {
        $catalog = ['Validation' => [self::TEMPLATE => 'Al menos {min} caracteres.']];

        $default = $this->client($catalog);
        $default->setLocale('es-es');
        $this->assertSame('The password must be at least 12 characters.', $default->translateMessage($this->entry()));

        $configured = $this->client($catalog, ['messages_category' => 'Validation']);
        $configured->setLocale('es-es');
        $this->assertSame('Al menos 12 caracteres.', $configured->translateMessage($this->entry()));
    }

    public function testAnEntryArrayRendersLikeTheEntry(): void
    {
        $client = $this->client(['Errors' => [self::TEMPLATE => 'Al menos {min} caracteres.']]);
        $client->setLocale('es-es');

        $this->assertSame($client->translateMessage($this->entry()), $client->translateMessage($this->entry()->toArray()));
        $this->assertSame('Only a message.', $client->translateMessage(['message' => 'Only a message.']));
        $this->assertSame('', $client->translateMessage([]));
    }

    public function testTheCodeNeverChoosesTheText(): void
    {
        $client = $this->client(['Errors' => [self::TEMPLATE => 'Al menos {min} caracteres.']]);
        $client->setLocale('es-es');

        $this->assertSame(
            $client->translateMessage(ServerMessage::make('too_short', self::TEMPLATE, ['min' => 12])),
            $client->translateMessage(ServerMessage::make('some_other_code', self::TEMPLATE, ['min' => 12]))
        );
    }

    public function testAnEmittedTemplateTheCatalogLacksIsRegisteredAfterTheResponse(): void
    {
        $client = $this->client(['Errors' => []]);
        $client->setLocale('es-es');
        $entry = $this->entry();

        $this->assertSame($entry, $client->emitMessage($entry));
        $this->assertSame([], $this->registered(), 'nothing is sent while the request is being served');
        $this->assertTrue($client->hasPendingRegistrations());

        $client->flushPendingRegistrations();

        $this->assertSame([[self::TEMPLATE, 'Errors']], $this->registered());
    }

    public function testAnEmittedTemplateTheCatalogListsIsNotRegistered(): void
    {
        $client = $this->client(['Errors' => [self::TEMPLATE => null]]);
        $client->setLocale('es-es');

        $client->emitMessage($this->entry());

        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testAWriteDisabledKeyRegistersNothing(): void
    {
        $client = $this->client(['Errors' => []], [], ['key_type' => 'read', 'write_enabled' => false, 'base_locale' => 'en-us']);
        $client->setLocale('es-es');

        $client->emitMessage($this->entry());
        $client->flushPendingRegistrations();

        $this->assertSame([], $this->registered());
    }

    public function testAFailedCatalogLookupDoesNotRegisterOnAGuess(): void
    {
        $client = $this->client(['Errors' => []], [], ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us'], new FailingCatalogHttpClient());
        $client->setLocale('es-es');

        $client->emitMessage($this->entry());

        $this->assertFalse($client->hasPendingRegistrations());
    }

    /**
     * MSG-1 / MSG-5: an entry with only a message has nothing to look up. It is
     * shown as its message, and nothing is queued for it.
     */
    public function testAnEntryWithNoTemplateIsItsMessageAndRegistersNothing(): void
    {
        $client = $this->client(['Errors' => []]);
        $client->setLocale('es-es');
        $entry = ServerMessage::fromArray(['message' => 'Something went wrong.']);

        $this->assertSame('Something went wrong.', $client->translateMessage($entry));
        $client->emitMessage($entry);

        $this->assertFalse($client->hasPendingRegistrations());
    }
}
