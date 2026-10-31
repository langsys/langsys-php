# Choosing what to call

Ask one question of every piece of text: **does a tag boundary inside it belong to the language or to the layout?** A link inside a sentence belongs to the language: a translator must be able to move it. Two paragraphs side by side belong to the layout: each is its own unit.

| Content | Call |
|---|---|
| Plain text from your code | `translate()` |
| One line from your code with inline markup (a link, `<b>`) | `translateRich()` |
| A fragment of HTML | `translateContentBlock()` |
| A whole document | `translatePage()` ([server-rendering.md](./server-rendering.md)) |
| One sentence with markup inside a page or fragment | `data-langsys-phrase` on its element |

## `translate()`: plain text

```php
$client->translate($phrase, $locale = null, $category = '__uncategorized__', $contentBlockId = null, array $params = []);

$client->translate('Save', null, 'UI');                                       // the request's locale
$client->translate('Hello, {name}!', 'es-es', 'UI', null, ['name' => $name]);
```

- It returns the translation, or the phrase itself until one exists, with its params filled either way.
- A miss is queued, and sent at the end of the request if the API lets the request write ([writing.md](./writing.md)). The same miss twice in a request queues once.
- It returns text, not HTML. Print it escaped, as you would any other text. A line that needs markup is `translateRich()`.
- `$contentBlockId` names the content block a phrase belongs to. App code passes `null`.

`resolve()` takes the same arguments without the block id and also says where the text came from: `['text' => ..., 'from' => 'catalog' | 'fallback' | 'source']`. `fallback` exists only with a miss fallback ([migration.md](./migration.md)).

## `translateRich()`: one line with inline markup

```php
$client->translateRich('Read our <a href="/terms">terms</a>', null, 'Legal');
// ['html' => 'Lee <a href="/terms">los términos</a>', 'from' => 'catalog']
```

The line registers as `Read our {m0o}terms{m0c}`, the same tokens the JavaScript SDKs' `<Phrase>` writes, so either SDK can render the other's entry. Rendering rebuilds only your source's elements, with their attributes as you wrote them, around the translated text. A tag or attribute in a translation comes out as text, so a translation can move your link but never add one, change its `href` or run script. Print `html` as HTML.

