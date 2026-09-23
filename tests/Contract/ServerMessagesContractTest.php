<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Messages\MessageCatalog;
use Langsys\SDK\Messages\MessageCatalogCommand;
use Langsys\SDK\Messages\MessageSource;
use Langsys\SDK\Messages\ServerMessage;

class TwoTemplates implements MessageSource
{
    public function collect(MessageCatalog $catalog)
    {
        $catalog->add('The name is required.', 'SampleApp\\SignupRequest', 'name');
        $catalog->add('The password must be at least {min} characters.', 'SampleApp\\SignupRequest', 'password');
    }
}

/**
 * MSG-6, MSG-7 and MSG-8 against the contract fixture: one category on both
 * sides, the build-time command registering only what the catalog lacks, and a
 * template emitted at runtime registered after the response.
 */
class ServerMessagesContractTest extends ContractTestCase
{
    const TEMPLATE = 'The password must be at least {min} characters.';

    public function testATemplateFiledUnderTheMessagesCategoryIsFoundThereAndNowhereElse(): void
    {
        $this->seedProject(['k-read' => ['type' => 'read']], ['phrases' => [['category' => 'Errors', 'phrase' => self::TEMPLATE, 'translations' => ['es-es' => 'La contraseña debe tener al menos {min} caracteres.']]]]);
        $entry = ServerMessage::make('too_short', self::TEMPLATE, ['min' => 12], 'password');

        $client = $this->client('k-read');
        $client->setLocale('es-es');
        $this->assertSame('La contraseña debe tener al menos 12 caracteres.', $client->translateMessage($entry));

        $elsewhere = $this->client('k-read', null, ['messages_category' => 'Validation']);
        $elsewhere->setLocale('es-es');
        $this->assertSame('The password must be at least 12 characters.', $elsewhere->translateMessage($entry));
    }

    public function testTheCommandRegistersOnlyWhatTheCatalogLacks(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']], ['phrases' => [['category' => 'Errors', 'phrase' => 'The name is required.']]]);
        $catalog = MessageCatalogCommand::collect([new TwoTemplates()]);

        $client = $this->client('k-write');
        $client->setLocale('en-us');
        $this->assertSame(['registered' => 1, 'skipped' => 1], MessageCatalogCommand::register($catalog, $client));

        $again = $this->client('k-write');
        $again->setLocale('en-us');
        $this->assertSame(['registered' => 0, 'skipped' => 2], MessageCatalogCommand::register($catalog, $again), 'a second run registers nothing new');

        $this->assertEqualsCanonicalizing([['Errors', 'The name is required.'], ['Errors', self::TEMPLATE]], $this->registeredPhrases());
    }

    public function testAnEmittedTemplateTheCatalogLacksIsRegisteredUnderTheMessagesCategory(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']]);

        $client = $this->client('k-write');
        $client->setLocale('es-es');
        $client->emitMessage(ServerMessage::make('too_short', self::TEMPLATE, ['min' => 12], 'password'));
        $this->assertSame([], $this->registeredPhrases(), 'nothing is sent while the request is served');

        $client->flushPendingRegistrations();
        $this->assertSame([['Errors', self::TEMPLATE]], $this->registeredPhrases());
    }

    public function testAKeyThatMayNotWriteRegistersNoEmittedTemplate(): void
    {
        $this->seedProject(['k-read' => ['type' => 'read']]);

        $client = $this->client('k-read');
        $client->setLocale('es-es');
        $client->emitMessage(ServerMessage::make('too_short', self::TEMPLATE, ['min' => 12], 'password'));
        $client->flushPendingRegistrations();

        $this->assertSame([], $this->registeredPhrases());
    }
}
