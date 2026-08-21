# Cross-SDK fixtures

Three reference files, one per boundary in the pipeline, each asserted against by
langsys-js-typescript as well as this SDK:

| file | boundary |
|---|---|
| `interpolation-reference.json` | template + params -> rendered string |
| `tokenizer-reference.json` | HTML -> tokens -> canonical JSON -> custom_id |
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
(`JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`, which is what
`JSON.stringify` produces).

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

### Case [12] records a defect, not a decision

That entry used to be described here as "script/style **opacity**", which reads
as "they are skipped". They are not. The fixture records what the code does:

```
html   : <div><p>Keep</p><script>var a=1;</script><style>.a{}</style></div>
tokens : ["Keep", "var a=1;", ".a{}"]
```

`HtmlParser::walkNode()` has no skip list. `PageTranslator::SKIP_ELEMENTS`
(`src/Html/PageTranslator.php:45`, checked at `:322`) does, but the content-block
path never reaches that check: `extractAsContentBlock()` hands the element's
inner HTML straight to `extractPhrases()` (`:488-489`). So a `<script>` inside a
content block is harvested as a translatable phrase, and it reaches the
**registration list** — measured, on the documented public API:

```
$client->translateContentBlock($html, 'pricing');  ->  getPendingContentBlocks()
  [0] Our pricing
  [1] Choose a plan
  [2] window.dataLayer.push({event:"view",sku:"ABC-123"});
  [3] .plan{color:#fff}
```

Control, and it is what makes the diagnosis specific: the **same page** without
`data-langsys-contentblock` queues only `["T","Plans","Pick one"]`. The skip list
works on the page-walk path and is absent from the content-block path — this is
one boundary, not a general blind spot.

**Why it has not been fixed here.** This file is a contract
`langsys-js-typescript` asserts against, so changing case [12] changes their
build, and changing the tokenizer re-keys every existing content block that
contains a script or a style. Which direction is correct depends on what the JS
tokenizer does with the same input, which cannot be determined from this
repository. Raised with the base SDK and with Darryl; until it is answered, the
fixture records the behaviour honestly rather than describing an intention the
code does not have.

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

Each case carries `requires_intl`, **measured** by generating the file twice —
once with the extension and once without — rather than inferred from the
template. Only 4 of 19 cases actually depend on it, and they are not the ones you
would guess: the ICU missing-argument recoveries are intl-independent, while a
plain `{id}` placeholder is not, because it needs CLDR number formatting.

Dates are deliberately absent: `IntlDateFormatter` output depends on the runtime
timezone and ICU version, so a date fixture pins the environment rather than the
contract.
