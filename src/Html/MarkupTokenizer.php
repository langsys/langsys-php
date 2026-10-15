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
     *
     * This is the THIRD route text can reach - a tokenized run, marked with
     * data-ls-phrase - alongside the page walk and the content-block walk, and
     * all three have to agree about what a phrase is or the same markup gets
     * different ids depending on how it was reached.
     *
     * `svg` is deliberately absent: SVG <text> is visible copy and is
     * tokenized, per TOK-1. It WAS here, with a comment claiming this list
     * mirrored PageTranslator::SKIP_ELEMENTS - which stopped being true the
     * moment the page path learned to translate SVG text, so a marked run
     * containing an icon registered `Hi {m0o}{m0c}` and its label was never
     * translated. `math` stays: notation, not prose.
     */
    const OPAQUE_ELEMENTS = [
        'script', 'style', 'noscript', 'template', 'math',
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

        // A nested marked host is its own unit (MARK-4): kept whole, and its
        // words are not this phrase's.
        return HtmlParser::isTranslationExcluded($element) || HtmlParser::isMarkedHost($element);
    }

    /**
     * Encode an element's children into a single tokenized phrase.
     *
     * An element kept whole - one its author excluded, script and its kin,
     * a nested marked host - is one empty token pair whatever it holds, its
     * slot a deep copy restored as given on render (TOK-1). With the values
     * read below $element, that copy holds each one as it was marked, never
     * as its placeholder.
     *
     * Where the phrase holds an author's exclusion, `earlier` is the key an
     * earlier reader gave it, one that walked into the excluded element, with
     * the map fromEarlier() brings that key's translation to these slots by.
     *
     * @param DOMElement $element The element whose CHILDREN are encoded
     * @param ValueMarkers|null $markers The value markers read below $element
     * @return array ['text' => string, 'slots' => DOMElement[], 'opaque' => int[], 'earlier' => array|null]
     */
    public function encode(DOMElement $element, ValueMarkers $markers = null)
    {
        $slots = [];
        $opaque = [];
        $excluded = false;
        $text = $this->encodeChildren($element, $slots, $opaque, $markers, $excluded);

        return [
            'text' => $this->collapseWhitespace($text),
            'slots' => $slots,
            'opaque' => $opaque,
            'earlier' => $excluded ? $this->earlierKey($element) : null,
        ];
    }

    /**
     * A source line with inline markup, read as it registers (FRM-8): its
     * fragment, the values marked in it, and its encoding, whose text is the
     * phrase. translateRich() renders from these, and a sync registers the
     * text, so the two agree on every line by construction.
     *
     * @param string $html
     * @return array{doc: DOMDocument, root: DOMElement, markers: ValueMarkers, tokenizer: self, encoded: array}|null
     *         Null when the line cannot be parsed
     */
    public static function readLine($html)
    {
        $internalErrors = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->encoding = 'UTF-8';
        $loaded = Document::loadFragment($doc, $html);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $root = $loaded ? Document::fragmentRoot($doc) : null;
        if (!$root instanceof DOMElement) {
            return null;
        }

        $markers = ValueMarkers::read($root);
        $tokenizer = new self();

        return ['doc' => $doc, 'root' => $root, 'markers' => $markers, 'tokenizer' => $tokenizer, 'encoded' => $tokenizer->encode($root, $markers)];
    }

    /**
     * Whether a phrase has text of its own: anything once its markup tokens
     * and whitespace are gone. One made only of elements kept whole has none,
     * and registers nothing (TOK-1).
     *
     * @param string $text
     * @return bool
     */
    public function hasText($text)
    {
        return Canonical::phrase((string) preg_replace('/\{m\d+[oc]\}/', '', (string) $text)) !== '';
    }

    /**
     * A translation of a phrase's earlier key (see encode()), in the current
     * key's terms: each token takes the slot its element has now, and one
     * for an element inside an excluded element, or for none at all, is
     * dropped. What the translation put inside an excluded element's pair is
     * then dropped on render, the element being restored from source.
     *
     * @param string $translation
     * @param array $earlier encode()'s `earlier`
     * @return string
     */
    public function fromEarlier($translation, array $earlier)
    {
        $map = $earlier['map'];

        return (string) preg_replace_callback('/\{m(\d+)([oc])\}/', function ($m) use ($map) {
            return isset($map[(int) $m[1]]) ? '{m' . $map[(int) $m[1]] . $m[2] . '}' : '';
        }, (string) $translation);
    }

    /**
     * Fill each `{name}` the caller passes, or its `%name%` spelling (TOK-5),
     * as plain text inside the slots kept whole because their author
     * excluded them, a boolean as `true`/`false` as the Interpolator writes
     * it. A framework call's line is a template: excluding an element keeps
     * its words from translation, not its placeholders from their values. A
     * value that is not a scalar, such as a date, and script and its kin are
     * left as written.
     *
     * @param DOMElement[] $slots
     * @param int[] $opaque encode()'s `opaque`
     * @param array $params
     * @return void
     */
    public function fillExcluded(array $slots, array $opaque, array $params)
    {
        foreach ($opaque as $index) {
            if (isset($slots[$index]) && $this->isAuthorExcluded($slots[$index])) {
                $this->fillText($slots[$index], $params);
            }
        }
    }

    /**
     * @param DOMNode $node
     * @param array $params
     * @return void
     */
    private function fillText(DOMNode $node, array $params)
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $child->nodeValue = (string) preg_replace_callback('/\{([A-Za-z_][A-Za-z0-9_]*)\}|%([A-Za-z_][A-Za-z0-9_]*)%/', function ($m) use ($params) {
                    $name = isset($m[2]) && $m[2] !== '' ? $m[2] : $m[1];
                    if (!array_key_exists($name, $params) || !is_scalar($params[$name])) {
                        return $m[0];
                    }

                    return is_bool($params[$name]) ? ($params[$name] ? 'true' : 'false') : (string) $params[$name];
                }, $child->nodeValue);
            } elseif ($child instanceof DOMElement && !in_array(strtolower($child->tagName), self::OPAQUE_ELEMENTS, true)) {
                $this->fillText($child, $params);
            }
        }
    }

    /**
     * Whether an element is kept whole because its author excluded it, not
     * for its kind: an earlier reader walked into it.
     *
     * @param DOMElement $element
     * @return bool
     */
    protected function isAuthorExcluded(DOMElement $element)
    {
        return HtmlParser::isTranslationExcluded($element)
            && !in_array(strtolower($element->tagName), self::OPAQUE_ELEMENTS, true);
    }

    /**
     * The key a reader that walked into author-excluded elements gave the
     * phrase, and each of its slots' current slot: null for a slot inside an
     * excluded element, which has none now.
     *
     * @param DOMElement $element
     * @return array{text: string, map: array<int, int|null>}
     */
    protected function earlierKey(DOMElement $element)
    {
        $map = [];
        $current = 0;
        $text = $this->encodeEarlier($element, $map, $current, false);

        return ['text' => $this->collapseWhitespace($text), 'map' => $map];
    }

    /**
     * @param DOMNode $node
     * @param array &$map
     * @param int &$current The next current slot
     * @param bool $inside Whether $node is inside an excluded element
     * @return string
     */
    private function encodeEarlier(DOMNode $node, array &$map, &$current, $inside)
    {
        $out = '';

        foreach ($node->childNodes as $child) {
            if ($child instanceof DOMText) {
                $out .= $child->textContent;
                continue;
            }

            if (!$child instanceof DOMElement) {
                continue;
            }

            $index = count($map);
            $walked = $this->isAuthorExcluded($child);
            $map[$index] = $inside ? null : $current++;

            if ($this->isOpaque($child) && !$walked) {
                $out .= '{m' . $index . 'o}{m' . $index . 'c}';
                continue;
            }

            $out .= '{m' . $index . 'o}'
                . $this->encodeEarlier($child, $map, $current, $inside || $walked)
                . '{m' . $index . 'c}';
        }

        return $out;
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
     * An element kept whole renders as its slot holds it: a translation's
     * text inside its token pair is dropped.
     *
     * @param string $text Interpolated text containing sentinels
     * @param DOMElement[] $slots Shallow element clones by index
     * @param DOMDocument $doc Document used to create nodes
     * @param int[] $opaque The slots kept whole (encode()'s `opaque`)
     * @return DOMNode[] Nodes ready to be appended
     */
    public function render($text, array $slots, DOMDocument $doc, array $opaque = [])
    {
        // Any DOM failure - most likely slots owned by a different document -
        // must fall to "lose the markup, keep the meaning" rather than escape.
        try {
            $result = $this->rebuild($text, $slots, $doc, $opaque);
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
     * @param int[] &$opaque The slots kept whole
     * @param ValueMarkers|null $markers
     * @param bool &$excluded Set when an author-excluded element is met
     * @return string
     */
    protected function encodeChildren(DOMNode $node, array &$slots, array &$opaque = [], ValueMarkers $markers = null, &$excluded = false)
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
                    $slots[] = $markers === null ? $child->cloneNode(true) : $markers->sealedClone($child);
                    $opaque[] = $index;
                    $excluded = $excluded || $this->isAuthorExcluded($child);
                    $out .= '{m' . $index . 'o}{m' . $index . 'c}';
                    continue;
                }

                $slots[] = $child->cloneNode(false); // Shallow: tag + attributes only.

                $out .= '{m' . $index . 'o}'
                    . $this->encodeChildren($child, $slots, $opaque, $markers, $excluded)
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
     * @param int[] $opaque
     * @return DOMNode[]|null Null when the stream is unusable
     */
    protected function rebuild($text, array $slots, DOMDocument $doc, array $opaque = [])
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
                $stack[] = ['index' => $index, 'node' => $element, 'kept' => in_array($index, $opaque, true)];
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

        // Nothing is added to an element kept whole.
        if (!empty($current['kept'])) {
            return;
        }

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
        return Canonical::phrase($text);
    }
}
