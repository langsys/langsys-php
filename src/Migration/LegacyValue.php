<?php

namespace Langsys\SDK\Migration;

/**
 * Turns a legacy i18n value into Langsys/ICU source text (MIG-4).
 *
 * Placeholders `{{name}}`, `{name}` and Laravel's `:name` become `{name}`.
 * Plurals - Laravel `|` strings with `{N}` and `[M,*]` ranges, vue-i18n ` | `
 * pipes, i18next `_one`/`_other` key pairs - become one ICU `plural` over
 * `count`, with the number written `#`, so they render through the catalog's
 * ICU like any other phrase.
 *
 * Anything this table does not recognise is returned exactly as written, with
 * `recognised` false and the reason in `issue`, so an unhandled form is
 * registered verbatim and reported rather than silently mangled.
 */
final class LegacyValue
{
    /** CLDR plural categories, in ICU's customary order. */
    const CATEGORIES = ['zero', 'one', 'two', 'few', 'many', 'other'];

    /**
     * @param string $value
     * @return array{text: string, recognised: bool, issue: string|null}
     */
    public static function convert($value)
    {
        $value = (string) $value;

        if (self::hasCaseTransform($value)) {
            return self::unrecognised($value, 'uses a case-transforming placeholder (:Name or :NAME), which ICU cannot express');
        }

        if (strpos($value, '|') !== false) {
            return self::convertPipes($value);
        }

        return ['text' => self::placeholders($value), 'recognised' => true, 'issue' => null];
    }

    /**
     * One ICU plural from forms keyed by CLDR category, such as the values of
     * i18next's `key_one` / `key_other`.
     *
     * @param array<string, string> $forms
     * @return array{text: string, recognised: bool, issue: string|null}
     */
    public static function fromPluralForms(array $forms)
    {
        $raw = implode(' | ', $forms);

        if (!isset($forms['other'])) {
            return self::unrecognised($raw, 'is a plural with no "other" form');
        }

        $branches = [];

        foreach (self::CATEGORIES as $category) {
            if (!isset($forms[$category])) {
                continue;
            }

            if (self::hasCaseTransform((string) $forms[$category])) {
                return self::unrecognised($raw, 'uses a case-transforming placeholder (:Name or :NAME), which ICU cannot express');
            }

            $branches[] = [$category, (string) $forms[$category]];
        }

        return ['text' => self::plural($branches), 'recognised' => true, 'issue' => null];
    }

    /**
     * @param string $value
     * @return array
     */
    private static function convertPipes($value)
    {
        $segments = array_map('trim', explode('|', $value));
        $ranged = false;

        foreach ($segments as $segment) {
            if (preg_match('/^(\{\d+\}|\[\d+,(\d+|\*)\])/', $segment)) {
                $ranged = true;
            }
        }

        if ($ranged) {
            return self::convertRanges($value, $segments);
        }

        if (strpos($value, ' | ') !== false) {
            // vue-i18n selects by count, not by locale: with two forms 1 picks
            // the first and anything else the second; with three, 0, 1, then the
            // rest. Exact values keep that selection in every source language,
            // where CLDR `one` would not (in French it covers 0).
            if (count($segments) === 2) {
                return ['text' => self::plural([['=1', $segments[0]], ['other', $segments[1]]]), 'recognised' => true, 'issue' => null];
            }

            if (count($segments) === 3) {
                return ['text' => self::plural([['=0', $segments[0]], ['=1', $segments[1]], ['other', $segments[2]]]), 'recognised' => true, 'issue' => null];
            }

            return self::unrecognised($value, 'is a pipe plural with ' . count($segments) . ' forms; only 2 or 3 map to ICU');
        }

        // Laravel without ranges selects by locale, so it maps to CLDR categories
        // - but only when both forms carry `:count`. Laravel treats a pipe as a
        // plural only through trans_choice, and `apple|apples` cannot be told from
        // text that happens to hold a pipe.
        if (count($segments) === 2 && self::hasCount($segments[0]) && self::hasCount($segments[1])) {
            return ['text' => self::plural([['one', $segments[0]], ['other', $segments[1]]]), 'recognised' => true, 'issue' => null];
        }

        return self::unrecognised($value, 'holds a "|" that is not a recognised plural form');
    }

