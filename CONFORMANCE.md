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
| GATE-5 | provisional | `tests/Html/PageTranslatorTest.php::testDiscoveredItemsAreNotRecordedAsRegisteredWhenOnlyQueued` — a queued item is an attempt, not an acceptance, and is not recorded until the server accepts it. Also `tests/ClientTest.php::testLegacyResolvedContentBlockIsNotQueuedForRegistration` |
| GATE-6 | provisional | `tests/ClientTest.php::testFlushReportsDroppedWhenTheRequestMayNotWrite` — the register half returns before the network when the request may not write. Report-half n/a: this SDK has no hint lane (HINT-2) |

## Reading the catalog

| Rule | Status | Evidence |
|---|---|---|
| CAT-1 | provisional | `::testTranslateDoesNotQueuePhraseWithNullCatalogValue` — present-with-null is a known phrase, not a miss. `translate()` uses `array_key_exists`, not `isset` |
| CAT-2 | provisional | `::testTranslateReturnsSourcePhraseWhenCatalogValueIsNull`, `::testTranslateInterpolatesSourcePhraseWhenCatalogValueIsNull` — the display half falls back to source text rather than rendering the null |
| CAT-3 | provisional | `tests/ClientTest.php::testContentBlockResolvesUnderItsLegacyPipeFormId` and `::testLegacyIdResolvingToDifferentContentIsRejected` — resolution requires an array-valued entry before treating a block as known, so `custom_id: null` is never mistaken for a registered block |

## The write lane

| Rule | Status | Evidence |
|---|---|---|
| REG-1 | provisional | `tests/ClientTest.php::testFlushReportsDroppedWhenTheRequestMayNotWrite` — the queue is dropped without a network call when the request may not write |
| REG-2 | n/a (profile: server) | No debounce window. Misses accumulate per request and send once at end of request |
| REG-3 | **not implemented** | Manual flush exists and `register_shutdown_function` is wired, but the automatic path is untested and a transient failure during it is unrecoverable and silent |
| REG-4 … REG-7 | n/a (profile: server) | No page teardown, no `visibilitychange`, and synchronous execution leaves no await window |
| REG-8 | **not implemented** | A failed send retains the queue but there is no retry and no backoff |
| REG-9 | provisional | Closed on `main` before this branch: `PageTranslator` queues rather than registering inline, and `flushPendingRegistrations()` batches through `createContentBlocks()`, which chunks to the server-provided limit. Covered indirectly by `tests/ClientTest.php::testFlushReportsSuccessWhenEverythingWasAccepted`; **no direct batch-size test on this line** |
| REG-10 | provisional | `::testFlushReportsDroppedWhenTheRequestMayNotWrite`, `::testFlushReportsSuccessWhenEverythingWasAccepted`. **Partial**: the throw-vs-swallow split across entry points is unchanged |
| REG-11 | **not implemented** | No ellipsis diagnostic |
| REG-12 | provisional | `tests/Html/PageTranslatorTest.php::testTextCollidingWithAContentBlockIdIsNotRegisteredAsAPhrase` — presence alone decides "known" on both the phrase-discovery path and in `Client::translate()`, so the two agree. They previously disagreed, and the page path re-registered colliding text on every render |

## Legacy id compatibility

| Rule | Status | Evidence |
|---|---|---|
| (no rule id yet) | provisional | Both rendering paths: `tests/Html/PageTranslatorTest.php::testTranslatePageServesAContentBlockFoundUnderItsLegacyId`, `::testTranslatePageDoesNotQueueALegacyResolvedContentBlock`, `::testPageAndContentBlockPathsAgreeOnALegacyBlock`; and `tests/ClientTest.php::testContentBlockResolvesUnderItsLegacyPipeFormId`, `::testUncategorizedLegacyBlockResolvesUnderTheEmptyCategorySlot`, `::testUncategorizedLegacyBlockResolvesUnderTheSentinelCategorySlot`, `::testLegacyIdResolvingToDifferentContentIsRejected`, `::testLegacyIdIsNeverSentToTheApi`. Not yet a spec rule — the fallback is SDK-local remediation. If it becomes one, the load-bearing half is that a legacy-resolved block is **not** queued |

## Content-block id (CID)

