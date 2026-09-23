<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Support\BuildsMockClient;
use PHPUnit\Framework\TestCase;

/**
 * ICU-1 on the page path: a translation that holds ICU renders its recovered
 * branch when the caller passes no params, as translate() and
 * translateContentBlock() already do. Every page-path site is covered, because
 * each one interpolates separately.
 */
class PageInterpolationWithoutParamsTest extends TestCase
{
    use BuildsMockClient;

    const ICU = '{name_gender, select, male {Bienvenido} female {Bienvenida} other {Bienvenide}}';

    public function testAPhraseHoldingIcuRendersItsRecoveredBranch(): void
    {
        $client = $this->mockClient(['__uncategorized__' => ['Welcome' => self::ICU]]);
        $client->setLocale('es-es');

        $this->assertSame('<p>Bienvenide</p>', self::body($client->translatePage('<html><body><p>Welcome</p></body></html>')));
    }

    public function testATitleHoldingIcuRendersItsRecoveredBranch(): void
    {
        $client = $this->mockClient(['__uncategorized__' => ['Welcome' => self::ICU]]);
        $client->setLocale('es-es');

        $page = $client->translatePage('<html><head><title>Welcome</title></head><body><p>Body</p></body></html>');

        $this->assertStringContainsString('<title>Bienvenide</title>', $page);
    }

    public function testAPhraseInsideABlockHoldingIcuRendersItsRecoveredBranch(): void
    {
        // One element with inline markup is a block on the page path; sibling
        // paragraphs are separate phrases.
        $id = (new HtmlParser())->generateCustomId('__uncategorized__', ['Welcome', 'Friend']);
        $client = $this->mockClient(['__uncategorized__' => [$id => ['Welcome' => self::ICU, 'Friend' => 'Amigo']]]);
        $client->setLocale('es-es');

        $this->assertSame(
            '<p data-ls-contentblock="' . $id . '">Bienvenide <b>Amigo</b></p>',
            self::body($client->translatePage('<html><body><p>Welcome <b>Friend</b></p></body></html>'))
        );
    }

    public function testAnAttributeInsideABlockHoldingIcuRendersItsRecoveredBranch(): void
    {
        $id = (new HtmlParser())->generateCustomId('__uncategorized__', ['Look', 'Welcome', 'here']);
        $client = $this->mockClient(['__uncategorized__' => [$id => ['Look' => 'Mira', 'Welcome' => self::ICU, 'here' => 'aqui']]]);
        $client->setLocale('es-es');

        $this->assertStringContainsString(
            'alt="Bienvenide"',
            $client->translatePage('<html><body><p>Look <img alt="Welcome"> here</p></body></html>')
        );
    }

    public function testParamsStillFillWhenTheyArePassed(): void
    {
        $client = $this->mockClient(['__uncategorized__' => ['Welcome' => self::ICU]]);
        $client->setLocale('es-es');

        $this->assertSame('<p>Bienvenida</p>', self::body($client->translatePage('<html><body><p>Welcome</p></body></html>', null, [], ['name_gender' => 'female'])));
    }

    public function testSourceTextWithAnUnfilledPlaceholderIsLeftAsWritten(): void
    {
        $client = $this->mockClient(['__uncategorized__' => []]);
        $client->setLocale('es-es');

        $this->assertSame('<p>Hello {name}</p>', self::body($client->translatePage('<html><body><p>Hello {name}</p></body></html>')));
    }
}
