# Conformance — langsys/php-sdk

Spec version implemented: **spec blob `042dedb5`** (`langsys2` @ `c6b08d11`, branch
`feature/838_write_key_gating`, fetched 2026-09-12T02:57:50Z via
`git rev-parse origin/feature/838_write_key_gating:docs/sdk-spec.mdx`)

**Coverage: 53 of 79 rules bind this SDK** (profiles `all` or `server`); 26 do not
(`browser`, `binding` — this repo is a core, not a binding). All 53 are rowed. Computed by
the script at the foot of this file, not by eye.

**Rebased from blob `45cdddf8` (41 binding) to `042dedb5` (53 binding).** Spec v8 adds three
families that all bind a server core — TOK-1…5 (tokenizer canonicalization), MARK-1…2
(identity stamping) and SRV-1…5 (serving translated HTML). Nothing that was rowed became
unrowed. The previous rebase note recorded the move from `06ae105a` (45 binding), where the
four that left were GRANT-1…4, re-profiled `all` → `browser`.

**Three rules here are implemented against rulings relayed AHEAD of publication.** The
operator ruled that SVG text is translated (so TOK-1 excludes math, not svg), that the
collapsed whitespace set is JavaScript's `\s` rather than PCRE's, and that `%name%`
normalises at capture. These land in spec **8.0.1**, which did not exist when this file was
written — the branch tip still carried `042dedb5`, so the blob above is the one this file can
actually derive, and the three rows say so rather than citing a hash that cannot be checked.
Re-derive and re-row when 8.0.1 is published. This is a deliberate exception to the rule the
rest of this lane held to — never implement ahead of the spec — made on the operator's
explicit instruction after the alternative was put to them.

**The blob moved mid-lane, which is why it is re-derived here rather than copied from the
brief.** This lane opened citing `b657b490`; by the time these rows were written the branch
tip carried `042dedb5`. The difference is the publication marker alone — *(authored)* →
*(published)* — and **no rule changed**, but a file that cites a blob it did not derive is
asserting rather than checking. The blob and commit above are re-derived at each write of
this file: a version line carried by hand is a version line that goes stale silently, which
is how the "Known gaps" section below once came to understate coverage this file already had.
Profile: **server**

Every rule maps to the test that proves it. A rule with no test is NOT IMPLEMENTED —
that is a fact about this SDK, not a documentation gap. Do not write "yes" without a
test reference: a self-reported claim is exactly the failure this file exists to prevent.

**Evidence grade (CONF-2).** Every test cited is `mock` — assertions against
`tests/Mock/MockHttpClient.php`, which accepts whatever it is given. Under CONF-1 that
does not count as proof, so implemented-and-tested behaviour is recorded as
`provisional`, never `implemented`. Nothing here reaches `implemented` until a
`contract` fixture exists that can reject a bad request and holds state across calls.

Behaviour changes were each checked red before landing, by reverting `src/` and
re-running. A regression test never seen red is a guess.

The baseline is **the tip each change was written against**, not `origin/main`. This is
a re-land branch, so `origin/main` does not contain the earlier work and reverting to it
would redden rows for reasons unrelated to the change under test — which proves nothing.
Concretely: the port was checked against `origin/main`, the full-spec audit round against
`5fa4d48`, and the audit-fix round against `c2a2118`. Each new guard was additionally
mutated in place and confirmed to redden a named assertion; a guard no test can turn red
is not a proven guard.

## Capability

