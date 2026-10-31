<?php

namespace Langsys\SDK\Html;

use DOMDocument;

/**
 * Loads HTML the way every path here reads it, with each `<script>` and
 * `<style>` body kept as written.
 *
 * HTML ends a raw-text body only at its own end tag, so
 * `el.innerHTML = '<p>hi</p>'` is one string of JavaScript. libxml2's HTML
 * parser (2.9) ends such a body at any end tag and drops that tag, which
 * would rewrite a page's own code, a JSON-LD string or a CSS comment, unless
 * it parses in recovery mode: then it reads the body on to its own end tag,
 * and parses everything else as it would without the mode. libxml 2.14's
 * HTML5 tokenizer reads raw text correctly either way.
 *
 * One end tag recovery mode still drops: one whose name begins with the
 * element's own, such as `</scripts>` written inside a script, which libxml
 * 2.9 takes for the element's end.
 */
final class Document
{
    /** libxml's HTML_PARSE_RECOVER, for which PHP defines no constant. */
    const RECOVER = 1;

    /**
     * libxml's HTML_PARSE_IGNORE_ENC: the encoding is the one the input is
     * given in, never one a meta inside it declares, which would switch
     * libxml mid-parse and garble text already decoded (Charset).
     */
    const IGNORE_ENCODING = 2097152;

    /**
     * What every load starts with, so libxml reads the input as UTF-8 rather
     * than ISO-8859-1. libxml2 2.9 reads it as a processing instruction, and
     * 2.14 and later as a comment, as a browser does; load() removes either.
     */
    const UTF8_HINT = '<?xml encoding="UTF-8">';

    /**
     * Load $html into $doc as DOMDocument::loadHTML() does, in recovery mode,
     * as UTF-8, and with each body read as a browser reads it, on any libxml2.
     *
     * @param DOMDocument $doc
     * @param string $html
     * @param int $options libxml options
     * @return bool Whether it loaded
     */
    public static function load(DOMDocument $doc, $html, $options = 0)
    {
        if (!$doc->loadHTML(self::UTF8_HINT . self::forLibxml((string) $html), $options | self::RECOVER | self::IGNORE_ENCODING)) {
            return false;
        }

        self::dropHint($doc);
        if (self::readsRawBodies()) {
            self::readAsMarkup($doc);
        }

        return true;
    }

