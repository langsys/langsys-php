# Cross-SDK fixtures

Three reference files, one per boundary in the pipeline, each asserted against by
langsys-js-typescript as well as this SDK:

| file | boundary |
|---|---|
| `interpolation-reference.json` | template + params -> rendered string |
| `tokenizer-reference.json` | HTML -> tokens -> canonical JSON -> custom_id |
| `canonicalization-reference.json` | the cross-SDK TOK-1/TOK-2/TOK-4 vectors: HTML -> tokens -> custom_id, with **each lane's measured output** beside the expectation |
| `parse-model-reference.json` | where libxml2 and the JS family build different DOMs from the same bytes, and what that does to the key |
| `custom-id-reference.json` | category + tokens -> custom_id |

**Why one per boundary rather than one suite.** Every cross-SDK defect found so
far lived in a *gap between* checks that were each individually correct. The
`<option>` double-harvest survived because the id fixtures take synthetic token
lists that never touch a DOM — the hash was verified, the thing producing its
input was not. The missing-ICU-argument bug survived because nothing pinned what
a phrase renders as.

> A chain of correct checks is not a correct check of the chain.

The fix isn't better checks, it's a reference file at each boundary — and each one
only proves anything because a *different implementation* executes it.


## `custom-id-reference.json`

**This is a shared contract, not a snapshot of PHP's behaviour.** Content block
identity is shared across every Langsys SDK: a block registered by one SDK must
resolve to the same `custom_id` in all of them, or the same content is stored
twice in the catalog everyone reads.

Each entry records:

| field | meaning |
|---|---|
| `category`, `tokens` | the inputs to `HtmlParser::generateCustomId()` |
| `canonical_json` | the exact bytes that get hashed |
| `custom_id` | `md5()` over the UTF-8 bytes of `canonical_json` |

`canonical_json` is recorded on purpose. Without it a matching `custom_id` proves
the outcome while hiding *whether the two implementations agreed for the right
reason* — two encoders could disagree on slash or unicode escaping and still
collide onto the same id by luck. With it, another SDK can hash the exact same
bytes rather than reimplementing our encoding choices
(`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS`,
which is what `JSON.stringify` produces).

**Three flags, not two.** The third is not implied by the second:
`JSON_UNESCAPED_UNICODE` still escapes U+2028 and U+2029 as `\u2028` / `\u2029`,
while `JSON.stringify` emits them raw and has no flag to do otherwise. Two flags
therefore produce a *different id* for any block containing a line terminator.
A 78-codepoint sweep found those two to be the only disagreement between the
encoders, non-BMP included — narrow enough to miss, and permanent once content
carrying one is registered. Row 13 exists to lock it: its `serialized_hex`
carries the raw `e280a8` / `e280a9` bytes, so a two-flag implementation fails the
byte comparison (`5c7532303238`) rather than quietly minting a second id.

Rows 1–12 are unaffected — none contains a line terminator, so their bytes are
identical under both forms.

### Who depends on this

`langsys-js-typescript` asserts its `generateCustomId` against this file
directly, rather than keeping a parallel suite that could drift. Both SDKs
therefore assert the same values from one source of truth.

**Do not move, rename or regenerate this file without telling that repo.** Adding
cases is safe and welcome; changing an existing `custom_id` is a breaking change
to content block identity in every SDK, and orphans already-registered blocks.

### How to verify cross-SDK agreement

Execute the *other* implementation against this file. Never re-derive the
expected value in the same language you are testing — comparing PHP `md5()` to
PHP `md5()` proves the JSON shape and nothing about any other SDK, and a false
parity claim shipped in 1.0.2 on exactly that basis.

`HtmlParserTest::testGenerateCustomIdMatchesTheReferenceFixtures` also checks
each entry is self-consistent (`md5(canonical_json) === custom_id`), so an
inconsistent hand-edit fails the suite rather than silently redefining the
contract.

## `tokenizer-reference.json`

The companion contract: **HTML fragment in, expected token list out**, plus the
`custom_id` those tokens produce, so the two files compose.

`custom-id-reference.json` pins the hash, but its inputs are synthetic token
lists that never touch a DOM. A divergence in how two SDKs *derive* tokens from
HTML therefore passes it silently — which is not hypothetical: the JS SDK
harvested every `<option>` twice, so any content block containing a `<select>`
had different ids in the two SDKs while both fixture suites were green. The hash
was identical; the inputs were not.

Each entry records `category` and `canonical_json` alongside `custom_id` for the
same reason the id fixtures do: **every input to the hash must be present, or the
column is unverifiable from outside while still looking verified.** The first
version of this file omitted `category`, so a consumer could check `html` ->
`tokens` but not `tokens` -> `custom_id` — the id column read as authoritative
and could not be reproduced by anyone.

### `html` is a content *fragment* — how you feed it matters

**Implementations whose tokenizer takes a root *element* must wrap the fragment
in a container and tokenize the container.** Do not pass the fragment's top-level
node as the root: a tokenizer that walks `root.childNodes` deliberately excludes
the root itself — in the JS SDKs the root is the consumer's own wrapper
(`<div use:translate>`), which is their markup, not translatable content — so
that node's own attributes are silently dropped.

This SDK never hits the ambiguity because `extractPhrases()` takes an HTML
*string* and does the wrapping internally. That is precisely why the note has to
be written down rather than left to be inferred from our usage.

It misleads on exactly **two of the seventeen cases**, both being the ones where
the top-level node itself carries the attributes:

| case | unwrapped (wrong) | wrapped (correct) |
|---|---|---|
| `[5]` `<input placeholder="Email" title="Your email" alt="icon">` | `[]` | 3 tokens |
| `[6]` `<div aria-label="Close" …>x</div>` | `["x"]` | 5 tokens |