| Rule | Status | Evidence |
|---|---|---|
| GATE-1 | provisional | `tests/ClientTest.php::testCanWriteWithIpWriteKeyThatServerReportsWriteEnabled`, `::testCannotWriteWithIpWriteKeyThatServerReportsNotWriteEnabled`, `::testPresentFlagWinsOverKeyTypeOnAFreshResponse`, `::testFallsBackToKeyTypeWhenTheApiOmitsWriteEnabled`, `::testFallsBackToKeyTypeForAReadKeyWhenTheApiOmitsWriteEnabled`. **⚠️ Depends on this SDK sending no write grant** — `read` and `write` keys are answered from the cached `key_type` with no round-trip, which a write grant would invalidate. That is now a *spec obligation* rather than a gap in this SDK: GRANT is `browser`-profile and a server SDK MUST NOT send `X-Write-Grant`, so the short-circuit is correct for as long as the SDK conforms. See the GRANT row |
| GATE-2 | n/a (profile: server) | Synchronous SDK — no unknown window exists. The residual obligation is REG-10 |
| GATE-3 | provisional | `::testWriteDecisionIsNeverWrittenToTheCache`, `::testWarmCacheStillResolvesPerRequestForAnIpWriteKey`, `::testResetRequestStateClearsTheWriteDecision`, and `::testWarmCacheCostsNoAuthorizationCallForAReadKey` (pins that correctness costs no per-render request) |
| GATE-4 | provisional | `::testWriteDecisionIsNeverWrittenToTheCache` — the `authorize-project` row, where the flag sits *inside* `data` and must be stripped from the body before caching, not merely dropped with an envelope |
| GATE-5 | provisional | `tests/Html/PageTranslatorTest.php::testDiscoveredItemsAreNotRecordedAsRegisteredWhenOnlyQueued` — a queued item is an attempt, not an acceptance, and is not recorded until the server accepts it. Also `tests/ClientTest.php::testLegacyResolvedContentBlockIsNotQueuedForRegistration` |
| GATE-7 | provisional | Every path that can detect unregistered content feeds the register lane: `Client::translate()` (`queuePhraseForRegistration`), `Client::translateContentBlock()` (`queueContentBlockForRegistration`), and `PageTranslator` (both, via the client). Verified fresh against this tip — a path that fed neither existed on an earlier branch (`lookupContent()`) and does not exist on this line. Report lane is n/a (HINT-2), so "exactly one" is satisfied by there being one to feed. Carried by `tests/ClientTest.php::testTranslateQueuesNewPhrase`, `::testTranslateContentBlockQueuesNewBlock` and `tests/Html/PageTranslatorTest.php::testDiscoveredItemsAreNotRecordedAsRegisteredWhenOnlyQueued` — one per path, so removing any path's feed reddens a named test rather than only this prose |
| GATE-8 | provisional | Four constraints, measured. **c1** — the fallback fires ONLY for the plain arm: `tests/ClientTest.php::testFallsBackToKeyTypeWhenTheApiOmitsWriteEnabled` plus `::testFallsBackToKeyTypeForAReadKeyWhenTheApiOmitsWriteEnabled`; measured across `write`/`read`/`ip_write`/`attested_session` with the flag absent, only `write` yields true. **c2** — nothing is latched at init, and `resetRequestState()` clears per request. **Noted deviation, stated as a bound rather than as a reassurance:** `read`/`write` are answered from a cached `key_type`, so the SDK can be wrong for up to one cache TTL about anything that changes a plain key's write capability — a server that starts emitting `write_enabled`, or a key whose type is changed server-side. It is not wrong today, because for the plain arms `key_type` and `write_enabled` cannot disagree; it becomes wrong the moment they can. Sending a write grant is exactly that change — and since the spec now forbids a server SDK from sending one, the condition holds by conformance rather than by luck. The TRIPWIRE in `tests/Http/HttpClientTest.php` is what turns that from a comment into a failing test. **c3** — vacuous here: no hint lane exists, so the two absences cannot diverge. **c4** — not an SDK obligation |
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
| REG-9 | provisional | `tests/ClientTest.php::testChunksToTheServerAdvertisedBatchLimit` — 7 phrases at an advertised limit of 3 chunk 3/3/1. **CORRECTION:** the previous row claimed the limit was read from `langsys_settings`, and that was measurably false. `syncBatchLimit()` read `langsys_settings.batch_limit`, one level short of the server's `langsys_settings.translatable_items.batch_limit`, so the server-provided limit **never applied** and the SDK always used its own default of 200 — meaning a server that lowered the limit would have had its oversized batches rejected and registration would have failed wholesale. Confirmed three ways before fixing: the spec's REG-9 text, `LangsysSettingsResource`, and a live `authorize-project` response returning `{"translatable_items":{"batch_limit":200}}`. **And the regression that fix introduced:** reading the value for the first time also meant honouring a bad one — a project carrying `0` reached `array_chunk()`, which raises a `ValueError` on a non-positive length, from the shutdown handler, where it is a fatal AFTER the response is sent. Non-positive limits are now ignored in favour of the default (`::testNonPositiveServerBatchLimitIsIgnored`, 0 and -1), and the flush catches `\Throwable` rather than `\Exception` so no `\Error` can escape a best-effort lane (`::testFlushSurvivesAnErrorNotJustAnException`) |
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
| CID-1 | provisional | `tests/Html/HtmlParserTest.php::testCustomIdFixtureIsSelfConsistentAtEveryLayer` asserts the fixture **programmatically at each layer** — recorded codepoints describe the input, the canonical serialization reproduces byte-for-byte against `serialized_hex` (hex of the exact string passed to `md5()`, recomputed rather than trusted), the hash of those bytes is the recorded `custom_id`, and `generateCustomId()` produces the same. `::testCustomIdFixtureRetainsItsUnicodeCoverage` pins ≥15 codepoints above U+00FF and ≥1 non-BMP, since an ASCII-only suite cannot tell a byte hash from a UTF-16 one. Serialization is **three** flags — `JSON_UNESCAPED_LINE_TERMINATORS` is not implied by `JSON_UNESCAPED_UNICODE`, and without it U+2028/U+2029 hash differently from `JSON.stringify`; row 13 locks that with raw `e280a8`/`e280a9` bytes |
| CID-2 | provisional | Enforced **inside** the id function, not at call sites: `$cat = ($category === null \|\| $category === '__uncategorized__') ? '' : $category;`. `tests/Html/HtmlParserTest.php::testGenerateCustomId` covers the null case |
| CID-3 | provisional | Emits only the CID-1 form — `::testLegacyIdIsNeverSentToTheApi`, narrowed to legacy shapes that DIFFER from the current id, since for ASCII content the code-unit hash and a byte hash agree exactly and finding that value in a payload proves nothing. **Tolerance breadth per the reversed ruling:** the rule binds anything that READS catalogs, not only the implementation that produced the ids, so this SDK now also tolerates the JS SDKs' pre-fix **code-unit** hash — a PHP page rendering content a JS SDK registered must resolve it, or it re-registers under the current id and strands those translations. Asymmetric risk is the argument: failing to tolerate costs real translations, while tolerating costs a lookup that misses, and every attach is gated by the CID-4 content guard regardless. The code-unit hash is **reimplemented in PHP and verified against executed TS vectors** — not shared code, so it can drift and the fixture is what would catch it. `tests/fixtures/legacy-custom-id-reference.json`, adopted byte-identically from `langsys-python` (blob `dc5556466dc54fe82e81ac9fdbf4549b2b76e7ce`), whose 20 vectors were generated by executing the TS core: all 20 match. `tests/Html/HtmlParserTest.php::testLegacyCustomIdsCoverBothToleratedShapes` proves the HASH; `::testEveryLegacyVectorIsReachableThroughTheLookupPath` proves the LOOKUP; `tests/ClientTest.php::testNonAsciiLegacyBlockResolvesThroughTheClient` proves a client actually RESOLVES such a block (Cyrillic, Japanese, Greek, Hebrew, Arabic, astral-plane) — every other Client-level legacy test is ASCII, where the two hashes agree, so a byte-hash stub passed all of them; it now reddens 8 cases instead of 2 — it drives the public `legacyCustomIds()` with each row's own inputs, which the hash test cannot do and which is what found the gap below. **Gap found and closed:** the legacy JS generator did not coalesce `null`, so an untyped caller's block was filed under `[null,[...]]` — a string no slot in `legacyCustomIds()` could produce, stranding that content. **Deliberate exclusion, asserted as one:** `md5codeunit('["__uncategorized__",[...]]')` is NOT looked up; the sentinel is this SDK's own spelling and only ever went out on the pipe-join form, so it is an id nothing ever wrote |
| CID-4 | **partial** | `Client::legacyBlockMatchesPhrases()` compares phrases before attaching, and the guard fails toward no-match: `::testLegacyIdResolvingToDifferentContentIsRejected`. **Two deviations, both structural rather than chosen** — (a) *category* is not compared explicitly because the lookup is already sliced to one category, so any hit is in the right one; (b) phrases are compared as a **set, not an ordered sequence**, because the catalog returns a block as a phrase-keyed map and order is not recoverable from it at attach time. See *Findings raised against this revision* |

