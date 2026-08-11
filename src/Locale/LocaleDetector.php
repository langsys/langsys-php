<?php

namespace Langsys\SDK\Locale;

/**
 * Utility class for detecting and normalizing locale codes.
 *
 * Provides browser-based locale detection from HTTP_ACCEPT_LANGUAGE header
 * and normalization of various locale formats.
 */
class LocaleDetector
{
    /**
     * Detect locale from browser HTTP_ACCEPT_LANGUAGE header.
     *
     * Detection algorithm:
     * 1. Try locale_accept_from_http() (ext-intl), which honours q-values
     * 2. Otherwise parse the header ourselves, also honouring q-values
     * 3. Either way, fill in a missing region ("en" -> "en-en"), because the
     *    Langsys API addresses translations by xx-yy locale codes
     *
     * Both paths deliberately produce the SAME result for a given header.
     * They used to diverge - the intl path returned a bare "en" while the
     * fallback synthesised "en-en", and the fallback picked whichever full
     * locale appeared in the string regardless of priority, so "en,es-MX;q=0.9"
     * resolved to "es-mx" even though "en" has an implicit q=1 and outranks it.
     * That meant the same visitor could be served a different language
     * depending on whether the host had ext-intl loaded.
     *
     * @return string|null Locale in "xx-yy" format, or null if unable to detect
     */
    public static function fromBrowser()
    {
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return null;
        }

