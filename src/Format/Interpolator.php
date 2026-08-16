<?php

namespace Langsys\SDK\Format;

use Langsys\SDK\Log\LoggerInterface;

/**
 * Interpolates {placeholder} values into translated strings.
 *
 * Behaviour is aligned with the Langsys JS SDK so that a single shared catalog
 * renders identically from a PHP backend and a JS frontend:
 *
 * - Unknown keys are left VERBATIM (including braces), never blanked. A missing
 *   value must be visible to the developer rather than silently rendering empty.
 *   The same applies to a key that is present but null.
 * - Whitespace inside braces is tolerated: `{ name }` resolves the key `name`.
 * - Numbers are formatted per the target locale's CLDR rules (1234.5 renders as
 *   "1.234,5" in de-DE). Pass a value as a string to opt out of grouping - the
 *   documented escape hatch for IDs, codes and version numbers.
 * - Dates are formatted with the locale's medium date style.
 * - Booleans render as "true"/"false" to match JS String(bool), not PHP's "1"/"".
 * - ICU MessageFormat ({n, plural, one {...} other {...}}) is detected and
 *   formatted with ext-intl so plural rules are correct per language.
 *
 * Failure handling is deliberately non-fatal in both directions:
 * - If a string uses ICU syntax but ext-intl is unavailable at runtime, a
 *   warning naming the phrase and the missing extension is logged once, and the
 *   string falls back to simple substitution. ext-intl is a hard composer
 *   requirement, so this only occurs when intl is disabled after install; the
 *   point is that the operator is told exactly why, rather than discovering it
 *   as a quietly mistranslated plural.
 * - If ICU parsing fails, we fall back to simple substitution rather than
 *   throwing. A visibly-imperfect string beats a crash mid-render, and backend
 *   validation is meant to stop malformed ICU reaching us in the first place.
 */
class Interpolator
{
    /**
     * Detects ICU MessageFormat argument slots.
     *
     * Deliberately matches style-less forms such as `{n, number}` as well as
     * `{n, plural, ...}`. Kept character-for-character in sync with the JS SDK.
     */
    const ICU_PATTERN = '/\{[^{}]+,\s*(plural|select|selectordinal|number|date|time)\s*[,}]/';

    /**
     * Matches a simple placeholder and captures its (untrimmed) key.
     */
    const PLACEHOLDER_PATTERN = '/\{([^{}]*)\}/';

    /**
     * Locale used for ICU formatting when no target locale is known.
     */
    const FALLBACK_LOCALE = 'en';

    /**
     * Maximum ICU nesting depth handled by the no-intl fallback.
     */
    const MAX_ICU_DEPTH = 24;

    /**
     * @var LoggerInterface|null
     */
    protected $logger;

    /**
     * @var bool Whether the missing-intl warning has already been emitted.
     */
    protected $warnedMissingIntl = false;

    /**
     * @param LoggerInterface|null $logger
     */
    public function __construct($logger = null)
    {
        $this->logger = $logger;
    }

    /**
     * Interpolate parameters into a string.
     *
     * @param string $text The (already translated) string
     * @param array $params Map of placeholder name => value
     * @param string|null $locale Target locale, drives plural rules and formatting
     * @return string
     */
    public function interpolate($text, array $params = [], $locale = null)
    {
        if (!is_string($text) || $text === '' || empty($params)) {
            return $text;
        }

        if (strpos($text, '{') === false) {
            return $text;
        }

        if ($this->hasIcuSyntax($text)) {
            if (!$this->hasIntl()) {
                // Simple substitution CANNOT handle an ICU construct - its
                // pattern can't match a slot containing commas and braces - so
                // the raw MessageFormat source would be emitted to the page.
                // For a localization product that is worse than an untranslated
                // phrase, which at least reads as a sentence.
                $this->warnMissingIntl($text);

                return $this->renderIcuWithoutIntl($text, $params, $locale);
            }

            // A well-formed pattern whose ARGUMENT is missing is not a parse
            // failure, so MessageFormatter neither throws nor returns false - it
            // echoes a bare "{argName}" and destroys the surrounding sentence.
            // The malformed-ICU fallback below is therefore never reached. Route
            // these to the branch-selecting renderer instead, which keeps the
            // sentence and makes the gap visible.
            //
            // Reachable without any caller error: the backend promotes a plain
            // {name} into {name_gender, select, ...} for gendered target locales,
            // so the argument does not exist in the source phrase the developer
            // wrote and nothing tells them the target grew one.
            if ($this->missingIcuArguments($text, $params)) {
                return $this->renderIcuWithoutIntl($text, $params, $locale);
            }

            $formatted = $this->formatIcu($text, $params, $locale);
            if ($formatted !== null) {
                return $formatted;
            }

            // Parseable ICU failed: fall through to simple substitution, which
            // leaves the construct untouched. This matches the JS SDK exactly
            // and is deliberate - see testMalformedIcuFallsBackToTemplateUnchanged.
        }

        return $this->substitute($text, $params, $locale);
    }

