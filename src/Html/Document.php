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
     * Load $html into $doc as DOMDocument::loadHTML() does, in recovery mode.
     *
     * @param DOMDocument $doc
     * @param string $html
     * @param int $options libxml options
     * @return bool Whether it loaded
     */
    public static function load(DOMDocument $doc, $html, $options = 0)
    {
        return $doc->loadHTML(self::forLibxml((string) $html), $options | self::RECOVER | self::IGNORE_ENCODING);
    }

    /**
     * $html written so libxml 2.9 reads the text a browser reads, which it
     * would not as written: a `<` that starts no tag, comment or doctype -
     * "Price < 10" - which libxml takes for a tag, losing the text up to the
     * next `>`, is written `&lt;`, as is every `<` in a textarea or a title,
     * whose bodies libxml parses as markup; in xmp, iframe, noembed and
     * noframes, which a browser reads without decoding, `<` and `&` are
     * escaped too, and html() writes them back as they were. A browser reads
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
     * iframe, noembed and noframes body as the text it holds: libxml writes
     * that text escaped, which a browser, reading those bodies as written,
     * would show. A body holding its own end tag stays escaped.
     *
     * @param \DOMNode $node
     * @return string
     */
    public static function html(\DOMNode $node)
    {
        $html = (string) ($node instanceof DOMDocument ? $node->saveHTML() : $node->ownerDocument->saveHTML($node));
        if (!preg_match('/<(?:iframe|noembed|noframes|xmp)[\s>\/]/i', $html)) {
            return $html;
        }

        $out = '';
        $at = 0;
        foreach (self::startTagsAt($html) as $tag) {
            if (!in_array($tag[0], self::RAW_BODIES, true) || $tag[1] < $at) {
                continue;
            }
            $body = $tag[1] + $tag[2];
            $end = stripos($html, '</' . $tag[0], $body);
            if ($end === false) {
                break;
            }
            // Raw only when nothing in it can end the element: the page's own
            // body never holds its end tag, so one there came with a
            // translation, and stays escaped.
            $text = html_entity_decode(substr($html, $body, $end - $body), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $out .= substr($html, $at, $body - $at) . (stripos($text, '</' . $tag[0]) === false ? $text : substr($html, $body, $end - $body));
            $at = $end;
        }

        return $out . substr($html, $at);
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
        return self::load($doc, '<?xml encoding="UTF-8"><' . self::FRAGMENT_ROOT . '>' . Charset::scrub($html), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
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
                } elseif ($points !== [] && $name === end($points)) {
                    array_pop($points);
                } elseif ($name === $foreign && --$depth === 0) {
                    self::closeNearest($open, $counts, [$foreign], []);
                    $foreign = null;
                    $points = [];
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
                    } elseif ($inPoint && in_array($name, self::TEXT_BODIES, true)) {
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
            if (in_array($name, self::TEXT_BODIES, true)) {
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
        $escape = in_array($name, self::DECODED_BODIES, true) ? '<' : (in_array($name, self::RAW_BODIES, true) ? '<&' : '');
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