        return self::fromAcceptLanguage($_SERVER['HTTP_ACCEPT_LANGUAGE']);
    }

    /**
     * Detect locale from an explicit Accept-Language header value.
     *
     * Same semantics as fromBrowser(), but takes the header directly instead of
     * reading $_SERVER - so a framework can pass its own request header without
     * having to fake superglobals.
     *
     * @param string $acceptLanguage Raw Accept-Language header value
     * @return string|null Locale in "xx-yy" format, or null if unable to detect
     */
    public static function fromAcceptLanguage($acceptLanguage)
    {
        if (!is_string($acceptLanguage) || trim($acceptLanguage) === '') {
            return null;
        }

        // Preferred: ext-intl, which implements RFC 4647 lookup properly.
        if (function_exists('locale_accept_from_http')) {
            $locale = locale_accept_from_http($acceptLanguage);
            if (!empty($locale) && preg_match('/^[a-z]{2}([_-][a-z]{2})?$/i', $locale)) {
                return self::withRegion(self::normalize($locale));
            }
        }

        // Fallback for hosts without ext-intl. Mirrors the above rather than
        // using a looser "first locale-shaped thing in the string" match.
        $preferred = self::highestPriorityLanguage($acceptLanguage);
        if ($preferred !== null) {
            return self::withRegion($preferred);
        }

        return null;
    }

    /**
     * Pick the highest-priority language tag from an Accept-Language header.
     *
     * Entries are "tag" or "tag;q=0.8"; a missing q defaults to 1. Ties keep
     * the order the browser sent, which is the documented tie-break.
     *
     * @param string $acceptLanguage Raw header value
     * @return string|null Normalized tag ("en", "es-mx"), or null if none parse
     */
    protected static function highestPriorityLanguage($acceptLanguage)
    {
        $best = null;

        // Starts at 0.0, not -1.0: q=0 means "not acceptable" (RFC 7231), so an
        // entry at q=0 must never be selected even if it is the only one.
        $bestQuality = 0.0;

        foreach (explode(',', $acceptLanguage) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $bits = explode(';', $part);
            $tag = trim($bits[0]);

            if (!preg_match('/^[a-z]{2}([_-][a-z]{2})?$/i', $tag)) {
                continue; // Ignore wildcards and anything not a simple tag.
            }

            $quality = self::parseQuality($bits);

            if ($quality === null) {
                continue; // Malformed or out-of-range q - discard the entry.
            }

            // Strictly greater, so an earlier entry wins an exact tie.
            if ($quality > $bestQuality) {
                $bestQuality = $quality;
                $best = self::normalize($tag);
            }
        }

        return $best;
    }

    /**
     * Read the quality value from an entry's parameters.
     *
     * A missing q defaults to 1. A q that is present but malformed or outside
     * 0..1 makes the whole entry unusable - silently treating "q=abc" as the
     * default 1 would promote a broken entry to top priority, which is the
     * opposite of what the sender meant.
     *
     * @param array $bits Entry split on ';'
     * @return float|null Null when the entry should be discarded
     */
    protected static function parseQuality(array $bits)
    {
        $quality = 1.0;

        for ($i = 1; $i < count($bits); $i++) {
            if (strpos($bits[$i], '=') === false || stripos(trim($bits[$i]), 'q') !== 0) {
                continue; // Some other parameter, e.g. an extension.
            }

            if (!preg_match('/^\s*q\s*=\s*(0(\.\d{1,3})?|1(\.0{1,3})?)\s*$/i', $bits[$i], $m)) {
                return null;
            }

            $quality = (float) $m[1];
            break;
        }

        return $quality;
    }

    /**
     * Fill in a missing region, assuming country matches language.
     *
     * The Langsys API addresses translations by xx-yy codes, so a bare "en"
     * would not match a project locale. "en" -> "en-en".
     *
     * @param string $locale
     * @return string
     */
    protected static function withRegion($locale)
    {
        if ($locale === '' || strpos($locale, '-') !== false) {
            return $locale;
        }

        return $locale . '-' . $locale;
    }

    /**
     * Normalize a locale string to lowercase with hyphen separator.
     *
     * Converts various formats to standard "xx-yy" format:
     * - "en_US" -> "en-us"
     * - "en-US" -> "en-us"
     * - "EN-US" -> "en-us"
     * - "en" -> "en" (unchanged if no region)
     *
     * @param string $locale The locale to normalize
     * @return string Normalized locale in lowercase with hyphen
     */
    public static function normalize($locale)
    {
        if (empty($locale)) {
            return $locale;
        }

        // Replace underscore with hyphen and convert to lowercase
        $normalized = strtolower(str_replace('_', '-', $locale));

        return $normalized;
    }

    /**
     * Convert locale to OpenGraph format.
     *
     * OpenGraph uses underscore separator with uppercase country:
     * - "es-es" -> "es_ES"
     * - "en-us" -> "en_US"
     *
     * @param string $locale The locale in standard format (xx-yy)
     * @return string Locale in OpenGraph format (xx_YY)
     */
    public static function toOpenGraphFormat($locale)
    {
        if (empty($locale)) {
            return $locale;
        }

        $parts = explode('-', strtolower($locale));

        if (count($parts) === 2) {
            return $parts[0] . '_' . strtoupper($parts[1]);
        }

        // If only language code, assume country matches
        if (count($parts) === 1 && strlen($parts[0]) === 2) {
            return $parts[0] . '_' . strtoupper($parts[0]);
        }

        return $locale;
    }

    /**
     * Extract language code from locale.
     *
     * @param string $locale The locale (e.g., "en-us", "es-es")
     * @return string The language code (e.g., "en", "es")
     */
    public static function getLanguageCode($locale)
    {
        if (empty($locale)) {
            return '';
        }

        $parts = explode('-', strtolower(str_replace('_', '-', $locale)));
        return $parts[0];
    }

    /**
     * Extract country/region code from locale.
     *
     * @param string $locale The locale (e.g., "en-us", "es-es")
     * @return string|null The country code (e.g., "us", "es") or null if not present
     */
    public static function getCountryCode($locale)
    {
        if (empty($locale)) {
            return null;
        }

        $parts = explode('-', strtolower(str_replace('_', '-', $locale)));

        if (count($parts) >= 2) {
            return $parts[1];
        }

        return null;
    }
}
