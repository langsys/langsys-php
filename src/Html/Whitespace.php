<?php

namespace Langsys\SDK\Html;

/**
 * The one whitespace normalisation every token path must share (TOK-2).
 *
 * This exists because the SDK had seven copies of "collapse whitespace and trim"
 * and they did not agree. Six were `preg_replace('/\s+/', ' ', $text)` without
 * `/u`, so PCRE's `\s` was ASCII-only and U+00A0, U+2028, U+2029, U+202F and
 * U+3000 survived the collapse; one (MarkupTokenizer) already had `/u`. Three
 * further sites in HeadHandler did no collapse at all. The codebase therefore
 * disagreed with itself about what a phrase IS, depending on which path reached
 * the text first.
 *
 * Two consequences, and the second is the expensive one:
 *
 *   1. A content block's id is a hash of its token list, so a block containing a
 *      non-breaking space hashed differently here than in the JS SDKs — same
 *      markup, same visible text, two ids, and no diff or terminal can show you
 *      why, because U+00A0 and U+0020 render identically.
 *
 *   2. Registration and lookup used different copies. A phrase registered as
 *      "A long description" was looked up as "A\u{00A0}long description" and
 *      missed forever — re-registering on every render and never converging.
 *
 * A whitespace-only node is the case worth remembering: `<p>&nbsp;</p>` is ONE
 * token where U+00A0 survives and ZERO where it collapses, which changes the id
 * of every block containing one.
 *
 * The collapsed set is JavaScript's `\s`, EXACTLY - see JS_WHITESPACE. PCRE's
 * `\s` is a different set, and each difference was a silent id divergence:
 *
 *   U+FEFF   PCRE/u no,  JS yes  ->  PHP kept it in the token, TS dropped it
 *   U+0085   PCRE/u yes, JS no   ->  PHP collapsed it, TS kept it
 *   U+180E   PCRE/u yes, JS no   ->  PHP collapsed it, TS kept it
 *
 * Same markup, two ids, nothing visible in a diff or a terminal. U+200B and
 * U+2060 are matched by neither and remain content.
 */
final class Whitespace
{
    /**
     * JavaScript's `\s`, written out.
     *
     * Spelled explicitly rather than as `\s` because PCRE's `\s` is a
     * DIFFERENT set, and the difference is invisible until two SDKs derive
     * different ids for the same bytes. The JS core is the identity authority,
     * so matching PCRE here was matching the wrong standard. Ordered as
     * ECMA-262 lists it: WhiteSpace, then LineTerminator.
     */
    const JS_WHITESPACE = '[\x{0009}\x{000A}\x{000B}\x{000C}\x{000D}\x{0020}'
        . '\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}\x{FEFF}]';

    /**
     * Collapse every whitespace run to a single space and trim.
     *
     * `trim()` needs no charlist BECAUSE the replace runs first: by the time
     * trim sees the string, every whitespace run — ASCII or not — is already a
     * single U+0020. Passing a charlist as well would work, but it is one more
     * list to keep in step with PCRE's idea of `\s`, and keeping two lists in
     * step is the failure this class exists to end.
     *
     * @param string $text
     * @return string
     */
    public static function collapse($text)
    {
        $collapsed = preg_replace('/' . self::JS_WHITESPACE . '+/u', ' ', (string) $text);

        // preg_replace returns null on malformed UTF-8 rather than raising, and
        // falling through with null would silently erase the phrase. The
        // ASCII-only pass is a worse normalisation but a real one, and a phrase
        // that survives imperfectly beats a phrase that vanishes.
        //
        // Byte escapes, not \t\n\v\f\r: inside a PCRE character class `\v`
        // is the vertical-whitespace CLASS, not a vertical tab, so the spelling
        // that reads as "ASCII only" silently matched U+0085 as well.
        if ($collapsed === null) {
            $collapsed = preg_replace('/[\x09\x0A\x0B\x0C\x0D\x20]+/', ' ', (string) $text);
        }

        return trim((string) $collapsed);
    }
}
