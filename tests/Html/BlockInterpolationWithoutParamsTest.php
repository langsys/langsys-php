<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Support\BuildsMockClient;
use PHPUnit\Framework\TestCase;

/**
 * ICU-1 on the content-block path: a block translation that holds ICU renders
 * its recovered branch when the caller passes no params, in text and in
 * attributes.
 */
class BlockInterpolationWithoutParamsTest extends TestCase
{
    use BuildsMockClient;

    const ICU = '{name_gender, select, male {Bienvenido} female {Bienvenida} other {Bienvenide}}';

    public function testABlockPhraseHoldingIcuRendersItsRecoveredBranch(): void
    {
        $id = (new HtmlParser())->generateCustomId('UI', ['Welcome', 'Friend']);
        $client = $this->mockClient(['UI' => [$id => ['Welcome' => self::ICU, 'Friend' => 'Amigo']]]);
        $client->setLocale('es-es');

        $this->assertStringContainsString('<p>Bienvenide</p><p>Amigo</p>', $client->translateContentBlock('<div><p>Welcome</p><p>Friend</p></div>', 'UI'));
    }

    public function testABlockAttributeHoldingIcuRendersItsRecoveredBranch(): void
    {
        $id = (new HtmlParser())->generateCustomId('UI', ['Look', 'Welcome', 'here']);
        $client = $this->mockClient(['UI' => [$id => ['Look' => 'Mira', 'Welcome' => self::ICU, 'here' => 'aqui']]]);
        $client->setLocale('es-es');

        $this->assertStringContainsString('alt="Bienvenide"', $client->translateContentBlock('<p>Look <img alt="Welcome"> here</p>', 'UI'));
    }
}
