# Testing notes

Run the suite with `vendor/bin/phpunit`. Run it a second time without `ext-intl`
(`php -n vendor/bin/phpunit`) — several behaviours only exist on that path, and
CI has a dedicated job for it.

## Pair every absence assertion with a positive one

`assertNotContains`, `assertSame(0, …)` and "nothing was registered" each pass
for **two** reasons: the behaviour is correct, or the capture point is dead.
Those are indistinguishable from the test result, so an absence-only test keeps
passing through a refactor that stops calling the thing it watches.

Assert first that the mechanism ran — items were discovered, the hook recorded
something — and only then assert what did not happen to them.

This exact shape has produced four defects in this repo:

| what passed | why it proved nothing |
|---|---|
| the 1.0.2 `custom_id` parity test | compared PHP `md5()` to PHP `md5()` — a tautology proving the JSON shape and nothing about the JS SDK, on the strength of which a false parity claim shipped |
| a CI job reporting the ICU suite green | ran with `ext-intl` absent, so every ICU test skipped |
| `data-notrans` exclusion tests | asserted only that excluded content was absent, while the attribute excluded nothing |
| `tokenizer-reference.json`'s `custom_id` column | computed from a category that was never recorded, so no other SDK could reproduce it — it read as verified while being uncheckable |

Same shape each time: **the check passed because nothing happened.**

## Assert on the registration list, not rendered output

For exclusion and registration behaviour, assert on what would reach the API —
`getPendingPhrases()`, `getPendingContentBlocks()`, or the recorded
`MockHttpClient` requests. Catalog pollution is the irreversible half, and a
phrase reaches the catalog long before a rendered page looks wrong.

## A comment is a claim, not the implementation

`HtmlParser` carried `// Skip elements with translate="no" or data-notrans`
above code that did the opposite — a bare `data-notrans` excluded nothing. A
downstream SDK documented the attribute from that comment, in good faith, and
shipped guidance that caused users to leak protected content into the shared
catalog.

Reading a source *file* is not the same as reading the source. A comment has the
same failure modes as a README, including being wrong the day it was written.
When behaviour matters, execute it.

## Cross-SDK fixtures

`tests/fixtures/` holds two contracts asserted against by other Langsys SDKs —
see the README there. The rule that matters: **verify another implementation by
executing it against the fixture, never by re-deriving the expectation in the
same language.** A fixture only proves something once a different implementation
runs against it.
