<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Support\BuildsMockClient;
use PHPUnit\Framework\TestCase;

/**
 * GATE-10 on the page path. Producing: a render in a non-base locale marks its
 * root `data-ls-resolved`, and a base-locale render does not. Reading: text in a
 * resolved subtree is output, not source, so it is never registered - while it
 * still translates and keeps its identity.
 */
class ResolvedMarkerTest extends TestCase
{
    use BuildsMockClient;

    private function render($html, array $catalog, $locale = 'es-es', array $auth = null)
    {
        $client = $this->mockClient($catalog, [], $auth);
        $client->setLocale($locale);
        $page = $client->translatePage($html);
        $client->flushPendingRegistrations();

        return $page;
    }

    public function testANonBaseLocaleRenderMarksItsRoot(): void
    {
        $page = $this->render('<html><body><p>Save</p></body></html>', ['__uncategorized__' => ['Save' => 'Guardar']]);

        $this->assertMatchesRegularExpression('#<html[^>]* data-ls-resolved="es-es"#', $page);
    }

    public function testABaseLocaleRenderDoesNotMarkItsRoot(): void
    {
        $page = $this->render('<html><body><p>Save</p></body></html>', ['__uncategorized__' => []], 'en-us');

        $this->assertStringNotContainsString('data-ls-resolved', $page);
    }

    public function testNothingIsMarkedWhenTheBaseLocaleIsUnknown(): void
    {
        $page = $this->render('<html><body><p>Save</p></body></html>', ['__uncategorized__' => ['Save' => 'Guardar']], 'es-es', ['key_type' => 'write', 'write_enabled' => true]);

        $this->assertStringNotContainsString('data-ls-resolved', $page);
    }

    public function testAMarkerAlreadyOnTheRootIsNeverOverwritten(): void
    {
        foreach (['data-ls-resolved="false"', 'data-langsys-resolved="fr-fr"'] as $existing) {
            $page = $this->render("<html $existing><body><p>Save</p></body></html>", ['__uncategorized__' => ['Save' => 'Guardar']]);

            $this->assertStringContainsString($existing, $page, $existing);
            $this->assertSame(1, substr_count($page, 'resolved='), "$existing: no second marker");
        }
    }

    public function testANonBaseRenderWalkedAgainRegistersNothing(): void
    {
        $page = $this->render('<html><body><p>Save</p><div><p>Buy <b>now</b></p></div></body></html>', ['__uncategorized__' => ['Save' => 'Guardar']]);

        $this->render($page, ['__uncategorized__' => []]);

        $this->assertSame([], $this->registered(), 'the translated output is not source');
    }

    public function testABaseRenderWalkedAgainStillRegisters(): void
    {
        $page = $this->render('<html><body><p>Save</p></body></html>', ['__uncategorized__' => []], 'en-us');

        $this->render($page, ['__uncategorized__' => []]);

        $this->assertSame([['phrase', 'Save']], $this->registered(), 'a base-locale render is source and stays discoverable');
    }

    public function testAResolvedSubtreeRegistersNothingWhileItsSiblingsDo(): void
    {
        $this->render('<html><body><div data-ls-resolved="es-es"><p>Hola</p></div><p>New source</p></body></html>', ['__uncategorized__' => []]);

        $this->assertSame([['phrase', 'New source']], $this->registered());
    }

    public function testTextInAResolvedSubtreeStillTranslates(): void
    {
        $page = $this->render('<html><body><div data-ls-resolved><p>Stamped</p></div></body></html>', ['__uncategorized__' => ['Stamped' => 'Estampado']]);

        $this->assertStringContainsString('<p>Estampado</p>', $page);
    }

    public function testBothSpellingsAndABareMarkerSuppress(): void
    {
        foreach (['data-ls-resolved', 'data-langsys-resolved', 'data-ls-resolved=""', 'data-ls-resolved="no"', 'data-ls-resolved="off"', 'data-ls-resolved="ES-ES"'] as $marker) {
            $this->render("<html><body><div $marker><p>Hola</p></div></body></html>", ['__uncategorized__' => []]);

            $this->assertSame([], $this->registered(), $marker);
        }
    }

    public function testFalseOrZeroOptsASubtreeBackOut(): void
    {
        foreach (['false', ' FALSE ', '0'] as $value) {
            $this->render("<html data-ls-resolved=\"es-es\"><body><p>Hola</p><div data-ls-resolved=\"$value\"><p>Island source</p></div></body></html>", ['__uncategorized__' => []]);

            $this->assertSame([['phrase', 'Island source']], $this->registered(), "=\"$value\"");
        }
    }

    public function testTheNearestMarkedAncestorDecides(): void
    {
        $this->render('<html><body data-ls-resolved><main><section><article><p>Deep</p></article></section></main></body></html>', ['__uncategorized__' => []]);
        $this->assertSame([], $this->registered(), 'inherited from an ancestor the reader walks to');

        $this->render('<html data-ls-resolved><body><main data-ls-resolved="false"><section data-ls-resolved><p>Inner</p></section><p>Outer</p></main></body></html>', ['__uncategorized__' => []]);
        $this->assertSame([['phrase', 'Outer']], $this->registered(), 'the nearest marker wins at each depth');
    }

    public function testHeadTextInAResolvedDocumentRegistersNothing(): void
    {
        $this->render('<html data-ls-resolved="es-es"><head><title>Titulo</title><meta name="description" content="Descripcion"></head><body></body></html>', ['__uncategorized__' => []]);

        $this->assertSame([], $this->registered());
    }

    public function testAMarkedRunAndItsAttributesInAResolvedScopeRegisterNothing(): void
    {
        $this->render('<html><body><div data-ls-resolved><p data-ls-phrase title="Consejo">Hola <b>mundo</b></p></div></body></html>', ['__uncategorized__' => []]);

        $this->assertSame([], $this->registered());
    }

    public function testABlockInAResolvedScopeRegistersNothingAndKeepsItsIdentity(): void
    {
        $html = '<html><body><div data-ls-resolved><div data-ls-contentblock><p>Alpha</p><p>Beta</p></div></div></body></html>';

        $this->render($html, ['__uncategorized__' => []]);
        $this->assertSame([], $this->registered(), 'not registered');

        $id = (new HtmlParser())->generateCustomId('__uncategorized__', ['Alpha', 'Beta']);
        $page = $this->render($html, ['__uncategorized__' => [$id => ['Alpha' => 'Alfa', 'Beta' => 'Beta ES']]]);
        $this->assertStringContainsString('<p>Alfa</p><p>Beta ES</p>', $page, 'still resolved by its id and translated');
    }

    /**
     * MARK-2: a marked host that misses registers WHOLE, once, as the one string
     * its host defines. It is never split, and never suppressed by the marker.
     */
    public function testAMarkedHostThatMissesRegistersWholeOnce(): void
    {
        foreach (['data-ls-phrase', 'data-langsys-phrase'] as $marker) {
            $this->render("<html><body><p $marker>Buy <strong>now</strong></p></body></html>", ['__uncategorized__' => []]);

            $this->assertSame([['phrase', 'Buy {m0o}now{m0c}']], $this->registered(), $marker);
        }
    }

    public function testAMarkedHostTheCatalogHoldsRegistersNothing(): void
    {
        $this->render('<html><body><p data-ls-phrase>Buy <strong>now</strong></p></body></html>', ['__uncategorized__' => ['Buy {m0o}now{m0c}' => 'Compra {m0o}ya{m0c}']]);

        $this->assertSame([], $this->registered());
    }
}
