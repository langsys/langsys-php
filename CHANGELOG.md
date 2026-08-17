# Changelog

All notable changes to `langsys/php-sdk` are recorded here.
This project follows [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased] — next release is a MAJOR

### ⚠️ Release sequencing — read before tagging

Nothing in this repository enforces the constraints below. They are ordering
requirements on the release itself, and the only thing standing between them
and a silent outage is whoever cuts the tag.

**1. `write_enabled` is optional, and must stay optional.** The SDK prefers the
server's `write_enabled` flag and falls back to `key_type === 'write'` when the
response does not carry one. That fallback is not legacy tolerance — it is what
keeps the SDK working against today's production API, which does not emit the
flag at all (`ApiKeyType` on backend `main` is `read`/`write`; `ip_write`,
the allow-list and the flag all ship on the unmerged `feature/838_write_key_gating`).
An earlier revision of this branch failed closed on a missing flag, which would
have silently disabled all phrase and content-block registration for every
customer, with a warning log as the only signal. Do not reintroduce that.

**2. Content-block `custom_id` values change, and this DESTROYS existing human
translations (see `e5fbed8`). Do not release until resolved.**

Verified end-to-end against a local dataset on 2026-08-17, not inferred: a
content block registered under the old id, given attributed human translations,
then re-registered with identical content under the new JSON-form id, comes back
**untranslated** — every phrase `null`. The human translations remain bound to
the old id and are not reachable from the new block. Machine translation was
disabled for the test, so nothing masked the result.

Tested with the project's translation memory both ON and OFF; it made no
difference. The code explains why: `TranslationService::handleMatchingPhrasesTranslations()`
deliberately skips reuse for content-block phrases ("a content-block phrase is
context-dependent — reusing a same-text translation produced in an unrelated
context would corrupt the block") and routes them to machine translation
instead. The reuse-by-content path that `e5fbed8`'s message relies on applies to
**standalone phrases only**.

The production case is worse than the test case. With machine translation
enabled — the default — the re-registered block is machine translated in
context, so the page still looks translated while the paid human translation has
silently stopped being served. Nothing errors, nothing logs, and the regression
is only visible by reading the output in each locale.

**Orphan / migration plan** — owner required, recommendation first:

- **A. Rewrite the ids in the backend before this SDK ships (recommended).** A
  migration that recomputes `content_blocks.custom_id` into the JSON form and
  updates `translations.custom_id` (and `translation_memory.custom_id`) to match
  keeps every existing translation attached, produces no re-registration, no
  machine-translation churn and no orphans. It belongs to langsys2: both the id
  computation and the affected rows live there. The SDK change then becomes a
  no-op for existing content.
- **B. Ship, then reconcile.** Re-point orphaned translations onto the new ids
  afterwards by matching project + category + phrase set. Strictly worse than A:
  there is a window in which customers are served machine translations in place
  of human ones, and reconciliation has to reconstruct the mapping A already has.
- **C. Accept the loss and re-translate.** Not viable anywhere human translation
  was paid for, which is the case that matters.

**Decision (2026-08-17): Option A, owned by langsys2.** Matching the JS SDKs
byte-for-byte is correct and stays — all SDKs must compute identical
`custom_id` hashes, and the behaviour spec will pin the exact serialization
including encoding edge cases. So `e5fbed8` needs no code change; only its
commit message is wrong, and this entry supersedes it.

**This release is blocked until the langsys2 re-keying migration has landed and
been verified.** That migration is the specific unblocking event — not a review
sign-off, and not this SDK reaching readiness, since the damage is done by the
backend's reaction to what the SDK sends rather than by SDK code. Its agreed
shape: a pre-flight residue report, a collision-merge rule that prefers human
translations over machine ones, and idempotent re-runnability so it can be
applied again once the whole SDK fleet is upgraded.

Until that migration is verified, releasing this SDK converts paid human
translation into machine output on every project using content blocks.

### Fixed

- **`translate()` no longer throws when the API is unreachable.** It, and
  `translateContentBlock()` and `lookupContent()`, propagated `ApiException`
  from the catalog fetch, so a Langsys outage or a transient cURL error
  returned a 500 on every page that called them. All three now degrade to
  source content and log. Nothing is queued on that path: a failed catalog
  fetch cannot distinguish a miss from a hit, and registering on a guess turns
  every outage into a write storm.
- **A malformed ICU string in a catalog no longer throws into the render path.**
  `MessageFormatter::formatMessage()` signals a bad pattern with `false` by
  default but with an `IntlException` under `intl.use_exceptions=1`, which `@`
  does not suppress. Both now fall back to simple interpolation.
- **An empty or null category no longer creates a second, unreachable
  namespace.** The catalog keys uncategorised items under `__uncategorized__`,
  so a lookup under `''` missed forever while registration wrote the phrase as
  uncategorised — re-registering the same phrase on every request, without ever
  converging. Empty and null now resolve to the sentinel on the way in,
  matching the JS SDKs' `category || '__uncategorized__'`.
- **Registration bookkeeping records confirmed acceptance, not attempts.**
  `PageTranslator` marked discovered items as registered even when the write was
  skipped or threw, suppressing them on every later render until the cache
  expired.
- **The write decision is never persisted.** It is derived from the caller's
  address, and was being written to a cache shared by every request on the host
  (and by the fleet on Redis).
- **A successful `204` no longer raises a parse error.** The response body was
  decoded unconditionally, so an empty-bodied success threw.
- **The `__uncategorized__` sentinel is no longer sent on the wire.**
  `createContentBlocks()` stripped it; `createPhrases()` and
  `createContentBlock()` did not.
- **Content-block ids no longer collapse on invalid UTF-8.** `json_encode()`
  returns `false` there and `md5(false) === md5('')`, so every affected block
  aliased to one id.
- **The registered-items cache key is namespaced by project.** It was the only
  key in the SDK without one, so two projects sharing a Redis prefix or a temp
  directory suppressed each other's registrations.
- **Interpolation matches the JS SDK again** (`src/interpolate.ts`). Style-less
  ICU slots such as `{n, number}` were rendered literally; bare `{n}` numbers
  and dates were string-cast rather than CLDR-formatted. The same catalog string
  now renders identically in both SDKs.

### Changed — breaking

- `translate()` takes `(phrase, category?, params?, locale?)`, matching the JS
  SDKs' `t()`. The previous positional order and the `$contentBlockId`
  parameter are gone.
- Content-block `custom_id` is now `md5(json_encode([category, phrases]))`,
  matching the JS SDKs. See the sequencing note above.
- `flushPendingRegistrations()` no longer returns `['success' => true]` for work
  it discarded. `success` now means every queued item was accepted, and the
  result carries `skipped`, split into `dropped` (gone) and `retained`
  (a later flush can send them).
- `canWrite()` resolves per request rather than from cache, and answers `false`
  for a `read` key even where the old code would have.

### Added

- `Client::resetRequestState()` — clears the per-request write decision and the
  request-scoped caches. Required under long-lived runtimes (Octane, Swoole,
  RoadRunner, queue workers) where the `Client` outlives the request it was
  built for.
- `CONFORMANCE.md` — maps every rule in the cross-SDK behaviour spec to the test
  that proves it, or records it as not implemented.
