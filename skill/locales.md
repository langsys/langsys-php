# Locales, the request's language, and the reference lists

## The SDK's locale form: lowercase `xx-yy`

The SDK's form is lowercase with hyphens: `es-es`, `pt-br`, `zh-hant-tw`. `setLocale()` and every lookup accept `es_ES` or `es-ES` and normalise them, so one catalog serves every spelling. Compare locales in the lowercase form. `getProject()` gives the project's `base_locale`, its `target_locales` and its `default_locales` (the default locale for each language).

## Which locale a request renders in

```php
$client->setLocale('fr-ca');   // explicit: every call after it uses fr-ca
$client->getLocale();          // the locale set, else this request's, chosen once per request
```

With no locale set, `getLocale()` chooses the request's locale once per request, from PHP's superglobals, taking the first usable source:

1. **The URL**: a `?locale=` query parameter, the first path segment (`/fr/pricing`) or the subdomain (`fr.example.com`).
2. **A cookie or session value** named `locale`.
3. **`Accept-Language`**, negotiated against the project's locales.
4. **The project's base locale.**

Every candidate is checked against the locales the project serves, its base and target locales. An unsupported one is skipped, never served. A language alone, or with another region (`fr`, `fr-be`), matches when the project serves one locale of that language, or its `default_locales` names one; a project serving `fr-fr` and `fr-ca` matches neither. A locale with a script part, such as `zh-hant-tw`, never matches by its own name in the URL, a cookie or `Accept-Language`; only a bare `zh` reaches it, under the rule above. Where that matters, set it with `setLocale()`.

**A locale your framework already resolved** (Laravel's `App::getLocale()`) is mapped to the project's form, `es_ES` to `es-es` and a bare `es` to the project's default Spanish, and served as the base locale when the project doesn't serve it. Apply it yourself, since `getLocale()` reads only the request:

```php
$client->setLocale($client->resolveRequestLocale(['framework' => $appLocale])['locale']);
```

```php
$client = new Client($key, $project, ['request_locale' => [
    'query_param' => 'lang',
    'cookie' => 'site_lang',        // null turns the cookie lookup off
    'session_key' => 'site_lang',   // null turns the session lookup off
    'path' => false,                // don't read the first path segment
    'subdomain' => false,           // don't read the host's first label
    'resolver' => function (array $request) {   // your own URL and cookie steps; its answer is checked like any other
        return ['locale' => $request['query']['l'] ?? null, 'from' => 'url'];
    },
]]);

$result = $client->resolveRequestLocale();   // ['locale' => 'fr-fr', 'source' => ..., 'vary' => 'Accept-Language, Cookie']
```

`resolveRequestLocale($request, $options)` reads PHP's superglobals when `$request` is `null`, or takes `framework`, `path`, `host`, `query`, `cookies`, `session` and `accept_language` from the array you pass. `$options` overrides `request_locale` for that call. It only returns its choice: `setLocale()` the `locale` for the translate calls to use it.

## `Vary`

The response's `Vary` names every request field the choice read, so a CDN never serves one visitor's language to the next:

| Decided by | `Vary` |
|---|---|
| The framework's locale, or the URL | none |
| A cookie or session value | `Cookie` |
| `Accept-Language` or the base locale | `Accept-Language, Cookie`, or `Accept-Language` alone with the cookie and session lookups off and no resolver |

The SDK sends the header with PHP's `header()`, and only while no output has been sent, so choose the locale before the page starts printing. A framework that sets `Vary` on its own response object passes `'send_vary' => false` in `request_locale` and puts the result's `vary` on its response. So does a long-lived worker (Swoole, RoadRunner), where `header()` and the superglobals don't belong to the request being served: pass that request to `resolveRequestLocale()`, `setLocale()` its `locale`, and put its `vary` on the response ([server-rendering.md](./server-rendering.md)). The SDK never writes a cookie: remembering a visitor's choice is the app's.

## The reference lists

Lists for your own forms, named in the locale you pass, else the current one. They match the JavaScript SDK's lists of the same names.

```php
$client->getCurrencies();           // [['code' => 'USD', 'name' => 'dólar estadounidense', 'symbol' => '$', ...], ...]
$client->getCountries('fr-fr');     // [['label' => 'Costa Rica', 'code' => 'CR', 'eu' => false], ...]
$client->getDialCodes();            // [['country_code' => 'CR', 'dial_code' => '+506', 'name' => 'Costa Rica (+506)'], ...]
$client->getLocalesFormat('flat');  // [['code' => 'es-cr', 'name' => 'Español (Costa Rica)'], ...]
```

- `getLocalesFormat()` takes `''` (grouped by language name), `'flat'` (`code` and `name`) or `'data'` (`code`, `locale_name` and `lang_name`), and writes every code `es-cr`.
- A list is held per display locale, in memory and in the cache for `cache_ttl`, so a later request sends nothing for it.
- Nothing throws. A display locale not shaped as a locale is refused and logged, and nothing is sent. An API that can't answer gives an empty list with the cause logged, and the list isn't asked for again for 3 seconds, doubling to 5 minutes. A client without credentials gets an empty list, unless `require_credentials` is on.

`utilities()` gives the raw, paginated endpoints (`getCountries($locale, ['page' => 1, ...])`, `getCountrySelectOptions()`, `getDialCodeSelectOptions()`, `getLocaleSelectOptions()` and more). Those do throw: an empty display locale, `.` or `..` is an `InvalidArgumentException`, and nothing is sent.
