<?php

namespace Langsys\SDK\Html;

use DOMCharacterData;
use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Loads HTML the way every path here reads it, with each `<script>` and
 * `<style>` body kept byte for byte.
 *
 * HTML ends a raw-text body only at its own end tag, so
 * `el.innerHTML = '<p>hi</p>'` is one string of JavaScript. libxml2's HTML
 * parser (2.9) ends it at any end tag and drops that tag, which would rewrite
 * a page's own code, a JSON-LD string or a CSS comment. Each body is set
 * aside under a token before parsing and put back afterwards,
 * wherever the token landed: the element's text, or a comment or attribute
 * value the source wrote the element inside.
 */
final class Document
{
    /**
     * A raw-text element with its body, the open tag read quote by quote so a
     * `>` inside an attribute value does not end it.
     */
    const RAW_TEXT_PATTERN = '#(<(script|style)\b(?:[^>"\']|"[^"]*"|\'[^\']*\')*>)(.*?)(</\2\s*>)#is';

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
            $nonce = bin2hex(random_bytes(8));
            $protected = preg_replace_callback(self::RAW_TEXT_PATTERN, function ($m) use (&$bodies, $nonce) {
                $token = 'lsraw' . $nonce . 'n' . count($bodies) . 'x';
                $bodies[$token] = $m[3];

                return $m[1] . $token . $m[4];
            }, $html);

            if ($protected !== null) {
                $html = $protected;
            } else {
                $bodies = [];
            }
        }

        $loaded = $doc->loadHTML($html, $options);

        if ($bodies !== []) {
            self::restore($doc, $bodies);
        }

        return $loaded;
    }

    /**
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

        if ($node instanceof DOMElement) {
            foreach ($node->attributes as $attribute) {
                if (strpos($attribute->value, 'lsraw') !== false) {
                    $attribute->value = strtr($attribute->value, $bodies);
                }
            }
        }

        if ($node->childNodes === null) {
            return;
        }

        foreach ($node->childNodes as $child) {
            self::restore($child, $bodies);
        }
    }
}
