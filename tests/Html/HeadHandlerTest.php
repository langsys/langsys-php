<?php

namespace Langsys\SDK\Tests\Html;

use PHPUnit\Framework\TestCase;
use Langsys\SDK\Html\HeadHandler;
use DOMDocument;

class HeadHandlerTest extends TestCase
{
    /**
     * @var HeadHandler
     */
    private $handler;

    protected function setUp(): void
    {
        $this->handler = new HeadHandler();
    }

    /**
     * Create a DOMDocument from HTML string.
     */
    private function createDocument($html)
    {
        $doc = new DOMDocument();
        libxml_use_internal_errors(true);

        // The encoding hint is what PageTranslator passes in production
        // (PageTranslator.php:253). Without it libxml decodes the bytes as
        // ISO-8859-1, so a UTF-8 U+00A0 arrives as "Ã‚" plus a non-breaking
        // space and every non-ASCII assertion in this file measures mojibake
        // instead of the SDK. The ASCII tests here never noticed.
        $doc->loadHTML('<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        return $doc;
    }

    // =========================================================================
    // extractPhrases() Tests
    // =========================================================================

    public function testExtractTitlePhrase(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Welcome to My Site</title></head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertContains('Welcome to My Site', $phrases);
    }

    public function testExtractMetaDescription(): void
    {
        $html = '<!DOCTYPE html><html><head><meta name="description" content="This is a great site"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertContains('This is a great site', $phrases);
    }

    public function testExtractMetaKeywords(): void
    {
        $html = '<!DOCTYPE html><html><head><meta name="keywords" content="php, sdk, translation"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertContains('php, sdk, translation', $phrases);
    }

    public function testExtractMetaAuthor(): void
    {
        $html = '<!DOCTYPE html><html><head><meta name="author" content="John Doe"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertContains('John Doe', $phrases);
    }

    public function testExtractOpenGraphTitle(): void
    {
        $html = '<!DOCTYPE html><html><head><meta property="og:title" content="OG Title Here"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertContains('OG Title Here', $phrases);
    }

    public function testExtractOpenGraphDescription(): void
    {
        $html = '<!DOCTYPE html><html><head><meta property="og:description" content="OG Description"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertContains('OG Description', $phrases);
    }

    public function testExtractOpenGraphSiteName(): void
    {
        $html = '<!DOCTYPE html><html><head><meta property="og:site_name" content="My Awesome Site"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertContains('My Awesome Site', $phrases);
    }

    public function testExtractTwitterTitle(): void
    {
        $html = '<!DOCTYPE html><html><head><meta name="twitter:title" content="Twitter Card Title"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertContains('Twitter Card Title', $phrases);
    }

    public function testExtractTwitterDescription(): void
    {
        $html = '<!DOCTYPE html><html><head><meta name="twitter:description" content="Twitter Description"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertContains('Twitter Description', $phrases);
    }

    public function testExtractMultiplePhrases(): void
    {
        $html = '<!DOCTYPE html><html><head>
            <title>Page Title</title>
            <meta name="description" content="Page description">
            <meta property="og:title" content="OG Title">
        </head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertCount(3, $phrases);
        $this->assertContains('Page Title', $phrases);
        $this->assertContains('Page description', $phrases);
        $this->assertContains('OG Title', $phrases);
    }

    public function testExtractIgnoresEmptyContent(): void
    {
        $html = '<!DOCTYPE html><html><head>
            <meta name="description" content="">
            <meta name="viewport" content="width=device-width">
        </head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertEmpty($phrases);
    }

    public function testExtractIgnoresNonTranslatableMeta(): void
    {
        $html = '<!DOCTYPE html><html><head>
            <meta name="viewport" content="width=device-width">
            <meta name="robots" content="index, follow">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
        </head><body></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertEmpty($phrases);
    }

    public function testExtractFromDocumentWithoutHead(): void
    {
        $html = '<!DOCTYPE html><html><body><p>Hello</p></body></html>';
        $doc = $this->createDocument($html);

        $phrases = $this->handler->extractPhrases($doc);

        $this->assertEmpty($phrases);
    }

    // =========================================================================
    // process() Tests - Lang Attribute
    // =========================================================================

    public function testProcessSetsLangAttribute(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body></body></html>';
        $doc = $this->createDocument($html);

        $this->handler->process($doc, 'es-es', [], null);

        $htmlElement = $doc->getElementsByTagName('html')->item(0);
        $this->assertEquals('es-es', $htmlElement->getAttribute('lang'));
    }

    public function testProcessOverwritesExistingLangAttribute(): void
    {
        $html = '<!DOCTYPE html><html lang="en-us"><head><title>Test</title></head><body></body></html>';
        $doc = $this->createDocument($html);

        $this->handler->process($doc, 'fr-ca', [], null);

        $htmlElement = $doc->getElementsByTagName('html')->item(0);
        $this->assertEquals('fr-ca', $htmlElement->getAttribute('lang'));
    }

    // =========================================================================
    // process() Tests - Charset Meta
    // =========================================================================

    public function testProcessEnsuresCharsetMeta(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body></body></html>';
        $doc = $this->createDocument($html);

        $this->handler->process($doc, 'en-us', [], null);

        $head = $doc->getElementsByTagName('head')->item(0);
        $metas = $head->getElementsByTagName('meta');

        $hasCharset = false;
        foreach ($metas as $meta) {
            if ($meta->hasAttribute('charset')) {
                $hasCharset = true;
                $this->assertEquals('utf-8', $meta->getAttribute('charset'));
            }
        }
        $this->assertTrue($hasCharset, 'Charset meta should be added');
    }

    public function testProcessUpdatesExistingCharsetMeta(): void
    {
        $html = '<!DOCTYPE html><html><head><meta charset="iso-8859-1"><title>Test</title></head><body></body></html>';
        $doc = $this->createDocument($html);

        $this->handler->process($doc, 'en-us', [], null);

        $head = $doc->getElementsByTagName('head')->item(0);
        $metas = $head->getElementsByTagName('meta');

        $found = false;
        foreach ($metas as $meta) {
            if ($meta->hasAttribute('charset')) {
                $found = true;
                $this->assertEquals('utf-8', $meta->getAttribute('charset'));
            }
        }
        $this->assertTrue($found);
    }

    public function testProcessHandlesHttpEquivContentType(): void
    {
        $html = '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1"><title>Test</title></head><body></body></html>';
        $doc = $this->createDocument($html);

        $this->handler->process($doc, 'en-us', [], null);

        $head = $doc->getElementsByTagName('head')->item(0);
        $metas = $head->getElementsByTagName('meta');

        foreach ($metas as $meta) {
            if (strtolower($meta->getAttribute('http-equiv')) === 'content-type') {
                $this->assertStringContainsString('utf-8', $meta->getAttribute('content'));
            }
        }
    }

    // =========================================================================
    // process() Tests - Title Translation
    // =========================================================================

    public function testProcessTranslatesTitle(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Welcome</title></head><body></body></html>';
        $doc = $this->createDocument($html);

        $translations = [
            'homepage' => [
                'Welcome' => 'Bienvenido',
            ],
        ];

        $this->handler->process($doc, 'es-es', $translations, 'homepage');

        $title = $doc->getElementsByTagName('title')->item(0);
        $this->assertEquals('Bienvenido', $title->textContent);
    }

    public function testProcessFallsBackForMissingTitleTranslation(): void
    {
        $html = '<!DOCTYPE html><html><head><title>Original Title</title></head><body></body></html>';
        $doc = $this->createDocument($html);

        $this->handler->process($doc, 'es-es', [], 'homepage');

        $title = $doc->getElementsByTagName('title')->item(0);
        $this->assertEquals('Original Title', $title->textContent);
    }

    // =========================================================================
    // process() Tests - Meta Tag Translation
    // =========================================================================

    public function testProcessTranslatesMetaDescription(): void
    {
        $html = '<!DOCTYPE html><html><head><meta name="description" content="Welcome to our site"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $translations = [
            '__uncategorized__' => [
                'Welcome to our site' => 'Bienvenido a nuestro sitio',
            ],
        ];

        $this->handler->process($doc, 'es-es', $translations, null);

        $metas = $doc->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if ($meta->getAttribute('name') === 'description') {
                $this->assertEquals('Bienvenido a nuestro sitio', $meta->getAttribute('content'));
            }
        }
    }

    public function testProcessTranslatesOpenGraphTags(): void
    {
        $html = '<!DOCTYPE html><html><head>
            <meta property="og:title" content="OG Title">
            <meta property="og:description" content="OG Description">
        </head><body></body></html>';
        $doc = $this->createDocument($html);

        $translations = [
            'page' => [
                'OG Title' => 'Titulo OG',
                'OG Description' => 'Descripcion OG',
            ],
        ];

        $this->handler->process($doc, 'es-es', $translations, 'page');

        $metas = $doc->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            $property = $meta->getAttribute('property');
            if ($property === 'og:title') {
                $this->assertEquals('Titulo OG', $meta->getAttribute('content'));
            }
            if ($property === 'og:description') {
                $this->assertEquals('Descripcion OG', $meta->getAttribute('content'));
            }
        }
    }

    public function testProcessUpdatesOpenGraphLocale(): void
    {
        $html = '<!DOCTYPE html><html><head><meta property="og:locale" content="en_US"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $this->handler->process($doc, 'es-es', [], null);

        $metas = $doc->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if ($meta->getAttribute('property') === 'og:locale') {
                $this->assertEquals('es_ES', $meta->getAttribute('content'));
            }
        }
    }

    public function testProcessTranslatesTwitterTags(): void
    {
        $html = '<!DOCTYPE html><html><head>
            <meta name="twitter:title" content="Twitter Title">
            <meta name="twitter:description" content="Twitter Desc">
        </head><body></body></html>';
        $doc = $this->createDocument($html);

        $translations = [
            '__uncategorized__' => [
                'Twitter Title' => 'Titulo Twitter',
                'Twitter Desc' => 'Descripcion Twitter',
            ],
        ];

        $this->handler->process($doc, 'es-es', $translations, null);

        $metas = $doc->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            $name = $meta->getAttribute('name');
            if ($name === 'twitter:title') {
                $this->assertEquals('Titulo Twitter', $meta->getAttribute('content'));
            }
            if ($name === 'twitter:description') {
                $this->assertEquals('Descripcion Twitter', $meta->getAttribute('content'));
            }
        }
    }

    // =========================================================================
    // process() Tests - Fallback Behavior
    // =========================================================================

    public function testProcessFallsBackForEmptyTranslation(): void
    {
        $html = '<!DOCTYPE html><html><head><meta name="description" content="Original"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $translations = [
            '__uncategorized__' => [
                'Original' => '', // Empty translation
            ],
        ];

        $this->handler->process($doc, 'es-es', $translations, null);

        $metas = $doc->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if ($meta->getAttribute('name') === 'description') {
                $this->assertEquals('Original', $meta->getAttribute('content'));
            }
        }
    }

    public function testProcessFallsBackForArrayTranslation(): void
    {
        $html = '<!DOCTYPE html><html><head><meta name="description" content="Original"></head><body></body></html>';
        $doc = $this->createDocument($html);

        $translations = [
            '__uncategorized__' => [
                'Original' => ['nested' => 'value'], // Array (content block)
            ],
        ];

        $this->handler->process($doc, 'es-es', $translations, null);

        $metas = $doc->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if ($meta->getAttribute('name') === 'description') {
                $this->assertEquals('Original', $meta->getAttribute('content'));
            }
        }
    }

    // =========================================================================
    // Full Document Tests
    // =========================================================================

    public function testProcessFullHead(): void
    {
        $html = '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="iso-8859-1">
            <title>Welcome Page</title>
            <meta name="description" content="A great website">
            <meta name="keywords" content="web, site">
            <meta property="og:title" content="OG Welcome">
            <meta property="og:description" content="OG Description">
            <meta property="og:locale" content="en_US">
            <meta name="twitter:title" content="Twitter Welcome">
        </head>
        <body></body>
        </html>';
        $doc = $this->createDocument($html);

        $translations = [
            'page' => [
                'Welcome Page' => 'Pagina de Bienvenida',
                'A great website' => 'Un gran sitio web',
                'web, site' => 'web, sitio',
                'OG Welcome' => 'OG Bienvenida',
                'OG Description' => 'Descripcion OG',
                'Twitter Welcome' => 'Twitter Bienvenida',
            ],
        ];

        $this->handler->process($doc, 'es-mx', $translations, 'page');

        // Check lang attribute
        $htmlElement = $doc->getElementsByTagName('html')->item(0);
        $this->assertEquals('es-mx', $htmlElement->getAttribute('lang'));

        // Check charset is utf-8
        $head = $doc->getElementsByTagName('head')->item(0);
        $metas = $head->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            if ($meta->hasAttribute('charset')) {
                $this->assertEquals('utf-8', $meta->getAttribute('charset'));
            }
        }

        // Check title
        $title = $doc->getElementsByTagName('title')->item(0);
        $this->assertEquals('Pagina de Bienvenida', $title->textContent);

        // Check og:locale
        foreach ($metas as $meta) {
            if ($meta->getAttribute('property') === 'og:locale') {
                $this->assertEquals('es_MX', $meta->getAttribute('content'));
            }
        }
    }

    // =========================================================================
    // TOK-2 — extraction and application must agree, or nothing translates
    // =========================================================================

    /**
     * The regression this test exists for.
     *
     * Collapsing on the extraction side alone is worse than not collapsing at
     * all. Extraction registered "Acme Home"; application looked up the raw
     * "Acme\n        Home"; the catalog key that WAS written could never be
     * found, and the key being looked for was never registered - so the title
     * silently stopped translating, and was not even re-registered, because the
     * collapsed form was already in the catalog.
     *
     * Before the collapse landed both sides were raw and agreed with each
     * other. That is the shape to remember: a half-applied normalisation breaks
     * a pair that was consistently wrong but working.
     *
     * @dataProvider titleWhitespaceProvider
     */
    public function testATitleIsRegisteredAndTranslatedOnTheSameRender($title, $expectedPhrase, $why)
    {
        $html = '<html><head><title>' . $title . '</title></head><body></body></html>';

        // What gets registered.
        $doc = $this->createDocument($html);
        $this->assertSame([$expectedPhrase], $this->handler->extractPhrases($doc), $why);

        // And the apply side must find exactly that key - the one we wrote.
        $doc = $this->createDocument($html);
        $this->handler->process($doc, 'es-es', ['__uncategorized__' => [$expectedPhrase => 'TRANSLATED']]);

        $this->assertSame(
            'TRANSLATED',
            $doc->getElementsByTagName('title')->item(0)->textContent,
            'registered as ' . json_encode($expectedPhrase) . ' but not found on apply'
        );
    }

    public function titleWhitespaceProvider()
    {
        return [
            'authored across lines' => [
                "Acme\n        Home", 'Acme Home',
                'the commonest authored shape - an indented title in a template',
            ],
            'internal run' => [
                'Acme   Home', 'Acme Home',
                'a plain multi-space run, no non-ASCII involved',
            ],
            'non-breaking space' => [
                "Acme\u{00A0}Home", 'Acme Home',
                'U+00A0 collapses like any other whitespace',
            ],
            'padded' => [
                "  Acme Home  ", 'Acme Home',
                'leading and trailing whitespace is trimmed on both sides',
            ],
        ];
    }

    /**
     * The same property stated generally: every phrase the head registers must
     * be findable by the code that applies translations to the head. A pair
     * that disagrees cannot be caught by testing either side alone, which is
     * how this shipped.
     */
    public function testEveryHeadPhraseRegisteredIsFoundOnApply()
    {
        $html = '<html><head>'
            . "<title>Acme\n   Home</title>"
            . '<meta name="description" content="A   long' . "\n" . '  description">'
            . '<meta property="og:title" content="Share' . "\u{00A0}" . 'title">'
            . '</head><body></body></html>';

        $doc = $this->createDocument($html);
        $phrases = $this->handler->extractPhrases($doc);

        $this->assertNotEmpty($phrases);

        $catalog = [];
        foreach ($phrases as $phrase) {
            $catalog[$phrase] = 'X:' . $phrase;
        }

        $doc = $this->createDocument($html);
        $this->handler->process($doc, 'es-es', ['__uncategorized__' => $catalog]);

        $rendered = $doc->saveHTML();
        foreach ($phrases as $phrase) {
            $this->assertStringContainsString(
                'X:' . $phrase,
                $rendered,
                'registered ' . json_encode($phrase) . ' but the apply side never looked it up'
            );
        }
    }
}
