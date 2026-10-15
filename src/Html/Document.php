<?php

namespace Langsys\SDK\Html;

use DOMCharacterData;
use DOMDocument;
use DOMNode;

/**
 * Loads HTML the way every path here reads it, with each `<script>` and
 * `<style>` body kept byte for byte.
 *
 * HTML ends a raw-text body only at its own end tag, so
 * `el.innerHTML = '<p>hi</p>'` is one string of JavaScript. libxml2's HTML
 * parser (2.9) ends it at any end tag and drops that tag, which would rewrite
 * a page's own code, a JSON-LD string or a CSS comment. Each body is set
 * aside under a token before parsing and put back afterwards.
 *
 * A body is found as the parser finds elements: a `<script>` or `<style>`
 * start tag in markup, matched on its whole name. The same words written in a
 * comment, in an attribute value or in a title or textarea body are text, and
 * `<style-guide>` is an element of its own.
 */
final class Document
{
    /** Elements whose body ends only at their own end tag. */
    const RAW_TEXT = ['script', 'style'];

    /** Elements whose body is text up to their own end tag, with no elements in it. */
    const TEXT_ONLY = ['script', 'style', 'title', 'textarea'];

    /**
     * Load $html into $doc as DOMDocument::loadHTML() does.
     *
     * @param DOMDocument $doc
     * @param string $html
     * @param int $options libxml options
     * @return bool Whether it loaded
     */
    public static function load(DOMDocument $doc, $html, $options = 0)
    {
        $html = (string) $html;
        $bodies = [];

        if (stripos($html, '<script') !== false || stripos($html, '<style') !== false) {
            $html = self::setAside($html, $bodies);
        }

        $loaded = $doc->loadHTML($html, $options);

        if ($bodies !== []) {
            self::restore($doc, $bodies);
        }

        return $loaded;
    }

    /**
     * $html with each script and style body replaced by a token.
     *
     * @param string $html
     * @param array<string, string> &$bodies token => body
     * @return string
     */
    private static function setAside($html, array &$bodies)
    {
        $nonce = bin2hex(random_bytes(8));
        $length = strlen($html);
        $out = '';
        $copied = 0;
        $at = 0;

        while (($open = strpos($html, '<', $at)) !== false) {
            if (substr_compare($html, '<!--', $open, 4) === 0) {
                $end = strpos($html, '-->', $open + 4);
                $at = $end === false ? $length : $end + 3;
                continue;
            }

            // Only a start tag opens an element; any other `<` is passed over.
            if (!preg_match('/\G<([A-Za-z][^\s\/>]*)/', $html, $m, 0, $open)) {
                $at = $open + 1;
                continue;
            }

            $tagEnd = self::tagEnd($html, $open + strlen($m[0]));
            if ($tagEnd === null) {
                break;
            }

            $name = strtolower($m[1]);
            $at = $tagEnd + 1;

            if (!in_array($name, self::TEXT_ONLY, true)) {
                continue;
            }

            if (!preg_match('#</' . $name . '[\s/>]#i', $html, $close, PREG_OFFSET_CAPTURE, $at)) {
                break;
            }

            $bodyEnd = $close[0][1];

            if (in_array($name, self::RAW_TEXT, true)) {
                $token = 'lsraw' . $nonce . 'n' . count($bodies) . 'x';
                $bodies[$token] = substr($html, $at, $bodyEnd - $at);
                $out .= substr($html, $copied, $at - $copied) . $token;
                $copied = $bodyEnd;
            }

            $at = $bodyEnd;
        }

        return $out . substr($html, $copied);
    }

    /**
     * The offset of the `>` closing a start tag whose name ends at $at, read
     * past quoted attribute values; null when the tag never closes.
     *
     * @param string $html
     * @param int $at
     * @return int|null
     */
    private static function tagEnd($html, $at)
    {
        $length = strlen($html);

        while ($at < $length) {
            $char = $html[$at];

            if ($char === '>') {
                return $at;
            }

            if ($char === '"' || $char === "'") {
                $quote = strpos($html, $char, $at + 1);
                if ($quote === false) {
                    return null;
                }
                $at = $quote + 1;
                continue;
            }

            $at++;
        }

        return null;
    }

    /**
     * Put each body back into the text it was set aside from.
     *
     * @param DOMNode $node
     * @param array<string, string> $bodies token => body
     * @return void
     */
    private static function restore(DOMNode $node, array $bodies)
    {
        if ($node instanceof DOMCharacterData) {
            if (strpos($node->nodeValue, 'lsraw') !== false) {
                $node->nodeValue = strtr($node->nodeValue, $bodies);
            }

            return;
        }

        if ($node->childNodes === null) {
            return;
        }

        foreach ($node->childNodes as $child) {
            self::restore($child, $bodies);
        }
    }
}
