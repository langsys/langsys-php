# Cross-SDK fixtures

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

Covered: `<select>`/`<optgroup>`, inline-markup splitting, translatable
attributes including ARIA, button and submit values, `translate="no"` and
`data-notrans` exclusion, script/style opacity, comments, void elements,
duplicate ordering, and whitespace collapsing.

Same rules as the id fixtures: verify another SDK by **executing it against this
file**, adding cases is safe, and changing an existing expectation is a breaking
change to content block identity in every SDK.