    /**
     * Whether this process's libxml2 reads an xmp body as the text written,
     * as a browser does - 2.14 and later - rather than parsing it as markup,
     * as 2.9 does. libxml2 is asked once per process.
     *
     * @return bool
     */
    public static function readsRawBodies()
    {
        static $raw = null;
        if ($raw === null) {
            $doc = new DOMDocument();
            $errors = libxml_use_internal_errors(true);
            $doc->loadHTML('<xmp>&amp;</xmp>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | self::RECOVER);
            if (!$errors) {
                libxml_clear_errors();
            }
            libxml_use_internal_errors($errors);
            $raw = $doc->documentElement !== null && $doc->documentElement->textContent === '&amp;';
        }

        return $raw;
    }

    /**
     * Remove the node UTF8_HINT was read as, the first in the document.
     *
     * @param DOMDocument $doc
     * @return void
     */
    private static function dropHint(DOMDocument $doc)
    {
        $node = $doc->firstChild;
        while ($node instanceof \DOMElement) {
            $node = $node->firstChild;
        }

        if (($node instanceof \DOMProcessingInstruction && strtolower($node->target) === 'xml')
            || ($node instanceof \DOMComment && strncmp($node->data, '?xml', 4) === 0)) {
            $node->parentNode->removeChild($node);
        }
    }

    /**
     * Read as markup each xmp, iframe, noembed and noframes body a browser
     * reads as markup - inside a <select>, or in svg or math outside their
     * integration points - which a libxml2 that reads raw bodies read as the
     * text written.
     *
     * @param DOMDocument $doc
     * @return void
     */
    private static function readAsMarkup(DOMDocument $doc)
    {
        $read = [];
        foreach (self::textOnlyBodies($doc) as $element) {
            if (strpbrk($element->textContent, '<&') !== false) {
                $read[] = $element;
            }
        }
        if ($read === []) {
            return;
        }

        $raw = self::rawBodies($doc);
        foreach ($read as $element) {
            if (in_array($element, $raw, true)) {
                continue;
            }

            $parsed = new DOMDocument();
            self::loadFragment($parsed, $element->textContent);
            while ($element->firstChild !== null) {
                $element->removeChild($element->firstChild);
            }
            foreach (iterator_to_array(self::fragmentRoot($parsed)->childNodes) as $child) {
                $element->appendChild($doc->importNode($child, true));
            }
        }
    }

    /**
     * The xmp, iframe, noembed and noframes elements at or below $node that
     * hold text and nothing else.
     *
     * @param \DOMNode $node
     * @return \DOMElement[]
     */
    private static function textOnlyBodies(\DOMNode $node)
    {
        $elements = [];
        if ($node instanceof \DOMElement && in_array(strtolower($node->nodeName), self::RAW_BODIES, true)) {
            $elements[] = $node;
        }
        if ($node instanceof DOMDocument || $node instanceof \DOMElement) {
            foreach (self::RAW_BODIES as $name) {
                foreach ($node->getElementsByTagName($name) as $element) {
                    $elements[] = $element;
                }
            }
        }

        return array_values(array_filter($elements, function (\DOMElement $element) {
            if (!$element->hasChildNodes()) {
                return false;
            }
            foreach ($element->childNodes as $child) {
                if (!$child instanceof \DOMText) {
                    return false;
                }
            }

            return true;
        }));
    }

    /**
     * $html written so libxml 2.9 reads the text a browser reads, which it
     * would not as written: a `<` that starts no tag, comment or doctype -
     * "Price < 10" - which libxml takes for a tag, losing the text up to the
     * next `>`, is written `&lt;`, as is every `<` in a textarea or a title,
     * whose bodies libxml parses as markup; in xmp, iframe, noembed and
     * noframes, which a browser reads without decoding, `<` and `&` are
     * escaped too where libxml2 parses those bodies as markup (2.9), and
     * html() writes them back as they were. A start tag the input ends
     * inside is left out, as a browser leaves it out. A browser reads
     * `<!-->` and `<!--->` as an empty comment and libxml as opening one that
     * runs to a later `-->`, so they are written `<!---->`.
     *
     * @param string $html
     * @return string
     */
    private static function forLibxml($html)
    {
        if (strpos($html, '<') === false) {
            return $html;
        }

        $edits = [];
        self::scan($html, $edits);

        $out = '';
        $at = 0;
        foreach ($edits as $edit) {
            $out .= substr($html, $at, $edit[0] - $at) . $edit[2];
            $at = $edit[0] + $edit[1];
        }

        return $out . substr($html, $at);
    }

    /** Text bodies libxml parses as markup: a browser decodes these. */
    const DECODED_BODIES = ['textarea', 'title'];

    /** Text bodies libxml parses as markup: a browser reads these as written. */
    const RAW_BODIES = ['iframe', 'noembed', 'noframes', 'xmp'];

    /**
     * $node as HTML, as DOMDocument::saveHTML() writes it, but with each xmp,
     * iframe, noembed and noframes body that holds only text written by the
     * SDK, not by libxml2, whose writers differ: 2.9 escapes those bodies
     * and 2.15 writes them as they are. A body a browser reads as
     * written - see rawBodies() - is written as the text it holds; any other,
     * or one holding its own end tag or a noscript's, is written escaped.
     *
     * @param \DOMNode $node
     * @return string
     */
    public static function html(\DOMNode $node)
    {
        $doc = $node instanceof DOMDocument ? $node : $node->ownerDocument;
        $html = (string) ($node instanceof DOMDocument ? $node->saveHTML() : $doc->saveHTML($node));
        if (stripos($html, '<xmp') === false && stripos($html, '<iframe') === false && stripos($html, '<noembed') === false && stripos($html, '<noframes') === false) {
            return $html;
        }

        $bodies = self::textOnlyBodies($node);
        if ($bodies === []) {
            return $html;
        }

        $raw = self::rawBodies($node);
        $nonce = bin2hex(random_bytes(8));
        $texts = [];
        $swapped = [];

        foreach ($bodies as $element) {
            // As written only when nothing in it can end the element, or a
            // <noscript> around it, which a browser reads as raw text too: the
            // page's own body never holds either end tag, so one there came
            // with a translation, and stays escaped.
            $text = $element->textContent;
            $asWritten = in_array($element, $raw, true)
                && stripos($text, '</' . strtolower($element->nodeName)) === false
                && stripos($text, '</noscript') === false;

            $placeholder = 'ls' . $nonce . 'r' . count($texts) . 'r';
            $texts[$placeholder] = $asWritten ? $text : htmlspecialchars($text, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $children = iterator_to_array($element->childNodes);
            foreach ($children as $child) {
                $element->removeChild($child);
            }
            $swapped[] = [$element, $element->appendChild($doc->createTextNode($placeholder)), $children];
        }

        try {
            $html = (string) ($node instanceof DOMDocument ? $node->saveHTML() : $doc->saveHTML($node));
        } finally {
            foreach ($swapped as list($element, $placeholder, $children)) {
                $element->removeChild($placeholder);
                foreach ($children as $child) {
                    $element->appendChild($child);
                }
            }
        }

        return strtr($html, $texts);
    }

    /**
     * The xmp, iframe, noembed and noframes elements at or below $node whose
     * body a browser reads as written: an HTML element holding only text,
     * outside svg and math or inside one of their integration points, or
     * after an element that ends svg or math content. Inside svg or math
     * otherwise it is the drawing's element; inside a <select> a browser
     * reads no such element at all, and its text as markup; and inside a
     * textarea or title, or anywhere after a <plaintext>, it reads it as
     * text, which written raw could end the element around it.
     *
     * $node is read in the content its ancestors put it in.
     *
     * @param \DOMNode $node
     * @return \DOMElement[]
     */
    private static function rawBodies(\DOMNode $node)
    {
        if (!$node instanceof DOMDocument && !$node instanceof \DOMElement) {
            return [];
        }

        $ancestors = [];
        for ($at = $node->parentNode; $at instanceof \DOMElement; $at = $at->parentNode) {
            $ancestors[] = $at;
        }
        $content = null;
        foreach (array_reverse($ancestors) as $ancestor) {
            $content = self::contentIn($ancestor, $content);
            if ($content === false) {
                return [];
            }
        }

        $found = [];
        $plaintext = false;
        self::walk($node instanceof DOMDocument ? $node->childNodes : [$node], $content, $found, $plaintext);

        return $found;
    }

    /**
     * Add the raw bodies among $nodes and below them to $found, reading them
     * in order in $content, as a browser does: an element that ends svg or
     * math content ends it for everything after it, which libxml leaves
     * inside the svg or math element.
     *
     * @param iterable $nodes
     * @param string|null $content
     * @param \DOMElement[] $found
     * @param bool $plaintext Whether a <plaintext> has been read, after which nothing is markup
     * @return bool Whether the svg or math content $nodes are in ends among them
     */
    private static function walk($nodes, $content, array &$found, &$plaintext)
    {
        $ended = false;
        foreach ($nodes as $node) {
            if (!$node instanceof \DOMElement) {
                continue;
            }

            $name = strtolower($node->nodeName);
            if ($content === null && $name === 'plaintext') {
                $plaintext = true;
            }
            if ($plaintext) {
                return $ended;
            }

            if ($content !== null && in_array($name, self::BREAKOUT, true)) {
                $content = null;
                $ended = true;
            }

            if ($content === null && in_array($name, self::RAW_BODIES, true)) {
                foreach ($node->childNodes as $child) {
                    if (!$child instanceof \DOMText) {
                        continue 2;
                    }
                }
                $found[] = $node;
                continue;
            }

            $inner = self::contentIn($node, $content);
            if ($inner !== false && self::walk($node->childNodes, $inner, $found, $plaintext) && $content !== null) {
                $content = null;
                $ended = true;
            }
        }

        return $ended;
    }

    /**
     * The content a browser reads $element's children in, given the content
     * $element is in: null for HTML, svg or math for theirs, and false where
     * no raw body is read as written - inside a <select>, a textarea, a title
     * or a <plaintext>.
     *
     * @param \DOMElement $element
     * @param string|null $content
     * @return string|null|false
     */
    private static function contentIn(\DOMElement $element, $content)
    {
        $name = strtolower($element->nodeName);
        if ($content === null) {
            if ($name === 'select' || $name === 'plaintext' || in_array($name, self::DECODED_BODIES, true)) {
                return false;
            }

            return in_array($name, self::FOREIGN, true) ? $name : null;
        }

        if (in_array($name, self::INTEGRATION_POINTS[$content], true) || in_array($name, self::BREAKOUT, true)
            || ($content === 'math' && $name === 'annotation-xml' && in_array(strtolower($element->getAttribute('encoding')), ['text/html', 'application/xhtml+xml'], true))) {
            return null;
        }

        return $content;
    }

    /** The element a fragment is read inside, one no fragment's end tag can close. */
    const FRAGMENT_ROOT = 'ls-fragment';

    /**
     * Load a fragment into $doc inside one wrapper element, the document's
     * first, as every fragment path reads it; fragmentRoot() finds it.
     *
     * The wrapper is opened and never closed: the end of the input closes it,
     * as it closes whatever the fragment leaves open, so a script, a comment,
     * a start tag or an attribute value the fragment ends inside ends where
     * the fragment does, as in a browser, with nothing of the SDK's in it.
     * It is an element of its own, so a stray `</div>` in the fragment closes
     * nothing, and what follows is still the fragment's, as an element's
     * innerHTML keeps it.
     *
     * @param DOMDocument $doc
     * @param string $html
     * @return bool Whether it loaded
     */
    public static function loadFragment(DOMDocument $doc, $html)
    {
        return self::load($doc, '<' . self::FRAGMENT_ROOT . '>' . Charset::scrub($html), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    }

    /** Elements whose body a browser reads as text, up to their own end tag. */
    const TEXT_BODIES = ['iframe', 'noembed', 'noframes', 'script', 'style', 'textarea', 'title', 'xmp'];

    /** Elements whose content is not HTML: an svg's <title> is the drawing's. */
    const FOREIGN = ['math', 'svg'];

    /**
     * The elements of svg and math whose content a browser reads as HTML
     * that stays the drawing's (HTML integration points, and MathML's text
     * integration points); an annotation-xml is one when its encoding is HTML.
     */
    const INTEGRATION_POINTS = ['svg' => ['foreignobject', 'desc', 'title'], 'math' => ['mi', 'mo', 'mn', 'ms', 'mtext']];

    /**
     * Start tags that end svg or math content, as in a browser's tree
     * builder: one written inside it is the page's again, unless it is inside
     * an integration point.
     */
    const BREAKOUT = [
        'b', 'big', 'blockquote', 'body', 'br', 'center', 'code', 'dd', 'div', 'dl', 'dt', 'em', 'embed',
        'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'head', 'hr', 'i', 'img', 'li', 'listing', 'menu', 'meta',
        'nobr', 'ol', 'p', 'pre', 'ruby', 's', 'small', 'span', 'strike', 'strong', 'sub', 'sup', 'table',
        'tt', 'u', 'ul', 'var',
    ];

    /**
     * The names of the HTML elements $html opens, in order and lowercase, as
     * a browser reads its tags: one written in a comment, a doctype, an
     * attribute value or a text body - a script's, a title's - is text, one
     * inside svg or math is not the page's, and nothing after <plaintext> is
     * a tag.
     *
     * @param string $html
     * @return string[]
     */
    public static function startTags($html)
    {
        $edits = [];

        return array_column(self::scan((string) $html, $edits), 0);
    }

    /**
     * The start tags of the elements startTags() names, each as [name, offset,
     * length, depth]: the depth is how many elements are open around it as a
     * browser builds the page, a void element opening none, and a tag that
     * ends an open one implicitly - a block after a <p>, an <li> after an
     * <li> - closing it first. A tag at depth 0 is a top-level element.
     *
     * @param string $html
     * @return array[]
     */
    public static function startTagsAt($html)
    {
        $edits = [];

        return self::scan((string) $html, $edits);
    }

    /** Elements that hold no content, so their start tag opens nothing. */
    const VOID = [
        'area', 'base', 'basefont', 'bgsound', 'br', 'col', 'embed', 'frame', 'hr', 'img', 'input', 'keygen',
        'link', 'meta', 'param', 'source', 'track', 'wbr',
    ];

    /** Start tags that close an open <p>. */
    const CLOSES_P = [
        'address', 'article', 'aside', 'blockquote', 'center', 'dd', 'details', 'dialog', 'dir', 'div', 'dl', 'dt',
        'fieldset', 'figcaption', 'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hgroup',
        'hr', 'li', 'listing', 'main', 'menu', 'nav', 'ol', 'p', 'plaintext', 'pre', 'section', 'summary', 'table', 'ul', 'xmp',
    ];

    /**
     * The scan startTags() reads: each start tag as startTagsAt() gives it,
     * and, into $edits, each [offset, length, replacement] forLibxml() makes.
     *
     * @param string $html
     * @param array $edits
     * @return array[]
     */
    private static function scan($html, array &$edits)
    {
        $tags = [];
        $open = [];
        $counts = [];
        $foreign = null;
        $depth = 0;
        $points = [];
        $pointSelects = 0;
        $at = 0;

        while (($lt = strpos($html, '<', $at)) !== false) {
            if (substr_compare($html, '<!--', $lt, 4) === 0) {
                $at = self::commentEnd($html, $lt);
                if ($at === null) {
                    break;
                }
                if ($at - $lt <= 6) {
                    $edits[] = [$lt, $at - $lt, '<!---->'];
                }
                continue;
            }
            if (preg_match('/\G<[!?\/][^>]*>?/', $html, $m, 0, $lt) && !preg_match('/\G<\/[a-zA-Z]/', $html, $unused, 0, $lt)) {
                $at = $lt + strlen($m[0]);
                continue;
            }
            if (preg_match('/\G<\/([a-zA-Z][^\s\/>]*)[^>]*>?/', $html, $m, 0, $lt)) {
                $name = strtolower($m[1]);
                if ($foreign === null) {
                    self::closeNearest($open, $counts, [$name], []);
                } elseif ($points !== [] && $name === 'select' && $pointSelects > 0) {
                    $pointSelects--;
                } elseif ($points !== [] && $name === end($points)) {
                    array_pop($points);
                } elseif ($name === $foreign && --$depth === 0) {
                    self::closeNearest($open, $counts, [$foreign], []);
                    $foreign = null;
                    $points = [];
                    $pointSelects = 0;
                }
                $at = $lt + strlen($m[0]);
                continue;
            }
            // A quote opens a value only after `=`; anywhere else in a tag it
            // is a character, as in a browser's tokenizer.
            if (!preg_match('/\G<([a-zA-Z][^\s\/>]*+)(?:[^>=]++|=[\t\n\f\r ]*+(?:"[^"]*+"?|\'[^\']*+\'?|[^\t\n\f\r >]*+))*+>?/', $html, $m, 0, $lt)) {
                // A `<` that opens nothing is text.
                $edits[] = [$lt, 1, '&lt;'];
                $at = $lt + 1;
                continue;
            }

            // A start tag the input ends inside is no tag at all to a browser.
            if (substr($m[0], -1) !== '>') {
                $edits[] = [$lt, strlen($m[0]), ''];
                break;
            }

            $name = strtolower($m[1]);
            $at = $lt + strlen($m[0]);
            $closed = self::selfClosing($m[0]);

            if ($foreign !== null) {
                $inPoint = $points !== [];
                if ($inPoint || !in_array($name, self::BREAKOUT, true)) {
                    if ($name === $foreign && !$closed) {
                        $depth++;
                    } elseif (!$inPoint && !$closed && self::integrationPoint($foreign, $name, $m[0])) {
                        $points[] = $name;
                    } elseif ($inPoint && $name === 'select' && !$closed) {
                        $pointSelects++;
                    } elseif ($inPoint && in_array($name, self::TEXT_BODIES, true) && !($pointSelects > 0 && in_array($name, self::RAW_BODIES, true))) {
                        $at = self::textBody($html, $name, $at, $edits);
                        if ($at === null) {
                            break;
                        }
                    }
                    continue;
                }
                self::closeNearest($open, $counts, [$foreign], []);
                $foreign = null;
            }

            self::closeImplied($open, $counts, $name);
            $tags[] = [$name, $lt, strlen($m[0]), count($open)];

            if (in_array($name, self::FOREIGN, true)) {
                if (!$closed) {
                    $foreign = $name;
                    $depth = 1;
                    self::push($open, $counts, $name);
                }
                continue;
            }
            if ($name === 'plaintext') {
                break;
            }
            if (!in_array($name, self::VOID, true)) {
                self::push($open, $counts, $name);
            }
            // Inside a <select> a browser reads no raw-body element, and its
            // text as markup.
            if (in_array($name, self::TEXT_BODIES, true) && !(!empty($counts['select']) && in_array($name, self::RAW_BODIES, true))) {
                $at = self::textBody($html, $name, $at, $edits);
                if ($at === null) {
                    break;
                }
            }
        }

        return $tags;
    }

    /**
     * Where the text body of $name, opening at $at, ends: at its own end tag,
     * or null when the input ends first. The edits forLibxml() makes inside
     * it are added to $edits.
     *
     * @param string $html
     * @param string $name
     * @param int $at
     * @param array $edits
     * @return int|null
     */
    private static function textBody($html, $name, $at, array &$edits)
    {
        $end = preg_match('/<\/' . $name . '[\s\/>]/i', $html, $found, PREG_OFFSET_CAPTURE, $at) ? $found[0][1] : null;
        $escape = in_array($name, self::DECODED_BODIES, true) ? '<' : (in_array($name, self::RAW_BODIES, true) && !self::readsRawBodies() ? '<&' : '');
        $stop = $end === null ? strlen($html) : $end;

        for ($i = $at + strcspn($html, $escape, $at); $escape !== '' && $i < $stop; $i += 1 + strcspn($html, $escape, $i + 1)) {
            $edits[] = [$i, 1, $html[$i] === '<' ? '&lt;' : '&amp;'];
        }

        return $end;
    }

    /**
     * Close what a start tag named $name ends implicitly, as a browser's tree
     * builder does: an open <p> before a block, an <li> before an <li> of the
     * same list, a <dt> or <dd> before either, an <option> before an option
     * or a group, and a cell, row or row group before the next.
     *
     * @param string[] $open
     * @param int[] $counts
     * @param string $name
     * @return void
     */
    private static function closeImplied(array &$open, array &$counts, $name)
    {
        if (in_array($name, self::CLOSES_P, true)) {
            self::closeNearest($open, $counts, ['p'], ['button', 'table', 'td', 'th', 'caption', 'object', 'template', 'applet', 'marquee']);
        }
        if ($name === 'li') {
            self::closeNearest($open, $counts, ['li'], ['ul', 'ol', 'menu', 'table', 'td', 'th', 'template']);
        } elseif ($name === 'dd' || $name === 'dt') {
            self::closeNearest($open, $counts, ['dd', 'dt'], ['dl', 'table', 'td', 'th', 'template']);
        } elseif (($name === 'option' || $name === 'optgroup') && end($open) === 'option') {
            self::pop($open, $counts);
        } elseif ($name === 'td' || $name === 'th') {
            self::closeNearest($open, $counts, ['td', 'th'], ['tr', 'table', 'template']);
        } elseif ($name === 'tr') {
            self::closeNearest($open, $counts, ['tr'], ['table', 'template']);
        } elseif ($name === 'tbody' || $name === 'thead' || $name === 'tfoot') {
            self::closeNearest($open, $counts, ['tbody', 'thead', 'tfoot'], ['table', 'template']);
        }
    }

    /**
     * Whether a start tag in $foreign content opens an integration point.
     *
     * @param string $foreign svg or math
     * @param string $name
     * @param string $startTag
     * @return bool
     */
    private static function integrationPoint($foreign, $name, $startTag)
    {
        if (in_array($name, self::INTEGRATION_POINTS[$foreign], true)) {
            return true;
        }

        return $foreign === 'math' && $name === 'annotation-xml'
            && preg_match('/\sencoding[\t\n\f\r ]*+=[\t\n\f\r ]*+["\']?(?:text\/html|application\/xhtml\+xml)["\'\s>\/]/i', $startTag);
    }

    /**
     * Whether a start tag is written self-closed, as a browser's tokenizer
     * reads it: it ends in `/>` and the slash is not the end of an unquoted
     * value - `<br/>` and `<svg a/>` are, `<a href=/docs/>` is not.
     *
     * @param string $startTag
     * @return bool
     */
    public static function selfClosing($startTag)
    {
        return substr($startTag, -2) === '/>'
            && !preg_match('/=[\t\n\f\r ]*+[^\t\n\f\r "\'>][^\t\n\f\r >]*+>$/', $startTag);
    }

    /**
     * @param string[] $open
     * @param int[] $counts How many of each name are open
     * @param string $name
     * @return void
     */
    private static function push(array &$open, array &$counts, $name)
    {
        $open[] = $name;
        $counts[$name] = isset($counts[$name]) ? $counts[$name] + 1 : 1;
    }

    /**
     * @param string[] $open
     * @param int[] $counts
     * @return void
     */
    private static function pop(array &$open, array &$counts)
    {
        $counts[array_pop($open)]--;
    }

    /**
     * Close the innermost open element named one of $names, and all inside
     * it, unless one named in $bounds is open inside that one first. With
     * none of $names open there is nothing to look for, so a page leaving
     * thousands of elements open is still read in linear time.
     *
     * @param string[] $open
     * @param int[] $counts
     * @param string[] $names
     * @param string[] $bounds
     * @return void
     */
    private static function closeNearest(array &$open, array &$counts, array $names, array $bounds)
    {
        $any = false;
        foreach ($names as $name) {
            $any = $any || !empty($counts[$name]);
        }
        if (!$any) {
            return;
        }

        for ($at = count($open) - 1; $at >= 0; $at--) {
            if (in_array($open[$at], $names, true)) {
                // Popped from the end: array_splice() would copy the stack.
                while (count($open) > $at) {
                    self::pop($open, $counts);
                }

                return;
            }
            if (in_array($open[$at], $bounds, true)) {
                return;
            }
        }
    }

    /**
     * How long the prolog $html opens with is: the whitespace, comments and
     * doctype before its first tag or text.
     *
     * @param string $html
     * @return int
     */
    public static function prologLength($html)
    {
        $html = (string) $html;
        $at = 0;
        while (true) {
            $at += strspn($html, " \t\n\f\r", $at);
            if (substr($html, $at, 4) === '<!--') {
                $end = self::commentEnd($html, $at);
                if ($end === null) {
                    return strlen($html);
                }
                $at = $end;
                continue;
            }
            if (strncasecmp(substr($html, $at, 9), '<!doctype', 9) === 0) {
                $end = strpos($html, '>', $at);
                if ($end === false) {
                    return strlen($html);
                }
                $at = $end + 1;
                continue;
            }

            return $at;
        }
    }

    /**
     * Where the comment opening at $lt ends, as a browser's tokenizer reads
     * it: after its first `-->`, which `<!-->` and `<!--->` already are, or
     * its first `--!>`; null when nothing ends it.
     *
     * @param string $html
     * @param int $lt
     * @return int|null
     */
    private static function commentEnd($html, $lt)
    {
        $ends = [];
        if (($dash = strpos($html, '-->', $lt + 2)) !== false) {
            $ends[] = $dash + 3;
        }
        if (($bang = strpos($html, '--!>', $lt + 4)) !== false) {
            $ends[] = $bang + 4;
        }

        return $ends === [] ? null : min($ends);
    }

    /**
     * The wrapper loadFragment() read a fragment inside, or null.
     *
     * @param DOMDocument|null $doc
     * @return \DOMElement|null
     */
    public static function fragmentRoot(?DOMDocument $doc = null)
    {
        return $doc === null ? null : $doc->documentElement;
    }
}
