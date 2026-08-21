# Conformance — langsys/php-sdk

Spec version implemented: **v6** (see *Known staleness* below)
Profile: **server**

Every rule maps to the test that proves it. A rule with no test is NOT IMPLEMENTED —
that is a fact about this SDK, not a documentation gap. Do not write "yes" without a
test reference: a self-reported claim is exactly the failure this file exists to prevent.

**Evidence grade (CONF-2).** Every test cited is `mock` — assertions against
`tests/Mock/MockHttpClient.php`, which accepts whatever it is given. Under CONF-1 that
does not count as proof, so implemented-and-tested behaviour is recorded as
`provisional`, never `implemented`. Nothing here reaches `implemented` until a
`contract` fixture exists that can reject a bad request and holds state across calls.

Behaviour changes on this branch were each checked red against `origin/main` by
stashing `src/` and re-running. A regression test never seen red is a guess.

## Capability

| Rule | Status | Evidence |
|---|---|---|
| GATE-1 | provisional | `tests/ClientTest.php::testCanWriteWithIpWriteKeyThatServerReportsWriteEnabled`, `::testCannotWriteWithIpWriteKeyThatServerReportsNotWriteEnabled`, `::testPresentFlagWinsOverKeyTypeOnAFreshResponse`, `::testFallsBackToKeyTypeWhenTheApiOmitsWriteEnabled`, `::testFallsBackToKeyTypeForAReadKeyWhenTheApiOmitsWriteEnabled`. **⚠️ Depends on GRANT-1…4 being unimplemented** — `read` and `write` keys are answered from the cached `key_type` with no round-trip, which a write grant would invalidate. See the GRANT row |
| GATE-2 | n/a (profile: server) | Synchronous SDK — no unknown window exists. The residual obligation is REG-10 |
| GATE-3 | provisional | `::testWriteDecisionIsNeverWrittenToTheCache`, `::testWarmCacheStillResolvesPerRequestForAnIpWriteKey`, `::testResetRequestStateClearsTheWriteDecision`, and `::testWarmCacheCostsNoAuthorizationCallForAReadKey` (pins that correctness costs no per-render request) |
| GATE-4 | provisional | `::testWriteDecisionIsNeverWrittenToTheCache` — the `authorize-project` row, where the flag sits *inside* `data` and must be stripped from the body before caching, not merely dropped with an envelope |
| GATE-5 | provisional | `::testLegacyResolvedContentBlockIsNotQueuedForRegistration` — a block served from a legacy id is not recorded as needing registration |
| GATE-6 | provisional | Register-half only. Report-half n/a — this SDK has no hint lane (HINT-2) |

## Reading the catalog

| Rule | Status | Evidence |
|---|---|---|
| CAT-1 | provisional | `::testTranslateDoesNotQueuePhraseWithNullCatalogValue` — present-with-null is a known phrase, not a miss. `translate()` uses `array_key_exists`, not `isset` |
| CAT-2 | provisional | `::testTranslateReturnsSourcePhraseWhenCatalogValueIsNull`, `::testTranslateInterpolatesSourcePhraseWhenCatalogValueIsNull` — the display half falls back to source text rather than rendering the null |
| CAT-3 | provisional | `Client::translateContentBlock()` requires `array_key_exists($customId, …) && is_array(…)` before treating a block as known |

## The write lane

| Rule | Status | Evidence |
|---|---|---|
| REG-1 | provisional | `PageTranslator` and `flushPendingRegistrations()` return before the network when the request may not write |
| REG-2 | n/a (profile: server) | No debounce window. Misses accumulate per request and send once at end of request |
| REG-3 | **not implemented** | Manual flush exists and `register_shutdown_function` is wired, but the automatic path is untested and a transient failure during it is unrecoverable and silent |
| REG-4 … REG-7 | n/a (profile: server) | No page teardown, no `visibilitychange`, and synchronous execution leaves no await window |
| REG-8 | **not implemented** | A failed send retains the queue but there is no retry and no backoff |
| REG-9 | provisional | Closed on `main` before this branch — `PageTranslator` batches through `createContentBlocks()` |
| REG-10 | provisional | `::testFlushReportsDroppedWhenTheRequestMayNotWrite`, `::testFlushReportsSuccessWhenEverythingWasAccepted`. **Partial**: the throw-vs-swallow split across entry points is unchanged |
| REG-11 | **not implemented** | No ellipsis diagnostic |
| REG-12 | provisional | A catalog value that is a nested map is a content block, never a missing phrase |

## Legacy id compatibility

| Rule | Status | Evidence |
|---|---|---|
| (no rule id yet) | provisional | `::testContentBlockResolvesUnderItsLegacyPipeFormId`, `::testUncategorizedLegacyBlockResolvesUnderTheEmptyCategorySlot`, `::testUncategorizedLegacyBlockResolvesUnderTheSentinelCategorySlot`, `::testLegacyIdResolvingToDifferentContentIsRejected`, `::testLegacyIdIsNeverSentToTheApi`. Not yet a spec rule — the fallback is SDK-local remediation. If it becomes one, the load-bearing half is that a legacy-resolved block is **not** queued |

