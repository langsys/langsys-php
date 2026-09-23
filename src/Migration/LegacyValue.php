<?php

namespace Langsys\SDK\Migration;

/**
 * Turns a legacy i18n value into Langsys/ICU source text (MIG-4).
 *
 * Placeholders convert the same way whatever the file's format: `{{name}}`,
 * `{name}` and Laravel's `:name` become `{name}`. Plurals convert by the format
 * the file declares, because the frameworks read the same characters
 * differently - vue-i18n reserves `|`, Laravel's `__()` treats it as text - and
 * each conversion preserves its framework's own selection:
 *
 * - `laravel`: a pipe string whose every segment carries a range covering
 *   0..N-1 before `[N,*]` becomes exact branches plus `other`; exactly two
 *   segments that both carry `:count` become CLDR `one`/`other`.
 * - `vue-i18n`: any pipe string, by count - `=1`/`other`, or `=0`/`=1`/`other`.
 * - `i18next`: suffix-paired keys, by CLDR category (see fromPluralForms()).
 * - `plain`: no plural conversion; a `|` is text.
 *
 * The plural argument is `count`, written `#` inside every branch. Anything a
 * format does not recognise is returned exactly as written, with `recognised`
 * false and the reason in `issue`, so it is registered verbatim and reported
 * rather than silently mangled.
 *
 * A literal miss - a call whose argument is not a key (MIG-2) - converts by
 * the entry point that received it rather than by a file; see fromCall().
 */
final class LegacyValue
{
    /** CLDR plural categories, in ICU's customary order. */
    const CATEGORIES = ['zero', 'one', 'two', 'few', 'many', 'other'];

    /** The formats a source file can declare (MIG-7). */
    const FORMATS = ['laravel', 'vue-i18n', 'i18next', 'plain'];

    /**
     * @param string $value
     * @param string $format laravel, vue-i18n, i18next or plain
     * @return array{text: string, recognised: bool, issue: string|null}
     */
    public static function convert($value, $format = 'plain')
    {
        $value = (string) $value;

        if (self::hasCaseTransform($value)) {
            return self::unrecognised($value, 'uses a case-transforming placeholder (:Name or :NAME), which ICU cannot express');
        }

        if (strpos($value, '|') === false) {
            return ['text' => self::placeholders($value), 'recognised' => true, 'issue' => null];
        }

        if ($format === 'laravel') {
            return self::convertLaravelPipes($value);
        }

        if ($format === 'vue-i18n') {
            return self::convertVuePipes($value);
        }

        return self::unrecognised($value, $format === 'i18next'
            ? 'holds a "|", which i18next does not read as a plural'
            : 'holds a "|" in a file with no plural format declared; declare the file\'s format to convert it');
    }

