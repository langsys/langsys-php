# Upgrading from 1.x to 2.0.0

`CHANGELOG.md` lists everything in 2.0.0. This page lists only what an app already running
1.x notices, and what to do about each. New features that change nothing until you use them
are not listed.

## Laravel apps

`langsys/langsys-php-laravel` 1.0.0 requires `langsys/langsys-php` `^1.3`. A Laravel app
that uses it stays on langsys-php 1.x until the Laravel package releases a version built on
2.0.0, and then upgrades the two together.

## Installing

Raise the constraint with `composer require "langsys/langsys-php:^2.0"`; an app whose
`composer.json` holds `^1.x` stays on 1.x through `composer update`. 2.0.0 also requires the
`dom`, `libxml`, `filter` and `tokenizer` extensions, beside `curl`, `json` and `intl`.
Most PHP builds include them; on a host that lacks one, Composer refuses the install and
names it.

## Content that registers under a new identity

A phrase's or block's identity is derived from its text. Where the SDK now reads text the
way the other Langsys SDKs do, some content gets a new identity: it registers once more,
and its existing translations are not found under the new identity. Nothing breaks on the
page, which shows the source until the new entry is translated, but the new entry is
machine translated, and billed, again.

| Content | What changes |
|---|---|
| Page or block text with a non-breaking space or a line or paragraph separator | Read as ordinary whitespace |
| Any text with a control character, a `translate()`, `sync()` or language-file line included | The control character is dropped; a line's other whitespace is kept as written |
| A `<title>` or a translated `<meta>` `content` (description, keywords, `og:*`, `twitter:*`) holding a line break, a run of spaces or a non-breaking space, or a `<meta>` `content` with an edge space | Its whitespace is collapsed to single spaces and trimmed, as page text is |
| A phrase written with `%name%` | Stored as `{name}` |
| A content block containing `<script>`, `<style>`, `<template>`, `<noscript>` or `<math>` | Their contents are no longer part of the block |
| Text inside `<svg>` | Translated now, so a block containing it has new text |
| A content block holding a `<textarea>` or `<title>` with markup in it | Its body is read as the text a browser shows |
| `translateContentBlock('<p>Hello</p>')` — one phrase in one text node | Registers as the phrase `Hello`, not as a block |
| A paragraph with a translatable attribute (`title`, `alt`, …) on the page | Registers as one block of the attribute and the text |
| An `<img alt>`, `<input placeholder>` or inline element outside a paragraph | Registers; it did not before |
| A block containing an element marked `data-ls-phrase` or `data-ls-contentblock` | The marked element is its own unit; the block around it has new text |
| `data-langsys-contentblock="<anything but true/1/yes/false/0>"` | The value is the block's id, rather than a request to derive one |
| A `data-langsys-phrase` run made only of `translate="no"` or `data-notrans` elements | Registers nothing; it registered `{m0o}{m0c}` |

**What to do:** nothing is required. Each affected item registers once and is translated
again. If a site has many of these shapes and its translations were human-made, re-apply
them to the new entries in the Translation Manager. Content registered before the JSON-form
id change (the `md5('category|phrase|…')` shape) is still found: lookups fall back to it,
and it is never registered again.

## Calls whose results changed

- **`flushPendingRegistrations()`** returns `success: false` whenever it did not send what
  was queued, including on a key that may not write. Each failure names its `reason`:
  `not_write_enabled`, `catalog_unavailable`, `backing_off`, `decision_unavailable` or
  `send_failed`. Code that alerts on `!$result['success']` should check `reason` first: a
  read key returns `not_write_enabled` on every request by design.
- **`Translations::getTranslationMap()` and `Client::getTranslations()`** throw
  `LangsysException` when the response is not a catalog (no `data` key, a malformed body),
  where they returned `[]`; `getTranslations()` also throws while a failed fetch's backoff
  window is open.
  `translate()`, `translatePage()` and `translateContentBlock()` catch this and render the
  source; wrap direct calls in `try`.
- **`Client::getTranslations()` and `Translations::getTranslationMap()`** return a plural
  or select Langsys generated for a target locale as ICU MessageFormat
  (`{count, plural, one {…} other {…}}`), where they returned its `other` branch.
  `translate()`, `translatePage()` and `translateContentBlock()` format it; code that
  renders those strings itself should pass them through
  `$client->getInterpolator()->interpolate($text, $params, $locale)`. A catalog cached
  before upgrading keeps the flattened text until `cache_ttl` runs out; call
  `clearCache()` after deploying to switch at once.
- **`new Client()` without an API key or project ID** no longer throws. It builds an
  unconfigured client that renders source text and sends nothing, and an explicit write
  throws a `ConfigurationException` (a `LangsysException`). An app that relies on the
  exception to fail fast passes `'require_credentials' => true`; the exception then comes
  from the client's first use rather than from the constructor.
