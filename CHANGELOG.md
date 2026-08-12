# Changelog

All notable changes to the Langsys PHP SDK are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- **Without `ext-intl`, ICU phrases rendered their raw MessageFormat source to
  end users.** Simple substitution cannot match a construct containing commas
  and nested braces, so a phrase like
  `{n, plural, one {# товар} few {# товара} other {# товаров}}` was emitted
  verbatim into the page — worse output than an untranslated phrase, which at
  least reads as a sentence.

  ICU phrases now degrade to a readable sentence. Branch selection is: an exact
  `=N` branch when one matches (standards-correct), then `one` when the value is
  exactly 1 and the translator supplied a `one` branch, then `other`. `select`
  matches the parameter value, falling back to `other`. `#` becomes the value,
  and nested placeholders inside the chosen branch still resolve.

  This is **not** CLDR-correct — that is exactly why `ext-intl` is required —
  but every language now produces prose rather than visible markup. Behaviour
  with `ext-intl` present is unchanged, and a malformed pattern is still emitted
  verbatim, matching the JS SDK.

## [1.0.2] - 2026-08-12

### Fixed

- **Content block `custom_id`s did not match the JS SDKs.** PHP hashed
  `md5('category|phrase1|phrase2')` while the JS SDKs hash
  `md5(JSON.stringify([category, tokens]))`, so the *same* content block
  registered under two different ids depending on which SDK saw it first —
  producing duplicate entries in the catalog every Langsys SDK shares.

  `generateCustomId()` now JSON-encodes with `JSON_UNESCAPED_SLASHES |
  JSON_UNESCAPED_UNICODE`, which is byte-identical to `JSON.stringify`, and
  normalises the reserved `__uncategorized__` sentinel and `null` to `''` — the
  JS side passes a raw category and never the sentinel. That also reconciles the
  two PHP callers, which previously defaulted differently
  (`translateContentBlock()` to `__uncategorized__`, `createContentBlock()` to
  `null`) and so disagreed with each other.

  **Upgrade note:** existing content blocks will be assigned new ids and
  re-register on first use. Previously-registered blocks under the old ids are
  orphaned and can be removed from the Translation Manager.

- **`Utilities::getLocaleData()` returned an empty array on a locale-code format
  mismatch.** The API keys its response by the display locale formatted in the
  organisation's configured `locale_code_format`, which need not match the string
  we sent (`en-US` vs `en-us`). Since a single display locale is requested, the
  only entry is now taken regardless of key formatting.

### Internal

- Tests no longer hardcode `md5()` content-block ids; they derive them via
  `generateCustomId()` so they survive a change to the hashing scheme.

## [1.0.1] - 2026-08-11

### Fixed

- **The ext-intl warning became a FATAL error under Laravel.** The runtime
  requirement guard used `trigger_error(E_USER_WARNING)`, which routes through
  the installed error handler. Laravel's `HandleExceptions` converts PHP errors
  into thrown `ErrorException`s, so on any Laravel host without `ext-intl`,
  **constructing the Client threw instead of degrading** — inverting the entire
  intent of a guard that exists to keep renders working. Now uses `error_log()`,
  which cannot be intercepted or escalated by any error handler. Reported by the
  Laravel wrapper with a reproducing stack trace.
- **`translatePage()` never interpolated the `<head>`.** `<title>`, meta
  description, `og:*` and `twitter:*` shipped raw `{name}` placeholders to the
  browser while the body resolved them correctly.
- **`LocaleDetector` treated `q=0` as acceptable.** RFC 7231 defines `q=0` as
  "not acceptable", but `Accept-Language: de;q=0` resolved to `de-de`. A `q` that
  is malformed (`q=abc`) or out of range (`q=1.5`) now discards that entry rather
  than silently defaulting it to `q=1`, which had promoted broken entries to top
  priority.
- **ICU calls could throw and break the render.** `MessageFormatter::create()`,
  `NumberFormatter::create()` and `new IntlDateFormatter()` can raise
  `IntlException` (and `@` does not suppress exceptions), violating this class's
  promise never to throw. All three are now guarded, as is the `false`-vs-`null`
  return difference between intl versions.
- `PageTranslator` no longer retains per-call params after `translatePage()`
  returns, including when it unwinds on an exception.

### Added

