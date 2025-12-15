<?php

namespace Langsys\SDK\Html;

use DOMElement;
use DOMXPath;
use Langsys\SDK\Exception\LangsysException;

/**
 * Matches DOM elements against CSS selectors for category assignment.
 *
 * Built-in CSS-to-XPath converter supporting common selectors:
 * - Tag: div, button, a
 * - Class: .class, .class1.class2
 * - ID: #id
 * - Tag + class/id: div.class, a#id
 * - Attribute: [attr], [attr="value"], [attr^="prefix"], [attr$="suffix"], [attr*="contains"]
 * - Descendant: nav a (space)
 * - Child: ul > li
 * - Comma-separated: button, .btn, .button
 *
 * PHP 5.6 - 8.x compatible, no external dependencies.
 */
class SelectorMatcher
{
    /**
     * Override rules (overrideParentElementCategory=true).
     *
     * @var array
     */
    protected $overrideRules = [];

    /**
     * Non-override rules (overrideParentElementCategory=false).
     *
     * @var array
     */
    protected $normalRules = [];

    /**
     * CSS to XPath cache.
     *
     * @var array
     */
    protected $xpathCache = [];

    /**
     * Create a new SelectorMatcher instance.
     *
     * @param array $selectorCategories Map of CSS selector => config
     * @throws LangsysException If selector syntax is invalid
     */
    public function __construct(array $selectorCategories = [])
    {
        if (!empty($selectorCategories)) {
            $this->parseRules($selectorCategories);
        }
    }

    /**
     * Check if any rules are configured.
     *
     * @return bool
     */
    public function hasRules()
    {
        return !empty($this->overrideRules) || !empty($this->normalRules);
    }

    /**
     * Match an element and return the category based on priority.
     *
     * Priority:
     * 1. Override selector match (overrideParentElementCategory=true)
     * 2. Element's data-langsys-category attribute (handled by caller)
     * 3. Inherited category (handled by caller)
     * 4. Non-override selector match (overrideParentElementCategory=false)
     *
     * @param DOMElement $element The element to match
     * @return array|null ['category' => string, 'override' => bool] or null if no match
     */
    public function matchElement(DOMElement $element)
    {
        // First check override rules (highest priority)
        foreach ($this->overrideRules as $rule) {
            if ($this->elementMatchesXpath($element, $rule['xpath'])) {
                return [
                    'category' => $rule['category'],
                    'override' => true,
                ];
            }
        }

        // Then check normal rules (lowest priority - caller should check data-langsys-category first)
        foreach ($this->normalRules as $rule) {
            if ($this->elementMatchesXpath($element, $rule['xpath'])) {
                return [
                    'category' => $rule['category'],
                    'override' => false,
                ];
            }
        }

        return null;
    }

    /**
     * Parse selector rules from configuration.
     *
     * @param array $selectorCategories Map of CSS selector => config
     * @return void
     */
    protected function parseRules(array $selectorCategories)
    {
        foreach ($selectorCategories as $selector => $config) {
            // Normalize config
            if (is_string($config)) {
                $config = ['category' => $config];
            }

            $category = isset($config['category']) ? $config['category'] : '__uncategorized__';
            $override = isset($config['overrideParentElementCategory']) && $config['overrideParentElementCategory'];

            // Convert CSS selector to XPath
            $xpath = $this->cssToXpath($selector);

            $rule = [
                'selector' => $selector,
                'xpath' => $xpath,
                'category' => $category,
                'override' => $override,
            ];

            if ($override) {
                $this->overrideRules[] = $rule;
            } else {
                $this->normalRules[] = $rule;
            }
        }
    }

    /**
     * Convert CSS selector to XPath.
     *
     * Supports:
     * - Tag: div, button
     * - Class: .class
     * - ID: #id
     * - Attribute: [attr], [attr="value"], [attr^="prefix"], [attr$="suffix"], [attr*="contains"]
     * - Descendant combinator: div p (space)
     * - Child combinator: div > p
     * - Multiple selectors: button, .btn (comma)
     *
     * @param string $selector CSS selector
     * @return string XPath expression
     * @throws LangsysException If selector syntax is invalid
     */
    public function cssToXpath($selector)
    {
        $selector = trim($selector);

        if (isset($this->xpathCache[$selector])) {
            return $this->xpathCache[$selector];
        }

        if ($selector === '') {
            throw new LangsysException('CSS selector cannot be empty');
        }

        // Handle comma-separated selectors (OR)
        if (strpos($selector, ',') !== false) {
            $parts = array_map('trim', explode(',', $selector));
            $xpathParts = [];
            foreach ($parts as $part) {
                if ($part !== '') {
                    $xpathParts[] = $this->cssToXpath($part);
                }
            }
            $xpath = implode(' | ', $xpathParts);
            $this->xpathCache[$selector] = $xpath;
            return $xpath;
        }

        // Tokenize the selector
        $xpath = $this->parseSelectorToXpath($selector);

        $this->xpathCache[$selector] = $xpath;
        return $xpath;
    }

