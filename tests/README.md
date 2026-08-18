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

This exact shape has produced five defects in this repo:

| what passed | why it proved nothing |
|---|---|
| the 1.0.2 `custom_id` parity test | compared PHP `md5()` to PHP `md5()` — a tautology proving the JSON shape and nothing about the JS SDK, on the strength of which a false parity claim shipped |
| a CI job reporting the ICU suite green | ran with `ext-intl` absent, so every ICU test skipped |
| `data-notrans` exclusion tests | asserted only that excluded content was absent, while the attribute excluded nothing |
| `tokenizer-reference.json`'s `custom_id` column | computed from a category that was never recorded, so no other SDK could reproduce it — it read as verified while being uncheckable |
| the Redis cache tests, on every CI leg | ext-redis was present but no server was, so all 8 skipped at the connection check and `RedisCache.php` was never even compiled — green across six legs for the whole life of the workflow |

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
    // getcwd(), NOT dirname(__DIR__): the probe lives in /tmp, so __DIR__ is
    // /tmp and the old form resolved to //src/ - matching nothing and reporting
    // every file unparsed, which is the exact false zero this section warns of.
    $root = getcwd() . '/src/';
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

Sanity-check the probe itself before trusting a clean or alarming result —
`comm -12 /tmp/all.txt /tmp/hit.txt | wc -l` should be close to the file count.
A zero there means the probe never matched anything, not that nothing loaded.

**Check the suite actually ran before believing a zero.** The probe measures the
*process*, not the suite, and cannot distinguish "nothing loaded" from "nothing
ran". A first run here reported 0/25 — every file in the SDK unreachable —
because the command carried `--no-output`, which PHPUnit 9 does not accept. It
exited on the unknown flag before loading anything, the shutdown handler fired on
an empty process, and `comm` then produced a clean and completely false finding.
The ratio is the tell: **a real reachability gap is narrow; total failure across
every file is the harness.** Same rule that caught a spurious 0/17 against the
JS SDK.

Current results:

| run | parsed | why |
|---|---|---|
| local, with `redis-server` running | 25/25 | `RedisCacheTest` connects, so `RedisCache` is instantiated and compiled |
| local `php -n` | 24/25 | `-n` resets `extension_dir`, so ext-redis is absent and the class skips at the extension guard |
| CI matrix legs (7.4–8.4) | 25/25 | a `redis:7-alpine` service runs, so the 8 Redis tests execute and the driver compiles |
| CI `no-intl` job | 24/25 | no service there by design; `setUp()` skips at the connection check, which sits *above* the `new RedisCache(...)` line, so the class never autoloads |

**Until `4c1f02b` the Redis driver had never executed in CI on any leg.** ext-redis
is installed on the runner, but no server was listening, so all eight tests
skipped at the connection check and `Cache/RedisCache.php` was never even
compiled — on any of the six matrix legs, for the entire life of the workflow,
while every run reported green. Only `php -l` in the `lint` job touched the file.
The driver was exercised solely on developer machines that happened to be running
`redis-server`.

Fixed by adding the service **and** a `requireRedis()` guard mirroring
`requireIntl()`: with `LANGSYS_REQUIRE_REDIS` set the tests *fail* rather than
skip, so they cannot go quiet again if the service is removed or stops being
reachable. Measured on the runner before and after — skips **27 → 19** and
assertions **1078 → 1097** on every leg, the +19 being exactly what those 8 tests
assert. A service alone would have fixed today's gap while leaving the silent-skip
mechanism intact.

**The local `php -n` stand-in agrees with the `no-intl` leg on every visible
number — 491 tests, 1076 assertions, 18 skipped, and the same missing file — while
being wrong about the cause.** Locally the extension is missing; in CI the
extension is present and the *server* is missing. Identical output, different mechanism, and the matching
totals are exactly what would certify a bad stand-in as validated. If you change
anything about Redis coverage, measure it on CI rather than trusting `-n`.

Two other traps found while measuring this, both of which produced confident
wrong numbers:

- `php -n -d extension=redis` **silently loads nothing** — `-n` resets
  `extension_dir`, so the `.so` is never found. It produced a run identical to
  plain `-n`, which is what exposed it; pass `-d extension_dir=$(php -i | grep
  '^extension_dir' | awk '{print $3}')` alongside it.
- Reading `extension_loaded('redis')` in the workflow tells you nothing about
  whether the *tests* ran. The extension guard and the connection guard are
  different lines with different consequences.

It is covered for syntax regardless: the `lint` job runs `php -l` over all of
`src` and `tests` whatever extensions a leg happens to have. That property is the
point of keeping the lint job even though the matrix looks
like it subsumes it — **linting does not require a file to be reachable, and
executing does.**

## Cross-SDK fixtures

`tests/fixtures/` holds three contracts asserted against by other Langsys SDKs —
see the README there. The rule that matters: **verify another implementation by
executing it against the fixture, never by re-deriving the expectation in the
same language.** A fixture only proves something once a different implementation
runs against it.