| Rule | Status | Evidence |
|---|---|---|
| CID-1 | provisional | `tests/Html/HtmlParserTest.php::testCustomIdFixtureIsSelfConsistentAtEveryLayer` asserts the fixture **programmatically at each layer** — recorded codepoints describe the input, the canonical serialization reproduces byte-for-byte against `serialized_hex` (hex of the exact string passed to `md5()`, recomputed rather than trusted), the hash of those bytes is the recorded `custom_id`, and `generateCustomId()` produces the same. `::testCustomIdFixtureRetainsItsUnicodeCoverage` pins ≥15 codepoints above U+00FF and ≥1 non-BMP, since an ASCII-only suite cannot tell a byte hash from a UTF-16 one |
| CID-2 | provisional | Enforced **inside** the id function, not at call sites: `$cat = ($category === null \|\| $category === '__uncategorized__') ? '' : $category;`. `tests/Html/HtmlParserTest.php::testGenerateCustomId` covers the null case |
| CID-3 | provisional | Emits only the CID-1 form — `tests/ClientTest.php::testLegacyIdIsNeverSentToTheApi` scans every outgoing POST body for a legacy id. Accepts historical shapes on lookup via `Client::resolveContentBlockTranslations()`, covered on **both** rendering paths. Atomicity holds by construction: both halves are in this branch and ship in the same release. No stored row is ever re-keyed — the fallback performs no writes |
| CID-4 | **partial** | `Client::legacyBlockMatchesPhrases()` compares phrases before attaching, and the guard fails toward no-match: `::testLegacyIdResolvingToDifferentContentIsRejected`. **Two deviations, both structural rather than chosen** — (a) *category* is not compared explicitly because the lookup is already sliced to one category, so any hit is in the right one; (b) phrases are compared as a **set, not an ordered sequence**, because the catalog returns a block as a phrase-keyed map and order is not recoverable from it at attach time. See *Findings raised against this revision* |

## Interpolation recovery (ICU)

| Rule | Status | Evidence |
|---|---|---|
| ICU-1 | provisional | A missing `select`/`plural` argument selects the `other` branch. `tests/Format/InterpolatorTest.php::testIcuRecoversWhenTheCallerSuppliesNoParamsAtAll` covers the case that previously escaped — the empty-params short-circuit in `Client::interpolate()` is gone, so a phrase whose ICU the caller knows nothing about still recovers |
| ICU-2 | provisional | A present-but-null argument is treated as absent. Covered by the shared fixture row *"NULL is missing, not zero"* in `tests/fixtures/interpolation-reference.json`, asserted by `::testInterpolationMatchesTheReferenceFixtures`. Recovery withholds missing arguments from intl so a null cannot substitute the recovered `{argName}` with empty |
| ICU-3 | provisional | `#` renders the literal `{argName}` and recovery descends into nested nodes: `::testRecoveryDescendsIntoASuppliedNode`. That test is **characterisation, not a regression pin** — a missing argument has no CLDR category to lose, so it produces the same string under the previous implementation; the discriminating vectors are in `cldrFewProvider` |
| ICU-4 | provisional | `::testRecoveryEmitsADebugNoticeNamingEveryDefaultedArgument` asserts the notice fires **and** names every defaulted argument and the locale — the rule's own test requirement, since a test that only checks the rendered string passes whether or not anyone can diagnose it. `::testRecoveryNoticeIsDedupedPerLocaleAndTemplate` pins the dedup, `::testNoNoticeWhenNothingWasDefaulted` the negative |
| *(no rule id)* | provisional | **Supplied arguments retain CLDR selection while others recover.** `::testSuppliedPluralKeepsCldrSelectionWhileAnotherArgumentRecovers` — pl/ru/ar at n=3 render `few`, with en-us as the control that proves the assertion tracks CLDR rather than a string. Previously one missing argument routed the whole string to the simplified renderer, degrading every other argument's plural selection |

## Hint lane / SSR

| Rule | Status | Evidence |
|---|---|---|
| HINT-1 … HINT-8 | n/a (profile: server) | Per HINT-2 a server SDK is the origin: it registers or it logs, never reports |
| HINT-10, HINT-11 | n/a (profile: browser) | Credential-parameter rules. Originally marked *browser, server*, which made them vacuous here — a server SDK never reports, so it can never transmit a credential-bearing URL. Rescoped to `browser` in spec `a37b0288` after this file raised it |
| HINT-12 | n/a (profile: browser) | Same rescoping. Its server mirror is backend behaviour rather than an SDK rule, and is documented alongside only because the union property is unreadable with the halves separated |
| SSR-1 … SSR-3 | n/a (profile: server) | JS-only strategies |

## Write grants