- **Registration is decided by the API, per request.** `canWrite()` returns, and every
  registration follows, the `write_enabled` the API answers for the request, never the
  key's type: the answer depends on the key, the caller's address and any write grant.
  1.x decided by type alone, so it registered with every `write` key and never with an
  `ip_write` key. Now an `ip_write` key registers from an address on its allow-list and
  nothing from anywhere else, and a `write` key registers nothing when the API answers
  `write_enabled: false` or leaves the field out. A `write` or `ip_write` key answered
  `write_enabled: false` is reported once, as a warning. `getKeyType()` still reports the
  key's type.
- **`canWrite()` asks the API** the first time it is called in a request, even with the
  authorization cached, and throws the `LangsysException` of a request that fails, where
  it answered from the cached authorization. Code that calls it directly wraps it in `try`.
- **A request that writes asks the API for its decision.** A cached authorization holds
  no decision, so the first write of a request - usually the flush after the response -
  asks the API once, whatever the key's type. If the API cannot be reached then, the
  flush reports `decision_unavailable` and keeps its queue for a later flush.
- **The `utilities()` resource throws `InvalidArgumentException`** for a display locale
  that names no path segment (empty, `.` or `..`), and sends nothing.

## Long-lived workers

A worker that keeps one `Client` across requests (Octane, Swoole, RoadRunner, a queue
worker) calls `flushPendingRegistrations()` and then `resetRequestState()` between
requests. The reset clears everything that belongs to one request: the write decision, the
queue, the catalogs read for it and the locale, including one set with `setLocale()`. A
worker that serves one fixed locale calls `setLocale()` again after each reset.

## Output that changed

- **`translatePage()` and `translateContentBlock()`** add `data-ls-contentblock="<id>"` to
  each content-block element they render.
- **`translatePage()` in a locale other than the project's base** adds
  `data-ls-resolved="<locale>"` on `<html>`. A later walk of that page — a Langsys
  browser SDK, a second middleware — registers none of it.
- **A `Vary` header.** With no locale set, the request locale comes from the URL, then a
  `locale` cookie or session value, then `Accept-Language`, and is always one the project
  serves, else the base locale. The response's `Vary` names every request field that
  choice read: none when the URL decided, `Cookie` when a cookie or session value did,
  and `Accept-Language, Cookie` when the header or the base locale did (`Accept-Language`
  alone when the cookie and session lookups are off and no resolver is set). A framework
  that sets the header on its own response passes `'send_vary' => false` in the
  `request_locale` option.

Selectors, snapshot tests or string comparisons on rendered HTML may need updating.

## Logging

With no `log_path`, warnings and errors are written to PHP's error log with a `[langsys]`
prefix. Pass `'error_log' => false` to turn that off. The one-time warnings about the setup
itself (a client without credentials, a missing `intl`, a PHP below 7.4) are written to
PHP's error log even then; pass `'warn_runtime_requirements' => false` to turn those off
too.

## Subclasses

`Client`, `PageTranslator`, `HtmlParser`, `MarkupTokenizer` and `Interpolator` are not
final. A subclass that overrides one of these methods must add the new trailing optional
parameters to its signature, or PHP refuses it as incompatible:

| Method | New parameters |
|---|---|
| `Client::applyBlockTranslations()` | `$customId = null` |
| `HtmlParser::walkNode()` | `&$textNodes = null` |
| `PageTranslator::interp()` | `?DOMNode $scope = null` |
| `Interpolator::chooseIcuBranch()` | `$locale = null` |
| `Interpolator::formatIcu()` | `$template = null` |
| `MarkupTokenizer::encode()` | `?ValueMarkers $markers = null` |
| `MarkupTokenizer::render()`, `MarkupTokenizer::rebuild()` | `array $opaque = []` |
| `MarkupTokenizer::encodeChildren()` | `array &$opaque = [], ?ValueMarkers $markers = null, &$excluded = false` |

`MarkupTokenizer::encode()` returns two more keys, `opaque` and `earlier`.

`Interpolator::formatIcu()` returns `false` when the pattern parses and the formatter fails
on it, and `null`, as before, when intl is absent or the pattern does not parse. A subclass
that calls it and tests for `null` treats `false` as a failure too.

`PageTranslator::markItemsAsRegistered()` and `markItemsAsRegisteredWithCategory()` no
longer exist.

`Client` has a destructor, which sends what a freed `Client` still has queued. A subclass
that defines `__destruct()` should call `parent::__destruct()`, or that queue is not sent.
The protected `Client::$shutdownRegistered` property no longer exists.

`Client::$pageTranslator` is no longer filled in: `translatePage()` builds a `PageTranslator`
for each call and keeps none. A subclass that sets it still has that one used.
