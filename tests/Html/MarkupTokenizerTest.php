<?php

namespace Langsys\SDK\Tests\Html;

use DOMDocument;
use Langsys\SDK\Html\MarkupTokenizer;
use PHPUnit\Framework\TestCase;

/**
 * Tests for inline-markup tokenization.
 *
 * The wire format must match the Langsys JS SDK's <Phrase> component exactly,
 * or catalog entries registered by one SDK are unusable by the other.
 */
class MarkupTokenizerTest extends TestCase
{
    const OPEN_START = "\xEE\x80\x80";
    const OPEN_END = "\xEE\x80\x81";
    const CLOSE_START = "\xEE\x80\x82";
    const CLOSE_END = "\xEE\x80\x83";

    /**
     * @var MarkupTokenizer
     */
    protected $tokenizer;

    protected function setUp(): void
    {
        $this->tokenizer = new MarkupTokenizer();
    }

    /**
     * Parse a fragment and return its wrapper element.
     */
    protected function element($html)
    {
        $doc = new DOMDocument();
        $internal = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($internal);

        return $doc->getElementsByTagName('div')->item(0);
    }

    protected function open($i)
    {
        return self::OPEN_START . $i . self::OPEN_END;
    }

    protected function close($i)
    {
        return self::CLOSE_START . $i . self::CLOSE_END;
    }

    /**
     * Render and serialise back to an HTML string.
     */
    protected function renderToHtml($text, array $slots)
    {
        $doc = new DOMDocument();
        // Without this, saveHTML() escapes non-ASCII as numeric entities. The
        // real callers parse UTF-8 documents, so match that here.
        $doc->encoding = 'UTF-8';
        $nodes = $this->tokenizer->render($text, $slots, $doc);

        $html = '';
        foreach ($nodes as $node) {
            $html .= $doc->saveHTML($node);
        }

        return $html;
    }

    // ---------------------------------------------------------------
    // Encoding
    // ---------------------------------------------------------------

    public function testEncodesInlineMarkupAsTokens()
    {
        $result = $this->tokenizer->encode($this->element('Based on {n} <strong>reviews</strong>'));

        $this->assertEquals('Based on {n} {m0o}reviews{m0c}', $result['text']);
        $this->assertCount(1, $result['slots']);
        $this->assertEquals('strong', $result['slots'][0]->tagName);
    }

    public function testPlainTextNeedsNoTokens()
    {
        $result = $this->tokenizer->encode($this->element('Just text'));

        $this->assertEquals('Just text', $result['text']);
        $this->assertCount(0, $result['slots']);
    }

    /**
     * Numbering is a single counter in depth-first PRE-order, with the index
     * claimed before recursing - so a parent always numbers below its children.
     */
    public function testNestedElementsNumberInPreOrder()
    {
        $result = $this->tokenizer->encode($this->element('<a href="#">go <em>now</em></a> please'));

        $this->assertEquals('{m0o}go {m1o}now{m1c}{m0c} please', $result['text']);
        $this->assertEquals('a', $result['slots'][0]->tagName);
        $this->assertEquals('em', $result['slots'][1]->tagName);
    }

    public function testSiblingsNumberSequentially()
    {
        $result = $this->tokenizer->encode($this->element('<b>one</b> and <i>two</i>'));

        $this->assertEquals('{m0o}one{m0c} and {m1o}two{m1c}', $result['text']);
        $this->assertCount(2, $result['slots']);
    }

    public function testVoidElementProducesEmptyPair()
    {
        $result = $this->tokenizer->encode($this->element('a<br>b'));

        $this->assertEquals('a{m0o}{m0c}b', $result['text']);
    }

    public function testSlotsHoldAttributesButNotChildren()
    {
        $result = $this->tokenizer->encode($this->element('<a href="/x" class="c">text</a>'));

        $slot = $result['slots'][0];

        $this->assertEquals('/x', $slot->getAttribute('href'));
        $this->assertEquals('c', $slot->getAttribute('class'));
        $this->assertFalse($slot->hasChildNodes(), 'Slots must be shallow clones');
    }

    public function testCommentsAreSkippedEntirely()
    {
        $result = $this->tokenizer->encode($this->element('a<!-- note -->b'));

        $this->assertEquals('ab', $result['text']);
        $this->assertCount(0, $result['slots']);
    }