Every other case keeps its content strictly below the top-level node, so both
harness shapes agree and the mistake stays invisible. Fifteen of seventeen green
reads as a working harness with two SDK bugs — the inversion is the whole hazard,
and it cost langsys-js-typescript two false failures. **Check the harness before
the implementation when the failures are few and structurally alike.**

Covered: `<select>`/`<optgroup>`, inline-markup splitting, translatable
attributes including ARIA, button and submit values, `translate="no"` and
`data-notrans` exclusion, script/style handling, comments, void elements,
duplicate ordering, and whitespace collapsing.

### Case [12] recorded a defect — RESOLVED by TOK-1

This row once asserted `["Keep", "var a=1;", ".a{}"]` for
`<div><p>Keep</p><script>var a=1;</script><style>.a{}</style></div>`, under a
description saying script and style contents are never harvested. The data was
right about the code at the time and the description was the intent: the
content-block path had no skip list, so a `<script>` inside a content block was
harvested as a translatable phrase and reached the registration list, while the
page-walk path did skip it. One boundary, not a general blind spot.

It was left recording the defect rather than the intention because the row is a
contract `langsys-js-typescript` asserts against, and fixing the tokenizer
re-keys every content block containing a script or a style, so the direction
needed a cross-SDK decision. That decision is TOK-1. The content-block path now
skips `<script>`, `<style>`, `<template>`, `<noscript>` and `<math>`, the row
asserts `["Keep"]` (corrected 2026-09-11), and the re-keying was measured on
production as negligible.

This section described the hazard in the present tense for a release after it
was fixed — the same staleness later found in the CHANGELOG's release gate. The
stale-phrase check in `CONFORMANCE.md` now reads this file too.

**The prose was the wrong half to trust.** The word "opacity" and the token list
sat four lines apart and contradicted each other, and the fixture passed
throughout — because it asserts what the code does, which is exactly its job. A
coverage summary is a claim about the data; read the data.

Same rules as the id fixtures: verify another SDK by **executing it against this
file**, adding cases is safe, and changing an existing expectation is a breaking
change to content block identity in every SDK.

## `interpolation-reference.json`

**What a phrase renders as** — template, params, locale, expected output.

The other two files pin markup tokens and content-block ids. Nothing pinned the
rendered sentence, and that is where the fourth cross-SDK defect lived: a missing
ICU argument produced different broken output in each SDK (PHP echoed a bare
`{arg}` and destroyed the sentence; JS dumped the entire raw pattern), and
neither suite noticed, because **every test on both sides supplied complete
params.**

Cases deliberately include the states nobody thinks to test: an argument absent,
an argument explicitly `null`, and a genuine `0`. That last pair matters most —
`null` must render `{count} items` while `0` renders `0 items`, because coercing
`null` to zero makes a data-fetch failure indistinguishable from an empty cart in
the page, in a screenshot, and in a support ticket.

Four cases call with **no params at all**: a select and a plural, each once with the
`params` key omitted and once with an empty map. Every earlier case supplied a params
object, and that is how three SDKs shipped a no-params short-circuit returning the raw
pattern while every fixture run stayed green. Where a case omits `params`, a harness
must call with the argument genuinely omitted, not with an empty map. Their
`requires_intl` was measured on this SDK's own no-intl path (`hasIntl()` forced false,
because the extension here loads even under `php -n`), controlled against the existing
cases: all 15 marked independent matched without it, and all 4 marked dependent differed.

Two cases pin a **formatter failure** (ICU-6): `You have {count, plural, one {{count}
car} other {{count} cars}}` in `en`, with `count` 3 and 1. On PHP 8.3.19 with ICU 76.1
the pattern parses and then fails to format (`U_ARGUMENT_TYPE_MISMATCH`, `{count}`
declared both as a plural and as a plain argument), so here the rows exercise the SDK's
own branch selection; a formatter that renders the pattern passes them directly. Both
expect the chosen branch with the value filled in, never an empty string and never the
raw construct, and both render identically without intl.

Each case carries `requires_intl`, **measured** by generating the file twice —
once with the extension and once without — rather than inferred from the
template. Only 4 of 25 cases actually depend on it, and they are not the ones you
would guess: the ICU missing-argument recoveries are intl-independent, while a
plain `{id}` placeholder is not, because it needs CLDR number formatting.

Dates are deliberately absent: `IntlDateFormatter` output depends on the runtime
timezone and ICU version, so a date fixture pins the environment rather than the
contract.

## Shared fixtures, cited by blob

Two files here are adopted byte-identically from sibling SDKs rather than
authored in this repo. Cite them by blob, never by path: the path has moved
once already, and a path citation cannot tell you whether the bytes changed.

| File | Origin | Blob |
|---|---|---|
| `legacy-custom-id-reference.json` | `langsys-python` | `dc5556466dc54fe82e81ac9fdbf4549b2b76e7ce` |
| `canonicalization-reference.json` | `langsys-js-typescript` @ `a5dca286` | `170b5646cd84db90e5bdb85793f7e6832e499d3e` |

`tokenizer-reference.json` is authored here. Its blob is recorded as a VALUE, not
as a command to run — a command tells you what the file is now, which is exactly
what a silent edit also tells you:

```
5689f3c1425502f3a2c4afd4b48e9bdbfc25a32d
```

Re-derive with `git rev-parse HEAD:tests/fixtures/tokenizer-reference.json` and
update this line deliberately when the file is meant to change.

Its row "script and style contents are never harvested" was **corrected on
2026-09-11** from `["Keep","var a=1;",".a{}"]` to `["Keep"]`. The name stated
the intent and the data recorded the defect, so the row locked in the behaviour
it was named for preventing.