## Interpolation recovery (ICU)

| Rule | Status | Evidence |
|---|---|---|
| ICU-1 | provisional | A missing `select`/`plural` argument selects the `other` branch. `tests/Format/InterpolatorTest.php::testIcuRecoversWhenTheCallerSuppliesNoParamsAtAll` covers the case that previously escaped — the empty-params short-circuit in `Client::interpolate()` is gone, so a phrase whose ICU the caller knows nothing about still recovers |
| ICU-2 | provisional | A present-but-null argument is treated as absent. Covered by the shared fixture row *"NULL is missing, not zero"* in `tests/fixtures/interpolation-reference.json`, asserted by `::testInterpolationMatchesTheReferenceFixtures`. Recovery withholds missing arguments from intl so a null cannot substitute the recovered `{argName}` with empty |
| ICU-3 | provisional | `#` renders the literal `{argName}` and recovery descends into nested nodes: `::testRecoveryDescendsIntoASuppliedNode`. That test is **characterisation, not a regression pin** — a missing argument has no CLDR category to lose, so it produces the same string under the previous implementation; the discriminating vectors are in `cldrFewProvider` |
| ICU-4 | provisional | `::testRecoveryEmitsADebugNoticeNamingEveryDefaultedArgument` asserts the notice fires **and** names every defaulted argument and the locale — the rule's own test requirement, since a test that only checks the rendered string passes whether or not anyone can diagnose it. `::testRecoveryNoticeIsDedupedPerLocaleAndTemplate` pins the dedup, `::testNoNoticeWhenNothingWasDefaulted` the negative |
| ICU-5 | provisional | **Outcome:** a recovered `{argName}` literal survives the formatter call, and a supplied `select`/`plural` keeps full CLDR selection while other arguments recover. `::testSuppliedPluralKeepsCldrSelectionWhileAnotherArgumentRecovers` — pl/ru/ar at n=3 render `few`, with en-us as the control proving the assertion tracks CLDR rather than a string. `::testRecoveredLiteralSurvivesTheFormatterCall` covers the literal's survival across typed arguments, absent **and** present-and-null, with supplied controls. **Mechanism here is `array_diff_key($params, array_flip($missing))`** — one conforming way to reach the outcome, not the outcome itself: the JS leg conforms differently, by replacing the node so no binding site exists for the formatter to fill. Confirmed against the final clause (langsys `3712d5a2`), which states the obligation as the outcome and names both mechanisms as conforming |

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
| GRANT-1 … GRANT-4 | n/a (profile: browser) | **Re-profiled `all` → `browser` in `cde6acdb`; this row was previously "not implemented".** The server posture is no longer an absence but an affirmative clause — *a server SDK MUST NOT send `X-Write-Grant`* — so the evidence is unchanged but its meaning is: `tests/Http/HttpClientTest.php::testNoWriteGrantHeaderIsSent` is now the **non-participation test the spec asks for**, not a placeholder standing in for unbuilt work. `HttpClient::getHeaders()` sends only `X-Authorization`. The test still doubles as the TRIPWIRE described below, and that obligation survives the re-profiling: if this SDK ever grows grant support it stops satisfying the clause, and `Client::resolveWriteDecision()`'s `KEY_TYPE_READ` short-circuit becomes wrong at the same moment |

## Caching · Observability · Wire

