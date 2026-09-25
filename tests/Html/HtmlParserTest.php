<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Html\HtmlParser;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the HtmlParser class.
 */
class HtmlParserTest extends TestCase
{
    /**
     * @var HtmlParser
     */
    protected $parser;

    protected function setUp(): void
    {
        $this->parser = new HtmlParser();
    }

    public function testExtractTextNodes()
    {
        $html = '<div><h1>Welcome</h1><p>Get started today</p></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(2, $phrases);
        $this->assertEquals('Welcome', $phrases[0]);
        $this->assertEquals('Get started today', $phrases[1]);
    }

    public function testExtractNestedTextNodes()
    {
        $html = '<div><span><strong>Bold text</strong> and <em>italic text</em></span></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(3, $phrases);
        $this->assertEquals('Bold text', $phrases[0]);
        $this->assertEquals('and', $phrases[1]);
        $this->assertEquals('italic text', $phrases[2]);
    }

    public function testExtractTranslatableAttributes()
    {
        $html = '<input placeholder="Enter your email" title="Email field" alt="Email icon">';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(3, $phrases);
        $this->assertContains('Enter your email', $phrases);
        $this->assertContains('Email field', $phrases);
        $this->assertContains('Email icon', $phrases);
    }

    public function testExtractAriaLabels()
    {
        $html = '<button aria-label="Close dialog" aria-placeholder="Search here">Click</button>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Close dialog', $phrases);
        $this->assertContains('Search here', $phrases);
        $this->assertContains('Click', $phrases);
    }

    public function testExtractDataErrorAttributes()
    {
        $html = '<input data-error="Invalid input" data-error-message="Please enter a valid value" data-validation-message="Validation failed">';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Invalid input', $phrases);
        $this->assertContains('Please enter a valid value', $phrases);
        $this->assertContains('Validation failed', $phrases);
    }

    public function testExtractButtonValues()
    {
        $html = '<button value="Submit Form">Submit</button>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Submit Form', $phrases);
        $this->assertContains('Submit', $phrases);
    }

    public function testExtractSubmitInputValues()
    {
        $html = '<input type="submit" value="Send Message">';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Send Message', $phrases[0]);
    }

    public function testExtractButtonInputValues()
    {
        $html = '<input type="button" value="Click Here">';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Click Here', $phrases[0]);
    }

    public function testDoNotExtractTextInputValues()
    {
        $html = '<input type="text" value="Default Value">';
        $phrases = $this->parser->extractPhrases($html);

        // text input values should not be extracted as they are user data
        $this->assertNotContains('Default Value', $phrases);
    }

    public function testExtractSelectOptions()
    {
        $html = '<select><option>Select an option</option><option>First</option><option>Second</option></select>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Select an option', $phrases);
        $this->assertContains('First', $phrases);
        $this->assertContains('Second', $phrases);
    }

    public function testRespectTranslateNo()
    {
        $html = '<div><p>Translate this</p><p translate="no">Do not translate this</p><p>And this</p></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Translate this', $phrases);
        $this->assertContains('And this', $phrases);
        $this->assertNotContains('Do not translate this', $phrases);
    }

    /**
     * `data-notrans` follows the same convention as `data-langsys-phrase`:
     * presence is intent, only an explicit off value opts out.
     *
     * It previously tested the raw attribute string for PHP truthiness, which
     * got both ends backwards - bare `data-notrans` is '' and therefore falsy,
     * so the natural form silently did nothing, while `data-notrans="false"` is
     * a non-empty string and therefore truthy, so the OFF value excluded. The
     * failure direction was the dangerous one: content an author marked to
     * protect went into the shared catalog.
     */
    public function testDataNotransPresenceIsIntent()
    {
        foreach (['data-notrans', 'data-notrans="true"', 'data-notrans="1"'] as $attr) {
            $phrases = $this->parser->extractPhrases('<div><p ' . $attr . '>Secret</p><p>Keep</p></div>');

            $this->assertNotContains('Secret', $phrases, $attr . ' must exclude');
            $this->assertContains('Keep', $phrases);
        }
    }

    public function testDataNotransExplicitOffValueOptsOut()
    {
        foreach (['data-notrans="false"', 'data-notrans="FALSE"', 'data-notrans="0"'] as $attr) {
            $this->assertContains(
                'Secret',
                $this->parser->extractPhrases('<div><p ' . $attr . '>Secret</p></div>'),
                $attr . ' must NOT exclude'
            );
        }
    }

    public function testTranslateNoIsCaseInsensitive()
    {
        $this->assertNotContains(
            'Secret',
            $this->parser->extractPhrases('<div><p translate="NO">Secret</p></div>')
        );
    }

    public function testRespectTranslateNoOnContainer()
    {
        $html = '<div translate="no"><h1>Skip this heading</h1><p>Skip this paragraph</p></div><div><p>Keep this</p></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertNotContains('Skip this heading', $phrases);
        $this->assertNotContains('Skip this paragraph', $phrases);
        $this->assertContains('Keep this', $phrases);
    }

    public function testWhitespaceNormalization()
    {
        $html = '<p>   Multiple   spaces   here   </p>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Multiple spaces here', $phrases[0]);
    }

    public function testNewlineNormalization()
    {
        $html = "<p>Line one\nLine two\n\nLine three</p>";
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Line one Line two Line three', $phrases[0]);
    }

    public function testTabNormalization()
    {
        $html = "<p>Tab\there\tand\tthere</p>";
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Tab here and there', $phrases[0]);
    }

    public function testPreserveDuplicatePhrases()
    {
        $html = '<ul><li>Item</li><li>Item</li><li>Item</li></ul>';
        $phrases = $this->parser->extractPhrases($html);

        // Should preserve all duplicates, not deduplicate
        $this->assertCount(3, $phrases);
        $this->assertEquals('Item', $phrases[0]);
        $this->assertEquals('Item', $phrases[1]);
        $this->assertEquals('Item', $phrases[2]);
    }

    public function testPreserveDuplicateInDifferentLocations()
    {
        $html = '<h1>Welcome</h1><p>Welcome to our site</p><footer>Welcome back</footer>';
        $phrases = $this->parser->extractPhrases($html);

        // All phrases preserved even if they contain same words
        $this->assertCount(3, $phrases);
    }

    public function testGenerateCustomId()
    {
        $phrases = ['Welcome', 'Get started today'];
        $id1 = $this->parser->generateCustomId('Marketing', $phrases);
        $id2 = $this->parser->generateCustomId('Marketing', $phrases);
        $id3 = $this->parser->generateCustomId('UI', $phrases);
        $id4 = $this->parser->generateCustomId(null, $phrases);

        // Same category and phrases should generate same ID
        $this->assertEquals($id1, $id2);

        // Different category should generate different ID
        $this->assertNotEquals($id1, $id3);

        // Null category should work
        $this->assertNotEquals($id1, $id4);
        $this->assertEquals(32, strlen($id4)); // md5 hash length
    }

    public function testGenerateCustomIdIsDeterministic()
    {
        $html = '<div><h1>Welcome</h1><p>Get started today</p></div>';
        $phrases = $this->parser->extractPhrases($html);

        $id1 = $this->parser->generateCustomId('Marketing', $phrases);
        $id2 = $this->parser->generateCustomId('Marketing', $phrases);

        $this->assertEquals($id1, $id2);
    }

    /**
     * generateCustomId() must equal md5() over the UTF-8 bytes of the canonical
     * JSON form - md5(JSON.stringify([category, tokens])) as a correct
     * implementation would compute it.
     *
     * Values come from tests/fixtures/custom-id-reference.json, which is the
     * REFERENCE SET for every Langsys SDK: content block identity is shared, so
     * an SDK that disagrees with these ids will duplicate catalog entries.
     *
     * IMPORTANT - what this does NOT prove. It does not verify agreement with
     * the JS SDK as that SDK behaves today. The previous version of this test
     * asserted md5('["Blog",...]') against generateCustomId(), i.e. PHP md5
     * against PHP md5, which proves the JSON shape and nothing about any other
     * implementation - and on the strength of it a false parity claim was
     * shipped in 1.0.2. The JS SDK currently uses a hand-rolled md5 over UTF-16
     * code units, so it agrees with these ids for ASCII only and additionally
     * collides with itself above U+00FF. Fixing that is tracked on the JS side;
     * these fixtures are the target it must reach.
     *
     * Verifying cross-SDK agreement therefore means EXECUTING the other SDK
     * against this file - never re-deriving the expected value in PHP.
     */
    public function testGenerateCustomIdMatchesTheReferenceFixtures()
    {
        $path = dirname(__DIR__) . '/fixtures/custom-id-reference.json';

        $this->assertFileExists($path);

        $cases = json_decode(file_get_contents($path), true);

        $this->assertNotEmpty($cases, 'Reference fixtures must be readable');

        foreach ($cases as $case) {
            // The canonical JSON is recorded so other SDKs can hash the exact
            // same bytes without reimplementing our encoding choices.
            $this->assertSame(
                $case['custom_id'],
                md5($case['canonical_json']),
                'Fixture is self-consistent: ' . $case['canonical_json']
            );

            $this->assertSame(
                $case['custom_id'],
                $this->parser->generateCustomId($case['category'], $case['tokens']),
                'generateCustomId must match the reference for ' . $case['canonical_json']
            );
        }
    }

    /**
     * The reserved sentinel and null both mean "no category" and must hash
     * identically to an empty string - the JS side passes a raw '' and never
     * the sentinel, and PHP's two callers default differently.
     */
    public function testUncategorizedSentinelNormalizesToEmptyCategory()
    {
        $expected = $this->parser->generateCustomId('', ['Hello', 'World']);

        $this->assertSame($expected, $this->parser->generateCustomId(null, ['Hello', 'World']));
        $this->assertSame($expected, $this->parser->generateCustomId('__uncategorized__', ['Hello', 'World']));
    }

    /**
     * The tokenizer reference fixtures: HTML fragment in, expected token list
     * out, plus the id those tokens produce.
     *
     * `custom-id-reference.json` pins the HASH but its inputs are synthetic
     * token lists that never touch a DOM — so a divergence in how the two SDKs
     * *derive* tokens from HTML passes it silently. That is not hypothetical:
     * the JS SDK harvested every `<option>` twice, so any content block with a
     * `<select>` had different ids in the two SDKs while both fixture suites
     * were green.
     *
     * Same rule as the id fixtures: verify another SDK by EXECUTING it against
     * this file, never by re-deriving the expectation in the same language.
     */
    /**
     * The cross-SDK canonicalization vectors (TOK-1, TOK-2, TOK-4).
     *
     * Authored by langsys-js-typescript and adopted byte-identically
     * (`a639ae8c:tests/fixtures/canonicalization-reference.json`, blob
     * `34034931872b93e761faea49fb040f3fd8a6b9f5`, 32 rows). Each row carries every
     * lane's MEASURED output alongside the expectation, so the file records
     * where the fleet disagreed rather than only where it should agree — this
     * SDK was 13/19 when the file was first adopted at `6596faf` (blob
     * `e4c1f185...`, 19 rows). Re-vendored at `4eac870` (26 rows) for the 8.0.1
     * re-row: no existing row's expectation changed, and the seven added rows
     * are the 8.0.1 behaviours. The current copy adds six rows for TOK-2's C0
     * strip (8.2.7), measured by this SDK, with no earlier row's expectation
     * changed; `spec_blob` names `5fa32cb9`.
     *
     * Every row also carries codepoints. That is the load-bearing part: the
     * three rules here turn on characters that are invisible in a terminal and
     * in a diff. U+00A0 and U+0020 render identically, which is exactly how a
     * divergence that re-keys content blocks survived in two SDKs at once.
     * Tests must use escapes, never literals, for the same reason.
     */
    public function testCanonicalizationMatchesTheCrossSdkVectors()
    {
        $path = dirname(__DIR__) . '/fixtures/canonicalization-reference.json';

        $this->assertFileExists($path);

        $fixture = json_decode(file_get_contents($path), true);
        $cases = $fixture['cases'];

        $this->assertCount(32, $cases, 'the adopted file has 32 rows');

        foreach ($cases as $case) {
            $tokens = array_values($this->parser->extractPhrases($case['html']));

            $this->assertSame(
                $case['expected_tokens'],
                $tokens,
                $case['id'] . ' — ' . $case['why'] . ' — got ' . $this->describeCodepoints($tokens)
            );

            $this->assertSame(
                $case['expected_custom_id'],
                $this->parser->generateCustomId($case['category'], $tokens),
                'id for ' . $case['id']
            );
        }
    }

    /**
     * Render a token list as codepoints.
     *
     * A failure message showing "A long description" against "A long
     * description" is worse than no message: the reader concludes the test is
     * broken. These rules are only legible as codepoints.
     */
    private function describeCodepoints(array $tokens)
    {
        $out = [];

        foreach ($tokens as $token) {
            $points = [];
            foreach (preg_split('//u', $token, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                $points[] = sprintf('U+%04X', mb_ord($char, 'UTF-8'));
            }
            $out[] = '[' . implode(' ', $points) . ']';
        }

        return implode(' ', $out);
    }

    /**
     * TOK-2, stated as vectors rather than only as a fixture row.
     *
     * Written with \u{...} escapes on purpose. A literal non-breaking space in
     * a test file is indistinguishable from a space in every editor, diff and
     * code review - which is how this divergence lived in two SDKs at once. A
     * test that cannot be read is not a test.
     *
     * @dataProvider unicodeWhitespaceProvider
     */
    public function testUnicodeWhitespaceCollapsesLikeAnyOtherWhitespace($html, $expected, $why)
    {
        $this->assertSame($expected, array_values($this->parser->extractPhrases($html)), $why);
    }

    public function unicodeWhitespaceProvider()
    {
        return [
            // Internal.
            'NBSP between words' => [
                "<p>A\u{00A0}long   description</p>",
                ['A long description'],
                'U+00A0 must collapse like U+0020',
            ],
            'line separators' => [
                "<p>line one\u{2028}line two\u{2029}para</p>",
                ['line one line two para'],
                'U+2028 and U+2029 are whitespace too',
            ],
            'narrow NBSP and ideographic space' => [
                "<p>a\u{202F}b\u{3000}c</p>",
                ['a b c'],
                'U+202F and U+3000 are matched by \s under /u',
            ],

            // Leading and trailing. trim()'s default charlist does NOT strip
            // these, so the collapse has to run first for them to go.
            'NBSP at the edges' => [
                "<p>\u{00A0}Buy now\u{00A0}</p>",
                ['Buy now'],
                'leading and trailing U+00A0 must be trimmed, which trim() alone will not do',
            ],

            // The count case. This is the one that re-keys blocks: a
            // whitespace-only node is ONE token where U+00A0 survives and ZERO
            // where it collapses, and a block id is a hash of its token list.
            'whitespace-only node yields no token' => [
                "<div><p>Buy now</p><p>\u{00A0}</p><p>Later</p></div>",
                ['Buy now', 'Later'],
                'a whitespace-only node must produce NO token, or every block containing one is re-keyed',
            ],
            'whitespace-only node, mixed' => [
                "<div><p>Buy now</p><p> \u{00A0}\u{2028} </p><p>Later</p></div>",
                ['Buy now', 'Later'],
                'mixed ASCII and non-ASCII whitespace is still whitespace-only',
            ],
        ];
    }

    /**
     * And the id actually moves with it - the reason TOK-2 is an identity rule
     * and not a cosmetic one.
     */
    public function testAWhitespaceOnlyNodeDoesNotChangeABlockId()
    {
        $withEmptyNode = $this->parser->extractPhrases("<div><p>Buy now</p><p>\u{00A0}</p><p>Later</p></div>");
        $withoutIt = $this->parser->extractPhrases('<div><p>Buy now</p><p>Later</p></div>');

        $this->assertSame($withoutIt, $withEmptyNode);
        $this->assertSame(
            $this->parser->generateCustomId('UI', $withoutIt),
            $this->parser->generateCustomId('UI', $withEmptyNode),
            'a block must not be re-keyed by a node that renders as nothing'
        );
    }

    public function testTokenizerMatchesTheReferenceFixtures()
    {
        $path = dirname(__DIR__) . '/fixtures/tokenizer-reference.json';

        $this->assertFileExists($path);

        $cases = json_decode(file_get_contents($path), true);

        $this->assertNotEmpty($cases);

        foreach ($cases as $case) {
            $this->assertSame(
                $case['tokens'],
                $this->parser->extractPhrases($case['html']),
                $case['description'] . ' — ' . $case['html']
            );

            // Every input to the hash is recorded, so another SDK can
            // reproduce the id without knowing our defaults. The category was
            // originally omitted, which made this column unverifiable from
            // outside - it looked checked while being uncheckable.
            $this->assertSame(
                $case['custom_id'],
                md5($case['canonical_json']),
                'fixture is self-consistent: ' . $case['canonical_json']
            );

            // The two fixture files compose: these tokens must produce the id.
            $this->assertSame(
                $case['custom_id'],
                $this->parser->generateCustomId($case['category'], $case['tokens']),
                'id for ' . $case['html']
            );
        }
    }

    public function testMalformedHtml()
    {
        // Unclosed tags
        $html = '<div><p>Paragraph without closing tag<span>More text';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Paragraph without closing tag', $phrases);
        $this->assertContains('More text', $phrases);
    }

    public function testHtmlEntities()
    {
        $html = '<p>Copyright &copy; 2024 &amp; beyond</p>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        // HTML entities should be decoded
        $this->assertStringContainsString('2024', $phrases[0]);
    }

    public function testEmptyHtml()
    {
        $phrases = $this->parser->extractPhrases('');
        $this->assertCount(0, $phrases);

        $phrases = $this->parser->extractPhrases(null);
        $this->assertCount(0, $phrases);
    }

    public function testWhitespaceOnlyHtml()
    {
        $html = '   <div>   </div>   ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(0, $phrases);
    }

    public function testComplexFormExample()
    {
        $html = '
            <form>
                <label>Name</label>
                <input type="text" placeholder="Enter your name" title="Full name required">

                <label>Email</label>
                <input type="email" placeholder="your@email.com" data-error-message="Invalid email">

                <button type="submit">Submit</button>
            </form>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Name', $phrases);
        $this->assertContains('Enter your name', $phrases);
        $this->assertContains('Full name required', $phrases);
        $this->assertContains('Email', $phrases);
        $this->assertContains('your@email.com', $phrases);
        $this->assertContains('Invalid email', $phrases);
        $this->assertContains('Submit', $phrases);
    }

    /**
     * This test used to assert only that 'Real text' was PRESENT, with a note
     * conceding that DOMDocument might parse the script and style anyway - so
     * it passed while the parser was harvesting `var x = "JavaScript text";`
     * and `.class { content: "CSS text"; }` as translatable phrases and sending
     * them for machine translation. A test that cannot fail is worse than an
     * absent one, because it is counted as coverage.
     *
     * assertSame on the whole list is the difference: it fails on anything
     * extra, which is precisely what the rule forbids.
     */
    public function testScriptAndStyleTagsIgnored()
    {
        $html = '<div><script>var x = "JavaScript text";</script><style>.class { content: "CSS text"; }</style><p>Real text</p></div>';

        $this->assertSame(['Real text'], array_values($this->parser->extractPhrases($html)));
    }

    /**
     * TOK-1 in the shape the spec states it: one document carrying all four
     * excluded elements plus ordinary markup.
     *
     * The ordinary-markup control is the whole test. Built only from excluded
     * elements, this passes against a tokenizer that tokenizes nothing at all -
     * so the surviving phrase is what proves the parser was working and chose
     * to skip the rest.
     */
    public function testTok1ExcludesAllFourElementsInOneDocument()
    {
        $html = '<div>'
            . '<script>window.dataLayer.push(1)</script>'
            . '<style>.plan{color:#fff}</style>'
            . '<template><p>Template copy</p></template>'
            . '<noscript>Enable JavaScript</noscript>'
            . '<p>Plans</p>'
            . '</div>';

        $this->assertSame(['Plans'], array_values($this->parser->extractPhrases($html)));
    }

    /**
     * `<template>` on its own, because it is the one exclusion no test covered:
     * dropping it from the list reddened nothing, while the element is live
     * under libxml2 (which puts template children in the tree, unlike a
     * scripting-enabled parser).
     *
     * Stated as a de-duplication so the failure is legible: without the
     * exclusion the same sentence is harvested twice, which both changes the
     * block's id and registers template source as page copy.
     */
    public function testTemplateContentsAreNotHarvested()
    {
        $html = '<template><p>Same sentence</p></template><p>Same sentence</p>';

        $this->assertSame(['Same sentence'], array_values($this->parser->extractPhrases($html)));
    }

    public function testCommentsIgnored()
    {
        $html = '<div><!-- This is a comment --><p>Visible text</p></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(1, $phrases);
        $this->assertEquals('Visible text', $phrases[0]);
    }

    public function testDataRequiredMessage()
    {
        $html = '<input data-required-message="This field is required" data-pattern-message="Invalid format" data-invalid-message="Please check your input">';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('This field is required', $phrases);
        $this->assertContains('Invalid format', $phrases);
        $this->assertContains('Please check your input', $phrases);
    }

    public function testOrderPreserved()
    {
        $html = '<div><span>First</span><span>Second</span><span>Third</span></div>';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertEquals('First', $phrases[0]);
        $this->assertEquals('Second', $phrases[1]);
        $this->assertEquals('Third', $phrases[2]);
    }

    public function testAttributeOrderPreserved()
    {
        $html = '<input placeholder="First attr" title="Second attr" alt="Third attr">';
        $phrases = $this->parser->extractPhrases($html);

        // Attributes are extracted in the order defined in TRANSLATABLE_ATTRIBUTES
        $this->assertEquals('First attr', $phrases[0]); // placeholder comes first in constant
        $this->assertEquals('Third attr', $phrases[1]); // alt
        $this->assertEquals('Second attr', $phrases[2]); // title
    }

    // =========================================================================
    // Real-world Content Block Scenarios
    // =========================================================================

    public function testNavigationMenu()
    {
        $html = '
            <nav>
                <a href="/">Home</a>
                <a href="/about">About Us</a>
                <a href="/services">Services</a>
                <a href="/contact">Contact</a>
            </nav>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(4, $phrases);
        $this->assertEquals('Home', $phrases[0]);
        $this->assertEquals('About Us', $phrases[1]);
        $this->assertEquals('Services', $phrases[2]);
        $this->assertEquals('Contact', $phrases[3]);
    }

    public function testHeroSection()
    {
        $html = '
            <section class="hero">
                <h1>Welcome to Our Platform</h1>
                <p>The best solution for your business needs</p>
                <a href="/signup" class="btn">Get Started Free</a>
                <a href="/demo" class="btn-secondary">Watch Demo</a>
            </section>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertCount(4, $phrases);
        $this->assertEquals('Welcome to Our Platform', $phrases[0]);
        $this->assertEquals('The best solution for your business needs', $phrases[1]);
        $this->assertEquals('Get Started Free', $phrases[2]);
        $this->assertEquals('Watch Demo', $phrases[3]);
    }

    public function testProductCard()
    {
        $html = '
            <article class="product-card">
                <img src="product.jpg" alt="Premium Headphones">
                <h3>Wireless Headphones</h3>
                <p class="price">$99.99</p>
                <p class="description">High-quality audio experience with noise cancellation</p>
                <button>Add to Cart</button>
            </article>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Premium Headphones', $phrases); // alt attribute
        $this->assertContains('Wireless Headphones', $phrases);
        $this->assertContains('$99.99', $phrases);
        $this->assertContains('High-quality audio experience with noise cancellation', $phrases);
        $this->assertContains('Add to Cart', $phrases);
    }

    public function testFooterBlock()
    {
        $html = '
            <footer>
                <div class="footer-col">
                    <h4>Company</h4>
                    <ul>
                        <li><a>About</a></li>
                        <li><a>Careers</a></li>
                        <li><a>Press</a></li>
                    </ul>
                </div>
                <p class="copyright">© 2024 Company Name. All rights reserved.</p>
            </footer>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Company', $phrases);
        $this->assertContains('About', $phrases);
        $this->assertContains('Careers', $phrases);
        $this->assertContains('Press', $phrases);
        $this->assertContains('© 2024 Company Name. All rights reserved.', $phrases);
    }

    public function testCallToActionBlock()
    {
        $html = '
            <div class="cta">
                <h2>Ready to get started?</h2>
                <p>Join thousands of satisfied customers today.</p>
                <input type="email" placeholder="Enter your email address">
                <button type="submit">Subscribe Now</button>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Ready to get started?', $phrases);
        $this->assertContains('Join thousands of satisfied customers today.', $phrases);
        $this->assertContains('Enter your email address', $phrases);
        $this->assertContains('Subscribe Now', $phrases);
    }

    public function testTableWithHeaders()
    {
        $html = '
            <table>
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Price</th>
                        <th>Quantity</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Widget A</td>
                        <td>$10.00</td>
                        <td>5</td>
                    </tr>
                </tbody>
            </table>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Product', $phrases);
        $this->assertContains('Price', $phrases);
        $this->assertContains('Quantity', $phrases);
        $this->assertContains('Widget A', $phrases);
        $this->assertContains('$10.00', $phrases);
        $this->assertContains('5', $phrases);
    }

    public function testImageGalleryWithCaptions()
    {
        $html = '
            <div class="gallery">
                <figure>
                    <img src="img1.jpg" alt="Mountain landscape at sunset">
                    <figcaption>Beautiful mountain view</figcaption>
                </figure>
                <figure>
                    <img src="img2.jpg" alt="Ocean waves on beach">
                    <figcaption>Peaceful beach scene</figcaption>
                </figure>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Mountain landscape at sunset', $phrases);
        $this->assertContains('Beautiful mountain view', $phrases);
        $this->assertContains('Ocean waves on beach', $phrases);
        $this->assertContains('Peaceful beach scene', $phrases);
    }

    public function testAccordionFAQ()
    {
        $html = '
            <div class="faq">
                <div class="faq-item">
                    <h3>How do I reset my password?</h3>
                    <p>Click on the forgot password link on the login page.</p>
                </div>
                <div class="faq-item">
                    <h3>What payment methods do you accept?</h3>
                    <p>We accept all major credit cards and PayPal.</p>
                </div>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('How do I reset my password?', $phrases);
        $this->assertContains('Click on the forgot password link on the login page.', $phrases);
        $this->assertContains('What payment methods do you accept?', $phrases);
        $this->assertContains('We accept all major credit cards and PayPal.', $phrases);
    }

    public function testPricingTable()
    {
        $html = '
            <div class="pricing-card">
                <h3>Professional</h3>
                <p class="price">$49/month</p>
                <ul>
                    <li>Unlimited projects</li>
                    <li>Priority support</li>
                    <li>Advanced analytics</li>
                </ul>
                <button>Choose Plan</button>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Professional', $phrases);
        $this->assertContains('$49/month', $phrases);
        $this->assertContains('Unlimited projects', $phrases);
        $this->assertContains('Priority support', $phrases);
        $this->assertContains('Advanced analytics', $phrases);
        $this->assertContains('Choose Plan', $phrases);
    }

    public function testContactFormComplete()
    {
        $html = '
            <form class="contact-form">
                <h2>Get in Touch</h2>
                <p>Fill out the form below and we\'ll get back to you soon.</p>

                <label>Full Name</label>
                <input type="text" placeholder="John Doe" data-required-message="Name is required">

                <label>Email Address</label>
                <input type="email" placeholder="john@example.com" data-error-message="Please enter a valid email">

                <label>Message</label>
                <textarea placeholder="How can we help you?"></textarea>

                <button type="submit">Send Message</button>
            </form>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Get in Touch', $phrases);
        $this->assertContains("Fill out the form below and we'll get back to you soon.", $phrases);
        $this->assertContains('Full Name', $phrases);
        $this->assertContains('John Doe', $phrases);
        $this->assertContains('Name is required', $phrases);
        $this->assertContains('Email Address', $phrases);
        $this->assertContains('john@example.com', $phrases);
        $this->assertContains('Please enter a valid email', $phrases);
        $this->assertContains('Message', $phrases);
        $this->assertContains('How can we help you?', $phrases);
        $this->assertContains('Send Message', $phrases);
    }

    public function testErrorPage()
    {
        $html = '
            <div class="error-page">
                <h1>404</h1>
                <h2>Page Not Found</h2>
                <p>Sorry, the page you are looking for does not exist.</p>
                <a href="/">Return to Homepage</a>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('404', $phrases);
        $this->assertContains('Page Not Found', $phrases);
        $this->assertContains('Sorry, the page you are looking for does not exist.', $phrases);
        $this->assertContains('Return to Homepage', $phrases);
    }

    public function testModalDialog()
    {
        $html = '
            <div class="modal">
                <h2>Confirm Action</h2>
                <p>Are you sure you want to delete this item? This action cannot be undone.</p>
                <button class="cancel">Cancel</button>
                <button class="confirm">Delete</button>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Confirm Action', $phrases);
        $this->assertContains('Are you sure you want to delete this item? This action cannot be undone.', $phrases);
        $this->assertContains('Cancel', $phrases);
        $this->assertContains('Delete', $phrases);
    }

    public function testLoginForm()
    {
        $html = '
            <form class="login">
                <h1>Welcome Back</h1>
                <p>Sign in to your account</p>

                <input type="email" placeholder="Email address" aria-label="Email input">
                <input type="password" placeholder="Password" aria-label="Password input">

                <label><input type="checkbox"> Remember me</label>

                <button type="submit">Sign In</button>

                <a>Forgot your password?</a>
                <p>Don\'t have an account? <a>Sign up</a></p>
            </form>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Welcome Back', $phrases);
        $this->assertContains('Sign in to your account', $phrases);
        $this->assertContains('Email address', $phrases);
        $this->assertContains('Email input', $phrases);
        $this->assertContains('Password', $phrases);
        $this->assertContains('Password input', $phrases);
        $this->assertContains('Remember me', $phrases);
        $this->assertContains('Sign In', $phrases);
        $this->assertContains('Forgot your password?', $phrases);
        $this->assertContains("Don't have an account?", $phrases);
        $this->assertContains('Sign up', $phrases);
    }

    public function testBreadcrumbs()
    {
        $html = '
            <nav aria-label="Breadcrumb navigation">
                <ol>
                    <li><a>Home</a></li>
                    <li><a>Products</a></li>
                    <li><a>Electronics</a></li>
                    <li aria-current="page">Headphones</li>
                </ol>
            </nav>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Breadcrumb navigation', $phrases);
        $this->assertContains('Home', $phrases);
        $this->assertContains('Products', $phrases);
        $this->assertContains('Electronics', $phrases);
        $this->assertContains('Headphones', $phrases);
    }

    public function testAlertMessages()
    {
        $html = '
            <div class="alert alert-success">
                <strong>Success!</strong> Your changes have been saved.
            </div>
            <div class="alert alert-error">
                <strong>Error!</strong> Something went wrong. Please try again.
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Success!', $phrases);
        $this->assertContains('Your changes have been saved.', $phrases);
        $this->assertContains('Error!', $phrases);
        $this->assertContains('Something went wrong. Please try again.', $phrases);
    }

    public function testTestimonialBlock()
    {
        $html = '
            <blockquote class="testimonial">
                <p>"This product changed my life. I can\'t imagine going back."</p>
                <footer>
                    <cite>Jane Smith</cite>
                    <span>CEO, TechCorp</span>
                </footer>
            </blockquote>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains("\"This product changed my life. I can't imagine going back.\"", $phrases);
        $this->assertContains('Jane Smith', $phrases);
        $this->assertContains('CEO, TechCorp', $phrases);
    }

    public function testSearchForm()
    {
        $html = '
            <form role="search">
                <input type="search" placeholder="Search products..." aria-label="Search">
                <button type="submit" aria-label="Submit search">
                    <span class="sr-only">Search</span>
                </button>
            </form>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Search products...', $phrases);
        $this->assertContains('Search', $phrases); // aria-label on input
        $this->assertContains('Submit search', $phrases);
    }

    public function testDropdownMenu()
    {
        $html = '
            <select aria-label="Select country">
                <option value="">Choose a country</option>
                <option value="us">United States</option>
                <option value="uk">United Kingdom</option>
                <option value="ca">Canada</option>
            </select>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Select country', $phrases);
        $this->assertContains('Choose a country', $phrases);
        $this->assertContains('United States', $phrases);
        $this->assertContains('United Kingdom', $phrases);
        $this->assertContains('Canada', $phrases);
    }

    public function testMixedContentWithTranslateNo()
    {
        $html = '
            <article>
                <h1>Understanding Machine Learning</h1>
                <p>Machine learning is a subset of artificial intelligence.</p>
                <pre translate="no"><code>model.fit(X_train, y_train)</code></pre>
                <p>The code above shows a simple training example.</p>
            </article>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Understanding Machine Learning', $phrases);
        $this->assertContains('Machine learning is a subset of artificial intelligence.', $phrases);
        $this->assertContains('The code above shows a simple training example.', $phrases);
        // Code should NOT be extracted
        $this->assertNotContains('model.fit(X_train, y_train)', $phrases);
    }

    public function testComplexNestedStructure()
    {
        $html = '
            <div class="card">
                <div class="card-header">
                    <h3>Featured Article</h3>
                    <span class="badge">New</span>
                </div>
                <div class="card-body">
                    <p>Discover the latest trends in web development.</p>
                    <ul>
                        <li><strong>React</strong> - Component-based UI</li>
                        <li><strong>Vue</strong> - Progressive framework</li>
                        <li><strong>Svelte</strong> - Compiler approach</li>
                    </ul>
                </div>
                <div class="card-footer">
                    <a>Read More</a>
                </div>
            </div>
        ';
        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('Featured Article', $phrases);
        $this->assertContains('New', $phrases);
        $this->assertContains('Discover the latest trends in web development.', $phrases);
        $this->assertContains('React', $phrases);
        $this->assertContains('- Component-based UI', $phrases);
        $this->assertContains('Vue', $phrases);
        $this->assertContains('- Progressive framework', $phrases);
        $this->assertContains('Svelte', $phrases);
        $this->assertContains('- Compiler approach', $phrases);
        $this->assertContains('Read More', $phrases);
    }

    // =========================================================================
    // Configurable Translatable Attributes Tests
    // =========================================================================

    public function testDefaultTranslatableAttributes()
    {
        $expected = [
            // Standard HTML
            'placeholder',
            'alt',
            'title',
            'label',

            // ARIA accessibility
            'aria-label',
            'aria-placeholder',
            'aria-description',
            'aria-valuetext',
            'aria-roledescription',

            // Form validation messages
            'data-error',
            'data-error-message',
            'data-validation-message',
            'data-invalid-message',
            'data-required-message',
            'data-pattern-message',

            // Common framework patterns
            'data-confirm',
            'data-tooltip',
            'data-title',
            'data-content',
            'data-original-title',
            'data-bs-title',
            'data-bs-content',
            'data-loading-text',
            'data-success-message',
            'data-warning-message',
            'data-empty-message',
            'data-placeholder',
        ];

        $this->assertEquals($expected, $this->parser->getTranslatableAttributes());
    }

    public function testSetCustomTranslatableAttributes()
    {
        $customAttrs = ['data-label', 'data-tooltip'];
        $this->parser->setTranslatableAttributes($customAttrs);

        $this->assertEquals($customAttrs, $this->parser->getTranslatableAttributes());
    }

    public function testAddTranslatableAttributes()
    {
        $this->parser->addTranslatableAttributes(['data-custom', 'data-tooltip']);

        $attrs = $this->parser->getTranslatableAttributes();
        $this->assertContains('placeholder', $attrs); // default still present
        $this->assertContains('data-custom', $attrs); // new attribute added
        $this->assertContains('data-tooltip', $attrs); // new attribute added
    }

    public function testAddTranslatableAttributesNoDuplicates()
    {
        $this->parser->addTranslatableAttributes(['placeholder', 'data-custom']);

        $attrs = $this->parser->getTranslatableAttributes();
        // Count how many times 'placeholder' appears
        $count = count(array_filter($attrs, function($a) { return $a === 'placeholder'; }));
        $this->assertEquals(1, $count);
    }

    public function testResetTranslatableAttributes()
    {
        $this->parser->setTranslatableAttributes(['only-this']);
        $this->assertEquals(['only-this'], $this->parser->getTranslatableAttributes());

        $this->parser->resetTranslatableAttributes();
        $this->assertEquals(HtmlParser::DEFAULT_TRANSLATABLE_ATTRIBUTES, $this->parser->getTranslatableAttributes());
    }

    public function testCustomAttributeExtraction()
    {
        $parser = new HtmlParser(['data-label', 'data-hint']);

        $html = '<div data-label="Custom Label" data-hint="Helpful hint" placeholder="Ignored"></div>';
        $phrases = $parser->extractPhrases($html);

        $this->assertContains('Custom Label', $phrases);
        $this->assertContains('Helpful hint', $phrases);
        $this->assertNotContains('Ignored', $phrases); // placeholder not in custom list
    }

    public function testConstructorWithCustomAttributes()
    {
        $customAttrs = ['data-text', 'data-description'];
        $parser = new HtmlParser($customAttrs);

        $this->assertEquals($customAttrs, $parser->getTranslatableAttributes());
    }

    public function testConstructorWithNullUsesDefaults()
    {
        $parser = new HtmlParser(null);

        $this->assertEquals(HtmlParser::DEFAULT_TRANSLATABLE_ATTRIBUTES, $parser->getTranslatableAttributes());
    }

    public function testFluentInterface()
    {
        $result = $this->parser
            ->setTranslatableAttributes(['a'])
            ->addTranslatableAttributes(['b'])
            ->resetTranslatableAttributes();

        $this->assertSame($this->parser, $result);
    }

    public function testRealWorldCustomAttributes()
    {
        // Simulate a developer adding their app-specific attributes
        $this->parser->addTranslatableAttributes([
            'data-i18n',
            'data-translate',
            'data-content',
        ]);

        $html = '
            <div data-i18n="welcome.message">Welcome</div>
            <span data-translate="cta.button">Click Here</span>
            <p data-content="description">Some description</p>
            <input placeholder="Email">
        ';

        $phrases = $this->parser->extractPhrases($html);

        $this->assertContains('welcome.message', $phrases);
        $this->assertContains('cta.button', $phrases);
        $this->assertContains('description', $phrases);
        $this->assertContains('Email', $phrases); // default attr still works
        $this->assertContains('Welcome', $phrases);
        $this->assertContains('Click Here', $phrases);
        $this->assertContains('Some description', $phrases);
    }

    // =========================================================================
    // URL Resolution Tests
    // =========================================================================

    public function testResolveRelativeUrls()
    {
        $html = '<img src="/images/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://example.com/images/photo.jpg"', $result);
    }

    public function testResolveRelativeUrlPath()
    {
        $html = '<img src="images/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://example.com/images/photo.jpg"', $result);
    }

    public function testResolveRelativeUrlsSkipsAbsolute()
    {
        $html = '<img src="https://cdn.example.com/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://cdn.example.com/photo.jpg"', $result);
    }

    public function testResolveRelativeUrlsSkipsDataUri()
    {
        $html = '<img src="data:image/png;base64,abc123" alt="Icon">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="data:image/png;base64,abc123"', $result);
    }

    public function testResolveRelativeUrlsSkipsProtocolRelative()
    {
        $html = '<img src="//cdn.example.com/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="//cdn.example.com/photo.jpg"', $result);
    }

    public function testResolveRelativeUrlsSrcset()
    {
        $html = '<img src="/photo.jpg" srcset="/photo-1x.jpg 1x, /photo-2x.jpg 2x" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://example.com/photo.jpg"', $result);
        $this->assertStringContainsString('srcset="https://example.com/photo-1x.jpg 1x, https://example.com/photo-2x.jpg 2x"', $result);
    }

    public function testResolveRelativeUrlsSrcsetWithWidth()
    {
        $html = '<img src="/photo.jpg" srcset="/small.jpg 100w, /large.jpg 200w" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('srcset="https://example.com/small.jpg 100w, https://example.com/large.jpg 200w"', $result);
    }

    public function testResolveRelativeUrlsVideoPoster()
    {
        $html = '<video src="/video.mp4" poster="/thumbnail.jpg"></video>';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://example.com/video.mp4"', $result);
        $this->assertStringContainsString('poster="https://example.com/thumbnail.jpg"', $result);
    }

    public function testResolveRelativeUrlsMultipleImages()
    {
        $html = '<div><img src="/img1.jpg"><img src="/img2.jpg"><img src="https://other.com/img3.jpg"></div>';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com');

        $this->assertStringContainsString('src="https://example.com/img1.jpg"', $result);
        $this->assertStringContainsString('src="https://example.com/img2.jpg"', $result);
        $this->assertStringContainsString('src="https://other.com/img3.jpg"', $result);
    }

    public function testResolveRelativeUrlsStripsTrailingSlash()
    {
        $html = '<img src="/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, 'https://example.com/');

        // Should not have double slash
        $this->assertStringContainsString('src="https://example.com/photo.jpg"', $result);
        $this->assertStringNotContainsString('src="https://example.com//photo.jpg"', $result);
    }

    public function testResolveRelativeUrlsEmptyBaseUrl()
    {
        $html = '<img src="/photo.jpg" alt="Photo">';
        $result = $this->parser->resolveRelativeUrls($html, '');

        // Should return original
        $this->assertStringContainsString('src="/photo.jpg"', $result);
    }

    public function testResolveRelativeUrlsEmptyHtml()
    {
        $result = $this->parser->resolveRelativeUrls('', 'https://example.com');
        $this->assertEquals('', $result);
    }

    /**
     * The 15 attributes langsys-js-typescript and langsys-js-server share with
     * this SDK, frozen here as a literal rather than sliced out of the constant.
     *
     * Slicing would compare the constant to itself and pass for any value of it,
     * which is the 1.0.2 custom_id parity mistake in a different costume. This
     * list is a RECORD of what the other SDKs implement; it must be edited only
     * when they change, and a diff here is the signal that they have.
     */
    const JS_SHARED_ATTRIBUTES = [
        'placeholder',
        'alt',
        'title',
        'label',
        'aria-label',
        'aria-placeholder',
        'aria-description',
        'aria-valuetext',
        'aria-roledescription',
        'data-error',
        'data-error-message',
        'data-validation-message',
        'data-invalid-message',
        'data-required-message',
        'data-pattern-message',
    ];

    /**
     * The 12 framework attributes that only this SDK carried until the 2026-08
     * cross-SDK decision converged the JS SDKs onto them.
     */
    const FRAMEWORK_ATTRIBUTES = [
        'data-confirm',
        'data-tooltip',
        'data-title',
        'data-content',
        'data-original-title',
        'data-bs-title',
        'data-bs-content',
        'data-loading-text',
        'data-success-message',
        'data-warning-message',
        'data-empty-message',
        'data-placeholder',
    ];

    /**
     * The default list is the normative cross-SDK contract, so it is pinned.
     *
     * A conformance suite that runs fixtures against an EXPLICITLY configured
     * list proves the tokenizers agree given a list; it proves nothing about
     * whether that list is the one real callers get, because nobody constructs
     * an HtmlParser with 27 arguments in production. Editing the constant would
     * leave such a suite green while every default-configured app in the world
     * silently re-keyed. This test is the other half.
     */
    public function testDefaultTranslatableAttributesArePinned()
    {
        $expected = array_merge(self::JS_SHARED_ATTRIBUTES, self::FRAMEWORK_ATTRIBUTES);

        // assertSame, not assertEquals: order is custom_id identity.
        $this->assertSame(
            $expected,
            HtmlParser::DEFAULT_TRANSLATABLE_ATTRIBUTES,
            'DEFAULT_TRANSLATABLE_ATTRIBUTES is the cross-SDK contract for content '
            . 'block identity. Changing it re-keys existing content blocks in every '
            . 'Langsys SDK. If this change is intended, update langsys-js-typescript '
            . 'and langsys-js-server together and say so in the release notes.'
        );

        // The 15 shared attributes must stay FIRST and contiguous. Appending is
        // cheap because it only re-keys blocks carrying an appended attribute;
        // interleaving would re-key every block carrying any of them.
        $this->assertSame(
            self::JS_SHARED_ATTRIBUTES,
            array_slice(HtmlParser::DEFAULT_TRANSLATABLE_ATTRIBUTES, 0, 15),
            'The JS-shared attributes must remain the first 15, in order.'
        );
        $this->assertSame(
            self::FRAMEWORK_ATTRIBUTES,
            array_slice(HtmlParser::DEFAULT_TRANSLATABLE_ATTRIBUTES, 15),
            'The framework attributes must remain one contiguous block after them.'
        );
    }

    /**
     * The pin above is a list comparison, which passes just as happily if the
     * parser has stopped consulting the list at all. Prove the mechanism runs:
     * every pinned attribute must actually produce a token.
     */
    public function testEveryPinnedAttributeIsActuallyHarvested()
    {
        $parser = new HtmlParser();

        foreach (HtmlParser::DEFAULT_TRANSLATABLE_ATTRIBUTES as $attr) {
            $phrases = $parser->extractPhrases(
                '<div ' . $attr . '="Harvest me">body</div>'
            );

            $this->assertContains(
                'Harvest me',
                $phrases,
                'Attribute "' . $attr . '" is in the pinned default list but produced '
                . 'no token, so the list and the walker disagree.'
            );
        }

        // Negative control: an attribute NOT on the list must be ignored, or the
        // assertions above would pass for a parser that harvests everything.
        $this->assertSame(
            ['body'],
            $parser->extractPhrases('<div data-not-translatable="Skip me">body</div>')
        );
    }

    /**
     * json_encode() returns false on invalid UTF-8 and md5(false) === md5(''),
     * so without a false-check every malformed block hashes to the same id and
     * aliases unrelated content. Behaviour already present on this line; pinned
     * so it cannot be simplified away.
     */
    public function testInvalidUtf8BlocksDoNotShareOneCustomId()
    {
        $a = $this->parser->generateCustomId('Cat', ["Caf\xE9 one"]);
        $b = $this->parser->generateCustomId('Cat', ["Totally \xE9 different"]);

        $this->assertNotEquals($a, $b, 'Distinct malformed content must not alias');
        $this->assertNotEquals(md5(''), $a, 'A block id must never be the md5 of an empty string');
    }

    /**
     * Legacy ids are lookup-only, but they must be computable for both category
     * slots the old code could have written.
     */
    public function testLegacyCustomIdsCoverBothCategorySlotsWhenUncategorized()
    {
        $ids = $this->parser->legacyCustomIds(null, ['Hello there']);

        $this->assertContains(md5(implode('|', ['', 'Hello there'])), $ids);
        $this->assertContains(md5(implode('|', ['__uncategorized__', 'Hello there'])), $ids);
    }

    /**
     * A real category yields both tolerated shapes: this SDK's pipe-join form
     * and the JS SDKs' pre-fix code-unit hash. CID-3 binds anything that reads
     * catalogs, so content another SDK registered has to resolve here too.
     */
    public function testLegacyCustomIdsCoverBothToleratedShapes()
    {
        $ids = $this->parser->legacyCustomIds('Marketing', ['Hello there']);

        $this->assertContains(
            md5(implode('|', ['Marketing', 'Hello there'])),
            $ids,
            "this SDK's own pre-change form"
        );

        $rows = json_decode(
            file_get_contents(dirname(__DIR__) . '/fixtures/legacy-custom-id-reference.json'),
            true
        );
        $this->assertNotEmpty($rows);

        // And the code-unit shape, proven against vectors generated by executing
        // the TS core rather than by reimplementing its algorithm here.
        $method = new \ReflectionMethod($this->parser, 'codeUnitMd5');
        $method->setAccessible(true);

        foreach ($rows as $index => $row) {
            $this->assertSame(
                $row['legacy_custom_id'],
                $method->invoke($this->parser, $row['canonical_json']),
                'legacy vector row ' . $index . ': ' . $row['note']
            );
        }
    }

    /**
     * And now the part the hash-primitive test cannot reach.
     *
     * Every legacy assertion above invokes codeUnitMd5() with the fixture's own
     * canonical_json. That proves the HASH is ported correctly and nothing
     * more - it says nothing about whether legacyCustomIds(), the method the
     * lookup path actually calls, ever ARRIVES at that string. A perfect hash
     * behind a wrong category slot or a wrong serialization strands the content
     * just as completely, and every one of those tests still passes.
     *
     * So: drive the public method with the row's own inputs and require the
     * row's id to come out. This is what caught the null-category gap - the
     * legacy JS generator did not coalesce null, so an untyped caller's block
     * was filed under '[null,[...]]', a string no slot in this method could
     * produce.
     */
    public function testEveryLegacyVectorIsReachableThroughTheLookupPath()
    {
        $rows = json_decode(
            file_get_contents(dirname(__DIR__) . '/fixtures/legacy-custom-id-reference.json'),
            true
        );

        foreach ($rows as $index => $row) {
            $ids = $this->parser->legacyCustomIds($row['category'], $row['tokens']);

            // The one deliberate exclusion, asserted as an exclusion rather than
            // quietly skipped. '__uncategorized__' is this SDK's own spelling and
            // only ever went out on the pipe-join form; a JS caller reached the
            // code-unit form with '' or with null, never with the sentinel. So
            // this id is one nothing ever wrote, and we decline to look it up.
            if ($row['category'] === '__uncategorized__') {
                $this->assertNotContains(
                    $row['legacy_custom_id'],
                    $ids,
                    'row ' . ($index + 1) . ': the sentinel has no code-unit spelling'
                );
            } else {
                $this->assertContains(
                    $row['legacy_custom_id'],
                    $ids,
                    'row ' . ($index + 1) . ' unreachable through legacyCustomIds(): ' . $row['note']
                );
            }

            if ($row['pipe_join_custom_id'] !== null) {
                $this->assertContains(
                    $row['pipe_join_custom_id'],
                    $ids,
                    'row ' . ($index + 1) . ': pipe-join form unreachable'
                );
            }
        }
    }

    /**
     * The vector file is a shared artifact, adopted byte-identically from
     * langsys-python (blob dc5556466dc54fe82e81ac9fdbf4549b2b76e7ce). Its
     * coverage is the point: ASCII rows are positive controls where the two
     * hashes AGREE, so an ASCII-only suite cannot tell a correct port from a
     * byte-hash stub.
     */
    public function testLegacyVectorFileRetainsItsDivergentCoverage()
    {
        $rows = json_decode(
            file_get_contents(dirname(__DIR__) . '/fixtures/legacy-custom-id-reference.json'),
            true
        );

        $this->assertCount(20, $rows);

        $divergent = 0;
        foreach ($rows as $row) {
            $text = ($row['category'] === null ? '' : $row['category']) . implode('', $row['tokens']);
            foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                if (mb_ord($char, 'UTF-8') > 0x7F) { $divergent++; break; }
            }
        }

        $this->assertGreaterThanOrEqual(
            10,
            $divergent,
            'rows above ASCII are the only ones that can distinguish the two hashes'
        );
    }

    /**
     * Assert the fixture's own columns, programmatically, at every layer of the
     * chain — not just the final hash.
     *
     * A hash-only assertion passes whenever two implementations agree on the
     * answer, including when they agree by coincidence and disagree on how they
     * got there. The enriched columns make each step checkable on its own:
     * codepoints fix the INPUT, `serialized_hex` fixes the exact BYTES fed to
     * md5, and `custom_id` fixes the OUTPUT. The JS SDK vendors this same file
     * and asserts the same three, so a divergence lands on the step that caused
     * it rather than on the hash.
     *
     * `serialized_hex` is hex of the very string passed to md5 here, recomputed
     * rather than trusted — a column derived from a re-serialization could agree
     * while the hashed bytes differed, which would make the cross-check vacuous.
     */
    public function testCustomIdFixtureIsSelfConsistentAtEveryLayer()
    {
        $path = dirname(__DIR__) . '/fixtures/custom-id-reference.json';
        $this->assertFileExists($path);

        $cases = json_decode(file_get_contents($path), true);
        $this->assertNotEmpty($cases);

        foreach ($cases as $index => $case) {
            $label = 'fixture row ' . $index;

            $this->assertArrayHasKey('codepoints', $case, $label);
            $this->assertArrayHasKey('serialized_hex', $case, $label);

            $category = $case['category'] === null ? '' : $case['category'];

            // 1. The recorded codepoints must describe the recorded input.
            $this->assertSame(
                $this->codepointsOf($category),
                $case['codepoints']['category'],
                $label . ': category codepoints'
            );
            foreach ($case['tokens'] as $t => $token) {
                $this->assertSame(
                    $this->codepointsOf($token),
                    $case['codepoints']['tokens'][$t],
                    $label . ': token ' . $t . ' codepoints'
                );
            }

            // 2. The canonical serialization must reproduce, byte for byte.
            $serialized = json_encode(
                [$category, $case['tokens']],
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS
            );

            $this->assertSame($case['canonical_json'], $serialized, $label . ': canonical json');
            $this->assertSame($case['serialized_hex'], bin2hex($serialized), $label . ': serialized bytes');

            // 3. And the hash of those exact bytes must be the recorded id...
            $this->assertSame($case['custom_id'], md5($serialized), $label . ': md5 of the serialized bytes');

            // 4. ...which is what the SDK actually produces.
            $this->assertSame(
                $case['custom_id'],
                $this->parser->generateCustomId($case['category'], $case['tokens']),
                $label . ': generateCustomId'
            );
        }
    }

    /**
     * The fixture carries unicode coverage on purpose: a suite that only reaches
     * ASCII cannot distinguish a byte-based hash from a UTF-16 code-unit one,
     * which is exactly how a false parity claim shipped in 1.0.2.
     */
    public function testCustomIdFixtureRetainsItsUnicodeCoverage()
    {
        $cases = json_decode(
            file_get_contents(dirname(__DIR__) . '/fixtures/custom-id-reference.json'),
            true
        );

        $aboveLatin1 = 0;
        $nonBmp = 0;

        foreach ($cases as $case) {
            $text = ($case['category'] === null ? '' : $case['category']) . implode('', $case['tokens']);

            foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
                $cp = mb_ord($char, 'UTF-8');
                if ($cp > 0xFF) { $aboveLatin1++; }
                if ($cp > 0xFFFF) { $nonBmp++; }
            }
        }

        $this->assertGreaterThanOrEqual(15, $aboveLatin1, 'coverage above U+00FF must not regress');
        $this->assertGreaterThanOrEqual(1, $nonBmp, 'at least one non-BMP codepoint must remain');
    }

    /**
     * @param string $text
     * @return array U+XXXX for each codepoint, so a reader can verify coverage
     *               without tooling
     */
    private function codepointsOf($text)
    {
        $out = [];

        foreach (preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) as $char) {
            $out[] = sprintf('U+%04X', mb_ord($char, 'UTF-8'));
        }

        return $out;
    }

    // =========================================================================
    // MARK-1 / MARK-2 — identity stamping across SDK boundaries
    // =========================================================================

    /**
     * MARK-2: a marker must be read under EITHER spelling.
     *
     * The TypeScript core writes `data-ls-*`; this SDK has always written
     * `data-langsys-*`. A PHP page hosting a JS-rendered component is the
     * ordinary case, and a reader that knows only its own spelling does not
     * merely miss an attribute - it re-splits a block that already has an
     * identity, registers it again under a different id, and strands whatever
     * was filed under the first.
     *
     * Asserted on the predicates rather than through extractPhrases(): the
     * phrase marker is deliberately NOT honoured on the content-block path (see
     * walkNode), so driving it from there would test the wrong surface and, in
     * an earlier draft of this test, did.
     *
     * @dataProvider phraseMarkerProvider
     */
    public function testPhraseMarkerIsReadUnderBothSpellings($attribute, $value, $expected)
    {
        $this->assertSame($expected, HtmlParser::isPhraseMarked($this->elementWith($attribute, $value)));
    }

    public function phraseMarkerProvider()
    {
        return [
            'ls spelling, bare'        => ['data-ls-phrase', '', true],
            'langsys spelling, bare'   => ['data-langsys-phrase', '', true],
            'ls spelling, truthy'      => ['data-ls-phrase', 'yes', true],
            // Off-values still opt out under both, so the second spelling added
            // no way around the convention.
            'ls spelling, false'       => ['data-ls-phrase', 'false', false],
            'langsys spelling, false'  => ['data-langsys-phrase', 'FALSE', false],
            'ls spelling, zero'        => ['data-ls-phrase', '0', false],
            'langsys spelling, padded' => ['data-langsys-phrase', ' false ', false],
        ];
    }

    /**
     * And the marker actually keeps a block together on the path that honours
     * it - the page path - under the JS spelling.
     */
    public function testJsSpellingKeepsABlockTogetherOnThePagePath()
    {
        $tokenizer = new \Langsys\SDK\Html\MarkupTokenizer();
        $doc = new \DOMDocument();
        $doc->loadHTML('<div data-ls-phrase>Buy <strong>now</strong></div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $element = $doc->getElementsByTagName('div')->item(0);

        $this->assertTrue(
            HtmlParser::isPhraseMarked($element),
            'the page path gates tokenization on this predicate'
        );
        $encoded = $tokenizer->encode($element);

        // One phrase carrying a markup slot, not two phrases split at the tag
        // boundary - which is what an unrecognised marker would have produced.
        $this->assertTrue($tokenizer->hasTokens($encoded['text']), 'the marked run encodes as one tokenized phrase');
        $this->assertSame('Buy {m0o}now{m0c}', $encoded['text']);
        $this->assertCount(1, $encoded['slots']);
    }

    private function elementWith($attribute, $value)
    {
        $doc = new \DOMDocument();
        $markup = $value === ''
            ? '<div ' . $attribute . '></div>'
            : '<div ' . $attribute . '="' . $value . '"></div>';
        $doc->loadHTML($markup, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        return $doc->getElementsByTagName('div')->item(0);
    }

    /**
     * The content-block marker, both spellings, via the public predicate.
     *
     * @dataProvider contentBlockMarkerProvider
     */
    public function testContentBlockMarkerIsReadUnderBothSpellings($attribute, $value, $expected)
    {
        $doc = new \DOMDocument();
        $doc->loadHTML('<div ' . $attribute . '="' . $value . '"></div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $element = $doc->getElementsByTagName('div')->item(0);

        $this->assertSame($expected, HtmlParser::isContentBlockMarked($element));
    }

    public function contentBlockMarkerProvider()
    {
        return [
            'ls spelling on'       => ['data-ls-contentblock', 'abc123', true],
            'langsys spelling on'  => ['data-langsys-contentblock', 'abc123', true],
            'ls spelling off'      => ['data-ls-contentblock', 'false', false],
            'langsys spelling off' => ['data-langsys-contentblock', '0', false],
        ];
    }

    // =========================================================================
    // TOK-1 / TOK-2, as ruled for spec 8.0.1
    // =========================================================================

    /**
     * SVG text is visible copy and IS translated; MathML is notation and is not.
     *
     * The two paths used to disagree: the block path tokenized both, the page
     * path skipped both, so the same `<svg>` produced different phrase lists
     * depending on which entry point saw it. The ordinary-markup control is
     * what proves the tokenizer ran at all.
     */
    public function testSvgTextIsTranslatedAndMathIsNot()
    {
        $html = '<svg><text>SvgLabel</text></svg><math><mi>MathLabel</mi></math><p>Ordinary</p>';

        $this->assertSame(
            ['SvgLabel', 'Ordinary'],
            array_values($this->parser->extractPhrases($html))
        );
    }

    /**
     * The collapse set is JavaScript's `\s`, not PCRE's.
     *
     * The JS core is the identity authority, so PCRE's set was the wrong
     * standard. Written as escapes, and asserted per codepoint, because the
     * three that differ are invisible in any rendering of this file.
     *
     * @dataProvider jsWhitespaceProvider
     */
    public function testTheCollapseSetIsJavascriptsWhitespace($codepoint, $collapses, $why)
    {
        $char = mb_chr($codepoint, 'UTF-8');
        $tokens = array_values($this->parser->extractPhrases('<p>a' . $char . 'b</p>'));

        $this->assertSame(
            $collapses ? ['a b'] : ['a' . $char . 'b'],
            $tokens,
            sprintf('U+%04X: %s', $codepoint, $why)
        );
    }

    public function jsWhitespaceProvider()
    {
        return [
            // The three that used to differ from the JS core.
            'U+FEFF now collapses'      => [0xFEFF, true,  'JS \s matches it; PCRE \s does not - PHP used to keep it'],
            'U+0085 no longer collapses' => [0x0085, false, 'PCRE \s matches it; JS \s does not - PHP used to collapse it'],
            'U+180E no longer collapses' => [0x180E, false, 'PCRE \s matches it; JS \s does not - PHP used to collapse it'],

            // Controls on both sides, so the rows above cannot pass by the
            // collapse being broken in general or disabled entirely.
            'U+00A0 still collapses'    => [0x00A0, true,  'the original TOK-2 case'],
            'U+3000 still collapses'    => [0x3000, true,  'ideographic space, matched by both'],
            'U+2007 still collapses'    => [0x2007, true,  'figure space, matched by both'],
            'U+200B still content'      => [0x200B, false, 'zero-width space is matched by neither'],
            'U+2060 still content'      => [0x2060, false, 'word joiner is matched by neither'],
        ];
    }

    /**
     * TOK-5 at CAPTURE: a phrase carries `{name}`, whichever form was authored.
     *
     * The Interpolator already accepted both at render time, but the phrase
     * stored in the catalog still carried whatever the author wrote - so
     * `Hello %name%` and `Hello {name}` were two different phrases with two
     * different block ids, and the JS core stored only the brace form. Measured
     * before: `bb74011a...` here against `1e4b462c...` there.
     *
     * @dataProvider capturedPlaceholderProvider
     */
    public function testPlaceholdersAreCanonicalisedAtCapture($html, $expected, $why)
    {
        $this->assertSame($expected, array_values($this->parser->extractPhrases($html)), $why);
    }

    public function capturedPlaceholderProvider()
    {
        return [
            'percent form is stored as braces' => [
                '<p>Hello %name%</p>', ['Hello {name}'],
                'the escape form must not create a second phrase',
            ],
            'brace form is unchanged' => [
                '<p>Hello {name}</p>', ['Hello {name}'],
                'positive control - the canonical form stays canonical',
            ],
            'both forms converge on one id' => [
                '<p>%a% and {b}</p>', ['{a} and {b}'],
                'a phrase may carry both spellings',
            ],
            'in an attribute too' => [
                '<img alt="Hi %name%">', ['Hi {name}'],
                'attribute values are phrases and follow the same rule',
            ],

            // The pattern is the only guard at capture, since there is no
            // parameter list to consult. These are what keep it narrow.
            'percentages are untouched' => [
                '<p>Save 20% on 5% APR</p>', ['Save 20% on 5% APR'],
                'spaces between the signs mean it cannot be an identifier',
            ],
            'a lone percent is untouched' => [
                '<p>width: 100%</p>', ['width: 100%'],
                'no closing sign, no rewrite',
            ],
        ];
    }

    /**
     * And both spellings produce the SAME block id, which is the whole point -
     * the id is what strands translations when it moves.
     */
    public function testBothPlaceholderSpellingsProduceOneBlockId()
    {
        $percent = $this->parser->extractPhrases('<p>Hello %name%</p>');
        $brace = $this->parser->extractPhrases('<p>Hello {name}</p>');

        $this->assertSame(
            $this->parser->generateCustomId('UI', $brace),
            $this->parser->generateCustomId('UI', $percent)
        );
    }

    /**
     * Registration and lookup must canonicalise placeholders identically, or
     * this fix recreates the exact register/lookup break the whitespace work
     * had to be fixed for twice.
     */
    public function testCapturedPlaceholderPhrasesAreFoundOnLookup()
    {
        $canonical = \Langsys\SDK\Html\Canonical::phrase('Hello %name%');

        $this->assertSame('Hello {name}', $canonical);
        $this->assertSame(
            $canonical,
            \Langsys\SDK\Html\Canonical::phrase('Hello {name}'),
            'both spellings must reach one key on both sides'
        );
    }

    /**
     * The ASCII fallback's character class, byte for byte.
     *
     * It only runs on malformed UTF-8, which is why its spelling went
     * unnoticed: inside a PCRE class `\v` is the vertical-whitespace CLASS, not
     * a vertical tab, so `[\t\n\v\f\r ]` also matched U+0085 - and on a
     * malformed string that means eating the lead byte of a truncated
     * sequence, corrupting bytes the collapse was supposed to leave alone.
     *
     * Asserted on BYTES, not on a string comparison: the difference is one
     * byte inside an invalid sequence, which renders as nothing at all.
     */
    public function testTheAsciiFallbackDoesNotEatNonAsciiBytes()
    {
        // Invalid UTF-8, so preg_replace with /u returns null and the fallback
        // runs. \xC2\x85 is a well-formed NEL; the trailing \xFF makes the
        // whole string invalid.
        $input = "a\xC2\x85b\xFF";

        $out = \Langsys\SDK\Html\Whitespace::collapse($input);

        $this->assertSame(
            bin2hex("a\xC2\x85b\xFF"),
            bin2hex($out),
            'the fallback must leave non-ASCII bytes alone; the old class ate the lead byte'
        );
    }

    /**
     * Control: the fallback still collapses the ASCII whitespace it is for.
     */
    public function testTheAsciiFallbackStillCollapsesAsciiWhitespace()
    {
        $out = \Langsys\SDK\Html\Whitespace::collapse("a \t\n b\xFF");

        $this->assertSame(bin2hex("a b\xFF"), bin2hex($out));
    }

    // =========================================================================
    // Fixtures must not carry invisible characters as literals
    // =========================================================================

    /**
     * A codepoint a test asserts about must not be written as itself.
     *
     * Raised by the TypeScript lane against their own file: a row whose whole
     * purpose was asserting that U+FEFF collapses contained a RAW U+FEFF, and
     * every BOM-stripper deletes that codepoint. Had one run, the row would
     * have gone on passing while asserting something else entirely - the same
     * shape as a test that cannot fail, and invisible in every diff and review.
     *
     * This SDK had the same problem in three files, two of which are vendored.
     *
     * The exemption is keyed on the BLOB, not the filename. A vendored fixture
     * is adopted byte-identically and its blob is cited in CONFORMANCE, so
     * escaping it would destroy the property that makes it worth having -
     * re-vendoring is the only legitimate way it changes. Keying on the blob
     * means the exemption stops applying the moment the bytes do change, so a
     * local edit to a vendored file fails here instead of hiding behind a name.
     *
     * @dataProvider scannedFileProvider
     */
    public function testSourcesWriteInvisibleCharactersAsEscapes($path, $blob): void
    {
        $vendored = [
            // canonicalization-reference.json needs no exemption any more: re-vendored
            // at langsys-js-typescript a639ae8c (blob 34034931), which is escaped at source.
            // legacy-custom-id-reference.json, langsys-python
            'dc5556466dc54fe82e81ac9fdbf4549b2b76e7ce' => 'adopted byte-identically from langsys-python',
        ];

        if (isset($vendored[$blob])) {
            $this->addToAssertionCount(1);
            return;
        }

        $raw = file_get_contents($path);
        $found = [];

        $chars = preg_split('//u', $raw, -1, PREG_SPLIT_NO_EMPTY);
        $this->assertNotFalse($chars, $path . ' is not valid UTF-8, so it cannot be scanned');

        foreach ($chars as $char) {
            $cp = mb_ord($char, 'UTF-8');
            if (isset(self::invisibleCodepoints()[$cp])) {
                $found[sprintf('U+%04X', $cp)] = true;
            }
        }

        $this->assertSame(
            [],
            array_keys($found),
            $path . ' carries invisible characters as literals; write them as \\uXXXX escapes'
        );
    }

    /**
     * The codepoints that must never appear raw in a scanned file.
     *
     * DERIVED from the collapse set rather than typed out. The typed list went
     * stale the moment the collapse set became JavaScript's `\s`: it omitted
     * eleven codepoints the SDK collapses (U+1680, U+2000-U+2006, U+2008,
     * U+2009, U+205F), so a raw U+2003 in a fixture reddened nothing. Derived,
     * the scan cannot fall behind the rule it protects.
     *
     * Plus, explicitly, the invisible codepoints the SDK deliberately does NOT
     * collapse - a reader cannot see them and an editor or BOM-stripper may
     * silently change them, so they must be escaped too - and ASCII VT/FF,
     * which libxml2 drops from DOM text. These cannot be derived from the
     * collapse set, because not being in it is what defines them.
     */
    private static function invisibleCodepoints(): array
    {
        static $set = null;
        if ($set !== null) {
            return $set;
        }

        $set = [];
        for ($cp = 0x80; $cp <= 0xFFFF; $cp++) {
            if ($cp >= 0xD800 && $cp <= 0xDFFF) {
                continue;
            }
            $char = mb_chr($cp, 'UTF-8');
            if ($char !== false && \Langsys\SDK\Html\Whitespace::collapse('a' . $char . 'b') === 'a b') {
                $set[$cp] = true;
            }
        }

        foreach ([0x000B, 0x000C, 0x0085, 0x180E, 0x200B, 0x2060] as $cp) {
            $set[$cp] = true;
        }

        return $set;
    }

    /**
     * Every file the scan covers: all PHP under src/ and tests/, the Markdown
     * under tests/, and the JSON fixtures. It was fixtures only, which let a raw
     * U+00A0 sit in a test source or in tests/fixtures/README.md with nothing
     * reddening - and a test file is where an invisible literal does the most
     * damage, because it is the thing making assertions about that codepoint.
     */
    public function scannedFileProvider(): array
    {
        $root = dirname(__DIR__, 2);
        $out = [];

        foreach (['src', 'tests'] as $dir) {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                $path = $file->getPathname();
                $ext = strtolower($file->getExtension());
                $inScope = $ext === 'php'
                    || ($dir === 'tests' && $ext === 'md')
                    || ($dir === 'tests' && $ext === 'json' && strpos($path, '/fixtures/') !== false);

                if (!$inScope) {
                    continue;
                }

                $relative = substr($path, strlen($root) + 1);
                $out[$relative] = [$path, sha1('blob ' . filesize($path) . "\0" . file_get_contents($path))];
            }
        }

        ksort($out);

        return $out;
    }
}
