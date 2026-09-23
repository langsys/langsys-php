<?php

namespace Langsys\SDK\Locale;

/**
 * Chooses a request's locale (SRV-6): the first usable candidate from the URL,
 * then a cookie or session value, then `Accept-Language` negotiated against the
 * project's locales, and otherwise the project's base locale.
 *
 * Every candidate is validated against the locales the project serves - its base
 * and target locales. An unsupported one is skipped and resolution falls through
 * to the next source; it is never served. A candidate matches a served locale
 * exactly, or by language when the project serves that language in one region
 * (`es` or `es-mx` for a project serving `es-es`).
 *
 * The result names the `Vary` header the choice requires: `Accept-Language` when
 * the header decided, `Cookie` when a cookie or a cookie-backed session did, and
 * none when the URL did, since the URL is already the cache key.
 *
 * Framework-agnostic: the request is plain data, so a binding passes its own.
 */
final class RequestLocale
{
    const DEFAULTS = [
        // Wiring (BIND-4): where the app keeps the values this rule reads.
        'query_param' => 'locale',
        'cookie' => 'locale',
        'session_key' => 'locale',
        'path' => true,
        'subdomain' => true,
        // An app-supplied `function (array $request)` standing in for the URL
        // and cookie steps, returning a locale, or ['locale' => ..., 'from' =>
        // 'url'|'cookie'] to say which kind of source answered (a bare locale
        // counts as 'cookie'). Its answer is validated like any other.
        'resolver' => null,
    ];

    /**
     * @param string[] $served The project's base and target locales
     * @param string $base The project's base locale
     * @param array $request path, host, query, cookies, session, accept_language
     * @param array $options See DEFAULTS
     * @return array{locale: string|null, source: string, vary: string|null}
     */
    public static function resolve(array $served, $base, array $request, array $options = [])
    {
        $options = array_merge(self::DEFAULTS, $options);
        $served = array_values(array_unique(array_filter(array_map([LocaleDetector::class, 'normalize'], $served))));

        if (is_callable($options['resolver'])) {
            $answer = call_user_func($options['resolver'], $request);
            $from = is_array($answer) && isset($answer['from']) && $answer['from'] === 'url' ? 'url' : 'cookie';
            $matched = self::match(is_array($answer) ? (isset($answer['locale']) ? $answer['locale'] : null) : $answer, $served);
            if ($matched !== null) {
                return ['locale' => $matched, 'source' => $from, 'vary' => $from === 'url' ? null : 'Cookie'];
            }
        } else {
            foreach (self::urlCandidates($request, $options) as $candidate) {
                $matched = self::match($candidate, $served);
                if ($matched !== null) {
                    return ['locale' => $matched, 'source' => 'url', 'vary' => null];
                }
            }

            foreach (self::storedCandidates($request, $options) as $candidate) {
                $matched = self::match($candidate, $served);
                if ($matched !== null) {
                    return ['locale' => $matched, 'source' => 'cookie', 'vary' => 'Cookie'];
                }
            }
        }

        $header = isset($request['accept_language']) ? $request['accept_language'] : null;
        foreach (self::acceptLanguageTags($header) as $tag) {
            $matched = self::match($tag, $served);
            if ($matched !== null) {
                return ['locale' => $matched, 'source' => 'accept-language', 'vary' => 'Accept-Language'];
            }
        }

        // The header was consulted and did not decide, but a different header
        // could have, so the response still varies on it.
        $vary = is_string($header) && trim($header) !== '' ? 'Accept-Language' : null;
        $base = $base === null ? null : LocaleDetector::normalize($base);

        return ['locale' => $base, 'source' => 'base', 'vary' => $vary];
    }

    /**
     * A served locale for a candidate: exact, else the one served locale of the
     * candidate's language.
     *
     * @param mixed $candidate
     * @param string[] $served
     * @return string|null
     */
    public static function match($candidate, array $served)
    {
        if (!is_string($candidate) || !preg_match('/^[a-z]{2,3}([_-][a-z0-9]{2,8})?$/i', trim($candidate))) {
            return null;
        }

        $candidate = LocaleDetector::normalize(trim($candidate));

        if (in_array($candidate, $served, true)) {
            return $candidate;
        }

        $language = explode('-', $candidate)[0];
        $sameLanguage = array_values(array_filter($served, function ($locale) use ($language) {
            return explode('-', $locale)[0] === $language;
        }));

        return count($sameLanguage) === 1 ? $sameLanguage[0] : null;
    }

    /**
     * @return string[]
     */
    private static function urlCandidates(array $request, array $options)
    {
        $candidates = [];

        $query = isset($request['query']) && is_array($request['query']) ? $request['query'] : [];
        if ($options['query_param'] !== null && isset($query[$options['query_param']])) {
            $candidates[] = $query[$options['query_param']];
        }

        if ($options['path'] && isset($request['path']) && is_string($request['path'])) {
            $segments = array_values(array_filter(explode('/', $request['path']), 'strlen'));
            if ($segments !== []) {
                $candidates[] = $segments[0];
            }
        }

        if ($options['subdomain'] && isset($request['host']) && is_string($request['host'])) {
            $labels = explode('.', preg_replace('/:\d+$/', '', $request['host']));
            if (count($labels) > 2) {
                $candidates[] = $labels[0];
            }
        }

        return $candidates;
    }

    /**
     * @return string[]
     */
    private static function storedCandidates(array $request, array $options)
    {
        $candidates = [];

        $cookies = isset($request['cookies']) && is_array($request['cookies']) ? $request['cookies'] : [];
        if ($options['cookie'] !== null && isset($cookies[$options['cookie']])) {
            $candidates[] = $cookies[$options['cookie']];
        }

        $session = isset($request['session']) && is_array($request['session']) ? $request['session'] : [];
        if ($options['session_key'] !== null && isset($session[$options['session_key']])) {
            $candidates[] = $session[$options['session_key']];
        }

        return $candidates;
    }

    /**
     * Accept-Language tags by descending quality, ties in the order sent; q=0
     * and malformed entries dropped.
     *
     * @param string|null $header
     * @return string[]
     */
    private static function acceptLanguageTags($header)
    {
        if (!is_string($header) || trim($header) === '') {
            return [];
        }

        $entries = [];

        foreach (explode(',', $header) as $index => $part) {
            $bits = array_map('trim', explode(';', trim($part)));
            $quality = 1.0;

            foreach (array_slice($bits, 1) as $parameter) {
                if (stripos($parameter, 'q') !== 0) {
                    continue;
                }
                if (!preg_match('/^q\s*=\s*(0(\.\d{1,3})?|1(\.0{1,3})?)$/i', $parameter, $m)) {
                    continue 2;
                }
                $quality = (float) $m[1];
            }

            if ($bits[0] === '' || $bits[0] === '*' || $quality <= 0.0) {
                continue;
            }

            $entries[] = [$bits[0], $quality, $index];
        }

        usort($entries, function ($a, $b) {
            return $a[1] === $b[1] ? $a[2] - $b[2] : ($a[1] < $b[1] ? 1 : -1);
        });

        return array_column($entries, 0);
    }
}
