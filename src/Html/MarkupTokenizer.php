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
     * Elements whose contents must never be translated or registered.
     * Mirrors PageTranslator::SKIP_ELEMENTS.
     */
    const OPAQUE_ELEMENTS = [
        'script', 'style', 'noscript', 'template', 'svg', 'math',
    ];

    /**
     * Whether an element's contents must be preserved verbatim.
     *
     * @param DOMElement $element
     * @return bool
     */
    protected function isOpaque(DOMElement $element)
    {
        if (in_array(strtolower($element->tagName), self::OPAQUE_ELEMENTS, true)) {
            return true;
        }

        return $element->getAttribute('translate') === 'no'
            || $element->getAttribute('data-notrans') !== '';
    }

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
        // Any DOM failure - most likely slots owned by a different document -
        // must fall to "lose the markup, keep the meaning" rather than escape.
        try {
            $result = $this->rebuild($text, $slots, $doc);
        } catch (\Throwable $e) {
            $result = null;
        }

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

        if ($stripped === null) {
            // The /u pattern fails outright on invalid UTF-8; without a
            // byte-level fallback the sentinels would ship to the browser.
            $stripped = $text;
        }

        // Also removes half-formed or mangled sentinels, which the full
        // START-digits-END pattern cannot match and would otherwise leak a
        // private-use character into the output.
        $bytes = preg_replace(
            '/\xEE\x80[\x80-\x83]/',
            '',
            $stripped
        );

        return $bytes === null ? $stripped : $bytes;
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

                // Never let script/style bodies - or anything the author marked
                // translate="no" - into the catalog. They would be registered as
                // translatable text, billed, possibly leak inline config, and
                // worst of all the catalog could rewrite them: a translation for
                // a <script> body is applied straight back into the element.
                //
                // The subtree is preserved rather than dropped, by storing a DEEP
                // clone and emitting an empty token pair. It renders back
                // untouched and contributes nothing to the phrase.
                if ($this->isOpaque($child)) {
                    $slots[] = $child->cloneNode(true);
                    $out .= '{m' . $index . 'o}{m' . $index . 'c}';
                    continue;
                }

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

        $found = preg_match_all(self::SENTINEL_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE);

        if ($found === false) {
            // PCRE failed outright (invalid UTF-8 against the /u pattern).
            // Must NOT be treated as "no sentinels", or the raw private-use
            // characters would be emitted as visible text.
            return null;
        }

        if ($found === 0) {
            // No complete sentinels - plain text. Still stripped, because a
            // truncated or mangled sentinel matches nothing yet would otherwise
            // render a private-use character to the browser.
            $plain = $this->stripSentinels($text);

            return $plain === '' ? [] : [$doc->createTextNode($plain)];
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
                // Clone FIRST, then import only if the clone belongs to another
                // document. importNode() returns the SAME node when the source
                // already belongs to $doc - which is the normal case here, since
                // slots are cloned from the live document - so importing
                // directly would alias one element across every occurrence of
                // the token. A translation repeating a token pair then moved the
                // single element instead of producing two.
                $clone = $slots[$index]->cloneNode($slots[$index]->hasChildNodes());

                $element = $clone->ownerDocument === $doc
                    ? $clone
                    : $doc->importNode($clone, true);

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