    /**
     * Parse a single selector (no commas) to XPath.
     *
     * @param string $selector CSS selector
     * @return string XPath expression
     * @throws LangsysException If selector syntax is invalid
     */
    protected function parseSelectorToXpath($selector)
    {
        // Normalize whitespace around combinators
        $selector = preg_replace('/\s*>\s*/', ' > ', $selector);
        $selector = preg_replace('/\s+/', ' ', trim($selector));

        // Split by combinators (space and >)
        $parts = $this->splitByCombinators($selector);

        $xpathParts = [];
        $isFirst = true;

        foreach ($parts as $part) {
            $combinator = $part['combinator'];
            $simpleSelector = $part['selector'];

            // Convert simple selector to XPath condition
            $condition = $this->simpleSelectoToXpath($simpleSelector);

            if ($isFirst) {
                // First part uses descendant-or-self
                $xpathParts[] = 'descendant-or-self::' . $condition;
                $isFirst = false;
            } else {
                if ($combinator === '>') {
                    // Child combinator
                    $xpathParts[] = '/' . $condition;
                } else {
                    // Descendant combinator (space)
                    $xpathParts[] = '//' . $condition;
                }
            }
        }

        return implode('', $xpathParts);
    }

    /**
     * Split selector by combinators (space and >).
     *
     * @param string $selector CSS selector
     * @return array Array of ['combinator' => string, 'selector' => string]
     */
    protected function splitByCombinators($selector)
    {
        $parts = [];
        $current = '';
        $inBracket = false;
        $inQuote = false;
        $quoteChar = '';
        $lastCombinator = '';
        $len = strlen($selector);

        for ($i = 0; $i < $len; $i++) {
            $char = $selector[$i];

            // Track brackets for attribute selectors
            if ($char === '[' && !$inQuote) {
                $inBracket = true;
            } elseif ($char === ']' && !$inQuote) {
                $inBracket = false;
            }

            // Track quotes inside brackets
            if ($inBracket && ($char === '"' || $char === "'")) {
                if (!$inQuote) {
                    $inQuote = true;
                    $quoteChar = $char;
                } elseif ($char === $quoteChar) {
                    $inQuote = false;
                    $quoteChar = '';
                }
            }

            // Check for combinators only outside brackets/quotes
            if (!$inBracket && !$inQuote) {
                if ($char === '>') {
                    if ($current !== '') {
                        $parts[] = [
                            'combinator' => $lastCombinator,
                            'selector' => trim($current),
                        ];
                        $current = '';
                    }
                    $lastCombinator = '>';
                    continue;
                }

                if ($char === ' ') {
                    // Check if this is a descendant combinator or just whitespace around >
                    $trimmedCurrent = trim($current);
                    if ($trimmedCurrent !== '') {
                        $parts[] = [
                            'combinator' => $lastCombinator,
                            'selector' => $trimmedCurrent,
                        ];
                        $current = '';
                        $lastCombinator = ' ';
                    }
                    continue;
                }
            }

            $current .= $char;
        }

        // Add the last part
        $trimmedCurrent = trim($current);
        if ($trimmedCurrent !== '') {
            $parts[] = [
                'combinator' => $lastCombinator,
                'selector' => $trimmedCurrent,
            ];
        }

        return $parts;
    }