| Rule | Status | Evidence |
|---|---|---|
| CACHE-1 | provisional | Every key is scoped by project **and by everything else that changes the answer**: `translations_<project>_<locale>`, `registered_items_<project>_<category>`, and `auth_<project>_<sha256(apiKey)[0:12]>`. The auth key previously omitted the key identity, and the authorize response depends on which key asked — so two keys on one host shared an entry and a read key inherited a write key's `canWrite()` with **zero HTTP calls**. Both contamination directions pinned as a shadow pair: `::testReadKeyDoesNotInheritAWriteKeysCachedCapability` (attempts writes it may not make) and `::testWriteKeyDoesNotInheritAReadKeysCachedCapability` (silently stops discovering). The key material is hashed, never raw — cache keys land on shared filesystems and in Redis keyspaces: `::testAuthCacheKeyCarriesNoRawKeyMaterial` |
| OBS-1 | **not implemented** | A write-expected key resolving `write_enabled: false` that never queues anything produces no diagnostic |
| WIRE-1 | provisional | `tests/Http/HttpClientTest.php::testAuthenticatesWithTheXAuthorizationHeader` |
| WIRE-2 | provisional | `tests/Http/HttpClientTest.php::testEmptyBodyOnSuccessIsNotAParseError`, `::testEmptyBodyOnErrorStatusRaisesTheMatchingException`, `::testEmptyBodyOnValidationErrorRaisesValidationException`, `::testMalformedJsonStillRaisesAParseError` |
| WIRE-3 | provisional | `tests/ClientTest.php::testUncategorizedPhrasesDoNotSendTheSentinelOnTheWire`. All three registration paths now normalize through one `TranslatableItems::normalizeCategory()`; previously `createContentBlocks()` stripped the sentinel while `createContentBlock()` and `createPhrases()` sent it, so identical blocks landed with different stored categories. Locale form is lowercase `xx-yy`, which is correct — the backend stores and compares lowercase |
| WIRE-5 | provisional | `tests/Http/HttpClientTest.php::testRedirectedRequestsActuallyArriveAtTheDouble` (both mechanisms). The evidence here was previously a citation of `src/Config.php:80` and the README — that the knob EXISTS, which is not the rule. The test now stands up a real server on a loopback port (`tests/fixtures/wire5-double.php`), points an otherwise untouched client at it via each mechanism, and asserts on the request the server RECEIVED: path, `Host`, and that credentials still travel. Documented on both surfaces an integrator reads: `api_url` constructor option and `LANGSYS_API_URL` env var, in the README's environment table and its constructor-options example. The env var is the load-bearing half — it redirects an existing, unmodified integration, which a constructor option cannot |
| WIRE-4 | provisional | Two fixes, because one was not enough. **Breadth:** every entry-point seam catches `\Throwable`, not `\Exception` — an `\Exception`-only catch is an enumeration of the failures we thought of, and an `\Error` (a `TypeError` from a wrong-shaped cache hit) escaped it and became a 500 on a customer page. **Source:** a malformed cache entry is now treated as a MISS and deleted, so the next request repopulates — degrading on every call while a poisoned entry sits out its TTL is the lesser fix. **Depth:** the shape check is depth 2, not depth 1. A top-level array of SCALAR slices satisfied `is_array()` and then raised a `TypeError` at the first index into a category — reproduced throwing from all three entry points, with the entry left poisoned for the rest of its TTL. **Origin:** the SDK was manufacturing the poison it guarded against — `getTranslations()` wrote whatever the API returned straight into the shared cache, so one malformed response was re-read by every later request; the payload is now validated in `Translations::getTranslationMap()`, which **throws** rather than returning `[]`, before it can reach a cache. **Correction to this row's previous wording, and to the test it described:** "depth 0 AND depth 1 across all three entry points" was true of the reproduction harness and false of the committed test — the depth-1 vectors were keyed on a category no render reads, so the bad slice was never indexed and the test was green against the unfixed code. Re-keyed to `Client::UNCATEGORIZED` they throw 9/9 without the fix. **And the fix's own regression:** returning `[]` for a rejected payload made an empty catalog the cached value, blanking translations for the whole TTL — worse than the defect, and unable to self-heal at all under a read key. `::testMalformedCacheEntryDoesNotReachTheRender` (depth 0 and depth 1, across all three entry points) and `::testMalformedCacheEntryIsInvalidatedRatherThanEndured` (the same vectors, `translate()` only — invalidation is a property of the shared read, not of the caller), `::testARejectedServerMapDoesNotBlankTheCatalogForTheTtl` (three requests, read key, second and third against a healthy server) with `::testAWellFormedServerMapStillPopulatesTheCatalog` as its positive control, and `::testMalformedServerMapIsNeverWrittenToTheCache`. **And the carve-out that re-opened it:** a 2xx with no `data` key was read as an empty catalog and cached, on the reasoning that a project with no translations legitimately has one. False — `ApiResponse::resourceResponse()` assigns `data` unconditionally, so an empty catalog arrives WITH the key, and `HttpClient::handleResponse()` turns any empty-bodied 2xx into `[]`, making an empty 200 from a proxy enough to blank a project for a TTL. Now rejected (`::testAResponseWithNoDataKeyIsTreatedAsMalformed`). **Cost of failing rather than answering:** a failure is memoized per REQUEST — never written to a cache — so an incident costs one fetch per locale per request rather than one per phrase (`::testAFailedCatalogFetchIsAskedOncePerRequest`, which also pins that `resetRequestState()` clears it). Plus the unreachable-API vectors via `tests/Mock/ThrowingHttpClient.php`, and the `\Error` vectors via `tests/Mock/ErrorThrowingHttpClient.php` and `tests/Mock/ErrorThrowingCache.php` — every `\Throwable` seam is now individually reddened by narrowing it to `\Exception`, which six of the seven were not. The enumeration itself was the defect twice over: "all five seams" counted the five I had grepped for, and the one it missed (`Client.php:1142`, the locale fallback) sits BEFORE the entry-point try, so an `\Error` there escaped all three render paths |

## Tokenizer canonicalization

The identity contract every implementation shares. These rules decide what a *phrase* is, and a
content block's `custom_id` is a hash of its phrase list in order — so a disagreement here is not
cosmetic, it re-keys blocks and strands their translations.

Measured against `tests/fixtures/canonicalization-reference.json`, authored by
langsys-js-typescript and adopted byte-identically (`6596faf:tests/fixtures/canonicalization-reference.json`,
blob `e4c1f185974fbf2ebda6154f36b8ed7416f1d7fa`). **This SDK was 13 of 19 when the file was
written**; the six misses are the two causes below.

