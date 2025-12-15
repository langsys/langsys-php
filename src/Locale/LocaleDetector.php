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
     * 1. Try locale_accept_from_http() if Intl extension available
     * 2. Parse HTTP_ACCEPT_LANGUAGE with regex for full locale (xx-YY or xx_YY)
     * 3. Fallback: use 2-letter language code, assume country = language
     *
     * @return string|null Locale in "xx-yy" format, or null if unable to detect
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
            if ($locale !== null && preg_match('/^[a-z]{2}(_[A-Z]{2})?$/i', $locale)) {
                return self::normalize($locale);
            }
        }

        // Try to extract full locale from the beginning of the string (xx-YY or xx_YY)
        if (preg_match('/^([a-z]{2})[_-]([a-z]{2})/i', $acceptLanguage, $matches)) {
            return strtolower($matches[1]) . '-' . strtolower($matches[2]);
        }

        // Try to extract full locale from anywhere in the string
        if (preg_match('/([a-z]{2})[_-]([a-z]{2})/i', $acceptLanguage, $matches)) {
            return strtolower($matches[1]) . '-' . strtolower($matches[2]);
        }

        // Fallback: use 2-letter language code, assume country matches language
        // This is a reasonable assumption for most common cases (en-en, es-es, etc.)
        $langCode = strtolower(substr($acceptLanguage, 0, 2));
        if (preg_match('/^[a-z]{2}$/', $langCode)) {
            return $langCode . '-' . $langCode;
        }

        return null;
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
