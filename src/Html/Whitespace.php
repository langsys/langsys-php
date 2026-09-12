<?php

namespace Langsys\SDK\Html;

/**
 * The one whitespace normalisation every token path must share (TOK-2).
 *
 * This exists because the SDK had five copies of "collapse whitespace and trim"
 * and they did not agree. Four were `preg_replace('/\s+/', ' ', $text)` without
 * `/u`, so PCRE's `\s` was ASCII-only and U+00A0, U+2028, U+2029, U+202F and
 * U+3000 survived the collapse; one (MarkupTokenizer) already had `/u`. The
 * codebase therefore disagreed with itself about what a phrase IS, depending on
 * which path reached the text first.
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
 * Deliberately NOT collapsed: U+200B (zero-width space) and U+FEFF, which
 * `/u` treats as format characters rather than whitespace. JavaScript's `\s`
 * DOES match U+FEFF, so that is a live cross-SDK divergence — measured and
 * reported rather than guessed at, since no shared vector covers it.
 */
final class Whitespace
{
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
        $collapsed = preg_replace('/\s+/u', ' ', (string) $text);

        // preg_replace returns null on malformed UTF-8 rather than raising, and
        // falling through with null would silently erase the phrase. The
        // ASCII-only pass is a worse normalisation but a real one, and a phrase
        // that survives imperfectly beats a phrase that vanishes.
        if ($collapsed === null) {
            $collapsed = preg_replace('/\s+/', ' ', (string) $text);
        }

        return trim((string) $collapsed);
    }
}
