# Upgrading from v1.3.1

Everything here is in `CHANGELOG.md` under Unreleased. This page lists only what an app
already running v1.3.1 notices, and what to do about each. New features that change nothing
until you use them are not listed.

## Content that registers under a new identity

A phrase's or block's identity is derived from its text. Where the SDK now reads text the
way the other Langsys SDKs do, some content gets a new identity: it registers once more,
and its existing translations are not found under the new identity. Nothing breaks on the
page, which shows the source until the new entry is translated, but the new entry is
machine translated, and billed, again.

| Content | What changes |
|---|---|
| Text with a non-breaking space, a line or paragraph separator, or a control character | Read as ordinary whitespace, or dropped (control characters) |
| A phrase written with `%name%` | Stored as `{name}` |
| A content block containing `<script>`, `<style>`, `<template>`, `<noscript>` or `<math>` | Their contents are no longer part of the block |
| Text inside `<svg>` | Translated now, so a block containing it has new text |
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
  `LangsysException` when the response is not a catalog (no `data` key, a malformed body)
  and while a failed fetch's backoff window is open, where they returned `[]`.
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
- **Registration is decided by the server.** A key registers only when authorization
  answers `write_enabled: true`. An address-restricted write key used from outside its
  allow-list registers nothing; it registered before.

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

Selectors, snapshot tests or string comparisons on rendered HTML may need updating.

## Logging

With no `log_path`, warnings and errors are written to PHP's error log with a `[langsys]`
prefix. Pass `'error_log' => false` to turn that off.

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

`PageTranslator::markItemsAsRegistered()` and `markItemsAsRegisteredWithCategory()` no
longer exist.

`Client` has a destructor, which sends what a freed `Client` still has queued. A subclass
that defines `__destruct()` should call `parent::__destruct()`, or that queue is not sent.
The protected `Client::$shutdownRegistered` property no longer exists.

`Client::$pageTranslator` is no longer filled in: `translatePage()` builds a `PageTranslator`
for each call and keeps none. A subclass that sets it still has that one used.
