<?php

namespace Langsys\SDK\Html;

use DOMComment;
use DOMDocument;
use DOMText;
use DOMXPath;

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

    /** What closes the wrapper a fragment is parsed in: a `</div>` and a mark. */
    const FRAGMENT_END = '</div><!--ls-fragment-end-->';

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
        return $doc->loadHTML((string) $html, $options | self::RECOVER);
    }

    /**
     * Load a fragment into $doc inside one `<div>`, the document's first
     * element, as every fragment path reads it.
     *
     * A script or style the fragment leaves open reads on to the end in
     * recovery mode, as a browser's does, which would take the wrapper's own
     * end tag into its body. The wrapper ends with a mark, and whatever of
     * it a body took is cut back off, so the body ends where the fragment
     * does.
     *
     * @param DOMDocument $doc
     * @param string $html
     * @return bool Whether it loaded
     */
    public static function loadFragment(DOMDocument $doc, $html)
    {
        $loaded = self::load($doc, '<?xml encoding="UTF-8"><div>' . $html . self::FRAGMENT_END, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        if (!$loaded) {
            return false;
        }

        foreach ((new DOMXPath($doc))->query('//script|//style|//comment()') as $node) {
            if ($node instanceof DOMComment) {
                if ($node->data === 'ls-fragment-end') {
                    $node->parentNode->removeChild($node);
                }
                continue;
            }

            $last = $node->lastChild;
            if ($last instanceof DOMText && substr($last->data, -strlen(self::FRAGMENT_END)) === self::FRAGMENT_END) {
                $last->data = substr($last->data, 0, -strlen(self::FRAGMENT_END));
            }
        }

        return true;
    }
}
