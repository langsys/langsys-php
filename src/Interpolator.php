<?php

namespace Langsys\SDK;

/**
 * Placeholder interpolation for translated strings.
 *
 * Mirrors the JS SDK's interpolate()/isICU() (langsys-js-typescript
 * src/interpolate.ts) so every Langsys SDK substitutes params identically:
 *
 *   - ICU MessageFormat ({var, plural|select|selectordinal|number|date|time, ...}):
 *     formatted via PHP's intl MessageFormatter when ext-intl is available,
 *     which knows each locale's plural rules and select branches. Malformed ICU
 *     (or a missing ext-intl) falls through to simple interpolation rather than
 *     throwing - a broken-but-visible string instead of a runtime crash.
 *   - Simple {name} interpolation: cheap regex replacement. Unknown placeholders
 *     are left untouched so missing data is visible to the developer rather than
 *     silently rendering empty.
 *
 * PHP 5.6 compatible: no scalar/return type declarations, no null coalescing.
 */
class Interpolator
{
    /**
     * Detect ICU MessageFormat syntax: {var, plural|select|selectordinal|number|date|time, ...}.
     * Does NOT match plain slots like {name} or {count}.
     *
     * @param string $template
     * @return bool
     */
    public static function isICU($template)
    {
        if (!is_string($template) || $template === '') {
            return false;
        }

        // Byte-for-byte the JS SDK's ICU_PATTERN (interpolate.ts:22). The
        // trailing [,}] matters: a style-less slot like "{n, number}" is valid
        // ICU, and requiring a comma there sent it down the simple path, where
        // it rendered as the literal "{n, number}".
        return preg_match('/\{[^{}]+,\s*(plural|select|selectordinal|number|date|time)\s*[,}]/', $template) === 1;
    }

    /**
     * Substitute placeholders in $template with values from $params.
     *
     * @param string $template
     * @param array $params
     * @param string|null $locale Drives ICU plural-rule selection; falls back to 'en'.
     * @return string
     */
    public static function interpolate($template, array $params, $locale = null)
    {
        if ($template === null) {
            return '';
        }

        if (self::isICU($template) && class_exists('MessageFormatter')) {
            $useLocale = ($locale !== null && $locale !== '') ? $locale : 'en';
            // formatMessage() signals a malformed pattern two different ways:
            // false by default, but an IntlException when intl.use_exceptions=1
            // (which '@' does NOT suppress - it only silences diagnostics). Both
            // have to fall through to simple interpolation, or a malformed ICU
            // string in a customer's catalog throws into their render path.
            // Matches the JS SDK's try/catch.
            try {
                $formatted = @\MessageFormatter::formatMessage($useLocale, $template, $params);
                if ($formatted !== false) {
                    return $formatted;
                }
            } catch (\Exception $e) {
                // Fall through.
            }
        }

        return self::simpleInterpolate($template, $params, $locale);
    }

    /**
     * Cheap {name} replacement. The [^{},] class excludes ICU-shaped slots so a
     * malformed ICU string that fell through here isn't mangled further.
     *
     * Numbers and dates get CLDR-formatted output, mirroring what the ICU
     * defaults ({n, number}, {d, date} -> medium) would produce - and matching
     * the JS SDK, so one catalog string renders identically in both.
     *
     * @param string $template
     * @param array $params
     * @param string|null $locale
     * @return string
     */
    private static function simpleInterpolate($template, array $params, $locale = null)
    {
        $useLocale = ($locale !== null && $locale !== '') ? $locale : 'en';

        return preg_replace_callback('/\{([^{},]+)\}/', function ($match) use ($params, $useLocale) {
            $key = trim($match[1]);
            if (!array_key_exists($key, $params)) {
                return $match[0];
            }
            $value = $params[$key];
            if ($value === null) {
                return $match[0];
            }
            if ($value instanceof \DateTimeInterface) {
                return self::formatDate($value, $useLocale);
            }
            // Before the numeric check: is_int() is false for bool, but keeping
            // this first makes the ordering independent of that.
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            if (is_int($value) || is_float($value)) {
                return self::formatNumber($value, $useLocale);
            }
            return (string) $value;
        }, $template);
    }

    /**
     * CLDR medium date, matching JS's
     * Intl.DateTimeFormat(locale, { dateStyle: 'medium' }) - date only, no time.
     *
     * @param \DateTimeInterface $value
     * @param string $locale
     * @return string
     */
    private static function formatDate($value, $locale)
    {
        if (class_exists('IntlDateFormatter')) {
            $formatter = new \IntlDateFormatter($locale, \IntlDateFormatter::MEDIUM, \IntlDateFormatter::NONE);
            $formatted = $formatter->format($value);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        // JS falls back to Date.toISOString() on an invalid locale tag: UTC,
        // milliseconds, 'Z' suffix. format('c') is none of those.
        $utc = new \DateTime('@' . $value->getTimestamp());
        $utc->setTimezone(new \DateTimeZone('UTC'));

        return $utc->format('Y-m-d\TH:i:s') . '.' . substr($value->format('u') . '000', 0, 3) . 'Z';
    }

    /**
     * CLDR number, matching JS's Intl.NumberFormat(locale).format(value).
     *
     * @param int|float $value
     * @param string $locale
     * @return string
     */
    private static function formatNumber($value, $locale)
    {
        if (class_exists('NumberFormatter')) {
            $formatter = new \NumberFormatter($locale, \NumberFormatter::DECIMAL);
            $formatted = $formatter->format($value);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return (string) $value;
    }
}