The arguments are `($sourceHtml, $locale = null, $category = '__uncategorized__', array $params = [])`. An element you exclude in the line ([below](#opting-out)) is never translated: it renders as you wrote it, with the params you pass filled in.

## `translateContentBlock()`: a fragment

```php
$client->translateContentBlock('<p><a href="#">Click here</a> to learn more</p>', 'Homepage');
$client->translateContentBlock('<p>Hi {name}</p>', 'Homepage', ['name' => $name]);
```

The fragment is one unit. If its one phrase is its one text node, as in `<p>Hello</p>`, it is the phrase `Hello`, written back into that text node. Anything else is a content block: its phrases, translatable attributes included, under one id, translated together. Placeholders fill in text and in translatable attributes. `registerContentBlock()` reads a fragment the same way ([writing.md](./writing.md)).

## Inside a page or a fragment

`translatePage()` walks a document into **units**: each element that holds no block elements of its own, such as a paragraph, a list item or a heading, or an `<img>`, `<input>`, `<button>` or `<a>` directly under a container. A unit whose one phrase is its one text node is a phrase (`<p><strong>Hello World</strong></p>` is `Hello World`). Any other unit is a content block (`<p><strong>Hello</strong> World</p>` is the block `["Hello", "World"]`, `<img alt="Logo">` the block `["Logo"]`). Every Langsys SDK gives the same content the same shape and the same ids.

Attributes change the walk. The author's spelling is `data-langsys-*`. `data-langsys-phrase`, `data-langsys-contentblock` and `data-langsys-resolved` are also read as `data-ls-*`, the spelling the JavaScript SDKs write. `data-langsys-category` has no other spelling.

### `data-langsys-phrase`: keep a sentence together

```html
<p>Based on {n} <strong>reviews</strong></p>                     <!-- two phrases: "Based on {n}", "reviews" -->
<p data-langsys-phrase>Based on {n} <strong>reviews</strong></p> <!-- one: "Based on {n} {m0o}reviews{m0c}" -->
```

Split, the count and the noun it inflects are in different phrases, and no plural rule can reach across. Marked, a translator moves the tokens and the markup is rebuilt where they land, with its attributes. Presence enables it; `"false"` or `"0"` opts out. A translation that drops, unbalances or misnumbers the tokens renders without the markup, never with literal `{m0o}`.

### `data-langsys-contentblock`: one block

On an element, it registers the element and everything in it as one content block. Its value, trimmed and case-insensitive:

- **None, `""`, `"true"`, `"1"` or `"yes"`:** a block. The render sets the attribute to the block's id.
- **`"false"` or `"0"`:** an ordinary element.
- **Anything else:** the block's id. The element renders from the catalog entry under that id, and registers under it when the catalog lacks it, unless the element or an ancestor carries `data-ls-resolved`.

Every rendered block host carries its id, in the attribute that declared the block: `<div data-langsys-contentblock>` renders as `data-langsys-contentblock="<id>"`, and a block the page walk found gets `data-ls-contentblock="<id>"`. A host whose value was already its id is left as written. An element marked with either attribute inside other content is its own unit, and contributes none of its words to what surrounds it: `<p>See <span data-langsys-phrase>our <b>new</b> plans</span> today</p>` is the block `["See", "today"]` and the phrase `our {m0o}new{m0c} plans`.

### Opting out

```html
<code translate="no">rm -rf /</code>
<div data-notrans>Internal build id: 4f2a91</div>
```

Both are equivalent. An excluded subtree is never extracted, registered or written over. `data-notrans` is on by presence, and only `"false"` or `"0"` opts out. Inside a `data-langsys-phrase` run or a `translateRich()` line, an excluded element is one empty token pair whatever it holds, and a run of only excluded elements registers nothing.

**Exclude what is already translated.** Content another mechanism already rendered in the visitor's language, walked without the marker, is looked up as a source phrase, missed, and registered: translated text enters the catalog as a source phrase.

Never translated, whatever you mark: `<script>`, `<style>`, `<template>`, `<noscript>`, `<math>` and `<plaintext>`. Text inside `<svg>` is translated, and the drawing is left alone.

### Translatable attributes

A unit's attributes are phrases too, ahead of its text: `placeholder`, `alt`, `title`, `label`, the ARIA labels and descriptions, and common validation and framework attributes (`data-error-message`, `data-tooltip`, `data-bs-title` and more). `getTranslatableAttributes()` lists them.

```php
$client->addTranslatableAttributes(['data-label']);   // add to the defaults
$client->setTranslatableAttributes(['data-label']);   // replace them
$client->resetTranslatableAttributes();
```

## Categories

The category groups phrases for translators. The entry skill says when one is worth having (categories.md in the installed `langsys` skill): one phrase in two categories is two catalog entries, translated twice.

- **The argument:** the third of `translate()`, the second of `translatePage()` and `translateContentBlock()`. Omitted, it is `__uncategorized__`. `null` and `''` mean the same.
- **`data-langsys-category="Footer"`** sets it for an element and everything in it, inherited until a descendant sets its own. An empty value is `__uncategorized__`.
- **Selectors**, as `translatePage()`'s third argument:

```php
echo $client->translatePage($html, 'Homepage', [
    'nav a, nav button' => ['category' => 'Navigation', 'overrideParentElementCategory' => true],
    '.promo'            => ['category' => 'Promotions'],
]);
```

Highest first: a selector with `overrideParentElementCategory: true`, the element's `data-langsys-category`, the inherited category (at the top, `translatePage()`'s argument), a selector without the override, `__uncategorized__`. So a selector without the override applies only where no category reaches the element, which on a page given a category is nowhere. Set the override on purpose. Selectors are tags, classes, ids, attributes (`[type="submit"]`, `[class^="btn-"]`), descendant and child combinators, and comma lists.
