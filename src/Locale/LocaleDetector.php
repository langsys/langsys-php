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
     * 1. Try locale_accept_from_http() if the Intl extension is available
     * 2. Otherwise parse HTTP_ACCEPT_LANGUAGE ourselves, honouring q-values
     *
     * Both paths return the same answer for the same header. A header with no
     * region yields the bare language tag ("en" -> "en"), never a fabricated
     * region: assuming the country matches the language happens to work for
     * es-ES and fr-FR, but invents en-EN, ja-JA, zh-ZH and uk-UK, and uk-UK in
     * particular reads as United Kingdom when it means Ukrainian. A bare
     * language tag is valid BCP 47 and lets the server do its own matching.
     *
     * @return string|null Locale in "xx-yy" or "xx" format, or null if unable to detect
     */
    public static function fromBrowser()
    {
        if (empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            return null;
        }

        $acceptLanguage = $_SERVER['HTTP_ACCEPT_LANGUAGE'];

        // Try built-in function if Intl extension is available
        if (function_exists('locale_accept_from_http')) {
            $locale = locale_accept_from_http($acceptLanguage);
            if (!empty($locale)) {
                $normalized = self::normalize($locale);
                if (self::isWellFormedTag($normalized)) {
                    return $normalized;
                }
            }
        }

        return self::preferredFromAcceptLanguage($acceptLanguage);
    }

    /**
     * Pick the most-preferred usable tag out of an Accept-Language header.
     *
     * The Intl-free mirror of locale_accept_from_http(): entries are ranked by
     * q-value (absent q means q=1) and ties keep header order, so
     * "en,es-MX;q=0.9" resolves to "en" -- en carries an implicit q=1 and
     * outranks es-MX. Entries with q=0 are explicitly unacceptable per RFC
     * 9110 and are skipped, as are "*" and anything malformed.
     *
     * @param string $header The raw HTTP_ACCEPT_LANGUAGE value
     * @return string|null Normalized locale, or null if nothing usable
     */
    private static function preferredFromAcceptLanguage($header)
    {
        $best = null;
        $bestQuality = 0.0;

        foreach (explode(',', $header) as $entry) {
            $parts = explode(';', trim($entry));
            $tag = self::normalize(trim(array_shift($parts)));

            $quality = 1.0;
            foreach ($parts as $parameter) {
                $parameter = trim($parameter);
                if (stripos($parameter, 'q=') === 0) {
                    $quality = (float) substr($parameter, 2);
                }
            }

            if ($quality <= 0 || $tag === '*' || !self::isWellFormedTag($tag)) {
                continue;
            }

            // Strictly greater, so an earlier entry wins a tie.
            if ($quality > $bestQuality) {
                $best = $tag;
                $bestQuality = $quality;
            }
        }

        return $best;
    }

    /**
     * Whether a normalized tag is one we are willing to hand to the API.
     *
     * Accepts language, language-script and language-region, so "en",
     * "zh-hant" and "es-mx" all pass. Deliberately narrow: the point is to
     * reject junk rather than to mangle it into something plausible-looking.
     *
     * @param string $tag A tag already through normalize()
     * @return bool
     */
    private static function isWellFormedTag($tag)
    {
        return (bool) preg_match('/^[a-z]{2,3}(-[a-z]{4})?(-([a-z]{2}|[0-9]{3}))?$/', $tag);
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
