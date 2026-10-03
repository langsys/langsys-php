# Testing this SDK locally

How to see each feature of this branch work, in plain PHP, from an app that installs the SDK
the way a real one does. Every check below prints what it did; the expected output is shown
under it.

## What you need

- PHP 7.4 or later with `intl`, and Composer.
- Node 18 or later, for the API double.

## Set up

**1. Start the API double.** It is the Langsys API as the backend implements it — keys,
catalog, registration, the error envelope — holding state you can read back
([`tests/contract-fixture/README.md`](tests/contract-fixture/README.md)). Give it a port of
its own; anything else that seeds the same port replaces its state.

```sh
node tests/contract-fixture/server.mjs --port 8791
```

It prints `{"ready":true,"base_url":"http://127.0.0.1:8791/api",...}`. On another port, set
`LANGSYS_DOUBLE=http://127.0.0.1:PORT` for the checks.

**2. Install the example app.** [`examples/local-testing/`](examples/local-testing/) requires
this checkout through a Composer path repository, so it runs the code in this tree:

```sh
cd examples/local-testing
composer install
```

Every check seeds the double from [`seed.json`](examples/local-testing/seed.json) when it
starts: project `p1`, base `en-us`, target `es-es`, a write key and a read key, and `Hello`
translated as `Hola`. Run them in any order.

## The checks

### 1. Translating, and the fallback chain

```sh
php 1-translate.php
```

```
Hola
{"text":"Welcome, Ana","from":"source"}
{"text":"Bienvenida, Ana","from":"fallback"}
{"text":"Not in the files","from":"source"}
no key: API key is required. Set LANGSYS_API_KEY environment variable or pass it to the constructor.
```

`translate()` answers from the catalog. `resolve()` says who wrote its text. With the API
unreachable, a call goes to the miss fallback — your framework's language files — and then
the source, and never throws. A `Client` built with no key is refused when it is created; a
framework integration builds none without a key, so its translate function is the
framework's own. README: *Language files as the fallback*.

### 2. The page walk and block identities

```sh
php 2-page.php
```

```
<html lang="es-es" data-ls-resolved="es-es"><body><h1>Hola</h1><p data-ls-contentblock="0d276b4e7fb0d94a18c6fb1184a5b329">Read the <b>terms</b> first.</p></body></html>
phrases: ["Hello"]
blocks:  [["0d276b4e7fb0d94a18c6fb1184a5b329",["Read the","terms","first."]]]
```

`translatePage()` translates the heading, keeps the paragraph's markup as one block, stamps
the block with its id, and marks the translated page resolved. The flush registers the new
block under that same id; `phrases` and `blocks` are what the double accepted. README:
*Full Page Translation*.

### 3. Values marked as variables

```sh
php 3-markers.php
```

```
<html lang="es-es" data-ls-resolved="es-es"><body><p>Welcome back, <!--ls:name-->Ana<!--/ls--></p></body></html>
<html lang="es-es" data-ls-resolved="es-es"><body><p>Welcome back, <!--ls:name-->Luis<!--/ls--></p></body></html>
phrases: ["Hello","Welcome back, {name}"]
```

The same sentence rendered for two users registers once, as `Welcome back, {name}`, and each
render keeps its own value inside its marker. README: *Values from variables*.

### 4. The sync plan

```sh
php 4-sync.php
```

```
offline: {"in_catalog":0,"with_translations":0,"new":5} reported lines [6] covered groups ["validation"] strict fails: true
  in_catalog         Hello                    []
  new                Your cart is empty.      []
  with_translations  Place order              {"es-es":"Realizar pedido"}
  new                Hi {name}                []
  with_translations  Total: {amount}          {"es-es":"Total: {amount}"}
{"success":true,"reason":null,"registered":4,"translations":2,"human_translations_saved":2,"human_translations_skipped":0}
stored: [["Hello",{"es-es":"Hola"}],["Your cart is empty.",[]],["Place order",{"es-es":"Realizar pedido"}],["Hi {name}",[]],["Total: {amount}",{"es-es":"Total: {amount}"}]]
```

The scanner reads [`app/views.php`](examples/local-testing/app/views.php). Planned offline —
no key, no catalog — every phrase is new and `__($dynamic)` on line 6 is reported, which
fails a strict run; `__("validation.$rule")` is covered by its group. Planned against the
catalog, `Hello` is already there, the lines the Spanish files translate go with their
translations, and `applySync()` registers what the catalog lacked. README: *Sync: register
from source, not at runtime*.

### 5. Importing existing translations

```sh
php 5-import.php
```

```
{"success":true,"reason":null,"phrases":2,"translations":2,"human_translations_saved":2,"human_translations_skipped":0,"skipped":[]}
{"Place order":"Realizar pedido","Total: {amount}":"Total: {amount}"}
refused: The locale it-it is not a target locale of this project, so its translations cannot be imported.
```

Every key of the source language files registers with its Spanish translation, stored as a
human translation; the next catalog read serves them. A locale the project does not target
is refused before anything is sent. README: *Import the translations you already have*.

### 6. An app's own messages

```sh
php 6-messages.php
```

```
[{"template":"You have used all {limit} of this month's requests.","source":"app\/QuotaExceeded.php","code":"quota_exceeded"}] problems: []
{"code":"quota_exceeded","message":"You have used all 500 of this month's requests.","template":"You have used all {limit} of this month's requests.","params":{"limit":500}}
```

A class implementing `HasAppMessageTemplate` is listed from its class name — built without
its constructor, its `{limit}` marker checked against its declared properties — once, with
its code. `ServerMessage::fromApp()` builds the entry an API response carries from the same
template. README: *Server Messages*.

### 7. Key-based translation files

```sh
php 7-legacy.php
```

```
Place order
Total: 12 €
["Total: {amount}","checkout","checkout.total"]
```

With the `migration` option, `translate('checkout.submit')` looks the key up in the source
language file and works with its text, converted to Langsys syntax, under the key's group.
README: *Migrating from Key-Based Translation Files*.

## Against a local Langsys

Point the checks at a running local Langsys instead of the double:

```sh
LANGSYS_API_URL=http://langsys2.test/api LANGSYS_API_KEY=<a local write key> LANGSYS_PROJECT_ID=<a local project> php 1-translate.php
```

Nothing is seeded, so the catalog is whatever the project holds, and the `phrases`,
`blocks` and `stored` lines print nothing: read what was registered in the Translation
Manager. Use a local project and key only; the checks register what they find.

## The test suite

```sh
vendor/bin/phpunit
```

from the repository root runs every test, including the contract tests, which start their own
API double. [`CONFORMANCE.md`](CONFORMANCE.md) maps each rule of the cross-SDK specification to
the tests that prove it. [`UPGRADING.md`](UPGRADING.md) lists what an app running v1.3.1
notices on this branch.
