# Conformance — langsys/php-sdk

Spec version implemented: **v2**
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
| GATE-2 | n/a (profile: server) | Synchronous SDK — no unknown window exists. Withdrawn for sync profiles in v2; the residual obligation is REG-10, which is **not implemented** |
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
| REG-10 | **not implemented** | Three behaviours across four call sites: `registerPhrases()`/`registerContentBlock()` throw; `PageTranslator` swallows; `flushPendingRegistrations()` clears both queues and returns `['success' => true]` for work it discarded |
| REG-11 | **not implemented** | No ellipsis diagnostic |
| REG-12 | **not implemented** | `Client::translate()` branches structurally on `is_array($value)` and refuses to queue a content-block id. But `PageTranslator::findNewPhrasesWithCategory()` skips only when *present and not an array*, so the two paths disagree — which v2 explicitly forbids |

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
| WIRE-3 | provisional | Category: `tests/Resources/TranslatableItemsTest.php::testPhrasesDoNotSendTheUncategorizedSentinel` and the three sibling tests, incl. `::testNormalisingCategoryDoesNotChangeTheCustomId`. **Locale half untested and possibly non-conforming** — `LocaleDetector::normalize()` produces lowercase `xx-yy` (`en-us`), not canonical BCP 47 (`en-US`). Consistent SDK-wide, but it is a deviation and wants a ruling |
| WIRE-4 | **not implemented** | `Client::translate()` and `::translateContentBlock()` propagate `ApiException` when the catalog fetch fails, so an API outage throws into the caller's render path. Only `translatePage()` catches. Verified by substituting a throwing HTTP client |

## Conformance meta

| Rule | Status | Evidence |
|---|---|---|
| CONF-1 | **not implemented** | Every test in this repo asserts on outgoing payloads via a mock that cannot reject. See the evidence-grade note at the top |
| CONF-2 | acknowledged | All implemented rules recorded as `provisional`, per the grading |

## Known gaps, ranked by what they cost

1. **WIRE-4** — `translate()` throws on API failure. This is the only gap that can take down a
   customer's page: it sits on every render path, and an outage or a transient cURL error
   surfaces as a 500 rather than untranslated text. Highest priority.
2. **REG-10** — `flushPendingRegistrations()` reports `['success' => true]` for registrations it
   discarded. A caller that correctly checks the return value is told it worked.
3. **REG-9** — one POST per content block inside the request; a first uncached render of a page
   with 40 new blocks is 40 sequential blocking POSTs while the visitor waits.
4. **REG-3 / REG-8** — a failure during the shutdown flush is unrecoverable and silent. Blocked
   on the spec's Open item; logging is the interim MUST and is partially in place.
5. **GRANT-1…4** — no write-grant support.
6. **REG-12** — the presence and structure checks disagree between `translate()` and
   `PageTranslator`. Requires a page to display a bare md5 that is also a block id in the same
   category, so the practical risk is negligible; listed because v2 requires the paths to agree.
7. **OBS-1**, **REG-11** — diagnostics.
8. **CONF-1** — blocked on the central contract fixture.