| Rule | Status | Evidence |
|---|---|---|
| GRANT-1 … GRANT-4 | **not implemented** | `HttpClient::getHeaders()` sends only `X-Authorization`. **⚠️ Implementing this requires a change elsewhere.** [GATE-1](#capability)'s implementation short-circuits a `read` key to `false` from the cached `key_type` with no per-request call — correct only while no grant is sent, since the server's gate is `type-allows-write OR valid-grant`. Adding grant support MUST also remove the `KEY_TYPE_READ` short-circuit in `Client::resolveWriteDecision()`. Enforced by `tests/Http/HttpClientTest.php::testNoWriteGrantHeaderIsSent`, which fails the moment an `X-Write-Grant` header appears |

## Caching · Observability · Wire

| Rule | Status | Evidence |
|---|---|---|
| CACHE-1 | provisional | `tests/Html/PageTranslatorTest.php::testRegisteredItemsCacheKeyIsProjectScoped`. The registered-items key was the only un-namespaced key in the SDK |
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

## Findings raised against this revision

Three places where an honest row could not simply be written, raised with the spec author
rather than resolved locally.

**1. ICU-1/2/3 were met by the interpolator and defeated by the public path — FIXED.** `Client::interpolate()`
short-circuits on `empty($params)`, which bypasses ICU recovery entirely. Measured through
`translate()` against a catalog value of `{count, plural, one {# item} other {# items}}`:

```
no params        -> '{count, plural, one {# item} other {# items}}'   raw ICU, shipped to the page
unrelated param  -> '{count} items'                                    recovery works
count supplied   -> '3 items'
```

The no-params call is the most common shape and got the worst output — a translator writing a
plural into the catalog shipped ICU source to end users whenever the caller passed nothing. Same
class as the 1.3.1 defect.

*Resolved:* the short-circuit in `Client::interpolate()` is removed, and the interpolator's own
fast path now skips only text with no ICU construct in it. Pinned by
`::testIcuRecoversWhenTheCallerSuppliesNoParamsAtAll`, which was red against the previous tip.

**2. CID-4 was unimplementable as written against a phrase-keyed catalog — ADOPTED, spec `a37b0288`.** The rule requires
comparing `(category, ordered phrases)`. This SDK's catalog returns a content block as a map keyed
by source phrase, so **order is not recoverable at attach time** — only the set is. The guard is
therefore set-based, which still defeats every known collision mode (all are collisions over
differing *content*), but it is strictly weaker than the rule: two blocks with identical phrases in
different orders are distinct under CID-1 and indistinguishable here. Either the rule should say
"ordered where the representation preserves order", or the catalog must carry order.

*Resolved:* the rule now reads "ordered where the representation preserves order; a set
comparison is conforming where it does not", and names the case where a set guard is
strictly weaker. **This row is now `provisional (met)` in substance**; it stays labelled
partial until the spec revision this file is written against is bumped, so the status and
the cited revision cannot disagree.

**3. HINT-10/11 were marked `browser, server` while HINT-2 forbids server SDKs from reporting — ADOPTED, spec `a37b0288`.** If a
server SDK never reports, it can never transmit a credential-bearing URL, so the rules are
vacuously satisfied and cannot be tested here. Either the profile line is over-broad or HINT-2's
scope has changed; recorded as `n/a` on the HINT-2 reading rather than claimed as met.

*Resolved:* all three rescoped to `browser`. The `n/a` rows above now match the spec
rather than anticipating it.

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

**Four rules regressed in `PageTranslator` and the port never touched that file.** The
legacy fallback covered `translateContentBlock()` but not `translatePage()` — the path a
website most likely renders with — so the hazard stayed fully live where it was most
likely to fire. GATE-5, REG-12 and CACHE-1 had all been fixed once and were reintroduced
by `main`'s v1.3.0 queue rearchitecture. My rule-by-rule check ran against `Client`;
`PageTranslator` duplicated those rules and was invisible to it. Both paths now share one
resolution entry point, so the next drift has nowhere to hide.

WIRE-2, WIRE-3 and WIRE-4 were all still live on `main` and were **absent from the
re-land port list**, despite having been fixed on the branch that list was drawn from.
They were found by checking rule-by-rule against running code rather than by reading the
list — and WIRE-4, the only gap here that can turn a working page into a 500, would
otherwise have shipped again.

One of them nearly shipped broken from this very file's process: the WIRE-2 fix
initially called a `raiseForStatus()` helper that did not exist. `php -l` passed, because
lint does not resolve method calls. It was caught by exercising every status/body
combination, which is the check that actually distinguishes "parses" from "works".