    public function testWhitespaceIsCollapsedAndTrimmed()
    {
        $result = $this->tokenizer->encode($this->element("  Hello   \n  <b>world</b>  "));

        $this->assertEquals('Hello {m0o}world{m0c}', $result['text']);
    }

    public function testTokenParamsMapToSentinels()
    {
        $params = $this->tokenizer->tokenParams(2);

        $this->assertEquals($this->open(0), $params['m0o']);
        $this->assertEquals($this->close(0), $params['m0c']);
        $this->assertEquals($this->open(1), $params['m1o']);
        $this->assertEquals($this->close(1), $params['m1c']);
    }

    public function testDetectsTokens()
    {
        $this->assertTrue($this->tokenizer->hasTokens('a {m0o}b{m0c}'));
        $this->assertFalse($this->tokenizer->hasTokens('a {name} b'));
    }

    // ---------------------------------------------------------------
    // Rendering
    // ---------------------------------------------------------------

    public function testRebuildsMarkup()
    {
        $slots = [$this->element('<strong>x</strong>')->firstChild->cloneNode(false)];

        $html = $this->renderToHtml('Based on 5 ' . $this->open(0) . 'reviews' . $this->close(0), $slots);

        $this->assertEquals('Based on 5 <strong>reviews</strong>', $html);
    }

    /**
     * Reordering is the entire point: a translation may move the markup, and
     * the element must land where the tokens now sit.
     */
    public function testReorderedTokensRebuildCorrectly()
    {
        $slots = [$this->element('<span>x</span>')->firstChild->cloneNode(false)];

        $html = $this->renderToHtml('Casa ' . $this->open(0) . 'Blanca' . $this->close(0), $slots);

        $this->assertEquals('Casa <span>Blanca</span>', $html);
    }

    public function testNestedTokensRebuild()
    {
        $a = $this->element('<a href="#">x</a>')->firstChild->cloneNode(false);
        $em = $this->element('<em>x</em>')->firstChild->cloneNode(false);

        $html = $this->renderToHtml(
            $this->open(0) . 'go ' . $this->open(1) . 'now' . $this->close(1) . $this->close(0) . ' please',
            [$a, $em]
        );

        $this->assertEquals('<a href="#">go <em>now</em></a> please', $html);
    }

    /**
     * Slots created in one document must render into another - encode-time and
     * render-time documents routinely differ.
     */
    public function testRendersSlotsFromADifferentDocument()
    {
        $other = new DOMDocument();
        $slots = [$other->createElement('strong')];

        $html = $this->renderToHtml($this->open(0) . 'hi' . $this->close(0), $slots);

        $this->assertEquals('<strong>hi</strong>', $html);
    }

    // ---------------------------------------------------------------
    // Failure modes: lose the markup, keep the meaning. Never throw.
    // ---------------------------------------------------------------

    public function testUnbalancedTokensFallBackToPlainText()
    {
        $slots = [$this->element('<b>x</b>')->firstChild->cloneNode(false)];

        $html = $this->renderToHtml($this->open(0) . 'no close', $slots);

        $this->assertEquals('no close', $html);
    }

    public function testUnknownSlotIndexFallsBackToPlainText()
    {
        $slots = [$this->element('<b>x</b>')->firstChild->cloneNode(false)];

        $html = $this->renderToHtml($this->open(7) . 'x' . $this->close(7), $slots);

        $this->assertEquals('x', $html);
    }

    public function testCrossedTokensFallBackToPlainText()
    {
        $slots = [
            $this->element('<b>x</b>')->firstChild->cloneNode(false),
            $this->element('<i>x</i>')->firstChild->cloneNode(false),
        ];

        // <b> opened, <i> opened, <b> closed before <i>.
        $html = $this->renderToHtml(
            $this->open(0) . 'a' . $this->open(1) . 'b' . $this->close(0) . 'c' . $this->close(1),
            $slots
        );

        $this->assertEquals('abc', $html);
    }

    public function testDroppedTokensRenderPlainText()
    {
        $slots = [$this->element('<b>x</b>')->firstChild->cloneNode(false)];

        $html = $this->renderToHtml('translation lost the markup', $slots);

        $this->assertEquals('translation lost the markup', $html);
    }

    /**
     * A truncated sentinel matches no complete token, so it must still be
     * stripped rather than rendered as a private-use character.
     */
    public function testHalfSentinelIsStripped()
    {
        $html = $this->renderToHtml('a' . self::OPEN_START . 'b', []);

        $this->assertEquals('ab', $html);
        $this->assertStringNotContainsString('E000', $html);
    }