## Hint lane / SSR

| Rule | Status | Evidence |
|---|---|---|
| HINT-1 … HINT-8 | n/a (profile: server) | Per HINT-2 a server SDK is the origin: it registers or it logs, never reports |
| SSR-1 … SSR-3 | n/a (profile: server) | JS-only strategies |

## Write grants

| Rule | Status | Evidence |
|---|---|---|
| GRANT-1 … GRANT-4 | **not implemented** | `HttpClient::getHeaders()` sends only `X-Authorization`. **⚠️ Implementing this requires a change elsewhere.** [GATE-1](#capability)'s implementation short-circuits a `read` key to `false` from the cached `key_type` with no per-request call — correct only while no grant is sent, since the server's gate is `type-allows-write OR valid-grant`. Adding grant support MUST also remove the `KEY_TYPE_READ` short-circuit in `Client::resolveWriteDecision()`. Enforced by `tests/Http/HttpClientTest.php::testNoWriteGrantHeaderIsSent`, which fails the moment an `X-Write-Grant` header appears |

## Caching · Observability · Wire

| Rule | Status | Evidence |
|---|---|---|
| CACHE-1 | provisional | Keys are namespaced by project |
| OBS-1 | **not implemented** | A write-expected key resolving `write_enabled: false` that never queues anything produces no diagnostic |
| WIRE-1 | provisional | `tests/Http/HttpClientTest.php::testAuthenticatesWithTheXAuthorizationHeader` |
| WIRE-2 | provisional | `tests/Http/HttpClientTest.php::testEmptyBodyOnSuccessIsNotAParseError`, `::testEmptyBodyOnErrorStatusRaisesTheMatchingException`, `::testEmptyBodyOnValidationErrorRaisesValidationException`, `::testMalformedJsonStillRaisesAParseError` |
| WIRE-3 | provisional | `tests/ClientTest.php::testUncategorizedPhrasesDoNotSendTheSentinelOnTheWire`. All three registration paths now normalize through one `TranslatableItems::normalizeCategory()`; previously `createContentBlocks()` stripped the sentinel while `createContentBlock()` and `createPhrases()` sent it, so identical blocks landed with different stored categories. Locale form is lowercase `xx-yy`, which is correct — the backend stores and compares lowercase |
| WIRE-4 | provisional | `tests/ClientTest.php::testTranslateReturnsSourceWhenTheApiIsUnreachable`, `::testTranslateStillInterpolatesWhenTheApiIsUnreachable`, `::testTranslateQueuesNothingWhenTheApiIsUnreachable`, `::testTranslateContentBlockReturnsSourceHtmlWhenTheApiIsUnreachable`. Evidence is `tests/Mock/ThrowingHttpClient.php` |

## Conformance meta

| Rule | Status | Evidence |
|---|---|---|
| CONF-1 | **not implemented** | Every test here asserts against a mock that cannot reject |
| CONF-2 | acknowledged | All implemented rules recorded as `provisional` |
| CONF-3 | provisional | Behaviour changes verified red against `origin/main` before landing |

## Known staleness

Written against spec **v6**; the spec is at v8. Not yet assessed here: **GATE-7**
(every path detecting unregistered content feeds exactly one lane), **BIND-1…6**,
**WIRE-5**, **HINT-9**, and the template's per-rule **Revision** column — whose hashes
are a lookup, not a derivation: `sectionRevisions` in
`~/Documents/dev/langsys-docs/mcp-server/internal-docs.json` under `pages.sdk-spec`.

Remaining gaps, ranked — a status table gives a 500-serving rule and a cosmetic one
identical weight, so the ranking is the part that survives someone deciding what to fix
next:

1. **REG-3 / REG-8** — a transient failure during the shutdown flush is unrecoverable
   and silent; there is no later context to retry into.
2. **OBS-1** — a misconfigured integration that never queues anything produces no
   diagnostic at all.
3. **REG-11** — no ellipsis diagnostic.
4. **CONF-1** — blocked on the central contract fixture, which gates every `provisional`
   above.

## What surfaced while writing this file

WIRE-2, WIRE-3 and WIRE-4 were all still live on `main` and were **absent from the
re-land port list**, despite having been fixed on the branch that list was drawn from.
They were found by checking rule-by-rule against running code rather than by reading the
list — and WIRE-4, the only gap here that can turn a working page into a 500, would
otherwise have shipped again.

One of them nearly shipped broken from this very file's process: the WIRE-2 fix
initially called a `raiseForStatus()` helper that did not exist. `php -l` passed, because
lint does not resolve method calls. It was caught by exercising every status/body
combination, which is the check that actually distinguishes "parses" from "works".