- `LocaleDetector::fromAcceptLanguage($header)` — same semantics as
  `fromBrowser()` but takes the header explicitly, so a framework can pass its
  own request header instead of faking `$_SERVER`. Requested by the Laravel wrapper.
- `warn_runtime_requirements` constructor option to silence the `error_log()` leg
  of the requirement warning (the SDK logger leg still fires).
- A CI job that runs the suite **without** `ext-intl`, so the graceful-degradation
  path is verified on CI rather than only on a developer machine that happens to
  lack the extension.

### Internal

- `tests/ClientRequirementsTest.php` restored PHP's error handler with
  `set_error_handler($previous)`, which *pushes* a handler rather than popping
  one. That silently disabled PHPUnit's error handler for every test that ran
  afterwards, so warnings across the rest of the suite were swallowed. Now uses
  `restore_error_handler()`.
- `SpyLogger` moved to `tests/Support/` so individual test files can be run on
  their own.

## [1.0.0] - 2026-08-10

First tagged release.

### Added

- **Placeholder interpolation** (`src/Format/Interpolator.php`). Translated
  strings can carry `{name}` placeholders, substituted at render time. The
  canonical stored form matches the Langsys JS SDKs, so a shared catalog renders
  identically from a PHP backend and a JS frontend.
  - `translate($phrase, $locale, $category, $contentBlockId, $params)`
  - `translateContentBlock($html, $category, $params)`
  - `translatePage($html, $category, $selectorCategories, $params)`
  - In HTML paths, placeholders resolve in text nodes **and** translatable
    attributes.
  - Unknown keys and `null` values are left verbatim so missing data stays
    visible; `{ name }` whitespace is tolerated; string values opt out of number
    formatting; numbers and dates format per the target locale.
  - Untranslated fallback text still interpolates, so newly-registered phrases
    never render a raw `{name}` to end users.
  - Registration always queues the **raw** placeholder-bearing phrase, so one
    catalog entry serves every runtime value.
- **ICU MessageFormat support** for plurals and select, giving correct plural
  categories per language (Russian's four, Arabic's six). Malformed ICU falls
  back to simple substitution rather than throwing.
- **Runtime requirement warnings** (`Client::checkRuntimeRequirements()`). Warns
  once per process when PHP is below 7.4 or `ext-intl` is missing. Reported
  through both the SDK logger and `trigger_error()`, because the manual
  `autoload.php` install typically has no log path configured and bypasses
  Composer's constraints entirely. Warns rather than throws — a missing
  `ext-intl` degrades to simple substitution instead of breaking the render.
- CI across PHP 7.4–8.4 with `ext-intl` loaded, so the ICU plural/select tests
  actually execute. `LANGSYS_REQUIRE_INTL` turns their `markTestSkipped` into a
  hard failure, preventing a green suite that silently skipped every plural
  assertion.

### Changed

- **BREAKING: minimum PHP version raised from 5.6 to 7.4.**
- **BREAKING: `ext-intl` is now a required extension**, needed for ICU plural
  rules and locale-aware number/date formatting.
- Dev dependency narrowed to PHPUnit ^9.0 (PHPUnit ^5.7 supported the old 5.6
  floor and is no longer relevant).

### Fixed

- **Browser locale detection was inconsistent between hosts.**
  `LocaleDetector::fromBrowser()` had two code paths that disagreed, and which
  one ran depended on whether `ext-intl` was loaded — so the same visitor could
  be served a different language on two otherwise identical deployments:

  | `Accept-Language` | without intl | with intl |
  |---|---|---|
  | `en` | `en-en` | `en` |
  | `en,es-MX;q=0.9` | `es-mx` | `en` |

  The non-intl path matched whichever locale-shaped substring appeared in the
  header regardless of priority, so `es-MX` at `q=0.9` beat `en` at an implicit
  `q=1`. The intl path handled priority correctly but returned a bare language
  code, which cannot match a project locale since the API addresses translations
  by `xx-yy` codes.

  Both paths now parse by quality value and then fill a missing region, so they
  produce identical results on every host. The fallback is a real
  `Accept-Language` parser rather than a substring match.

### Notes

- Building dynamic strings before translating — `translate(sprintf('Hello, %s!',
  $name))` — registers a new catalog phrase for every distinct runtime value and
  pollutes the catalog shared with every other Langsys SDK. Use `$params`
  instead. This cannot be detected at runtime; see README.