    /**
     * Laravel ranges: exact `{N}` forms covering every count below M, then one
     * final `[M,*]`. Anything else - `[2,19]`, a count left uncovered, an open
     * range that is not last - has no exact ICU equivalent. Exact branches are
     * written in ascending order, since the string is the phrase's identity.
     *
     * @param string $value
     * @param string[] $segments
     * @return array
     */
    private static function convertRanges($value, array $segments)
    {
        $exact = [];
        $other = null;
        $last = count($segments) - 1;

        foreach ($segments as $index => $segment) {
            if (preg_match('/^\{(\d+)\}\s*(.*)$/s', $segment, $m) && !isset($exact[(int) $m[1]])) {
                $exact[(int) $m[1]] = $m[2];
                continue;
            }

            if ($index === $last && preg_match('/^\[(\d+),\*\]\s*(.*)$/s', $segment, $m)) {
                $other = [(int) $m[1], $m[2]];
                continue;
            }

            return self::unrecognised($value, 'uses plural ranges that do not map exactly to ICU; rewrite it as {count, plural, ...}');
        }

        if ($other === null) {
            return self::unrecognised($value, 'is a ranged plural with no open "[N,*]" form');
        }

        ksort($exact);

        if (array_keys($exact) !== ($other[0] > 0 ? range(0, $other[0] - 1) : [])) {
            return self::unrecognised($value, 'is a ranged plural that leaves a count uncovered before its "[N,*]" form');
        }

        $branches = [];

        foreach ($exact as $count => $text) {
            $branches[] = ['=' . $count, $text];
        }

        $branches[] = ['other', $other[1]];

        return ['text' => self::plural($branches), 'recognised' => true, 'issue' => null];
    }

    /**
     * @param array<int, array{0: string, 1: string}> $branches
     * @return string
     */
    private static function plural(array $branches)
    {
        $parts = [];

        foreach ($branches as list($selector, $text)) {
            $parts[] = $selector . ' {' . self::countMarker(self::placeholders($text)) . '}';
        }

        return '{count, plural, ' . implode(' ', $parts) . '}';
    }

    /**
     * @param string $text
     * @return string
     */
    private static function placeholders($text)
    {
        $text = preg_replace('/\{\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*\}\}/', '{$1}', $text);

        return preg_replace('/(?<![\w:]):([a-z][a-z0-9_]*)/', '{$1}', $text);
    }

    /**
     * Inside a plural branch the number is ICU's `#`. Written as `{count}` the
     * pattern renders nothing: measured on ICU 76.1, intl accepts it and formats
     * it to an empty string, where `#` renders the number. vue-i18n's `{n}` is
     * the same number.
     *
     * @param string $text
     * @return string
     */
    private static function countMarker($text)
    {
        return str_replace(['{count}', '{n}'], '#', $text);
    }

    /**
     * @param string $text
     * @return bool
     */
    private static function hasCount($text)
    {
        return preg_match('/(?<![\w:]):count\b|\{\s*\{?\s*(count|n)\s*\}?\s*\}/', $text) === 1;
    }

    /**
     * @param string $text
     * @return bool
     */
    private static function hasCaseTransform($text)
    {
        return preg_match('/(?<![\w:]):[A-Z][A-Za-z0-9_]*/', $text) === 1;
    }

    /**
     * @param string $value
     * @param string $issue
     * @return array
     */
    private static function unrecognised($value, $issue)
    {
        return ['text' => $value, 'recognised' => false, 'issue' => $issue];
    }
}
