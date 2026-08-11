<?php

namespace Langsys\SDK\Html;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Encodes an element's inline markup as {m<i>o}/{m<i>c} tokens so a run of
 * mixed content can be registered as ONE phrase instead of being split at tag
 * boundaries.
 *
 * Why this exists: splitting `<p>Based on {n} <strong>reviews</strong></p>`
 * yields the separate catalog entries "Based on {n}" and "reviews", which puts
 * the count in a different phrase from the noun it inflects. No ICU plural rule
 * can then select the correct form, which is fatal in languages with rich
 * plural morphology (Russian's four categories, Arabic's six, Polish). Keeping
 * the run whole is a correctness requirement, not a formatting preference.
 *
 * The wire format is deliberately identical to the Langsys JS SDK's <Phrase>
 * component, so catalog entries registered from either SDK are consumable by
 * the other:
 *
 *   <p>Based on {n} <strong>reviews</strong></p>
 *     -> "Based on {n} {m0o}reviews{m0c}"
 *
 * Token names are valid ICU argument names, so a token sitting inside a plural
 * or select branch parses normally - which is what makes markup-bearing phrases
 * pluralizable at all.
 *
 * Slots hold tag + attributes only (a SHALLOW clone), never children. The real
 * element is reused at render time so scoped-CSS classes and event bindings
 * survive, while the stored phrase contains only the tokens and never any
 * build-specific class hashes - which is what keeps the phrase key stable
 * across builds.
 */
class MarkupTokenizer
{
    /**
     * Private-use sentinels used while rebuilding markup.
     *
     * Deliberately brace-free so that ICU treats an already-substituted
     * sentinel as literal text rather than trying to parse it as an argument.
     * These are transient: they are never registered and never sent over the
     * wire. The catalog only ever contains {m<i>o}/{m<i>c}.
     */
    const OPEN_START = "\xEE\x80\x80";  // U+E000
    const OPEN_END = "\xEE\x80\x81";    // U+E001
    const CLOSE_START = "\xEE\x80\x82"; // U+E002
    const CLOSE_END = "\xEE\x80\x83";   // U+E003

    /**
     * Matches either sentinel form, capturing the slot index.
     */
    const SENTINEL_PATTERN = '/\x{E000}(\d+)\x{E001}|\x{E002}(\d+)\x{E003}/u';

    /**
     * Encode an element's children into a single tokenized phrase.
     *
     * @param DOMElement $element The element whose CHILDREN are encoded
     * @return array ['text' => string, 'slots' => DOMElement[]]
     */
    public function encode(DOMElement $element)
    {
        $slots = [];
        $text = $this->encodeChildren($element, $slots);

        return [
            'text' => $this->collapseWhitespace($text),
            'slots' => $slots,
        ];
    }

    /**
     * Whether a phrase carries any markup tokens.
     *
     * @param string $text
     * @return bool
     */
    public function hasTokens($text)
    {
        return is_string($text) && preg_match('/\{m\d+[oc]\}/', $text) === 1;
    }

    /**
     * Build the interpolation params that map tokens to sentinels.
     *
     * These are merged with the caller's own params and passed through the
     * normal interpolation path, so ICU substitutes tokens inside plural and
     * select branches exactly as it does any other argument.
     *
     * @param int $slotCount
     * @return array
     */
    public function tokenParams($slotCount)
    {
        $params = [];

        for ($i = 0; $i < $slotCount; $i++) {
            $params['m' . $i . 'o'] = self::OPEN_START . $i . self::OPEN_END;
            $params['m' . $i . 'c'] = self::CLOSE_START . $i . self::CLOSE_END;
        }

        return $params;
    }

    /**
     * Rebuild DOM nodes from text containing sentinels.
     *
     * Failure handling is "lose the markup, keep the meaning": if the
     * translation dropped a token, left them unbalanced, or referenced a slot
     * that does not exist, reconstruction is abandoned and a single text node
     * with all sentinels stripped is returned. A translation that reorders
     * tokens works by construction - elements are placed where the tokens now
     * sit, not where they originally were, which is the entire point.
     *
     * @param string $text Interpolated text containing sentinels
     * @param DOMElement[] $slots Shallow element clones by index
     * @param DOMDocument $doc Document used to create nodes
     * @return DOMNode[] Nodes ready to be appended
     */
    public function render($text, array $slots, DOMDocument $doc)
    {
        $result = $this->rebuild($text, $slots, $doc);

        if ($result === null) {
            return [$doc->createTextNode($this->stripSentinels($text))];
        }

        return $result;
    }

