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
        return $doc->loadHTML((string) $html, $options | self::RECOVER | self::IGNORE_ENCODING);
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
     * Start tags that end svg or math content, as in a browser's tree
     * builder: one written inside it is the page's again.
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
     * a tag. A comment ends at the first `-->`, or at once as `<!-->`.
     *
     * @param string $html
     * @return string[]
     */
    public static function startTags($html)
    {
        $html = (string) $html;
        $names = [];
        $foreign = null;
        $depth = 0;
        $at = 0;

        while (($lt = strpos($html, '<', $at)) !== false) {
            if (substr_compare($html, '<!--', $lt, 4) === 0) {
                if (!preg_match('/\G<!--(?:-?>|.*?-->)/s', $html, $m, 0, $lt)) {
                    break;
                }
                $at = $lt + strlen($m[0]);
                continue;
            }
            if (preg_match('/\G<[!?\/][^>]*>?/', $html, $m, 0, $lt) && !preg_match('/\G<\/[a-zA-Z]/', $html, $unused, 0, $lt)) {
                $at = $lt + strlen($m[0]);
                continue;
            }
            if (preg_match('/\G<\/([a-zA-Z][^\s\/>]*)[^>]*>?/', $html, $m, 0, $lt)) {
                if ($foreign !== null && strtolower($m[1]) === $foreign && --$depth === 0) {
                    $foreign = null;
                }
                $at = $lt + strlen($m[0]);
                continue;
            }
            if (!preg_match('/\G<([a-zA-Z][^\s\/>]*)(?:[^>"\']|"[^"]*"|\'[^\']*\')*>?/', $html, $m, 0, $lt)) {
                $at = $lt + 1;
                continue;
            }

            $name = strtolower($m[1]);
            $at = $lt + strlen($m[0]);
            $closed = substr($m[0], -2) === '/>';

            if ($foreign !== null) {
                if (!in_array($name, self::BREAKOUT, true)) {
                    if ($name === $foreign && !$closed) {
                        $depth++;
                    }
                    continue;
                }
                $foreign = null;
            }

            $names[] = $name;

            if (in_array($name, self::FOREIGN, true)) {
                if (!$closed) {
                    $foreign = $name;
                    $depth = 1;
                }
                continue;
            }
            if ($name === 'plaintext') {
                break;
            }
            if (in_array($name, self::TEXT_BODIES, true)) {
                if (!preg_match('/<\/' . $name . '[\s\/>]/i', $html, $end, PREG_OFFSET_CAPTURE, $at)) {
                    break;
                }
                $at = $end[0][1];
            }
        }

        return $names;
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
