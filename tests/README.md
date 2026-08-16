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

## Measure which files the suite loads — don't infer it

A green suite says nothing about a source file it never compiled. PHP parses a
file only when something includes it, so under PSR-4 a class no test transitively
reaches is never compiled: syntax errors and all. Grepping for the class name
does **not** answer this. In `langsys-php-laravel` that grep reported thirteen
references to a facade with zero real ones — `\bLangsys\b` was matching the
`Langsys\Laravel\…` namespace in every test file. The strongest available signal
pointed the wrong way, and the file had never been parsed by any of their 59
passing tests.

Measure it instead:

```bash
cat > /tmp/probe.php <<'PHP'
<?php
register_shutdown_function(function () {
    $root = dirname(__DIR__) . '/src/';   // adjust if run from elsewhere
    $hit = [];
    foreach (get_included_files() as $f) {
        if (strpos($f, $root) === 0) { $hit[substr($f, strlen($root))] = true; }
    }
    file_put_contents('/tmp/loaded.txt', implode("\n", array_keys($hit)) . "\n", FILE_APPEND);
});
PHP

rm -f /tmp/loaded.txt
php -d auto_prepend_file=/tmp/probe.php vendor/bin/phpunit
find src -name '*.php' | sed 's|^src/||' | sort > /tmp/all.txt
sort -u /tmp/loaded.txt | grep -v '^$' > /tmp/hit.txt
comm -23 /tmp/all.txt /tmp/hit.txt     # files the suite never parsed
```

**Check the suite actually ran before believing a zero.** The probe measures the
*process*, not the suite, and cannot distinguish "nothing loaded" from "nothing
ran". A first run here reported 0/25 — every file in the SDK unreachable —
because the command carried `--no-output`, which PHPUnit 9 does not accept. It
exited on the unknown flag before loading anything, the shutdown handler fired on
an empty process, and `comm` then produced a clean and completely false finding.
The ratio is the tell: **a real reachability gap is narrow; total failure across
every file is the harness.** Same rule that caught a spurious 0/17 against the
JS SDK.

Current results, which differ by leg:

| run | parsed | note |
|---|---|---|
| `vendor/bin/phpunit` | 25/25 | no gap |
| `php -n vendor/bin/phpunit` | 24/25 | `-n` drops ext-redis too, so `RedisCacheTest` skips wholesale and `Cache/RedisCache.php` never compiles |

CI's `no-intl` job has that same hole for the same reason — its `extensions:
curl, json, :intl` carries no redis. It is covered twice regardless: the six-leg
matrix loads `redis` so the file executes there, and the `lint` job runs `php -l`
over all of `src` and `tests` whatever extensions a leg happens to have. That
last property is the point of keeping the lint job even though the matrix looks
like it subsumes it — **linting does not require a file to be reachable, and
executing does.**

## Cross-SDK fixtures

`tests/fixtures/` holds three contracts asserted against by other Langsys SDKs —
see the README there. The rule that matters: **verify another implementation by
executing it against the fixture, never by re-deriving the expectation in the
same language.** A fixture only proves something once a different implementation
runs against it.