    /**
     * Remove every sentinel from a string.
     *
     * @param string $text
     * @return string
     */
    public function stripSentinels($text)
    {
        $stripped = preg_replace(self::SENTINEL_PATTERN, '', $text);

        return $stripped === null ? $text : $stripped;
    }

    /**
     * Walk children, appending text verbatim and wrapping elements in tokens.
     *
     * The slot index is claimed BEFORE recursing, so a parent always holds a
     * lower index than its descendants. Numbering is a single counter across
     * the whole phrase in depth-first pre-order - never per-depth, never reset.
     *
     * @param DOMNode $node
     * @param array &$slots
     * @return string
     */
    protected function encodeChildren(DOMNode $node, array &$slots)
    {
        $out = '';

        if (!$node->hasChildNodes()) {
            return $out;
        }

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $out .= $child->textContent;
                continue;
            }

            if ($child instanceof DOMElement) {
                $index = count($slots);
                $slots[] = $child->cloneNode(false); // Shallow: tag + attributes only.

                $out .= '{m' . $index . 'o}'
                    . $this->encodeChildren($child, $slots)
                    . '{m' . $index . 'c}';
                continue;
            }

            // Comments, CDATA and everything else contribute nothing.
        }

        return $out;
    }

    /**
     * Stack-scan the sentinel stream and rebuild nodes.
     *
     * @param string $text
     * @param DOMElement[] $slots
     * @param DOMDocument $doc
     * @return DOMNode[]|null Null when the stream is unusable
     */
    protected function rebuild($text, array $slots, DOMDocument $doc)
    {
        $top = [];          // Nodes at the outermost level.
        $stack = [];        // Open elements: ['index' => int, 'node' => DOMElement].
        $offset = 0;

        if (!preg_match_all(self::SENTINEL_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE)) {
            // No sentinels at all - plain text.
            return $text === '' ? [] : [$doc->createTextNode($text)];
        }

        foreach ($matches[0] as $position => $match) {
            $marker = $match[0];
            $markerOffset = $match[1];

            // Text preceding this marker.
            if ($markerOffset > $offset) {
                $this->appendNode(
                    $doc->createTextNode(substr($text, $offset, $markerOffset - $offset)),
                    $top,
                    $stack
                );
            }

            $offset = $markerOffset + strlen($marker);

            $isOpen = $matches[1][$position][1] !== -1;
            $index = (int) ($isOpen ? $matches[1][$position][0] : $matches[2][$position][0]);

            if (!isset($slots[$index])) {
                return null; // Unknown slot index.
            }

            if ($isOpen) {
                $element = $slots[$index]->cloneNode(false);
                $this->appendNode($element, $top, $stack);
                $stack[] = ['index' => $index, 'node' => $element];
                continue;
            }

            // Closing marker: must match the currently open element.
            if (empty($stack)) {
                return null;
            }

            $openTag = array_pop($stack);
            if ($openTag['index'] !== $index) {
                return null; // Crossed or mismatched tokens.
            }
        }

        // Trailing text after the last marker.
        if ($offset < strlen($text)) {
            $this->appendNode($doc->createTextNode(substr($text, $offset)), $top, $stack);
        }

        if (!empty($stack)) {
            return null; // Unclosed token.
        }

        return $top;
    }

    /**
     * Append a node to the currently open element, or to the top level.
     *
     * @param DOMNode $node
     * @param array &$top
     * @param array $stack
     * @return void
     */
    protected function appendNode(DOMNode $node, array &$top, array $stack)
    {
        if (empty($stack)) {
            $top[] = $node;
            return;
        }

        $current = $stack[count($stack) - 1];
        $current['node']->appendChild($node);
    }

    /**
     * Collapse runs of whitespace and trim.
     *
     * @param string $text
     * @return string
     */
    protected function collapseWhitespace($text)
    {
        $collapsed = preg_replace('/\s+/u', ' ', $text);

        return trim($collapsed === null ? $text : $collapsed);
    }
}
