<?php

namespace Langsys\SDK\Tests\Contract;

/**
 * ICU-5 against the contract fixture. The catalog serves a promoted plural as
 * its ICU message only to a client that sends `X-Langsys-Capabilities: icu`,
 * and the Client sends it on every request, so a count of 1 renders the
 * singular branch through translate().
 */
class IcuContractTest extends ContractTestCase
{
    protected static $seed = 'icu';

    const PLURAL = '{count, plural, one {# file} other {# files}}';

    public function testAtACountOfOneTheSingularBranchRenders(): void
    {
        $world = $this->world('plural');
        $client = $this->client($world->key('read'), $world->project());
        $client->setLocale('en');

        $this->assertSame('1 file', $client->translate('{count} files', null, 'UI', null, ['count' => 1]));
        $this->assertSame('3 files', $client->translate('{count} files', null, 'UI', null, ['count' => 3]));
    }

    /**
     * Control: the same catalog read without the header serves the phrase, so
     * the singular came from the ICU message.
     */
    public function testControlWithoutTheHeaderTheCatalogServesThePhrase(): void
    {
        $world = $this->world('plural');
        $path = '/translations?' . http_build_query(['project_id' => $world->project(), 'locale' => 'en']);

        $this->assertSame('{count} files', $this->api($path, ['key' => $world->key('read')])['body']['data']['UI']['{count} files']);
        $this->assertSame(self::PLURAL, $this->api($path, ['key' => $world->key('read'), 'headers' => ['X-Langsys-Capabilities: icu']])['body']['data']['UI']['{count} files']);
    }
}
