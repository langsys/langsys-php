# Invariants

The rules other i18n habits break. Each one fails silently: the page still renders, and the wrong thing is registered.

## 1. Never build a phrase

```php
$client->translate(sprintf('Hello, %s!', $user->name));                          // ❌ one phrase per user
$client->translate('Hello, ' . $user->name . '!');                               // ❌ the same
$client->translate("You have {$count} items");                                   // ❌ one per count
$client->translate('Hello, {name}!', null, 'UI', null, ['name' => $user->name]); // ✅ one phrase
$client->translate($article->title, null, 'Blog');                               // ✅ passing content through
```

`translate()` registers what it misses, so a string built from a runtime value is a new catalog entry for every distinct value, permanently, in the catalog every SDK on the project shares, and each is billed as words to translate. Fixing the code afterwards removes none of them. Nothing can detect it at runtime: an interpolated string is indistinguishable from an authored one.

The test is cardinality, not syntax. A CMS's 400 product names registering 400 phrases is the content you want translated. A visitor's name registers one per visitor. The entry skill states the rule for every SDK; [interpolation.md](./interpolation.md) has how params render.

## 2. The phrase is the key

```php
$client->translate('Welcome back', null, 'Home');  // ✅ the source text is the key and the default
$client->translate('home.welcome');                // ❌ registers the literal text "home.welcome"
```

There are no catalog files to create. An untranslated phrase renders as itself, so the base language is always right. An app that already calls by key keeps its source-language file as input through the `migration` option, and then `home.welcome` resolves to its text ([migration.md](./migration.md)). Without that option a key is text like any other.

## 3. The locale comes before the category

```php
$client->translate('Save', 'UI');          // ❌ looks 'Save' up in a locale called "ui"
$client->translate('Save', null, 'UI');    // ✅ the request's locale, category UI
```

`translate($phrase, $locale, $category, $contentBlockId, $params)`. Every argument after the phrase is optional, so a swap runs: the phrase renders as its source in every language, and every request asks the API for the catalog of a locale called `ui`. The JavaScript SDKs put the category second (`t(phrase, category, params)`), so code ported from them is the usual source of this. `translatePage()` and `translateContentBlock()` take the category second, because they have no locale argument: they use the request's.

## 4. Categorize only when the same words mean different things

One phrase in two categories is two catalog entries, translated and billed twice. Leaving phrases uncategorised is fine. The entry skill's categories.md says when a category earns its place.

## 5. A page's or block's text must not depend on runtime data

A content block's id is derived from its category and the text it holds, so anything that changes the text changes the block:

```php
echo $client->translateContentBlock("<p>Hi {$user->name}, you have {$n} items</p>");                                    // ❌ a block per name and count
echo $client->translateContentBlock('<p>Hi {name}, you have {count} items</p>', null, ['name' => $user->name, 'count' => $n]); // ✅ one block
```

The same holds for `translatePage()`. There, have the template engine mark each value it prints ([value-markers.md](./value-markers.md)). Writing `{name}` in the template and passing the value in `translatePage()`'s fourth argument also keeps one block, but a page it can't translate comes back as given, with the `{name}` showing ([interpolation.md](./interpolation.md)). A list that grows or a branch that appears inside one block also changes its text and gives it a new id.

## 6. Never translate translated text

Text already rendered in the visitor's language, walked again, is looked up as a source phrase, missed and registered: Spanish enters the catalog as a source phrase. A page `translatePage()` rendered in a language other than the base carries `data-ls-resolved` and is never registered from again ([server-rendering.md](./server-rendering.md)). Content another mechanism translated needs `translate="no"` or `markResolved()`.

## 7. Locale identifiers are lowercase

The SDK's form is `xx-yy` in lowercase (`es-es`, `pt-br`, `zh-hant-tw`). `setLocale('es_ES')` stores `es-es`. Compare locales in that form ([locales.md](./locales.md)).

## 8. The API decides who writes

Whether a request registers is the API's `write_enabled` for that request, not the key's type. A `write` key can be read-only, and an `ip_write` key writes only from its allow-list ([writing.md](./writing.md)). Keep `write` keys out of anything shipped to a browser.

## Quick reference

| Do | Don't |
|---|---|
| `translate('Hello, {name}!', null, 'UI', null, ['name' => $n])` | `translate(sprintf('Hello, %s!', $n))` |
| Pass authored strings and CMS content through | Build strings with `.`, `"{$x}"` or `sprintf` |
| `translate($phrase, null, $category)` | `translate($phrase, $category)` |
| Marked values on a page, `{name}` with params in a block | Values printed into a block's text |
| Compare `en-us` | Compare `en-US` |
| `flushPendingRegistrations()` then `resetRequestState()` per request in a worker | One `Client` per worker with neither |
