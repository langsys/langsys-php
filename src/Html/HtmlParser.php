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
        $tokens = array_merge(
            [$category !== null ? $category : ''],
            $phrases
        );

        return md5(implode('|', $tokens));
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
        // Skip elements with translate="no" or data-notrans
        if ($node instanceof DOMElement && (
            $node->getAttribute('translate') === 'no' ||
            $node->getAttribute('data-notrans')
        )) {
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
