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

    /**
     * @var MockHttpClient|null
     */
    protected $mock;

    protected function makeClient(array $translations, $keyType = 'read')
    {
        $mock = new MockHttpClient();
        $this->mock = $mock;
        $mock->setResponse('GET', 'authorize-project/p', ['data' => ['key_type' => $keyType]]);
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

    /**
     * Every phrase string sent to the registration endpoint for a page.
     *
     * Uses a write key so registration actually fires, then reads the recorded
     * POST bodies - asserting on what would really reach the shared catalog
     * rather than on an internal method's return value.
     *
     * @param string $inner Body HTML
     * @return array
     */
    protected function registeredFor($inner)
    {
        $client = $this->makeClient(['home' => []], 'write');
        $client->setLocale('es-es');
        $client->translatePage($this->pageWith($inner), 'home');
        $client->flushPendingRegistrations();

        $found = [];

        foreach ($this->mock->getRequests() as $request) {
            if ($request['method'] !== 'POST') {
                continue;
            }

            $json = json_encode($request, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            preg_match_all('/"phrase":"((?:[^"\\\\]|\\\\.)*)"/', $json, $m);

            foreach ($m[1] as $phrase) {
                $found[] = stripcslashes($phrase);
            }
        }

        return $found;
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

    /**
     * The marker is a translatePage() feature. HtmlParser deliberately ignores
     * it: content blocks are applied by a path with no tokenized branch, so
     * honouring it there would register an entry that could never be rendered.
     */
    public function testHtmlParserIgnoresTheMarker()
    {
        $this->assertEquals(
            ['Based on {n}', 'reviews'],
            $this->parser->extractPhrases('<p data-langsys-phrase>Based on {n} <strong>reviews</strong></p>')
        );
    }

    public function testMarkerKeepsRunAsOnePhraseOnAPage()
    {
        $registered = $this->registeredFor('<p data-langsys-phrase>Based on {n} <strong>reviews</strong></p>');

        // Registered as ONE tokenized phrase, not split at the tag boundary.
        $this->assertContains('Based on {n} {m0o}reviews{m0c}', $registered);
        $this->assertNotContains('reviews', $registered);
    }

    /**
     * Presence alone is intent, like any boolean HTML attribute.
     */
    public function testBareAttributeIsTruthy()
    {
        $this->assertSame(
            $this->registeredFor('<p data-langsys-phrase>a <b>b</b></p>'),
            $this->registeredFor('<p data-langsys-phrase="true">a <b>b</b></p>')
        );
    }

    public function testExplicitFalseOptsOut()
    {
        foreach (['false', 'False', '0'] as $value) {
            $registered = $this->registeredFor('<p data-langsys-phrase="' . $value . '">a <b>b</b></p>');

            $this->assertNotContains(
                'a {m0o}b{m0c}',
                $registered,
                'Value "' . $value . '" must opt out of tokenization'
            );
        }
    }

    /**
     * Whitespace around the opt-out value must not defeat it. This trim was
     * previously present on data-notrans but missing here, so
     * `data-langsys-phrase=" false "` opted out of neither convention.
     */
    public function testOptOutToleratesSurroundingWhitespace()
    {
        foreach ([' false ', ' FALSE ', " 0 "] as $value) {
            $registered = $this->registeredFor('<p data-langsys-phrase="' . $value . '">a <b>b</b></p>');

            $this->assertNotContains(
                'a {m0o}b{m0c}',
                $registered,
                'Value "' . $value . '" must opt out'
            );
        }
    }

    public function testNestedMarkupNumbersInPreOrder()
    {
        $this->assertContains(
            '{m0o}go {m1o}now{m1c}{m0c} please',
            $this->registeredFor('<p data-langsys-phrase><a href="#">go <em>now</em></a> please</p>')
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

    /**
     * Adding the marker must not silently un-translate the element's
     * title/alt/placeholder - those live outside the tokenized text.
     */
    public function testAttributesInsideAMarkedSubtreeAreStillTranslated()
    {
        $client = $this->makeClient([
            'home' => [
                'Read the {m0o}docs{m0c} now' => 'Lee la {m0o}documentación{m0c} ahora',
                'Tooltip' => 'Consejo',
                'Logo' => 'Logotipo',
            ],
        ]);
        $client->setLocale('es-es');

        $out = $client->translatePage(
            $this->pageWith(
                '<p data-langsys-phrase title="Tooltip">Read the <a href="/d">docs</a> now <img alt="Logo"></p>'
            ),
            'home'
        );

        $this->assertStringContainsString('title="Consejo"', $out);
        $this->assertStringContainsString('alt="Logotipo"', $out);
    }

    public function testAttributesInsideAMarkedSubtreeAreRegistered()
    {
        $registered = $this->registeredFor(
            '<p data-langsys-phrase title="Tooltip">Read the <a href="/d">docs</a> now</p>'
        );

        $this->assertContains('Tooltip', $registered);
    }

    /**
     * data-langsys-contentblock deliberately differs from the other two markers:
     * a BARE attribute does not enable it, because its documented contract
     * requires a non-empty value. Trimming is shared, though - whitespace must
     * not defeat the opt-out.
     */
    public function testContentBlockAttributeOptOutToleratesWhitespace()
    {
        foreach ([' false ', ' FALSE ', ' 0 '] as $value) {
            $registered = $this->registeredFor(
                '<div data-langsys-contentblock="' . $value . '"><p>One</p><p>Two</p></div>'
            );

            // Opted out, so the paragraphs register as individual phrases.
            $this->assertContains('One', $registered, 'Value "' . $value . '" must opt out');
            $this->assertContains('Two', $registered);
        }
    }

    public function testMarkerTakesPrecedenceOverContentBlock()
    {
        $this->assertContains(
            'a {m0o}b{m0c}',
            $this->registeredFor('<p data-langsys-phrase data-langsys-contentblock="true">a <b>b</b></p>')
        );
    }
}
