<?php

namespace Langsys\SDK\Html;

use DOMDocument;
use DOMNode;
use DOMElement;
use DOMText;

/**
 * Parses HTML content and extracts translatable phrases.
 *
 * Extracts text from:
 * - Text nodes
 * - Translatable attributes (placeholder, alt, title, aria-label, etc.)
 * - Button values and submit input values
 * - Select option text
 *
 * Respects the translate="no" attribute to skip elements.
 */
class HtmlParser
{
    /**
     * Default attributes that contain translatable text.
     */
    const DEFAULT_TRANSLATABLE_ATTRIBUTES = [
        // Standard HTML
        'placeholder',
        'alt',
        'title',
        'label',              // <optgroup>, <option>, <track>

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
        'data-confirm',           // Confirmation dialogs (Rails, etc.)
        'data-tooltip',           // Tooltip text
        'data-title',             // Alternative title attribute
        'data-content',           // Popover/modal content
        'data-original-title',    // Bootstrap 3/4 tooltips
        'data-bs-title',          // Bootstrap 5 tooltips
        'data-bs-content',        // Bootstrap 5 popovers
        'data-loading-text',      // Loading button states
        'data-success-message',   // Success notifications
        'data-warning-message',   // Warning notifications
        'data-empty-message',     // Empty state messages
        'data-placeholder',       // Custom placeholder attributes
    ];

    /**
     * @var array Current translatable attributes
     */
    protected $translatableAttributes;

    /**
     * Create a new HtmlParser instance.
     *
     * @param array|null $translatableAttributes Custom attributes to extract (null uses defaults)
     */
    public function __construct($translatableAttributes = null)
    {
        $this->translatableAttributes = $translatableAttributes !== null
            ? $translatableAttributes
            : self::DEFAULT_TRANSLATABLE_ATTRIBUTES;
    }

    /**
     * Get the current translatable attributes.
     *
     * @return array
     */
    public function getTranslatableAttributes()
    {
        return $this->translatableAttributes;
    }

    /**
     * Set the translatable attributes (replaces all).
     *
     * @param array $attributes
     * @return $this
     */
    public function setTranslatableAttributes(array $attributes)
    {
        $this->translatableAttributes = $attributes;
        return $this;
    }

    /**
     * Add additional translatable attributes to the existing list.
     *
     * @param array $attributes
     * @return $this
     */
    public function addTranslatableAttributes(array $attributes)
    {
        $this->translatableAttributes = array_unique(
            array_merge($this->translatableAttributes, $attributes)
        );
        return $this;
    }

    /**
     * Reset to default translatable attributes.
     *
     * @return $this
     */
    public function resetTranslatableAttributes()
    {
        $this->translatableAttributes = self::DEFAULT_TRANSLATABLE_ATTRIBUTES;
        return $this;
    }

    /**
     * Extract translatable phrases from HTML content.
     *
     * @param string $html The HTML content to parse
     * @return array Array of phrases (preserves duplicates, in order encountered)
     */
    public function extractPhrases($html)
    {
        if (empty($html)) {
            return [];
        }

        $phrases = [];

        // Suppress warnings for malformed HTML
        $internalErrors = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        // Wrap in a div to handle fragments, use UTF-8 encoding
        $wrapped = '<?xml encoding="UTF-8"><div>' . $html . '</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        // Walk through all nodes
        $this->walkNode($doc->documentElement, $phrases);

        return $phrases;
    }

