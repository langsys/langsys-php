# Writing from the SDK

Whether a request registers what it misses is the API's decision, made for each request from the API key, the address the request comes from and any write grant. **That concept belongs to the entry skill:** `langsys` covers `write_enabled`, the key types, the `ip_write` allow-list, write grants and what to check when nothing registers. Read it first, in projects-and-keys.md in the installed `langsys` skill, or at [https://docs.langsys.dev/skill/langsys/projects-and-keys.md](https://docs.langsys.dev/skill/langsys/projects-and-keys.md). This file is how the PHP SDK exposes the decision and acts on it.

## Reading the decision

```php
try {
    $writable = $client->canWrite();   // this request may register, or is read-only
} catch (\Langsys\SDK\Exception\LangsysException $e) {
    $writable = false;                 // the API could not be asked
}

$client->getKeyType();                 // "read", "write" or "ip_write": the key's type, not the decision
```

- The SDK never decides from the key's type. An answer without `write_enabled`, or with it `false`, is read-only.
- A cached authorization holds no decision, so a request that writes asks the API once, when it first needs to: at the flush, after the response. `canWrite()` asks on its first call in a request.
- A `write` or `ip_write` key the API answers read-only is reported **once**, as a warning (`This key cannot register new text: …`). A `read` key stays silent, since read-only is its configured outcome. If you see that warning, the key isn't the problem: work through the entry skill's checks.
- A client without credentials writes nothing ([init-config.md](./init-config.md)).

## The queue

`translate()`, `resolve()`, `translateRich()`, `translateContentBlock()`, `translatePage()`, `translateMessage()` and `emitMessage()` queue what the catalog lacks, whatever the API's decision; the flush decides. Nothing is sent while the page renders. The queue is sent when the `Client` is freed, or from a shutdown function when the process ends, or when you call it yourself. Under PHP-FPM the shutdown send runs before the connection closes, so the visitor waits for it ([server-rendering.md](./server-rendering.md)).

```php
$result = $client->flushPendingRegistrations();
// ['phrases' => 5, 'content_blocks' => 2, 'skipped' => 0, 'dropped' => 0, 'retained' => 0,
//  'success' => true, 'reason' => null]
```

`success` is `true` only when every queued item was accepted. `skipped` counts what wasn't sent, split into `dropped` (nothing will send it) and `retained` (still queued for a later flush). `reason` says why a flush didn't succeed:

| `reason` | Meaning | The queue |
|---|---|---|
| `not_write_enabled` | This request may not write | dropped |
| `catalog_unavailable` | The catalog couldn't be read, so no miss could be decided | nothing was queued |
| `backing_off` | Waiting after a failed send | kept |
| `decision_unavailable` | Whether this request may write couldn't be read | kept |
| `send_failed` | The API refused or never received the batch | kept |

After a failed send, or a decision that couldn't be read, the next flush waits 3 seconds, then 6, doubling to 5 minutes, and the first successful send resets the wait. The wait belongs to the `Client`, so on a long-lived worker it carries across requests ([server-rendering.md](./server-rendering.md)).

```php
$client->hasPendingRegistrations();
$client->getPendingPhrases();
$client->getPendingContentBlocks();
$client->clearPendingRegistrations();   // drop the queue without sending it
```

**Registering only from a sync.** With `'runtime_registration' => false`, `translate()`, `resolve()`, `translateRich()`, `translateMessage()` and `emitMessage()` queue nothing, for an app whose phrases register from its source code instead ([migration.md](./migration.md)). The one exception is a sentence with a declared value the last sync didn't see ([interpolation.md](./interpolation.md)). `translatePage()` and `translateContentBlock()` still queue what they render.

A phrase ending in `…` or `...` is not registered when a longer phrase starting with the same text is already known in its category: code upstream probably cut that text short. `Loading…` registers normally.

## Registering explicitly

These send at once, and throw a `LangsysException` when the API doesn't let the request write, or a `ConfigurationException` on a client without credentials:

```php
$client->registerPhrases(['Home', 'About']);
$client->registerPhrases([
    ['phrase' => 'Dashboard', 'category' => 'Navigation'],
    ['phrase' => 'Copyright 2024', 'category' => 'Footer', 'translatable' => false],
]);

$client->registerContentBlock('<nav><a>Home</a><a>About</a></nav>', 'Navigation');
$client->registerContentBlock($html, 'Navigation', 'Main menu', 'main-menu-001'); // a label and your own id
```

`registerContentBlock()` reads the fragment as `translateContentBlock()` does, so `<p>Hello</p>` registers the phrase `Hello`. With your own id it registers a block whatever its shape. Relative `src`, `srcset` and `poster` URLs are made absolute, from the `base_url` option, else `LANGSYS_BASE_URL`, else the request's host.

`sync($localPhrases, $locale)` registers what the catalog lacks from a list of `['phrase' => ..., 'category' => ...]` and returns `translations`, `new_phrases` and `synced`. A read-only request lists the new phrases and registers none (`synced` is `false`). For an app that calls a framework's translate function, `planSync()` is the sync to use ([migration.md](./migration.md)).

## What to check when nothing registers

1. **`isConfigured()` is `false`:** no key or project id reached the `Client` ([init-config.md](./init-config.md)).
2. **`canWrite()` is `false`:** the API made the request read-only. The entry skill says why that happens.
3. **The flush's `reason`** says what happened to the queue. In a worker, check that the flush runs at all.
4. **`canWrite()` is `true` and the flush succeeds:** not a capability problem. Check the call and the content ([primitives.md](./primitives.md), [invariants.md](./invariants.md)), `runtime_registration`, and whether the page carries `data-ls-resolved`.