    /**
     * Convert a simple selector (no combinators) to XPath.
     *
     * @param string $selector Simple CSS selector (e.g., "div.class#id[attr='val']")
     * @return string XPath node test and predicates (e.g., "div[@class and @id='val']")
     * @throws LangsysException If selector syntax is invalid
     */
    protected function simpleSelectoToXpath($selector)
    {
        $tag = '*';
        $conditions = [];

        // Extract tag name (if present at the start)
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9-]*)/', $selector, $matches)) {
            $tag = strtolower($matches[1]);
            $selector = substr($selector, strlen($matches[1]));
        }

        // Parse the rest: classes, IDs, attributes
        $remaining = $selector;
        while ($remaining !== '') {
            if ($remaining[0] === '.') {
                // Class selector
                if (preg_match('/^\.([a-zA-Z_-][a-zA-Z0-9_-]*)/', $remaining, $matches)) {
                    $class = $matches[1];
                    $conditions[] = "contains(concat(' ', normalize-space(@class), ' '), ' " . $class . " ')";
                    $remaining = substr($remaining, strlen($matches[0]));
                } else {
                    throw new LangsysException('Invalid class selector in: ' . $selector);
                }
            } elseif ($remaining[0] === '#') {
                // ID selector
                if (preg_match('/^#([a-zA-Z_-][a-zA-Z0-9_-]*)/', $remaining, $matches)) {
                    $id = $matches[1];
                    $conditions[] = "@id='" . $this->escapeXpathString($id) . "'";
                    $remaining = substr($remaining, strlen($matches[0]));
                } else {
                    throw new LangsysException('Invalid ID selector in: ' . $selector);
                }
            } elseif ($remaining[0] === '[') {
                // Attribute selector
                $result = $this->parseAttributeSelector($remaining);
                $conditions[] = $result['condition'];
                $remaining = $result['remaining'];
            } else {
                throw new LangsysException('Unexpected character in selector: ' . $remaining[0]);
            }
        }

        // Build final XPath
        if (empty($conditions)) {
            return $tag;
        }

        return $tag . '[' . implode(' and ', $conditions) . ']';
    }

    /**
     * Parse an attribute selector and return XPath condition.
     *
     * Supports: [attr], [attr="val"], [attr^="prefix"], [attr$="suffix"], [attr*="contains"]
     *
     * @param string $str String starting with [
     * @return array ['condition' => string, 'remaining' => string]
     * @throws LangsysException If syntax is invalid
     */
    protected function parseAttributeSelector($str)
    {
        // Match attribute selector pattern
        $pattern = '/^\[([a-zA-Z_][a-zA-Z0-9_-]*)(?:([~|^$*]?=)(["\'])([^\3]*?)\3)?\]/';

        if (!preg_match($pattern, $str, $matches)) {
            throw new LangsysException('Invalid attribute selector: ' . substr($str, 0, 50));
        }

        $fullMatch = $matches[0];
        $attr = $matches[1];
        $operator = isset($matches[2]) ? $matches[2] : '';
        $value = isset($matches[4]) ? $matches[4] : '';

        $remaining = substr($str, strlen($fullMatch));

        // Build XPath condition based on operator
        if ($operator === '') {
            // [attr] - attribute exists
            $condition = '@' . $attr;
        } elseif ($operator === '=') {
            // [attr="value"] - exact match
            $condition = "@" . $attr . "='" . $this->escapeXpathString($value) . "'";
        } elseif ($operator === '^=') {
            // [attr^="value"] - starts with
            $condition = "starts-with(@" . $attr . ", '" . $this->escapeXpathString($value) . "')";
        } elseif ($operator === '$=') {
            // [attr$="value"] - ends with
            $escapedValue = $this->escapeXpathString($value);
            $condition = "substring(@" . $attr . ", string-length(@" . $attr . ") - string-length('" . $escapedValue . "') + 1) = '" . $escapedValue . "'";
        } elseif ($operator === '*=') {
            // [attr*="value"] - contains
            $condition = "contains(@" . $attr . ", '" . $this->escapeXpathString($value) . "')";
        } elseif ($operator === '~=') {
            // [attr~="value"] - word in space-separated list
            $escapedValue = $this->escapeXpathString($value);
            $condition = "contains(concat(' ', normalize-space(@" . $attr . "), ' '), ' " . $escapedValue . " ')";
        } elseif ($operator === '|=') {
            // [attr|="value"] - value or value-*
            $escapedValue = $this->escapeXpathString($value);
            $condition = "(@" . $attr . "='" . $escapedValue . "' or starts-with(@" . $attr . ", '" . $escapedValue . "-'))";
        } else {
            throw new LangsysException('Unsupported attribute operator: ' . $operator);
        }

        return [
            'condition' => $condition,
            'remaining' => $remaining,
        ];
    }

    /**
     * Escape a string for use in XPath single-quoted string.
     *
     * @param string $str The string to escape
     * @return string Escaped string
     */
    protected function escapeXpathString($str)
    {
        // For simplicity, we replace single quotes with concat('..', "'", '..')
        // But this is complex, so for now just escape basic cases
        if (strpos($str, "'") === false) {
            return $str;
        }

        // If string contains single quotes, we need to use concat()
        // This is a simplified approach - for more complex cases, would need concat()
        return str_replace("'", "', \"'\", '", $str);
    }

    /**
     * Check if an element matches an XPath expression.
     *
     * @param DOMElement $element The element to check
     * @param string $xpath The XPath expression
     * @return bool True if element matches
     */
    protected function elementMatchesXpath(DOMElement $element, $xpath)
    {
        $doc = $element->ownerDocument;
        if ($doc === null) {
            return false;
        }

        $domXpath = new DOMXPath($doc);

        try {
            $nodes = $domXpath->query($xpath);
            if ($nodes === false) {
                return false;
            }

            foreach ($nodes as $node) {
                if ($node->isSameNode($element)) {
                    return true;
                }
            }
        } catch (\Exception $e) {
            return false;
        }

        return false;
    }
}
