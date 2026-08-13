# Conformance — langsys/php-sdk

Spec version implemented: **v4**
Profile: **server**

Every rule maps to the test that proves it. A rule with no test is NOT IMPLEMENTED —
that is a fact about this SDK, not a documentation gap. Do not write "yes" without a
test reference: a self-reported claim is exactly the failure this file exists to
prevent.

**Evidence grade (CONF-2).** Every test below is `mock` — assertions against
`tests/Mock/MockHttpClient.php`, which accepts whatever it is given. Under CONF-1 that
does not count as proof, so behaviour that is implemented and tested is recorded here as
`provisional`, never `implemented`. Nothing in this repo can reach `implemented` until a
`contract` fixture exists — one that can reject a bad request and holds state across
calls, so a second read observes what the first write registered. That fixture is an Open
item on the spec and is deliberately not being invented here.

Every test cited below was checked to fail against the pre-fix code by stashing `src/`
and re-running. A regression test never seen red is a guess.

## Capability

| Rule | Status | Evidence |
|---|---|---|
| GATE-1 | provisional | `tests/ClientTest.php::testCanWriteWithIpWriteKeyThatServerReportsWriteEnabled`, `::testCannotWriteWithIpWriteKeyThatServerReportsNotWriteEnabled`, `::testWriteEnabledOverridesWriteKeyType`, `::testTreatsMissingWriteEnabledAsReadOnly`, `tests/Html/PageTranslatorTest.php::testRegistersWithIpWriteKeyWhenServerSaysWriteEnabled` |
| GATE-2 | n/a (profile: server) | Synchronous SDK — no unknown window exists. Withdrawn for sync profiles in v2; the residual obligation is REG-10, now partially met |
| GATE-3 | provisional | `tests/ClientTest.php::testWriteDecisionIsNeverWrittenToTheCache`, `::testCacheHitStillResolvesTheWriteDecisionForThisRequest`, `::testResetRequestStateClearsTheWriteDecision` |
| GATE-4 | provisional | `tests/ClientTest.php::testWriteDecisionIsNeverWrittenToTheCache` — the `authorize-project` row (strip from within `data`). The `/translations` row is satisfied structurally: `Translations::getTranslationMap()` returns `$response['data']`, so the envelope never reaches the cache — **untested**, and the refactor that would break it is invisible |
| GATE-5 | provisional | `tests/Html/PageTranslatorTest.php::testDoesNotRecordRegistrationThatNeverHappened` |
| GATE-6 | provisional | Register-half only: `PageTranslator::registerNewItemsWithCategory()` returns before the network when not write-enabled. Report-half is n/a — this SDK has no hint lane (HINT-2) |

## Reading the catalog

| Rule | Status | Evidence |
|---|---|---|
| CAT-1 | provisional | `tests/ClientTest.php::testTranslateExistingPhraseWithNullValueFallsBackToSource` — asserts a present-with-null phrase is **not** queued. `Client::translate()` uses `array_key_exists`, not `isset` |
| CAT-2 | provisional | Same test — asserts the display half falls back to source text rather than rendering the null |
| CAT-3 | provisional | `tests/ClientTest.php::testTranslateContentBlockQueuesNewBlock` and `Client::translateContentBlock()`, which requires `array_key_exists($customId, …) && is_array(…)` before treating a block as known |

## The write lane

| Rule | Status | Evidence |
|---|---|---|
| REG-1 | provisional | `tests/Html/PageTranslatorTest.php::testDoesNotRegisterWithIpWriteKeyWhenServerSaysNotWriteEnabled` — returns before touching the network |
| REG-2 | n/a (profile: server) | No debounce window exists. A request accumulates misses in `$pendingPhrases` and sends once at end of request, which is what the debounce achieves in a browser |
| REG-3 | **not implemented** | Public manual flush exists (`Client::flushPendingRegistrations()`, covered by `tests/ClientTest.php::testFlushPendingRegistrationsWithPhrases`) and `register_shutdown_function` is wired in `registerShutdownHandler()`. But the automatic path is untested, and see the REG-3/REG-8 conflict in the spec's Open section: a transient failure during the shutdown flush is currently unrecoverable **and silent** |
| REG-4 | n/a (profile: server) | No page teardown exists |
| REG-5 | n/a (profile: server) | No `visibilitychange` exists |
| REG-6 | n/a (profile: server) | Synchronous — there is no await window during which the live queue can diverge from the sent batch |
| REG-7 | n/a (profile: server) | Synchronous — one send in flight by construction |
| REG-8 | **not implemented** | On a failed send the queue is retained (not cleared), but there is no retry and no backoff, and at shutdown there is no later context to retry into |
| REG-9 | **not implemented** | `TranslatableItems::createPhrases()` and `::createContentBlocks()` chunk to the server limit, and `Client::syncBatchLimit()` reads it from `langsys_settings`. But `PageTranslator::registerNewItemsWithCategory()` loops **one POST per content block**, inside the request |
| REG-10 | provisional | `tests/ClientTest.php::testFlushReportsFailureAndCountWhenTheRequestMayNotWrite`, `::testFlushReportsSuccessWhenEverythingWasAccepted`, `::testFlushWithNothingQueuedIsSuccessNotASkip`. `flushPendingRegistrations()` now reports `success => false` and a `skipped` count for work it did not send. **Partial**: the throw-vs-swallow split across `registerPhrases()` and `PageTranslator` is unchanged, so the "exactly one behaviour" half is not met |
| REG-11 | **not implemented** | No ellipsis diagnostic |
| REG-12 | provisional | `tests/Html/PageTranslatorTest.php::testTextCollidingWithAContentBlockIdIsNotRegisteredAsAPhrase`. Both paths now treat presence alone as known, so the presence and structure checks agree |

