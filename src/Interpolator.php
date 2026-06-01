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
        return preg_match('/\{[^{}]+,\s*(plural|select|selectordinal|number|date|time)\s*,/', $template) === 1;
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
        if (self::isICU($template) && class_exists('MessageFormatter')) {
            $useLocale = ($locale !== null && $locale !== '') ? $locale : 'en';
            // formatMessage() returns false on a malformed pattern (and may warn);
            // suppress and fall through to simple interpolation, matching the JS
            // SDK's try/catch defense-in-depth.
            $formatted = @\MessageFormatter::formatMessage($useLocale, $template, $params);
            if ($formatted !== false) {
                return $formatted;
            }
        }

        return self::simpleInterpolate($template, $params);
    }

    /**
     * Cheap {name} replacement. The [^{},] class excludes ICU-shaped slots so a
     * malformed ICU string that fell through here isn't mangled further.
     *
     * @param string $template
     * @param array $params
     * @return string
     */
    private static function simpleInterpolate($template, array $params)
    {
        return preg_replace_callback('/\{([^{},]+)\}/', function ($match) use ($params) {
            $key = trim($match[1]);
            if (!array_key_exists($key, $params)) {
                return $match[0];
            }
            $value = $params[$key];
            if ($value === null) {
                return $match[0];
            }
            if ($value instanceof \DateTimeInterface) {
                // ISO 8601, matching the JS Date.toISOString() behaviour.
                return $value->format('c');
            }
            if (is_bool($value)) {
                return $value ? 'true' : 'false';
            }
            return (string) $value;
        }, $template);
    }
}
