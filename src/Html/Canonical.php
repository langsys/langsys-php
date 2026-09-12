<?php

namespace Langsys\SDK\Html;

/**
 * The canonical form of a phrase: what every token path must agree on.
 *
 * `Whitespace::collapse()` is the whitespace primitive. This is the whole
 * phrase contract, which is collapse PLUS placeholder canonicalisation, and it
 * exists as one function for the reason the whitespace fix already proved
 * twice: the moment a canonicalisation is applied on the registration side and
 * not the lookup side, the key that was written can never be found again, the
 * phrase re-registers on every render, and nothing translates. That failure is
 * invisible in a diff and produces no error.
 *
 * So: every site that PRODUCES a token and every site that LOOKS ONE UP calls
 * this, and neither is allowed its own variant.
 */
final class Canonical
{
    /**
     * Canonicalise a phrase for registration or lookup.
     *
     * @param string $text
     * @return string
     */
    public static function phrase($text)
    {
        return self::placeholders(Whitespace::collapse($text));
    }

    /**
     * Rewrite `%name%` to `{name}` (TOK-5).
     *
     * `{name}` is the placeholder form a phrase CARRIES; `%name%` is an
     * accepted escape for authors whose templating language eats braces. The JS
     * core normalises at capture, so a phrase authored as `Hello %name%` is
     * stored as `Hello {name}` there — and since a block's id hashes its
     * phrases, keeping the raw form here gave the same markup two ids across
     * the fleet (measured: `bb74011a…` here against `1e4b462c…` in the TS core).
     *
     * Unconditional, unlike the Interpolator's version, which rewrites only for
     * keys the caller supplied. At capture there is no caller and no parameter
     * list to consult, so the form alone has to decide.
     *
     * That makes the pattern the only guard, and it is deliberately narrow:
     * the span between the two signs must look like an identifier, so ordinary
     * copy is untouched — `Save 20% on 5% APR` has spaces between its signs and
     * `width: 100%` has no closing sign. The residual case is prose where two
     * percent signs bracket a bare word with no spaces (`100%cotton%blend`),
     * which is rewritten. Accepted knowingly: converging with the core's stored
     * form matters more than that shape, and the alternative — diverging ids on
     * every phrase carrying a real placeholder — is the commoner harm.
     *
     * @param string $text
     * @return string
     */
    public static function placeholders($text)
    {
        $result = preg_replace('/%([A-Za-z_][A-Za-z0-9_]*)%/', '{$1}', (string) $text);

        return $result === null ? (string) $text : $result;
    }
}