    /**
     * Convert the text a Laravel call passed when it is not a key (MIG-2), by
     * that entry point's own behaviour, so a legacy call and a Langsys call for
     * the same sentence register one phrase:
     *
     * - `__`: only the `:key` placeholders whose key is passed become `{key}`,
     *   substituted as Laravel substitutes them, longest key first. Any other
     *   `:word` and any `|` print as written through `__()`, so they stay.
     * - `trans_choice`: the same, plus the `laravel` plural table, since that is
     *   where Laravel reads `|` as a plural. `count` is the number passed, as
     *   Laravel adds it to the replacements.
     *
     * `:Name` and `:NAME` for a passed key upper-case its value in Laravel,
     * which `{name}` cannot express, so such text is returned as written with
     * `recognised` false. `params` is what to render the phrase with.
     *
     * @param string $text
     * @param array $params The replacements the call passed
     * @param string $entryPoint `__` or `trans_choice`
     * @param int|float|null $count trans_choice's number
     * @return array{text: string, params: array, recognised: bool, issue: string|null}
     */
    public static function fromCall($text, array $params = [], $entryPoint = '__', $count = null)
    {
        if ($entryPoint !== '__' && $entryPoint !== 'trans_choice') {
            throw new \InvalidArgumentException('Unknown entry point "' . $entryPoint . '"; expected __ or trans_choice');
        }

        $text = (string) $text;

        if ($entryPoint === 'trans_choice') {
            $params['count'] = $count;
        }

        $keys = array_values(array_filter(array_keys($params), 'is_string'));

        foreach ($keys as $key) {
            foreach ([ucfirst($key), strtoupper($key)] as $cased) {
                if ($cased !== $key && strpos($text, ':' . $cased) !== false) {
                    return self::unrecognised($text, 'uses a case-transforming placeholder (:Name or :NAME), which ICU cannot express') + ['params' => $params];
                }
            }

            if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $key) && strpos($text, ':' . $key) !== false) {
                return self::unrecognised($text, 'passes a replacement named "' . $key . '", which is not a valid ICU argument name') + ['params' => $params];
            }
        }

        $convert = function ($segment) use ($keys) {
            return self::passedPlaceholders($segment, $keys);
        };

        $converted = $entryPoint === 'trans_choice' && strpos($text, '|') !== false
            ? self::convertLaravelPipes($text, $convert)
            : ['text' => $convert($text), 'recognised' => true, 'issue' => null];

        return $converted + ['params' => $params];
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
     * Laravel reads a pipe string as a plural only through trans_choice, so a
     * pipe is a plural here only in the two forms that say so unambiguously.
     *
     * @param string $value
     * @return array
     */
    private static function convertLaravelPipes($value, callable $convert = null)
    {
        $segments = array_map('trim', explode('|', $value));

        foreach ($segments as $segment) {
            if (preg_match('/^(\{\d+\}|\[\d+,(\d+|\*)\])/', $segment)) {
                return self::convertRanges($value, $segments, $convert);
            }
        }

        // Laravel without ranges selects by locale, so it maps to CLDR
        // categories - only when both forms carry `:count`, since `apple|apples`
        // cannot be told from text that happens to hold a pipe.
        if (count($segments) === 2 && self::hasCount($segments[0]) && self::hasCount($segments[1])) {
            return ['text' => self::plural([['one', $segments[0]], ['other', $segments[1]]], $convert), 'recognised' => true, 'issue' => null];
        }

        return self::unrecognised($value, 'holds a "|" that Laravel does not read as a plural');
    }

    /**
     * vue-i18n reserves `|` and selects by count, not by locale: with two forms 1
     * picks the first and anything else the second; with three, 0, 1, then the
     * rest. Exact values keep that selection in every source language, where
     * CLDR `one` would not (in French it covers 0).
     *
     * @param string $value
     * @return array
     */
    private static function convertVuePipes($value)
    {
        $segments = array_map('trim', explode('|', $value));

        if (count($segments) === 2) {
            return ['text' => self::plural([['=1', $segments[0]], ['other', $segments[1]]]), 'recognised' => true, 'issue' => null];
        }

        if (count($segments) === 3) {
            return ['text' => self::plural([['=0', $segments[0]], ['=1', $segments[1]], ['other', $segments[2]]]), 'recognised' => true, 'issue' => null];
        }

        return self::unrecognised($value, 'is a vue-i18n plural with ' . count($segments) . ' forms; only 2 or 3 map to ICU');
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
    private static function convertRanges($value, array $segments, callable $convert = null)
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

        return ['text' => self::plural($branches, $convert), 'recognised' => true, 'issue' => null];
    }

    /**
     * @param array<int, array{0: string, 1: string}> $branches
     * @param callable|null $convert Placeholder conversion; every `:word` when null
     * @return string
     */
    private static function plural(array $branches, callable $convert = null)
    {
        $parts = [];

        foreach ($branches as list($selector, $text)) {
            $text = $convert === null ? self::placeholders($text) : $convert($text);
            $parts[] = $selector . ' {' . self::countMarker($text) . '}';
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
     * Laravel's own substitution for the keys a call passed: `:key` becomes
     * `{key}` wherever it appears, longest key first, as strtr() matches.
     *
     * @param string $text
     * @param string[] $keys
     * @return string
     */
    private static function passedPlaceholders($text, array $keys)
    {
        $map = [];

        foreach ($keys as $key) {
            $map[':' . $key] = '{' . $key . '}';
        }

        return $map === [] ? $text : strtr($text, $map);
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
