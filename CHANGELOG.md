# Changelog

All notable changes to the Langsys PHP SDK are documented here.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

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

### Changed

- **BREAKING: minimum PHP version raised from 5.6 to 7.4.**
- **BREAKING: `ext-intl` is now a required extension**, needed for ICU plural
  rules and locale-aware number/date formatting.
- Dev dependency narrowed to PHPUnit ^9.0 (PHPUnit ^5.7 supported the old 5.6
  floor and is no longer relevant).

### Notes

- Building dynamic strings before translating — `translate(sprintf('Hello, %s!',
  $name))` — registers a new catalog phrase for every distinct runtime value and
  pollutes the catalog shared with every other Langsys SDK. Use `$params`
  instead. This cannot be detected at runtime; see README.
