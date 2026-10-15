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

    /**
     * Load a fragment into $doc inside one `<div>`, the document's first
     * element, as every fragment path reads it.
     *
     * The wrapper is opened and never closed: the end of the input closes it,
     * as it closes whatever the fragment leaves open, so a script, a comment,
     * a start tag or an attribute value the fragment ends inside ends where
     * the fragment does, as in a browser, with nothing of the SDK's in it.
     *
     * @param DOMDocument $doc
     * @param string $html
     * @return bool Whether it loaded
     */
    public static function loadFragment(DOMDocument $doc, $html)
    {
        return self::load($doc, '<?xml encoding="UTF-8"><div>' . Charset::scrub($html), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    }
}