| Rule | Status | Evidence |
|---|---|---|
| TOK-1 | provisional | `tests/Html/HtmlParserTest.php::testCanonicalizationMatchesTheCrossSdkVectors` (rows `script-subtree`, `style-subtree`, `noscript-subtree`) plus the corrected `tokenizer-reference.json` row. **The content-block path had no skip list at all** — `HtmlParser::walkNode()` harvested `<script>`, `<style>`, `<template>` and `<noscript>` text as phrases, so minified CSS was registered in the shared catalog and sent for machine translation; and because the id hashes the token list, an analytics payload carrying a nonce re-keyed its block on every render. The page path (`PageTranslator::SKIP_ELEMENTS`) had always skipped them, so this was the block path only. **Ruling folded in (spec 8.0.1):** the excluded set is script/style/template/**math** — `svg` is NOT excluded, because SVG `<text>` is visible copy a reader is expected to read, while MathML is notation. **Fleet state:** this SDK AGREES with the TS core on **both** — svg (`<p>Hello <svg><text>Label</text></svg></p>` is `c1878e2119418b1ed433babeb4a09297` in both) and now math, which the TS core excluded in `105943f`, five minutes after this row was written claiming a divergence. The row had gone stale within the hour; re-measured rather than carried forward. **Both paths now conform**, after one reverted attempt. `<svg>` is handled as a LEAF where it is found, not promoted to a block element: promoting it made `containsNestedBlocks()` true for an icon's parent, so the walker recursed past it and skipped the parent's own text — `<p>Click <svg/> to continue</p>` registered **nothing**. And any subtree containing an `<svg>` is kept out of the simple-phrase branch, which applies by assigning to `textContent` and so replaced every `<path>` with the translated string; `<p><svg><path/><text>Label</text></svg></p>` rendered as `<p>Etiqueta</p>`. Stated as three behaviours rather than as structure, across all THREE routes text can reach — the page walk, the content-block walk, and a tokenized `data-ls-phrase` run: SVG `<text>` is tokenized and translated; an inline `<svg>` never costs its parent's own text, and the block's tokens are its text plus the svg's in document order; translation replaces the text node in place and never touches the svg's elements. `tests/Html/PageTranslatorTest.php::testThePagePathTranslatesSvgTextButNotMath`, `::testAnInlineIconKeepsDocumentOrder` (4 vectors), `::testTranslatingSvgTextLeavesTheDrawingIntact` (3 vectors), `::testTheBlockPathTokenizesSvgTextAndNotMath`, `::testTextAroundAnInlineSvgIconIsStillRegistered`, `::testAStandaloneSvgSurvivesRendering`. Mutation: removing the standalone-svg branch reddens 2, removing the `containsGraphic` guard 1, restoring `svg` to `SKIP_ELEMENTS` 2, and **promoting `svg` back to a block element reddens 8** — the regression that shipped once is now caught. `::testSvgTextIsTranslatedAndMathIsNot`, `::testTok1ExcludesAllFourElementsInOneDocument`, `::testTemplateContentsAreNotHarvested`. **Each excluded element is now mutated individually** — dropping any one reddens a named case, where before `template` reddened nothing at all and `::testScriptAndStyleTagsIgnored` asserted only that ordinary text was PRESENT, so it passed while script source was being harvested |
| TOK-2 | provisional | `::testUnicodeWhitespaceCollapsesLikeAnyOtherWhitespace` (6 vectors — internal, edges, and the whitespace-only count case), `::testAWhitespaceOnlyNodeDoesNotChangeABlockId`, and the fixture's `nbsp-in-text`, `attr-nbsp` and `line-separators` rows. Fixed in `src/Html/Whitespace.php`, one shared helper, because **the SDK had seven copies of this normalisation and they disagreed** — six ASCII-only (`HtmlParser`, `Client`, `TranslatableItems`, `PageTranslator`×3) and one already `/u` (`MarkupTokenizer`), plus three sites in `HeadHandler` (the `<title>` and both `<meta content>` paths) that did no collapse at all. The brief named four sites; the sweep found ten. Several are registration/lookup pairs, so a phrase registered as `A long description` was looked up as `A\u{00A0}long description` and missed forever, re-registering on every render. **Ruling folded in (spec 8.0.1): the collapsed set is JavaScript's `\s`, exactly** — not PCRE's, which differs on three reachable codepoints and made each a silent id divergence: U+FEFF (PCRE no / JS yes — PHP kept it, TS dropped it), U+0085 and U+180E (PCRE yes / JS no — PHP collapsed them, TS kept them). Spelled out in `Whitespace::JS_WHITESPACE` rather than written as `\s`. `::testTheCollapseSetIsJavascriptsWhitespace` (8 vectors, controls on both sides). **Three more register/lookup pairs found in review, all of which made translation fail outright rather than merely move an id:** the page `<title>` (registered collapsed, looked up raw — so titles silently never translated, and were not re-registered either), page-path attribute values (collected trimmed, looked up raw — a miss on PLAIN SPACES, which is why attributes looked like they worked), and the edge checks that re-add padding after a translated run, which used ASCII `\s` and dropped non-breaking padding so words ran together. `tests/Html/HeadHandlerTest.php::testATitleIsRegisteredAndTranslatedOnTheSameRender` and `::testEveryHeadPhraseRegisteredIsFoundOnApply`, `tests/Html/PageTranslatorTest.php::testPageAttributesAreRegisteredAndTranslatedOnTheSameRender`, `tests/ClientTest.php::testNonBreakingPaddingSurvivesApply`. Mutation: dropping the JS set reddens 8 named cases; reverting the title lookup reddens 4 |
| TOK-3 | provisional | The 27-entry list in `HtmlParser`, which the spec takes as normative in this SDK's order. The order is load-bearing: it decides the sequence phrases are produced in, and therefore the id |
| TOK-4 | provisional | Satisfied by the same helper — `extractAttributePhrases()` routes every attribute value through `normalizeWhitespace()`. Proven by the fixture's `attr-nbsp` row, which is `nbsp-in-text`'s twin: the same content in an attribute must produce the same id |
| TOK-5 | provisional | `tests/Format/InterpolatorTest.php::testPercentPlaceholdersAreAccepted` (8 vectors). **`%name%` previously reached the reader verbatim** — a placeholder rendered as literal text on the page. Normalised to `{name}` once, before ICU detection, so no downstream path learns a second spelling. Rewritten **only for keys the caller supplied**, which is the safety property: `Save 20% on 5% APR` and `width: 100%` have no matching parameter and are returned untouched. **Ruling folded in (spec 8.0.1): `%name%` normalises to `{name}` at CAPTURE too**, not only at render. The stored phrase previously carried whatever the author wrote, so `Hello %name%` and `Hello {name}` were two phrases with two ids and the JS core stored only the brace form — measured `bb74011a…` here against `1e4b462c…` there, which now agree. Both sides go through `Html\Canonical::phrase()`, one function, because applying this at capture and not at lookup would have recreated the exact register/lookup break the whitespace work had to be fixed for twice. At capture there is no parameter list to gate on, so the narrow pattern is the only guard: `Save 20% on 5% APR` and `width: 100%` are untouched; the knowingly-accepted residual is prose where two signs bracket a bare word. `tests/Html/HtmlParserTest.php::testPlaceholdersAreCanonicalisedAtCapture` (6 vectors), `::testBothPlaceholderSpellingsProduceOneBlockId`, `::testCapturedPlaceholderPhrasesAreFoundOnLookup`. Mutation: removing the render-side normalisation reddens 2 cases; removing the capture-side one reddens 5 |

## Identity stamping

| Rule | Status | Evidence |
|---|---|---|
| MARK-1 | provisional | `tests/ClientTest.php::testARenderedBlockIsStampedWithItsResolvedId`, `::testAMultiRootBlockIsNotStamped`, `::testAnExistingStampIsNeverOverwritten` (both spellings), `::testABlockIsNotStampedWhenTheLookupFailed`. Stamped only where the fragment has exactly one element root: claiming a block's id for one of several siblings would make that sibling read as the block the next time anything parsed the page. Not stamped when the lookup failed — that would publish an identity claim built on an outage. An existing marker is another writer's claim and is never overwritten. **Corrected after review:** the guard counted ELEMENT children only, so `Buy <strong>now</strong>` stamped the `<strong>` with the whole fragment's id while that element's own subtree derives a different one — a false identity claim in the served bytes, which any later reader believes. The fragment must now be a single node entire (`::testABlockWithTextSiblingsIsNotStamped`, 3 vectors, with `::testASingleElementFragmentStillStamps` as the control) |
| MARK-2 | provisional | `tests/Html/HtmlParserTest.php::testPhraseMarkerIsReadUnderBothSpellings` (7 vectors including off-values), `::testContentBlockMarkerIsReadUnderBothSpellings` (4), `::testJsSpellingKeepsABlockTogetherOnThePagePath`. Read accepts `data-ls-*` and `data-langsys-*`; what this SDK WRITES stays one spelling. End to end on the page path: `tests/ClientTest.php::testAJsRenderedHostIsNotReSplitOnThePagePath`, with `::testTheSameHostWithoutAMarkerIsSplit` as the control that the page path registers anything at all. **Two limits, stated because the row would otherwise overclaim:** (1) the CONTENT-BLOCK path honours neither spelling — a marked run inside a block still splits, deliberately, since that path has no tokenized branch to render a tokenized entry; (2) the page path reads these markers as BOOLEANS only, so a host carrying `data-ls-contentblock="<id>"` is not translated *from that id* — PHP re-derives the id from the content and uses that. Mutation: dropping the JS spelling reddens 5 cases |

## Serving translated HTML

| Rule | Status | Evidence |
|---|---|---|
| SRV-1 | provisional | The served bytes are the translated ones by construction: `translatePage()` and `translateContentBlock()` return translated HTML synchronously, and there is no post-hydration correction step because there is no hydration. Carried by the rendering tests in `tests/Html/PageTranslatorTest.php` |
| SRV-2 | provisional | `Client::$translationsMemoryCache` is per-instance and cleared by `resetRequestState()`, which also clears the fetch-failure memo — `tests/ClientTest.php::testAFailedCatalogFetchIsAskedOncePerRequest` pins the clearing half. The long-lived-runtime hazard (Octane, Swoole, RoadRunner) is what that method exists for |
| SRV-3 | provisional | `flushPendingRegistrations()` runs from a shutdown handler — after the response is flushed — and is gated on the server's per-request write decision. `::testFlushReportsDroppedWhenTheRequestMayNotWrite` proves the read-only half; GATE-3 and GATE-5 carry the rest |
| SRV-4 | **not implemented** | **Rowed against the rule body rather than the brief.** This lane was briefed to row SRV-4 `n/a` as "the JS hydration half". That is wrong for the half the profile actually assigns here: the body's first sentence — *the server MUST hand the client the catalog it rendered with* — is the SERVER's obligation, and only the synchronous seed belongs to the browser core. This SDK has no hand-off: nothing in `src/` emits a catalog for a client to pick up. `getTranslations()` is public, so an integrator can serialise it themselves, but the SDK neither does it nor documents it. Recorded as a gap, because `n/a` here would claim a pass for work that does not exist. **Do not implement against this row without checking the spec first:** the rule's author has confirmed it over-binds a page-translation server SDK — SRV-4 is the hydration hand-off, and the profile word `server` was meant as *the server side of a hydration hand-off* (`langsys-js-server` produces a `result.catalog` for a client to seed from). `translatePage()` emits terminal HTML that nothing hydrates, so there is no client to hand a catalog to. A normative clarification is in flight via the Reviewer, after which this becomes `n/a` for the same **structural** reason as SRV-5 — no hydration model, not absent work. Held at `not implemented` until the rule is corrected, since that is the more honest of the two while the published text reads as it does |
| SRV-5 | partly provisional, partly n/a | **Also not for the brief's reason.** The profile names `server`, so this does not fall away on profile — it falls away on mechanism. SRV-5 governs *component child capture*: Svelte's re-entrant render registering 2^n copies of one miss, and React capturing a `Suspense` fallback so a block is keyed on a loading spinner. This SDK walks a DOM once and has no component model, no re-entrant render and no lazy children, so neither failure has a site here. The mechanism is named so the claim is checkable rather than asserted. **Corrected twice.** Rowing the WHOLE rule `n/a` was too broad: the *fail-loudly* half has no site here, but *once-per-subtree* applies to any walker. The first attempt to measure it counted REQUESTS, and that could not see the property — three dedupe layers (`findNewPhrasesWithCategory`'s `$seen`, the `pendingPhrases` key, the `pendingContentBlocks` id) collapse a walker producing 2^n copies down to one POST, so a re-entrant walker passed. Now asserted on the RAW WALKER output before any dedupe (`tests/Html/PageTranslatorTest.php::testADeeplyNestedMissIsWalkedExactlyOnce`), with a re-entrant mutant confirming it reddens. "Duplicates are identical so assert the count" was right about the count and wrong about the subject. SRV-4 above is the same shape and is expected to become `n/a` once its clarification lands |

## Conformance meta

| Rule | Status | Evidence |
|---|---|---|
| CONF-1 | **not implemented** | Every test here asserts against a mock that cannot reject |
| CONF-2 | acknowledged | All implemented rules recorded as `provisional` |
| *(precondition)* | provisional | **The suite must be exercising this tree, and now asserts it.** `tests/ProvenanceTest.php` resolves `ReflectionClass::getFileName()` for five SDK classes against `realpath(src/)`, asserts no vendored copy of this package shadows `src/`, and carries a **negative control** proving the guard can fail (a `vendor/` class must NOT resolve under `src/`). Written because a sibling's verification pass reported a clean red-first run that was loading FIXED source through a vendor symlink — the "before" state never existed and the check could not have failed |
| CONF-3 | provisional | Behaviour changes verified red against the tip each was written against before landing (`origin/main` for the port, `5fa4d48` for the audit round, `c2a2118` for the audit-fix round), and each new guard mutated in place to confirm it reddens a named assertion. See the note under *Evidence grade* |

## Computed summary

Produced by the script below, run against the spec blob cited in the header — not
counted by hand.

```
binding rules (all | server)  53 of 79
rowed                         53
missing rows                   0
```

```python
# Re-derive the blob and the counts together, so the header cannot drift from the body:
#
#   cd ~/Documents/dev/langsys2 && git fetch -q origin \
#     && git rev-parse --short origin/feature/838_write_key_gating:docs/sdk-spec.mdx \
#     && git show origin/feature/838_write_key_gating:docs/sdk-spec.mdx > /tmp/spec.mdx
#
# Last run 2026-09-12T02:57:50Z against blob 042dedb5 (langsys2 @ c6b08d11):
#   79 total, 53 binding, 53 rowed, 0 missing.
#
# python3 - <<'EOF'   (spec at /tmp/spec.mdx, written by the command above)
import re
spec = open('/tmp/spec.mdx').read()
binding = []
for m in re.finditer(r'^### ([A-Z]+-\d+)\s*—', spec, re.M):
    tail = spec[m.end():m.end()+600]
    pm = re.search(r'\*\*Profiles:\*\*\s*(.+)', tail)
    prof = pm.group(1).strip() if pm else ''
    if 'all' in prof or 'server' in prof:
        binding.append(m.group(1))
conf = open('CONFORMANCE.md').read()
rowed = set(re.findall(r'^\|\s*\*{0,2}([A-Z]+-\d+)', conf, re.M))
for m in re.finditer(r'^\|\s*([A-Z]+)-(\d+)\s*…\s*(?:[A-Z]+-)?(\d+)', conf, re.M):
    rowed |= {f"{m.group(1)}-{n}" for n in range(int(m.group(2)), int(m.group(3))+1)}
for m in re.finditer(r'^\|\s*((?:[A-Z]+-\d+,\s*)+[A-Z]+-\d+)', conf, re.M):
    rowed |= set(re.findall(r'[A-Z]+-\d+', m.group(1)))
missing = [r for r in binding if r not in rowed]
print(len(binding), len(binding) - len(missing), missing)
# EOF
```

## Findings raised against this revision

**SRV-4 over-binds a page-translation server SDK — ACCEPTED by the rule's author, clarification
in flight.** Rowing it `not implemented` rather than the briefed `n/a` surfaced that the profile
word `server` meant *the server side of a hydration hand-off*. Routed to the Reviewer; this file
holds the gap row until the published text changes.

**`svg` and `math` diverged between this SDK's paths — RESOLVED.** The finding as raised said
TOK-1 named four elements while `SKIP_ELEMENTS` carried six, and held both paths unchanged
pending a ruling. The ruling came (SVG `<text>` is translated, MathML is not), and all THREE
routes now agree: the content-block walk, the page walk, and the tokenized-run route
(`MarkupTokenizer::OPAQUE_ELEMENTS`, which had been missed — its comment claimed it mirrored
`SKIP_ELEMENTS`, which stopped being true the moment the page path changed, so "both paths" was
two routes out of three). See the TOK-1 row.

**A phrase wrapped in inline markup was flattened on the page path — FIXED, and it predates
this branch.** `<li><a href="#">Label</a></li>` rendered as `<li>X:Label</li>`: the link gone
from the served bytes, on `main` (224dc8b) as well as here. `replaceTextContent()` looked at
direct child text nodes only and then replaced the element's whole content with a string. It now
finds the carrying text node at any depth and replaces it in place. Nav items, card links,
buttons and table cells are all this shape. `tests/Html/PageTranslatorTest.php::testAPhraseWrappedInInlineMarkupKeepsItsMarkup`
(5 vectors), `::testABreakInsideATranslatedElementSurvives`.

**Bare text under a non-block ancestor is not registered — OPEN, pre-existing.** At body level
`<a>Click to continue</a>` registers nothing, with or without an icon inside it, on `main` and
here: the page walk only descends into elements and collects text from block elements, so text
whose nearest block ancestor is `<body>` is never reached. Recorded rather than fixed — it is
not a canonicalization defect and changing which elements the walk collects from is a wider
behaviour change than this lane should make.

**Blast radius measured on production 2026-09-12 — negligible, and no migration follows.** The figures below were **supplied by the operator from a production measurement**, not derived in this repo, which has no production access; the no-migration ruling is theirs too.
TOK-1 and TOK-2 change which `custom_id` this SDK derives, so a live content block whose
phrases carry `U+00A0`, `U+2028`/`U+2029`, or script/style source re-keys on its next
registration. Measured:

```
live content blocks affected        1  of 516
translated words behind them      794
live phrases affected               6  of 17,432   (all U+00A0)
```

Every affected phrase is the `U+00A0` case; no block was affected by the script/style or
line-separator changes. The operator has ruled that **no orphan handling and no id migration
will be built** for this. Recorded here with denominators because the figure that decides a
release should be checkable, and because the comparable measurement in August (pipe-form ids)
was reported an order of magnitude apart by two sweeps until the live-versus-deleted split
was stated — 32 live of 518, not 330 of 3,012.

**`U+FEFF` — CLOSED.** It was a live divergence (JS `\s` matches it, PCRE's does not);
the collapse set is now JavaScript's `\s` exactly, so U+FEFF collapses and U+0085/U+180E no
longer do. `src/Html/Whitespace.php` records the full comparison.

**Four parser-level splits against the JS family — MEASURED 2026-09-12, named, not papered
over.** A tokenized phrase's key IS its encoded string, so a disagreement about how the DOM is
BUILT changes the key before canonicalisation is reached; no TOK rule can close one. Measured
against `langsys-js-server` @ `8105faa`, where real Chromium 153 agreed with parse5 9 of 9 over
these families, so the expectations are the family's answer rather than one engine's. libxml2
scores **3 of 7**:

| Family | Result | What libxml2 does differently |
|---|---|---|
| implied close | **3/3 agree** | `<p>` after `<p>`, bare `<li>`, bare `<option>` all close identically |
| raw text | **0/2 diverge** | Chromium and parse5 make a `<textarea>`/`<title>` body RAW TEXT, so `<b>` is literal characters. libxml2 parses it as markup, so `<b>` becomes an element, takes a slot, and shifts every later slot index |
| foster parenting | **0/2 diverge** | Chromium and parse5 move a stray `<b>` and loose text OUT of a `<table>`, ahead of it. libxml2 leaves both inside. They also insert a `<tbody>` libxml2 does not, adding a slot level |

Each divergence is a **different key for identical source**. The keys are the ENCODED STRINGS
themselves — a tokenized phrase keys by string and never computes an id — and every one of them
is in `tests/fixtures/parse-model-reference.json`, pinned by the test below. The
content-block ids, where they move, are in the fixture too and pinned by the same test.

An earlier revision of this paragraph cited eight hashes here instead. They were **synthetic**:
`generateCustomId('UI', [encodedString])`, an invented category with the whole encoded string
wrapped as a single token — reproducible only if you guessed that construction, and not a key
any real input produces. Withdrawn rather than explained: citing an unreproducible identifier is
the failure this file exists to prevent, and the encoded strings say the same thing checkably.

This is the same shape as the `<noscript>` reversal the fleet already knows, and the same shape
as `U+000B`/`U+000C` below: **a parser-model split this SDK cannot close from its own side.**
Recorded rather than worked around, and pinned by
`tests/Html/MarkupTokenizerTest.php::testParseModelAgreementIsWhatWeMeasured` — the divergent
rows assert the divergence, so if one ever starts agreeing the row is stale and must be
rewritten, and the agreeing rows assert agreement so losing it fails. Flipping a divergence to
`agrees` reddens TWO tests, not one — the per-case assertion and the fixture-level count control. Fixture:
`tests/fixtures/parse-model-reference.json`.

**The two identity paths do not have the same exposure, and this file's first reading of which
matters more was backwards.** A `<Phrase>` key is a MARKER STRING and encodes nesting, so any
tree difference moves it. A content block's `custom_id` is a flat ORDERED TOKEN ARRAY and
survives a tree difference that preserves document order. Measured:

| Family | `<Phrase>` key | content-block `custom_id` |
|---|---|---|
| foster parenting | **splits** | **survives** — `c9a556e3…` on both trees; hoisting the stray element does not reorder anything |
| raw text | **splits** | **splits, and by ARITY** — 3 tokens against 2, which per CID-1 re-keys every block containing one |

So the raw-text family is the more damaging, not foster parenting. I reported the opposite to
two lanes before measuring the block half; the TypeScript lane raised the distinction and it is
measured here. A stray element in a table still splits a phrase silently — that part held — but
it leaves block ids alone, while `<title>`/`<textarea>` carrying markup moves both.

**`U+000B`/`U+000C` are a divergence this SDK cannot close.** libxml2 DROPS them from DOM
text entirely (`<p>a\x0Bb</p>` yields `ab`), where a JS DOM keeps them and collapses them to
a space (`a b`). So a document carrying a vertical tab or form feed derives different ids in
the two SDKs regardless of what this SDK's collapse does. Raised to the spec rather than
worked around.



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

## Known gaps

The staleness note that stood here was itself stale: it named GATE-7, WIRE-5, BIND-1…6
and HINT-9 as unassessed and the file as written against spec v6. Every binding rule is
rowed (GATE-7 at line 33, WIRE-5 at line 108; BIND-1…6 and HINT-9 do not bind a server
core). Left in place, it would have understated this file's coverage to the next reader —
the opposite of the failure it exists to prevent, and no less wrong.

The counts and the blob live in the header, and **only** in the header: the sentence
above used to carry "all 45 … against blob `06ae105a`" of its own, which survived the
rebase to `45cdddf8` and contradicted the header two paragraphs after that header cited
this very section as the example of a hand-carried line going stale. A number written
twice is a number that will disagree with itself.

One structural gap survives from it: the template's per-rule **Revision** column is not
populated here. The hashes are a lookup rather than a derivation — `sectionRevisions` in
`~/Documents/dev/langsys-docs/mcp-server/internal-docs.json` under `pages.sdk-spec`.

Remaining behavioural gaps, ranked — a status table gives a 500-serving rule and a cosmetic one
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

## Stale-phrase check

The summary script above proves the COUNTS are right. This proves the PROSE is — that no
sentence still asserts something a later revision made false. Both failures this file has
actually had were of that second kind: a "Known gaps" paragraph describing coverage the file
already had, and a rule count written twice that disagreed with itself after a rebase.

Fixed strings with expected counts, not a regex sweep, and the expectation is asserted rather
than eyeballed. The `sed` truncation matters: without it this section's own phrase list is
counted as occurrences and every expectation fails - the check would be measuring itself. A count that is deliberately non-zero (a correction record, a blob named in
several places) is written down as such, so that the NEXT person can tell a surviving
correction from a missed one.

```sh
# Run from the repo root. Exits non-zero on any mismatch.
fail=0
while IFS="$(printf '\t')" read -r exp phrase; do
  [ -z "$exp" ] && continue
  got=$(sed '/^## Stale-phrase check/q' CONFORMANCE.md | grep -Fc -- "$phrase")
  if [ "$got" != "$exp" ]; then
    printf 'MISMATCH  expected %s got %s  %s\n' "$exp" "$got" "$phrase"; fail=1
  fi
done <<'EOF'
0	binding rules (all | server)  41
0	All 45 binding
0	45 of 67
0	41 of 67
0	git show origin/main:docs/sdk-spec.mdx
2	53 of 79
5	042dedb5
1	b657b490
2	45cdddf8
EOF
exit $fail
```

**This block previously could not run at all**, and the line recording a passing run was
written from a copy that differed from it. `IFS='\t'` in `sh` sets IFS to the two literal
characters `\` and `t`, not a tab: every line lands in `$exp`, `$phrase` is empty, and
`grep -Fc ''` matches all 344 lines of the file. It printed nine mismatches and exited 1,
and had never done anything else. A check that cannot pass, recorded as passing, in the file
whose whole argument is against exactly that — noted here rather than quietly corrected.

The non-zero expectations are deliberate —
`53 of 79` appears in the header and in the computed-summary block; `042dedb5` in the
version line, both rebase notes, the ahead-of-publication note and the script provenance;
`b657b490` once and `45cdddf8` twice are correction records naming what this file used to cite.

**Run 2026-09-12 against this text**, extracted verbatim from this block, under `sh`, `bash`
and `zsh`: all nine match, exit 0. Positive control: with `All 45 binding` planted above, it
reports the mismatch and exits 1. Re-run it by extracting this block rather than by
retyping it — the previous failure was precisely a recorded run of text that differed from
what was committed.