    /**
     * Generate a custom ID from category and phrases using md5 hash.
     *
     * @param string|null $category The category
     * @param array $phrases Array of phrases
     * @return string The generated custom ID (md5 hash)
     */
    public function generateCustomId($category, array $phrases)
    {
        // Mirror the JS SDKs' generateCustomId so the same content block resolves
        // to the same custom_id across every Langsys SDK. JS computes:
        //   md5(JSON.stringify([category, tokens]))
        //
        // - JSON-encode the [category, phrases] tuple. JSON_UNESCAPED_SLASHES +
        //   JSON_UNESCAPED_UNICODE make json_encode match JS JSON.stringify
        //   byte-for-byte (JS escapes neither slashes nor non-ASCII).
        // - Treat the reserved '__uncategorized__' sentinel (and null) as "no
        //   category" - hashed as '' - matching the JS side, which passes the raw
        //   category ('' when none) and never the sentinel. This also makes the
        //   two PHP callers (translateContentBlock default '__uncategorized__' and
        //   createContentBlock default null) agree with each other.
        $cat = ($category === null || $category === '__uncategorized__') ? '' : $category;

        $encoded = json_encode([$cat, array_values($phrases)], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        // json_encode returns false on invalid UTF-8, and md5(false) is md5('') -
        // which would collapse EVERY such content block onto one id. Fall back to
        // a serialization that cannot fail so distinct blocks stay distinct.
        if ($encoded === false) {
            $encoded = serialize([$cat, array_values($phrases)]);
        }

        return md5($encoded);
    }

    /**
     * Whether an element is excluded from translation entirely.
     *
     * Two spellings, both author-facing: the standard HTML `translate="no"`, and
     * `data-notrans` as an alias for hosts whose templating strips unknown bare
     * attributes or where `translate` collides with another tool.
     *
     * `data-notrans` follows the same convention as `data-langsys-phrase`:
     * presence alone is intent, so the bare attribute works like any boolean
     * HTML attribute, and only an explicit off value opts out.
     *
     * This previously tested the raw attribute string for PHP truthiness, which
     * got both ends backwards: bare `data-notrans` is '' and therefore FALSY, so
     * the natural form silently did nothing, while `data-notrans="false"` is a
     * non-empty string and therefore TRUTHY, so the off value excluded. The
     * failure direction was the dangerous one - content an author had marked to
     * protect went into the shared catalog.
     *
     * @param DOMElement $element
     * @return bool
     */
    public static function isTranslationExcluded(DOMElement $element)
    {
        if (strtolower($element->getAttribute('translate')) === 'no') {
            return true;
        }

        if (!$element->hasAttribute('data-notrans')) {
            return false;
        }

        $value = strtolower(trim($element->getAttribute('data-notrans')));

        return $value !== 'false' && $value !== '0';
    }

    /**
     * @var MarkupTokenizer|null Lazily created.
     */
    protected $markupTokenizer = null;

    /**
     * @return MarkupTokenizer
     */
    protected function markupTokenizer()
    {
        if ($this->markupTokenizer === null) {
            $this->markupTokenizer = new MarkupTokenizer();
        }

        return $this->markupTokenizer;
    }

    /**
     * Whether an element is marked as a single content block.
     *
     * Same convention as isPhraseMarked() and isTranslationExcluded(): presence
     * alone is intent, only an explicit off value opts out, values trimmed and
     * lowercased.
     *
     * A bare attribute previously did NOTHING here, because the documented
     * contract required a non-empty value - so `data-langsys-contentblock` on
     * its own silently had no effect. That is the same failure shape as the
     * data-notrans bug: a marker that looks applied and isn't. All three markers
     * now behave identically, so there is one rule to learn rather than three.
     *
     * @param DOMElement $element
     * @return bool
     */
    public static function isContentBlockMarked(DOMElement $element)
    {
        if (!$element->hasAttribute('data-langsys-contentblock')) {
            return false;
        }

        $value = strtolower(trim($element->getAttribute('data-langsys-contentblock')));

        return $value !== '0' && $value !== 'false';
    }

    /**
     * Whether an element is marked as a single keep-together phrase.
     *
     * Presence alone is intent, so a bare `data-langsys-phrase` works like any
     * boolean HTML attribute; only an explicit off value opts out. Values are
     * trimmed and lowercased, matching isTranslationExcluded() - the two had
     * drifted, so `data-langsys-phrase=" false "` opted out of neither
     * convention while `data-notrans=" false "` opted out of one.
     *
     * Static and public because PageTranslator applies the same rule, and the
     * JS SDK mirrors it: a duplicated literal in two places is how the marker
     * drifted from its documentation the first time.
     *
     * @param DOMElement $element
     * @return bool
     */
    public static function isPhraseMarked(DOMElement $element)
    {
        if (!$element->hasAttribute('data-langsys-phrase')) {
            return false;
        }

        $value = strtolower(trim($element->getAttribute('data-langsys-phrase')));

        return $value !== '0' && $value !== 'false';
    }

    /**
     * Recursively walk DOM nodes and extract phrases.
     *
     * @param DOMNode $node The node to process
     * @param array &$phrases Array to collect phrases into
     * @return void
     */
    protected function walkNode(DOMNode $node, array &$phrases)
    {
        // Skip elements excluded from translation entirely.
        if ($node instanceof DOMElement && self::isTranslationExcluded($node)) {
            return;
        }

        // Handle text nodes
        if ($node instanceof DOMText) {
            $text = $this->normalizeWhitespace($node->textContent);
            if ($text !== '') {
                $phrases[] = $text;
            }
            return;
        }

        // Handle element nodes
        if ($node instanceof DOMElement) {
            // NOTE: data-langsys-phrase is deliberately NOT honoured here.
            //
            // It is a translatePage() feature, applied by PageTranslator, which
            // rebuilds the element's children from markup tokens. Content blocks
            // are applied by a different path that has no tokenized branch, so
            // honouring the marker here registered a tokenized catalog entry
            // that could never be rendered - paying for an entry, and polluting
            // the shared catalog, for no effect.
            //
            // Inside a content block a marked run therefore splits as usual.

            // Extract translatable attributes
            $this->extractAttributePhrases($node, $phrases);

            // Extract value from buttons and submit/button inputs
            $this->extractButtonValue($node, $phrases);

            // Extract option text from select elements
            $this->extractSelectOptions($node, $phrases);
        }

        // Recurse into child nodes
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->walkNode($child, $phrases);
            }
        }
    }

    /**
     * Extract translatable attribute values from an element.
     *
     * @param DOMElement $element The element to check
     * @param array &$phrases Array to collect phrases into
     * @return void
     */
    protected function extractAttributePhrases(DOMElement $element, array &$phrases)
    {
        foreach ($this->translatableAttributes as $attr) {
            if ($element->hasAttribute($attr)) {
                $value = $this->normalizeWhitespace($element->getAttribute($attr));
                if ($value !== '') {
                    $phrases[] = $value;
                }
            }
        }
    }

    /**
     * Extract value attribute from buttons and submit/button inputs.
     *
     * @param DOMElement $element The element to check
     * @param array &$phrases Array to collect phrases into
     * @return void
     */
    protected function extractButtonValue(DOMElement $element, array &$phrases)
    {
        $tagName = strtolower($element->tagName);

        // Check button elements
        if ($tagName === 'button' && $element->hasAttribute('value')) {
            $value = $this->normalizeWhitespace($element->getAttribute('value'));
            if ($value !== '') {
                $phrases[] = $value;
            }
            return;
        }

        // Check input elements with type submit or button
        if ($tagName === 'input' && $element->hasAttribute('value')) {
            $type = strtolower($element->getAttribute('type'));
            if ($type === 'submit' || $type === 'button') {
                $value = $this->normalizeWhitespace($element->getAttribute('value'));
                if ($value !== '') {
                    $phrases[] = $value;
                }
            }
        }
    }

    /**
     * Extract text from option elements within a select.
     * Note: Option text content is already extracted by walkNode,
     * so this method handles cases where options might have value attributes
     * that differ from their text content.
     *
     * @param DOMElement $element The element to check
     * @param array &$phrases Array to collect phrases into
     * @return void
     */
    protected function extractSelectOptions(DOMElement $element, array &$phrases)
    {
        // Option text is extracted via text nodes, no additional handling needed
        // This method exists for future extensibility
    }

    /**
     * Normalize whitespace in text content.
     *
     * @param string $text The text to normalize
     * @return string Normalized text with trimmed whitespace
     */
    protected function normalizeWhitespace($text)
    {
        // Replace multiple whitespace (including newlines) with single space, then trim
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Resolve relative URLs in HTML content to absolute URLs.
     *
     * Converts relative src, srcset, and href attributes on media elements
     * to absolute URLs using the provided base URL.
     *
     * @param string $html The HTML content
     * @param string $baseUrl The base URL to prepend to relative URLs
     * @return string HTML with resolved URLs
     */
    public function resolveRelativeUrls($html, $baseUrl)
    {
        if (empty($html) || empty($baseUrl)) {
            return $html;
        }

        // Normalize base URL
        $baseUrl = rtrim($baseUrl, '/');

        // Suppress warnings for malformed HTML
        $internalErrors = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->encoding = 'UTF-8';
        // Wrap in a div to handle fragments
        $wrapped = '<?xml encoding="UTF-8"><div>' . $html . '</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        // Process elements with src attribute (img, video, audio, source, iframe, embed)
        $srcElements = $doc->getElementsByTagName('*');
        foreach ($srcElements as $element) {
            if (!($element instanceof DOMElement)) {
                continue;
            }

            // Resolve src attribute
            if ($element->hasAttribute('src')) {
                $src = $element->getAttribute('src');
                $resolved = $this->resolveUrl($src, $baseUrl);
                if ($resolved !== $src) {
                    $element->setAttribute('src', $resolved);
                }
            }

            // Resolve srcset attribute (for responsive images)
            if ($element->hasAttribute('srcset')) {
                $srcset = $element->getAttribute('srcset');
                $resolvedSrcset = $this->resolveSrcset($srcset, $baseUrl);
                if ($resolvedSrcset !== $srcset) {
                    $element->setAttribute('srcset', $resolvedSrcset);
                }
            }

            // Resolve poster attribute (for video)
            if ($element->hasAttribute('poster')) {
                $poster = $element->getAttribute('poster');
                $resolved = $this->resolveUrl($poster, $baseUrl);
                if ($resolved !== $poster) {
                    $element->setAttribute('poster', $resolved);
                }
            }
        }

        // Extract inner HTML of the wrapper div
        $wrapper = $doc->getElementsByTagName('div')->item(0);
        if ($wrapper === null) {
            return $html;
        }

        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return $result;
    }

    /**
     * Resolve a single URL against a base URL.
     *
     * @param string $url The URL to resolve
     * @param string $baseUrl The base URL
     * @return string The resolved URL
     */
    protected function resolveUrl($url, $baseUrl)
    {
        // Skip if already absolute (has scheme) or is a data URI
        if (preg_match('#^(https?://|data:|//)#i', $url)) {
            return $url;
        }

        // Skip empty URLs
        if ($url === '') {
            return $url;
        }

        // Handle absolute path (starts with /)
        if ($url[0] === '/') {
            return $baseUrl . $url;
        }

        // Handle relative path
        return $baseUrl . '/' . $url;
    }

    /**
     * Resolve URLs in a srcset attribute value.
     *
     * srcset format: "url1 1x, url2 2x" or "url1 100w, url2 200w"
     *
     * @param string $srcset The srcset value
     * @param string $baseUrl The base URL
     * @return string The resolved srcset
     */
    protected function resolveSrcset($srcset, $baseUrl)
    {
        $parts = explode(',', $srcset);
        $resolved = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            // Split into URL and descriptor (e.g., "image.jpg 2x" -> ["image.jpg", "2x"])
            $tokens = preg_split('/\s+/', $part, 2);
            $url = $tokens[0];
            $descriptor = isset($tokens[1]) ? $tokens[1] : '';

            $resolvedUrl = $this->resolveUrl($url, $baseUrl);

            $resolved[] = $descriptor !== '' ? $resolvedUrl . ' ' . $descriptor : $resolvedUrl;
        }

        return implode(', ', $resolved);
    }
}
