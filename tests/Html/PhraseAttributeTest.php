<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the data-langsys-phrase keep-together primitive.
 *
 * Without it, PHP splits inline markup at tag boundaries, so
 * `<p>Based on {n} <strong>reviews</strong></p>` becomes the separate catalog
 * entries "Based on {n}" and "reviews". That puts the count in a different
 * phrase from the noun it inflects, and no ICU plural rule can reach across
 * that boundary - fatal in Russian, Arabic and Polish, where the noun form
 * depends on the number.
 */
class PhraseAttributeTest extends TestCase
{
    /**
     * @var HtmlParser
     */
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
    }

    protected function makeClient(array $translations)
    {
        $mock = new MockHttpClient();
        $mock->setResponse('GET', 'authorize-project/p', ['data' => ['key_type' => 'read']]);
        $mock->setResponse('GET', 'translations', ['data' => $translations]);

        $client = new Client('k', 'p', ['cache' => new NullCache()]);
        $reflection = new \ReflectionClass($client);

        $http = $reflection->getProperty('http');
        $http->setAccessible(true);
        $http->setValue($client, $mock);

        foreach (['translations', 'translatableItems'] as $prop) {
            $p = $reflection->getProperty($prop);
            $p->setAccessible(true);
            $resource = $p->getValue($client);

            $rr = new \ReflectionClass($resource);
            $rh = $rr->getProperty('http');
            $rh->setAccessible(true);
            $rh->setValue($resource, $mock);
        }

        return $client;
    }

    protected function paragraphOf($html)
    {
        preg_match('#<p[^>]*>.*?</p>#s', $html, $m);

        return isset($m[0]) ? html_entity_decode($m[0], ENT_QUOTES, 'UTF-8') : '';
    }

    // ---------------------------------------------------------------
    // Extraction
    // ---------------------------------------------------------------

    public function testWithoutMarkerInlineMarkupIsSplit()
    {
        $this->assertEquals(
            ['Based on {n}', 'reviews'],
            $this->parser->extractPhrases('<p>Based on {n} <strong>reviews</strong></p>')
        );
    }

    public function testMarkerKeepsRunAsOnePhrase()
    {
        $this->assertEquals(
            ['Based on {n} {m0o}reviews{m0c}'],
            $this->parser->extractPhrases('<p data-langsys-phrase>Based on {n} <strong>reviews</strong></p>')
        );
    }

    /**
     * Presence alone is intent, like any boolean HTML attribute.
     */
    public function testBareAttributeIsTruthy()
    {
        $bare = $this->parser->extractPhrases('<p data-langsys-phrase>a <b>b</b></p>');
        $explicit = $this->parser->extractPhrases('<p data-langsys-phrase="true">a <b>b</b></p>');

        $this->assertEquals($bare, $explicit);
        $this->assertCount(1, $bare);
    }

    public function testExplicitFalseOptsOut()
    {
        foreach (['false', 'False', '0'] as $value) {
            $this->assertCount(
                2,
                $this->parser->extractPhrases('<p data-langsys-phrase="' . $value . '">a <b>b</b></p>'),
                'Value "' . $value . '" must opt out'
            );
        }
    }

    public function testNestedMarkupNumbersInPreOrder()
    {
        $this->assertEquals(
            ['{m0o}go {m1o}now{m1c}{m0c} please'],
            $this->parser->extractPhrases('<p data-langsys-phrase><a href="#">go <em>now</em></a> please</p>')
        );
    }

    // ---------------------------------------------------------------
    // Page rendering
    // ---------------------------------------------------------------

    protected function pageWith($inner)
    {
        return '<!DOCTYPE html><html><head><title>T</title></head><body>' . $inner . '</body></html>';
    }

    public function testTranslatedTokensRebuildMarkup()
    {
        $client = $this->makeClient([
            'home' => ['Read the {m0o}docs{m0c} now' => 'Lee la {m0o}documentación{m0c} ahora'],
        ]);
        $client->setLocale('es-es');

        $out = $client->translatePage(
            $this->pageWith('<p data-langsys-phrase>Read the <a href="/docs" class="lnk">docs</a> now</p>'),
            'home'
        );

        $p = $this->paragraphOf($out);

        $this->assertStringContainsString('Lee la <a href="/docs" class="lnk">documentación</a> ahora', $p);
    }

    /**
     * The whole reason this primitive exists: the count and the noun stay in
     * ONE phrase, so an ICU plural can inflect the noun correctly.
     */
    public function testPluralInflectsTheNounInsideMarkup()
    {
        if (!extension_loaded('intl')) {
            $this->markTestSkipped('ICU plural selection requires ext-intl');
        }

        $source = 'Based on {n} {m0o}reviews{m0c}';

        $client = $this->makeClient([
            'home' => [
                $source => 'На основе {n, plural, one {# {m0o}отзыва{m0c}} other {# {m0o}отзывов{m0c}}}',
            ],
        ]);
        $client->setLocale('ru-ru');

        $page = $this->pageWith('<p data-langsys-phrase>Based on {n} <strong>reviews</strong></p>');

        $this->assertStringContainsString(
            'На основе 1 <strong>отзыва</strong>',
            $this->paragraphOf($client->translatePage($page, 'home', [], ['n' => 1]))
        );

        $this->assertStringContainsString(
            'На основе 3 <strong>отзывов</strong>',
            $this->paragraphOf($client->translatePage($page, 'home', [], ['n' => 3]))
        );
    }

    public function testUntranslatedMarkedPhraseKeepsItsMarkup()
    {
        $client = $this->makeClient(['home' => []]);
        $client->setLocale('es-es');

        $out = $client->translatePage(
            $this->pageWith('<p data-langsys-phrase>Read the <a href="/d">docs</a> now</p>'),
            'home'
        );

        $p = $this->paragraphOf($out);

        $this->assertStringContainsString('Read the <a href="/d">docs</a> now', $p);
        $this->assertStringNotContainsString('{m0o}', $p);
    }

    /**
     * A translation referencing a slot that does not exist must never ship the
     * literal token to the browser.
     */
    public function testUnknownSlotIndexDoesNotLeakTokens()
    {
        $client = $this->makeClient([
            'home' => ['Read the {m0o}docs{m0c} now' => 'Lee {m7o}esto{m7c} ahora'],
        ]);
        $client->setLocale('es-es');

        $p = $this->paragraphOf($client->translatePage(
            $this->pageWith('<p data-langsys-phrase>Read the <a href="/d">docs</a> now</p>'),
            'home'
        ));

        $this->assertStringNotContainsString('{m7o}', $p);
        $this->assertStringNotContainsString('{m', $p);
        $this->assertStringContainsString('Lee esto ahora', $p);
    }

    public function testTranslationDroppingMarkupStillRenders()
    {
        $client = $this->makeClient([
            'home' => ['Read the {m0o}docs{m0c} now' => 'Lee los documentos ahora'],
        ]);
        $client->setLocale('es-es');

        $p = $this->paragraphOf($client->translatePage(
            $this->pageWith('<p data-langsys-phrase>Read the <a href="/d">docs</a> now</p>'),
            'home'
        ));

        $this->assertStringContainsString('Lee los documentos ahora', $p);
    }

    public function testMarkerTakesPrecedenceOverContentBlock()
    {
        $phrases = $this->parser->extractPhrases(
            '<p data-langsys-phrase data-langsys-contentblock="true">a <b>b</b></p>'
        );

        $this->assertEquals(['a {m0o}b{m0c}'], $phrases);
    }
}
