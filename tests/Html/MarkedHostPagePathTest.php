<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Tests\Support\BuildsMockClient;
use PHPUnit\Framework\TestCase;

/**
 * MARK-2 on the page path's declared-block host: a container carrying the
 * content-block marker, in either spelling, registers as one block - never split
 * into the phrases its children would be without it.
 */
class MarkedHostPagePathTest extends TestCase
{
    use BuildsMockClient;

    public function testADeclaredBlockHostIsOneBlockUnderEitherSpelling(): void
    {
        foreach (['data-ls-contentblock', 'data-langsys-contentblock'] as $marker) {
            $client = $this->mockClient(['__uncategorized__' => []]);
            $client->setLocale('es-es');
            $client->translatePage("<html><body><div $marker><p>Alpha</p><p>Beta</p></div></body></html>");
            $client->flushPendingRegistrations();

            $this->assertSame([['block', ['Alpha', 'Beta']]], $this->registered(), $marker);
        }
    }

    public function testWithoutTheMarkerTheSameContainerSplits(): void
    {
        $client = $this->mockClient(['__uncategorized__' => []]);
        $client->setLocale('es-es');
        $client->translatePage('<html><body><div><p>Alpha</p><p>Beta</p></div></body></html>');
        $client->flushPendingRegistrations();

        $this->assertSame([['phrase', 'Alpha'], ['phrase', 'Beta']], $this->registered());
    }
}