## The discovery-report (hint) lane

| Rule | Status | Evidence |
|---|---|---|
| HINT-1 … HINT-8 | n/a (profile: server) | Per HINT-2, server SDKs never report. This SDK is the origin: it registers or it logs. No hint lane is implemented and none should be |

## Server-side rendering (JS)

| Rule | Status | Evidence |
|---|---|---|
| SSR-1 … SSR-3 | n/a (profile: server) | JS-only strategies |

## Write grants

| Rule | Status | Evidence |
|---|---|---|
| GRANT-1 … GRANT-4 | **not implemented** | `HttpClient::getHeaders()` sends only `X-Authorization`. No `X-Write-Grant` support, no grant provider. Note this is not merely a missing feature: without it, an `ip_write` key cannot write for an authenticated user from a non-allow-listed address, which is the case grants exist to serve |

## Caching

| Rule | Status | Evidence |
|---|---|---|
| CACHE-1 | provisional | `tests/Html/PageTranslatorTest.php::testRegisteredItemsCacheKeyIsProjectScoped`. All other keys were already namespaced |

## Observability

| Rule | Status | Evidence |
|---|---|---|
| OBS-1 | **not implemented** | `authorize()` warns when `write_enabled` is absent, and `flushPendingRegistrations()` warns when a flush is skipped — but only if something was pending, and per flush rather than once per process. A write-expected key that resolves `write_enabled: false` and never queues anything produces no diagnostic at all |

## The wire boundary

| Rule | Status | Evidence |
|---|---|---|
| WIRE-1 | provisional | `HttpClient::getHeaders()` sends `X-Authorization`; no cookie or query-parameter path exists |
| WIRE-2 | provisional | `tests/Http/HttpClientTest.php` — 9 tests covering empty bodies on 2xx, 401, 422 and 5xx, plus genuinely malformed JSON still failing |
| WIRE-3 | provisional | Category: `tests/Resources/TranslatableItemsTest.php::testPhrasesDoNotSendTheUncategorizedSentinel` and the three sibling tests, incl. `::testNormalisingCategoryDoesNotChangeTheCustomId`. Locale: `tests/Locale/LocaleDetectorTest.php`. The lowercase `xx-yy` form is **correct** — v4 corrected the rule from "canonical BCP 47" after checking the backend, where `locales.code` is lowercase and case-sensitively compared |
| WIRE-4 | provisional | `tests/ClientTest.php::testTranslateReturnsSourceWhenTheApiIsUnreachable` and four siblings covering interpolation, the queue staying empty, `translateContentBlock()` and `lookupContent()`. All three entry points now degrade; evidence is `tests/Mock/ThrowingHttpClient.php` |

## Conformance meta

| Rule | Status | Evidence |
|---|---|---|
| CONF-1 | **not implemented** | Every test in this repo asserts on outgoing payloads via a mock that cannot reject. See the evidence-grade note at the top |
| CONF-2 | acknowledged | All implemented rules recorded as `provisional`, per the grading |

## Known gaps, ranked by what they cost

1. **REG-9** — `PageTranslator` sends one POST per content block inside the request. A first
   uncached render of a page with 40 new blocks is 40 sequential blocking POSTs while the
   visitor waits. Now the most expensive remaining gap.
2. **REG-10 (partial)** — the return value no longer lies, but the *behaviour* still differs by
   entry point: `registerPhrases()`/`registerContentBlock()` throw while `PageTranslator`
   swallows. The rule asks for exactly one behaviour.
3. **REG-3 / REG-8** — a failure during the shutdown flush is unrecoverable and silent. Blocked
   on the spec's Open item; logging is the interim MUST and is in place.
4. **GRANT-1…4** — no write-grant support. Without it an `ip_write` key cannot write for an
   authenticated user from a non-allow-listed address, which is the case grants exist to serve.
5. **OBS-1** — a write-expected key that resolves `write_enabled: false` and never queues
   anything produces no diagnostic at all.
6. **REG-11** — no ellipsis diagnostic.
7. **GATE-4, second row** — satisfied structurally but untested; the refactor that breaks it
   (caching the whole response rather than `data`) looks like pure cleanup.
8. **CONF-1** — blocked on the central contract fixture, which gates every `provisional` above.

## History

Written against spec v2 and updated through v4. Three rules moved from *not implemented* to
*provisional* after the fixes on this branch: WIRE-4, REG-10 and REG-12. Writing this file is
what surfaced WIRE-4, which was the most costly defect in the SDK and was not on anyone's list
before the rules were checked one at a time against running code.