    public function testInvalidUtf8DoesNotLeakSentinels()
    {
        $slots = [$this->element('<b>x</b>')->firstChild->cloneNode(false)];

        $text = "abc\xC3\x28 " . $this->open(0) . 'x' . $this->close(0);
        $html = $this->renderToHtml($text, $slots);

        $this->assertStringNotContainsString(self::OPEN_START, $html);
        $this->assertStringNotContainsString('E000', $html);
    }

    public function testStripSentinelsHandlesInvalidUtf8()
    {
        $text = "abc\xC3\x28 " . $this->open(0) . 'x' . $this->close(0);

        $this->assertStringNotContainsString(
            self::OPEN_START,
            $this->tokenizer->stripSentinels($text)
        );
    }

    // ---------------------------------------------------------------
    // Opaque subtrees
    // ---------------------------------------------------------------

    /**
     * script/style bodies must never enter the catalog. If they did they would
     * be registered as translatable text AND the catalog could rewrite them -
     * a translation for a <script> body is applied straight back into the page.
     */
    public function testScriptContentIsNotEncodedIntoThePhrase()
    {
        $result = $this->tokenizer->encode($this->element('Total: <script>var t = 0;</script>'));

        $this->assertEquals('Total: {m0o}{m0c}', $result['text']);
        $this->assertStringNotContainsString('var t', $result['text']);
    }

    public function testTranslateNoSubtreeIsNotEncoded()
    {
        $result = $this->tokenizer->encode($this->element('Run <code translate="no">rm -rf /</code>'));

        $this->assertEquals('Run {m0o}{m0c}', $result['text']);
        $this->assertStringNotContainsString('rm -rf', $result['text']);
    }

    public function testOpaqueSubtreeIsPreservedOnRender()
    {
        $encoded = $this->tokenizer->encode($this->element('Total: <script>var t = 0;</script>'));

        $params = $this->tokenizer->tokenParams(count($encoded['slots']));
        $text = str_replace(['{m0o}', '{m0c}'], [$params['m0o'], $params['m0c']], $encoded['text']);

        $this->assertEquals(
            'Total: <script>var t = 0;</script>',
            $this->renderToHtml($text, $encoded['slots'])
        );
    }

    /**
     * DOMDocument::importNode returns the SAME node when the source already
     * belongs to the target document - the normal case here - so importing
     * directly aliased one element across every occurrence of a token.
     */
    public function testRepeatedTokenPairProducesSeparateElements()
    {
        $slots = [$this->element('<a href="/d">x</a>')->firstChild->cloneNode(false)];

        $html = $this->renderToHtml(
            $this->open(0) . 'Lee' . $this->close(0) . ' los ' . $this->open(0) . 'docs' . $this->close(0),
            $slots
        );

        $this->assertEquals('<a href="/d">Lee</a> los <a href="/d">docs</a>', $html);
    }

    // ---------------------------------------------------------------
    // Round trip
    // ---------------------------------------------------------------

    public function testRoundTripPreservesMarkup()
    {
        $element = $this->element('Read the <a href="/docs" class="link">docs</a> now');
        $encoded = $this->tokenizer->encode($element);

        $this->assertEquals('Read the {m0o}docs{m0c} now', $encoded['text']);

        // Simulate the interpolation step that swaps tokens for sentinels.
        $params = $this->tokenizer->tokenParams(count($encoded['slots']));
        $withSentinels = str_replace(
            ['{m0o}', '{m0c}'],
            [$params['m0o'], $params['m0c']],
            $encoded['text']
        );

        $this->assertEquals(
            'Read the <a href="/docs" class="link">docs</a> now',
            $this->renderToHtml($withSentinels, $encoded['slots'])
        );
    }

    public function testRoundTripWithMultibyteText()
    {
        $element = $this->element('Основано на 5 <strong>отзывах</strong>');
        $encoded = $this->tokenizer->encode($element);

        $this->assertEquals('Основано на 5 {m0o}отзывах{m0c}', $encoded['text']);

        $params = $this->tokenizer->tokenParams(1);
        $withSentinels = str_replace(
            ['{m0o}', '{m0c}'],
            [$params['m0o'], $params['m0c']],
            $encoded['text']
        );

        $this->assertEquals(
            'Основано на 5 <strong>отзывах</strong>',
            $this->renderToHtml($withSentinels, $encoded['slots'])
        );
    }
}
