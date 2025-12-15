<?php

namespace Langsys\SDK\Tests\Html;

use DOMDocument;
use Langsys\SDK\Html\SelectorMatcher;
use Langsys\SDK\Exception\LangsysException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the SelectorMatcher class.
 */
class SelectorMatcherTest extends TestCase
{
    // =========================================================================
    // CSS to XPath Conversion Tests
    // =========================================================================

    public function testCssToXpathTagSelector()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('div');
        $this->assertEquals('descendant-or-self::div', $xpath);
    }

    public function testCssToXpathClassSelector()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('.button');
        $this->assertStringContainsString("contains(concat(' ', normalize-space(@class), ' '), ' button ')", $xpath);
    }

    public function testCssToXpathIdSelector()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('#main');
        $this->assertStringContainsString("@id='main'", $xpath);
    }

    public function testCssToXpathTagWithClass()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('a.button');
        $this->assertStringContainsString('descendant-or-self::a', $xpath);
        $this->assertStringContainsString("contains(concat(' ', normalize-space(@class), ' '), ' button ')", $xpath);
    }

    public function testCssToXpathTagWithMultipleClasses()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('button.btn.primary');
        $this->assertStringContainsString('descendant-or-self::button', $xpath);
        $this->assertStringContainsString("' btn '", $xpath);
        $this->assertStringContainsString("' primary '", $xpath);
    }

    public function testCssToXpathTagWithId()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('div#container');
        $this->assertStringContainsString('descendant-or-self::div', $xpath);
        $this->assertStringContainsString("@id='container'", $xpath);
    }

    public function testCssToXpathAttributeExists()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('[disabled]');
        $this->assertStringContainsString('@disabled', $xpath);
    }

    public function testCssToXpathAttributeEquals()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('[type="submit"]');
        $this->assertStringContainsString("@type='submit'", $xpath);
    }

    public function testCssToXpathAttributeStartsWith()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('[class^="btn-"]');
        $this->assertStringContainsString("starts-with(@class, 'btn-')", $xpath);
    }

    public function testCssToXpathAttributeEndsWith()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('[href$=".pdf"]');
        $this->assertStringContainsString("substring(@href", $xpath);
        $this->assertStringContainsString("'.pdf'", $xpath);
    }

    public function testCssToXpathAttributeContains()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('[class*="icon"]');
        $this->assertStringContainsString("contains(@class, 'icon')", $xpath);
    }

    public function testCssToXpathDescendantCombinator()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('nav a');
        $this->assertStringContainsString('descendant-or-self::nav', $xpath);
        $this->assertStringContainsString('//a', $xpath);
    }

    public function testCssToXpathChildCombinator()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('ul > li');
        $this->assertStringContainsString('descendant-or-self::ul', $xpath);
        $this->assertStringContainsString('/li', $xpath);
        $this->assertStringNotContainsString('//li', $xpath);
    }

    public function testCssToXpathCommaSeparated()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('button, .btn');
        $this->assertStringContainsString('descendant-or-self::button', $xpath);
        $this->assertStringContainsString(' | ', $xpath);
        $this->assertStringContainsString("' btn '", $xpath);
    }

    public function testCssToXpathComplexSelector()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath('nav.main-nav > ul > li.active a.link');
        $this->assertStringContainsString('descendant-or-self::nav', $xpath);
        $this->assertStringContainsString("' main-nav '", $xpath);
        $this->assertStringContainsString('/ul', $xpath);
        $this->assertStringContainsString('/li', $xpath);
        $this->assertStringContainsString("' active '", $xpath);
        $this->assertStringContainsString('//a', $xpath);
        $this->assertStringContainsString("' link '", $xpath);
    }

    public function testCssToXpathEmptySelectorThrows()
    {
        $this->expectException(LangsysException::class);
        $this->expectExceptionMessage('empty');

        $matcher = new SelectorMatcher();
        $matcher->cssToXpath('');
    }

    public function testCssToXpathInvalidSelectorThrows()
    {
        $this->expectException(LangsysException::class);

        $matcher = new SelectorMatcher();
        $matcher->cssToXpath('.'); // Invalid - class name missing
    }

    // =========================================================================
    // Element Matching Tests
    // =========================================================================

    public function testMatchElementByTag()
    {
        $matcher = new SelectorMatcher([
            'button' => ['category' => 'UI'],
        ]);

        $doc = $this->createDocument('<div><button>Click</button></div>');
        $button = $doc->getElementsByTagName('button')->item(0);
        $div = $doc->getElementsByTagName('div')->item(0);

        $result = $matcher->matchElement($button);
        $this->assertNotNull($result);
        $this->assertEquals('UI', $result['category']);

        $result = $matcher->matchElement($div);
        $this->assertNull($result);
    }

    public function testMatchElementByClass()
    {
        $matcher = new SelectorMatcher([
            '.btn' => ['category' => 'Buttons'],
        ]);

        $doc = $this->createDocument('<div><a class="btn primary">Link</a><a>Other</a></div>');
        $btnLink = $doc->getElementsByTagName('a')->item(0);
        $otherLink = $doc->getElementsByTagName('a')->item(1);

        $result = $matcher->matchElement($btnLink);
        $this->assertNotNull($result);
        $this->assertEquals('Buttons', $result['category']);

        $result = $matcher->matchElement($otherLink);
        $this->assertNull($result);
    }

    public function testMatchElementById()
    {
        $matcher = new SelectorMatcher([
            '#submit-btn' => ['category' => 'Forms'],
        ]);

        $doc = $this->createDocument('<button id="submit-btn">Submit</button><button id="other">Other</button>');
        $submitBtn = $doc->getElementById('submit-btn');
        $otherBtn = $doc->getElementById('other');

        $result = $matcher->matchElement($submitBtn);
        $this->assertNotNull($result);
        $this->assertEquals('Forms', $result['category']);

        $result = $matcher->matchElement($otherBtn);
        $this->assertNull($result);
    }

    public function testMatchElementByAttribute()
    {
        $matcher = new SelectorMatcher([
            '[type="submit"]' => ['category' => 'Submit Buttons'],
        ]);

        $doc = $this->createDocument('<input type="submit" value="Go"><input type="text">');
        $inputs = $doc->getElementsByTagName('input');
        $submitInput = $inputs->item(0);
        $textInput = $inputs->item(1);

        $result = $matcher->matchElement($submitInput);
        $this->assertNotNull($result);
        $this->assertEquals('Submit Buttons', $result['category']);

        $result = $matcher->matchElement($textInput);
        $this->assertNull($result);
    }

    public function testMatchElementByDescendant()
    {
        $matcher = new SelectorMatcher([
            'nav a' => ['category' => 'Navigation'],
        ]);

        $doc = $this->createDocument('<nav><ul><li><a href="#">Link</a></li></ul></nav><div><a href="#">Other</a></div>');
        $navLink = $doc->getElementsByTagName('nav')->item(0)->getElementsByTagName('a')->item(0);
        $divLink = $doc->getElementsByTagName('div')->item(0)->getElementsByTagName('a')->item(0);

        $result = $matcher->matchElement($navLink);
        $this->assertNotNull($result);
        $this->assertEquals('Navigation', $result['category']);

        $result = $matcher->matchElement($divLink);
        $this->assertNull($result);
    }

    public function testMatchElementByChild()
    {
        $matcher = new SelectorMatcher([
            'ul > li' => ['category' => 'List Items'],
        ]);

        $doc = $this->createDocument('<ul><li>Direct</li></ul><ul><li><ul><li>Nested</li></ul></li></ul>');
        $directLi = $doc->getElementsByTagName('ul')->item(0)->getElementsByTagName('li')->item(0);

        $result = $matcher->matchElement($directLi);
        $this->assertNotNull($result);
        $this->assertEquals('List Items', $result['category']);
    }

    public function testMatchElementCommaSeparated()
    {
        $matcher = new SelectorMatcher([
            'button, .btn, input[type="submit"]' => ['category' => 'Clickables'],
        ]);

        $doc = $this->createDocument('<button>Btn</button><a class="btn">Link</a><input type="submit"><span>Other</span>');

        $button = $doc->getElementsByTagName('button')->item(0);
        $result = $matcher->matchElement($button);
        $this->assertNotNull($result);
        $this->assertEquals('Clickables', $result['category']);

        $link = $doc->getElementsByTagName('a')->item(0);
        $result = $matcher->matchElement($link);
        $this->assertNotNull($result);
        $this->assertEquals('Clickables', $result['category']);

        $input = $doc->getElementsByTagName('input')->item(0);
        $result = $matcher->matchElement($input);
        $this->assertNotNull($result);
        $this->assertEquals('Clickables', $result['category']);

        $span = $doc->getElementsByTagName('span')->item(0);
        $result = $matcher->matchElement($span);
        $this->assertNull($result);
    }

    // =========================================================================
    // Override Priority Tests
    // =========================================================================

    public function testOverrideRulesMatchFirst()
    {
        $matcher = new SelectorMatcher([
            '.btn' => ['category' => 'Normal Buttons', 'overrideParentElementCategory' => false],
            'button' => ['category' => 'Override Buttons', 'overrideParentElementCategory' => true],
        ]);

        $doc = $this->createDocument('<button class="btn">Click</button>');
        $button = $doc->getElementsByTagName('button')->item(0);

        $result = $matcher->matchElement($button);
        $this->assertNotNull($result);
        $this->assertEquals('Override Buttons', $result['category']);
        $this->assertTrue($result['override']);
    }

    public function testNonOverrideReturnsOverrideFalse()
    {
        $matcher = new SelectorMatcher([
            '.btn' => ['category' => 'Buttons'], // default override=false
        ]);

        $doc = $this->createDocument('<a class="btn">Link</a>');
        $link = $doc->getElementsByTagName('a')->item(0);

        $result = $matcher->matchElement($link);
        $this->assertNotNull($result);
        $this->assertFalse($result['override']);
    }

    public function testOverrideReturnsOverrideTrue()
    {
        $matcher = new SelectorMatcher([
            '.btn' => ['category' => 'Buttons', 'overrideParentElementCategory' => true],
        ]);

        $doc = $this->createDocument('<a class="btn">Link</a>');
        $link = $doc->getElementsByTagName('a')->item(0);

        $result = $matcher->matchElement($link);
        $this->assertNotNull($result);
        $this->assertTrue($result['override']);
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function testHasRulesEmpty()
    {
        $matcher = new SelectorMatcher([]);
        $this->assertFalse($matcher->hasRules());
    }

    public function testHasRulesWithRules()
    {
        $matcher = new SelectorMatcher(['button' => 'UI']);
        $this->assertTrue($matcher->hasRules());
    }

    public function testStringShorthandConfig()
    {
        $matcher = new SelectorMatcher([
            'button' => 'UI Elements', // String shorthand
        ]);

        $doc = $this->createDocument('<button>Click</button>');
        $button = $doc->getElementsByTagName('button')->item(0);

        $result = $matcher->matchElement($button);
        $this->assertNotNull($result);
        $this->assertEquals('UI Elements', $result['category']);
        $this->assertFalse($result['override']); // Default
    }

    public function testNoMatchReturnsNull()
    {
        $matcher = new SelectorMatcher([
            'button' => 'UI',
        ]);

        $doc = $this->createDocument('<div><span>Text</span></div>');
        $span = $doc->getElementsByTagName('span')->item(0);

        $result = $matcher->matchElement($span);
        $this->assertNull($result);
    }

    public function testAttributeSelectorWithDoubleQuotes()
    {
        $matcher = new SelectorMatcher([
            '[data-type="primary"]' => 'Primary',
        ]);

        $doc = $this->createDocument('<button data-type="primary">Click</button>');
        $button = $doc->getElementsByTagName('button')->item(0);

        $result = $matcher->matchElement($button);
        $this->assertNotNull($result);
        $this->assertEquals('Primary', $result['category']);
    }

    public function testAttributeSelectorWithSingleQuotes()
    {
        $matcher = new SelectorMatcher();
        $xpath = $matcher->cssToXpath("[data-type='primary']");
        $this->assertStringContainsString("@data-type='primary'", $xpath);
    }

    public function testComplexRealWorldSelector()
    {
        $matcher = new SelectorMatcher([
            'form.contact-form button[type="submit"], form.contact-form input[type="submit"]' => [
                'category' => 'Contact Form Buttons',
                'overrideParentElementCategory' => true,
            ],
        ]);

        $doc = $this->createDocument('
            <form class="contact-form">
                <input type="text" name="name">
                <button type="submit">Send</button>
            </form>
            <form class="other-form">
                <button type="submit">Other</button>
            </form>
        ');

        $contactFormBtn = $doc->getElementsByTagName('form')->item(0)->getElementsByTagName('button')->item(0);
        $otherFormBtn = $doc->getElementsByTagName('form')->item(1)->getElementsByTagName('button')->item(0);

        $result = $matcher->matchElement($contactFormBtn);
        $this->assertNotNull($result);
        $this->assertEquals('Contact Form Buttons', $result['category']);

        $result = $matcher->matchElement($otherFormBtn);
        $this->assertNull($result);
    }

    public function testClassSelectorWithHyphen()
    {
        $matcher = new SelectorMatcher([
            '.my-button' => 'Buttons',
        ]);

        $doc = $this->createDocument('<button class="my-button">Click</button>');
        $button = $doc->getElementsByTagName('button')->item(0);

        $result = $matcher->matchElement($button);
        $this->assertNotNull($result);
    }

    public function testClassSelectorWithUnderscore()
    {
        $matcher = new SelectorMatcher([
            '.my_button' => 'Buttons',
        ]);

        $doc = $this->createDocument('<button class="my_button">Click</button>');
        $button = $doc->getElementsByTagName('button')->item(0);

        $result = $matcher->matchElement($button);
        $this->assertNotNull($result);
    }

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Create a DOMDocument from HTML.
     *
     * @param string $html HTML content
     * @return DOMDocument
     */
    protected function createDocument($html)
    {
        $doc = new DOMDocument();
        // Suppress warnings for HTML5 tags (nav, section, etc.)
        $internalErrors = libxml_use_internal_errors(true);
        $doc->loadHTML('<!DOCTYPE html><html><body>' . $html . '</body></html>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);
        return $doc;
    }
}
