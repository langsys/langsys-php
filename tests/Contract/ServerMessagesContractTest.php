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
    protected static $seed = 'server-messages';

    const TEMPLATE = 'The password must be at least {min} characters.';

    public function testATemplateFiledUnderTheMessagesCategoryIsFoundThereAndNowhereElse(): void
    {
        $world = $this->world('translated');
        $entry = ServerMessage::make('too_short', self::TEMPLATE, ['min' => 12], 'password');

        $client = $this->client($world->key('read'), $world->project());
        $client->setLocale('es-es');
        $this->assertSame('La contraseña debe tener al menos 12 caracteres.', $client->translateMessage($entry));

        $elsewhere = $this->client($world->key('read'), $world->project(), null, ['messages_category' => 'Validation']);
        $elsewhere->setLocale('es-es');
        $this->assertSame('The password must be at least 12 characters.', $elsewhere->translateMessage($entry));
    }

    public function testTheCommandRegistersOnlyWhatTheCatalogLacks(): void
    {
        $world = $this->world('one-listed');
        $catalog = MessageCatalogCommand::collect([new TwoTemplates()]);

        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('en-us');
        $this->assertSame(['registered' => 1, 'skipped' => 1], MessageCatalogCommand::register($catalog, $client));

        $again = $this->client($world->key('write'), $world->project());
        $again->setLocale('en-us');
        $this->assertSame(['registered' => 0, 'skipped' => 2], MessageCatalogCommand::register($catalog, $again), 'a second run registers nothing new');

        $this->assertSame(['Errors' => ['The name is required.', self::TEMPLATE]], $this->held($world->key('write'), $world->project(), 'en-us')['phrases']);
    }

    public function testAnEmittedTemplateTheCatalogLacksIsRegisteredUnderTheMessagesCategory(): void
    {
        $world = $this->world('writer');

        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('es-es');
        $client->emitMessage(ServerMessage::make('too_short', self::TEMPLATE, ['min' => 12], 'password'));
        $this->assertSame([], $this->held($world->key('write'), $world->project(), 'en-us')['phrases'], 'nothing is sent while the request is served');

        $client->flushPendingRegistrations();
        $this->assertSame(['Errors' => [self::TEMPLATE]], $this->held($world->key('write'), $world->project(), 'en-us')['phrases']);
    }

    public function testAKeyThatMayNotWriteRegistersNoEmittedTemplate(): void
    {
        $world = $this->world('reader');

        $client = $this->client($world->key('read'), $world->project());
        $client->setLocale('es-es');
        $client->emitMessage(ServerMessage::make('too_short', self::TEMPLATE, ['min' => 12], 'password'));
        $client->flushPendingRegistrations();

        $this->assertSame([], $this->held($world->key('read'), $world->project(), 'en-us')['phrases']);
    }
}