    /**
     * Whether a string contains ICU MessageFormat argument slots.
     *
     * @param string $text
     * @return bool
     */
    public function hasIcuSyntax($text)
    {
        return is_string($text) && preg_match(self::ICU_PATTERN, $text) === 1;
    }

    /**
     * Whether a string contains any simple placeholder.
     *
     * @param string $text
     * @return bool
     */
    public function hasPlaceholders($text)
    {
        return is_string($text) && preg_match(self::PLACEHOLDER_PATTERN, $text) === 1;
    }

    /**
     * Format a string via ICU MessageFormat.
     *
     * @param string $text
     * @param array $params
     * @param string|null $locale
     * @return string|null Null when ICU could not be applied (caller falls back)
     */
    protected function formatIcu($text, array $params, $locale)
    {
        if (!$this->hasIntl()) {
            $this->warnMissingIntl($text);
            return null;
        }

        $icuLocale = $this->resolveLocale($locale);

        // MessageFormatter wants scalars/timestamps; DateTime is not accepted.
        $icuParams = [];
        foreach ($params as $key => $value) {
            $icuParams[$key] = $this->toIcuValue($value);
        }

        // intl can THROW rather than return a falsy value: MessageFormatter
        // raises IntlException when intl.use_exceptions is On, and @ does not
        // suppress exceptions. Escaping here would break the render, which this
        // class promises never to do.
        try {
            $formatter = @\MessageFormatter::create($icuLocale, $text);

            // create() returns null on some versions and false on others.
            if (!$formatter) {
                $this->log('warning', 'ICU pattern could not be parsed; falling back to simple interpolation', [
                    'phrase' => $text,
                    'locale' => $icuLocale,
                    'intl_error' => intl_get_error_message(),
                ]);
                return null;
            }

            $result = @$formatter->format($icuParams);

            if ($result === false) {
                $this->log('warning', 'ICU formatting failed; falling back to simple interpolation', [
                    'phrase' => $text,
                    'locale' => $icuLocale,
                    'intl_error' => $formatter->getErrorMessage(),
                ]);
                return null;
            }

            return $result;
        } catch (\Throwable $e) {
            $this->log('warning', 'ICU formatting threw; falling back to simple interpolation', [
                'phrase' => $text,
                'locale' => $icuLocale,
                'exception' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * ICU argument names referenced by the pattern but absent from $params.
     *
     * @param string $text
     * @param array $params
     * @return array
     */
    protected function missingIcuArguments($text, array $params)
    {
        if (!preg_match_all('/\{\s*([A-Za-z_][A-Za-z0-9_]*)\s*,\s*(?:plural|select|selectordinal|number|date|time)\s*[,}]/', $text, $m)) {
            return [];
        }

        $missing = [];

        foreach ($m[1] as $name) {
            if (!array_key_exists($name, $params) || $params[$name] === null) {
                $missing[$name] = $name;
            }
        }

        return array_values($missing);
    }

    /**
     * Best-effort ICU rendering without ext-intl.
     *
     * Produces a READABLE SENTENCE rather than raw MessageFormat source. It is
     * not CLDR-correct - that is precisely why ext-intl is a hard requirement -
     * but every language ends up with prose instead of visible markup.
     *
     * Branch selection, in order:
     *   1. An exact `=N` branch matching the value (standards-correct).
     *   2. `one` when the value is exactly 1 AND the translator supplied a
     *      `one` branch. Across CLDR languages that define `one`, n=1 falls in
     *      it, so this is right far more often than `other` would be.
     *   3. `other`, the category every plural form is required to provide.
     *
     * For `select`, the branch matching the parameter value is used, else
     * `other`. `#` inside the chosen branch becomes the formatted value.
     *
     * @param string $text
     * @param array $params
     * @param string|null $locale
     * @return string
     */
    protected function renderIcuWithoutIntl($text, array $params, $locale, $hash = null, $depth = 0)
    {
        // Bail on pathological nesting rather than exhausting memory. Catalog
        // content is not caller-controlled, but a deeply nested pattern would
        // otherwise take the whole render down with an uncatchable fatal.
        if ($depth > self::MAX_ICU_DEPTH) {
            return $text;
        }

        $out = '';
        $length = strlen($text);
        $i = 0;

        while ($i < $length) {
            $char = $text[$i];

            // '#' is the enclosing plural's value. Emitted HERE during the walk
            // rather than substituted into the branch before recursing: doing it
            // by substitution let a value that itself looked like ICU be
            // re-scanned (unbounded recursion), and let an outer '#' overwrite a
            // nested plural's own '#'.
            if ($char === '#' && $hash !== null) {
                $out .= $hash;
                $i++;
                continue;
            }

            if ($char !== '{') {
                $out .= $char;
                $i++;
                continue;
            }

            $end = $this->matchingBrace($text, $i);

            if ($end === null) {
                // Unbalanced - emit verbatim rather than looping forever.
                $out .= substr($text, $i);
                break;
            }

            $out .= $this->renderIcuArgument(
                substr($text, $i + 1, $end - $i - 1),
                $params,
                $locale,
                $depth
            );

            $i = $end + 1;
        }

        return $out;
    }

    /**
     * Render one ICU argument body (the text between its outer braces).
     *
     * @param string $body
     * @param array $params
     * @param string|null $locale
     * @return string
     */
    protected function renderIcuArgument($body, array $params, $locale, $depth = 0)
    {
        $verbatim = '{' . $body . '}';

        $comma = strpos($body, ',');

        // No comma: an ordinary {key} placeholder.
        if ($comma === false) {
            $key = trim($body);

            if ($key === '' || !array_key_exists($key, $params) || $params[$key] === null) {
                return $verbatim;
            }

            $formatted = $this->formatValue($params[$key], $locale);

            return $formatted === null ? $verbatim : $formatted;
        }

        $name = trim(substr($body, 0, $comma));
        $rest = substr($body, $comma + 1);

        $secondComma = strpos($rest, ',');
        $type = strtolower(trim($secondComma === false ? $rest : substr($rest, 0, $secondComma)));

        $missing = !array_key_exists($name, $params) || $params[$name] === null;

        if ($missing && !in_array($type, ['plural', 'selectordinal', 'select'], true)) {
            return $verbatim; // {v, number} etc: nothing sensible to render.
        }

        $value = $missing ? null : $params[$name];

        // Style-less forms: {v, number}, {d, date}, {t, time}.
        if (in_array($type, ['number', 'date', 'time'], true)) {
            $formatted = $this->formatValue($value, $locale);

            return $formatted === null ? $verbatim : $formatted;
        }

        if (!in_array($type, ['plural', 'selectordinal', 'select'], true)) {
            return $verbatim;
        }

        if ($secondComma === false) {
            return $verbatim; // Declared a branch type but supplied no branches.
        }

        $branches = $this->parseIcuBranches(substr($rest, $secondComma + 1));

        if (empty($branches)) {
            return $verbatim;
        }

        // A missing argument selects `other`, which every plural and select is
        // required to provide. For select that yields a CORRECT sentence - the
        // neutral branch is exactly what an unknown gender should render. For
        // plural nothing can be inferred, so the count itself stays visible as
        // {name} while the sentence around it survives, which beats both
        // destroying the sentence and dumping the pattern.
        $chosen = $missing
            ? (isset($branches['other']) ? $branches['other'] : null)
            : $this->chooseIcuBranch($type, $value, $branches);

        if ($chosen === null) {
            return $verbatim;
        }

        // The branch is walked with THIS argument's value as its '#'. A nested
        // plural inside it supplies its own, so the outer value cannot clobber
        // the inner one, and a value that happens to contain '#' or braces is
        // never re-scanned.
        $number = $missing ? '{' . $name . '}' : $this->formatValue($value, $locale);

        return $this->renderIcuWithoutIntl(
            $chosen,
            $params,
            $locale,
            $number === null ? '' : $number,
            $depth + 1
        );
    }

    /**
     * Split an ICU branch list into keyword => content.
     *
     * @param string $text e.g. "one {# item} other {# items}"
     * @return array
     */
    protected function parseIcuBranches($text)
    {
        $branches = [];
        $length = strlen($text);
        $i = 0;

        while ($i < $length) {
            // Read the keyword up to its opening brace.
            while ($i < $length && $text[$i] !== '{') {
                $i++;
            }

            if ($i >= $length) {
                break;
            }

            $keyword = trim(substr($text, 0, $i));

            // A plural may carry an `offset:N` prefix before its branches;
            // without stripping it the first branch is keyed "offset:1 one".
            if (preg_match('/^offset\s*:\s*\S+\s+(.*)$/s', $keyword, $m)) {
                $keyword = trim($m[1]);
            }

            $end = $this->matchingBrace($text, $i);

            if ($end === null) {
                break;
            }

            if ($keyword !== '') {
                $branches[$keyword] = substr($text, $i + 1, $end - $i - 1);
            }

            // Continue with whatever follows this branch.
            $text = substr($text, $end + 1);
            $length = strlen($text);
            $i = 0;
        }

        return $branches;
    }

    /**
     * Pick a branch without CLDR plural rules.
     *
     * @param string $type
     * @param mixed $value
     * @param array $branches
     * @return string|null
     */
    protected function chooseIcuBranch($type, $value, array $branches)
    {
        if ($type === 'select') {
            $key = is_scalar($value) ? (string) $value : '';

            if (isset($branches[$key])) {
                return $branches[$key];
            }

            return isset($branches['other']) ? $branches['other'] : null;
        }

        // Exact matches are standards-correct and always safe.
        if (is_numeric($value)) {
            $number = (float) $value;

            foreach ($branches as $keyword => $content) {
                // Compare numerically so "=1" matches 1, "1.0" and "1".
                if (strlen($keyword) > 1 && $keyword[0] === '='
                    && is_numeric(substr($keyword, 1))
                    && (float) substr($keyword, 1) === $number) {
                    return $content;
                }
            }

            // Loose compare: 1, 1.0 and "1.0" must all take the `one` branch.
            if ($number === 1.0 && isset($branches['one'])) {
                return $branches['one'];
            }
        }

        if (isset($branches['other'])) {
            return $branches['other'];
        }

        // No 'other' (technically invalid ICU) - take whatever exists.
        return reset($branches) === false ? null : reset($branches);
    }

    /**
     * Find the '}' matching the '{' at $start, honouring nesting.
     *
     * @param string $text
     * @param int $start
     * @return int|null
     */
    protected function matchingBrace($text, $start)
    {
        $depth = 0;
        $length = strlen($text);

        for ($i = $start; $i < $length; $i++) {
            if ($text[$i] === '{') {
                $depth++;
                continue;
            }

            if ($text[$i] === '}') {
                $depth--;

                if ($depth === 0) {
                    return $i;
                }
            }
        }

        return null;
    }

    /**
     * Replace simple {key} placeholders.
     *
     * Uses preg_replace_callback rather than preg_replace on purpose: with a
     * replacement STRING, PHP interprets $1 / \1 inside the replacement as
     * backreferences, so any translation or parameter value containing a dollar
     * sign (a price like "$5", or a stray "$&") gets mangled. Returning the
     * value from a callback sidesteps that entirely.
     *
     * @param string $text
     * @param array $params
     * @param string|null $locale
     * @return string
     */
    protected function substitute($text, array $params, $locale)
    {
        $self = $this;

        $result = preg_replace_callback(
            self::PLACEHOLDER_PATTERN,
            function ($matches) use ($params, $locale, $self) {
                $key = trim($matches[1]);

                if ($key === '' || !array_key_exists($key, $params)) {
                    return $matches[0]; // Unknown key - leave verbatim.
                }

                $value = $params[$key];

                if ($value === null) {
                    return $matches[0]; // Present but null - also left visible.
                }

                $formatted = $self->formatValue($value, $locale);

                return $formatted === null ? $matches[0] : $formatted;
            },
            $text
        );

        // preg_replace_callback returns null on PCRE failure (e.g. backtrack
        // limit on a pathological string); never hand back null to a renderer.
        return $result === null ? $text : $result;
    }

    /**
     * Format a single parameter value for output.
     *
     * Public so the substitution callback can reach it on PHP 7.4 without
     * binding gymnastics.
     *
     * @param mixed $value
     * @param string|null $locale
     * @return string|null Null when the value has no sensible string form
     */
    public function formatValue($value, $locale = null)
    {
        // Strings pass through untouched - this is the documented opt-out from
        // locale number formatting.
        if (is_string($value)) {
            return $value;
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_int($value) || is_float($value)) {
            return $this->formatNumber($value, $locale);
        }

        if ($value instanceof \DateTimeInterface) {
            return $this->formatDate($value, $locale);
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        // Arrays, resources, objects without __toString: no sensible rendering.
        return null;
    }

    /**
     * Format a number per the locale's CLDR rules.
     *
     * @param int|float $value
     * @param string|null $locale
     * @return string
     */
    protected function formatNumber($value, $locale)
    {
        if (!$this->hasIntl()) {
            return (string) $value;
        }

        try {
            $formatter = @\NumberFormatter::create($this->resolveLocale($locale), \NumberFormatter::DECIMAL);

            // Returns null on some versions, false on others.
            if (!$formatter) {
                return (string) $value;
            }

            $formatted = @$formatter->format($value);

            return $formatted === false ? (string) $value : $formatted;
        } catch (\Throwable $e) {
            return (string) $value;
        }
    }

    /**
     * Format a date with the locale's medium date style.
     *
     * @param \DateTimeInterface $value
     * @param string|null $locale
     * @return string
     */
    protected function formatDate(\DateTimeInterface $value, $locale)
    {
        if (!$this->hasIntl()) {
            return $value->format('Y-m-d');
        }

        // IntlDateFormatter's CONSTRUCTOR throws on PHP 8+ for a bad locale;
        // @ cannot suppress an exception, so this must be caught explicitly.
        try {
            $formatter = new \IntlDateFormatter(
                $this->resolveLocale($locale),
                \IntlDateFormatter::MEDIUM,
                \IntlDateFormatter::NONE
            );

            $formatted = @$formatter->format($value);

            return $formatted === false ? $value->format('Y-m-d') : $formatted;
        } catch (\Throwable $e) {
            return $value->format('Y-m-d');
        }
    }

    /**
     * Convert a value into something MessageFormatter accepts.
     *
     * @param mixed $value
     * @return mixed
     */
    protected function toIcuValue($value)
    {
        if ($value instanceof \DateTimeInterface) {
            return $value->getTimestamp();
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_object($value) && method_exists($value, '__toString')) {
            return (string) $value;
        }

        return $value;
    }

    /**
     * @param string|null $locale
     * @return string
     */
    protected function resolveLocale($locale)
    {
        if (!is_string($locale) || trim($locale) === '') {
            return self::FALLBACK_LOCALE;
        }

        return $locale;
    }

    /**
     * @return bool
     */
    protected function hasIntl()
    {
        return class_exists('\MessageFormatter');
    }

    /**
     * Warn once per instance that ICU content cannot be formatted.
     *
     * @param string $text
     * @return void
     */
    protected function warnMissingIntl($text)
    {
        if ($this->warnedMissingIntl) {
            return;
        }

        $this->warnedMissingIntl = true;

        $this->log('warning', 'Phrase uses ICU MessageFormat but ext-intl is not loaded; plural and select rules will not be applied', [
            'phrase' => $text,
            'missing_extension' => 'intl',
            'fallback' => 'simple placeholder substitution',
        ]);
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function log($level, $message, array $context = [])
    {
        if ($this->logger instanceof LoggerInterface) {
            $this->logger->log($level, $message, $context);
        }
    }
}
