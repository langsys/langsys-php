# Langsys PHP SDK

[![packagist](https://img.shields.io/packagist/v/langsys/langsys-php.svg?style=flat)](https://packagist.org/packages/langsys/langsys-php)
[![build](https://img.shields.io/github/actions/workflow/status/langsys/langsys-php/ci.yml?style=flat)](https://github.com/langsys/langsys-php/actions)
[![last commit](https://img.shields.io/github/last-commit/langsys/langsys-php.svg?style=flat)](https://github.com/langsys/langsys-php/commits)
[![commit activity](https://img.shields.io/github/commit-activity/m/langsys/langsys-php.svg?style=flat)](https://github.com/langsys/langsys-php/pulse)
[![php](https://img.shields.io/packagist/dependency-v/langsys/langsys-php/php?style=flat)](https://packagist.org/packages/langsys/langsys-php)
[![downloads](https://img.shields.io/packagist/dm/langsys/langsys-php.svg?style=flat)](https://packagist.org/packages/langsys/langsys-php)
[![license](https://img.shields.io/packagist/l/langsys/langsys-php.svg?style=flat)](./LICENSE)

Official PHP SDK for the [Langsys](https://langsys.dev) Translation API. Manage translations, register phrases, and sync your application's translatable content.

## Requirements

- PHP 7.4 or higher (tested up to PHP 8.4)
- cURL extension
- JSON extension
- intl extension (for ICU plurals and locale-aware number/date formatting)
- Redis extension (optional, for Redis caching)

## Installation

### Via Composer (Recommended)

```bash
composer require langsys/langsys-php
```

### Manual Installation (Without Composer)

1. Download or clone the repository
2. Include the autoloader:

```php
<?php
require_once '/path/to/langsys-php/autoload.php';

use Langsys\SDK\Client;

$client = new Client('your-api-key', 'your-project-id');
```

## Quick Start

```php
<?php
require 'vendor/autoload.php';

use Langsys\SDK\Client;

// Initialize client (uses environment variables)
$client = new Client();

// Get translations for Spanish
$translations = $client->getTranslations('es-es');

// Translate a phrase
echo $client->translate('Home', 'es-es', 'UI'); // "Inicio"
```

## Configuration

### Environment Variables

Set these environment variables to configure the SDK:

| Variable | Required | Default | Description |
|----------|----------|---------|-------------|
| `LANGSYS_API_KEY` | Yes | - | Your Langsys API key |
| `LANGSYS_PROJECT_ID` | Yes | - | Your project UUID |
| `LANGSYS_API_URL` | No | `https://api.langsys.dev/api` | API base URL |
| `LANGSYS_CACHE_DRIVER` | No | `file` | Cache driver: `file`, `redis`, or `none` |
| `LANGSYS_CACHE_PATH` | No | System temp dir | Directory for file cache |
| `LANGSYS_CACHE_TTL` | No | `3600` | Cache TTL in seconds |
| `LANGSYS_BASE_URL` | No | Auto-detect | Base URL for resolving relative URLs in content blocks |
| `LANGSYS_LOG_PATH` | No | - | Path to log file (without it, warnings and errors go to PHP's error log) |
| `LANGSYS_LOG_LEVEL` | No | `info` | Minimum log level: `debug`, `info`, `warning`, `error` |
| `LANGSYS_MESSAGES_CATEGORY` | No | `Errors` | Category server message templates are registered and looked up under |

### Constructor Options

You can also pass configuration directly:

```php
$client = new Client(
    'your-api-key',           // API key (or null to use env)
    'your-project-id',        // Project ID (or null to use env)
    [
        'api_url' => 'https://api.langsys.dev/api',
        'cache_driver' => 'file',
        'cache_path' => '/tmp/langsys-cache',
        'cache_ttl' => 3600,
        'cache_clear' => false,               // Clear cache on initialization
        'base_url' => 'https://example.com',  // For resolving relative URLs
        'log_path' => '/var/log/langsys.log', // Enable logging
        'log_level' => 'info',                // Minimum level to log
        'messages_category' => 'Errors',      // Category for server message templates
    ]
);
```

## API Key Types

Langsys supports two types of API keys:

- **Read**: Can only fetch translations
- **Write**: Can fetch translations AND register new phrases/content blocks

Check your key type:

```php
if ($client->canWrite()) {
    // Can register phrases
} else {
    // Read-only access
}

// Or get the type directly
echo $client->getKeyType(); // "read" or "write"
```

## Full Page Translation

The `translatePage()` method translates an entire HTML document in one pass. It automatically:
- Sets the `lang` attribute on `<html>`
- Ensures `<meta charset="utf-8">` exists
- Translates `<title>`, meta description, OpenGraph tags, Twitter cards
- Extracts and translates body content (phrases and content blocks)
- Registers new phrases automatically (if write key)
- Falls back to original content when translations are missing

### Basic Usage

```php
<?php
ob_start();
// ... your application generates the HTML page ...
$html = ob_get_contents();
ob_end_clean();

require 'vendor/autoload.php';
use Langsys\SDK\Client;

$client = new Client();
$client->setLocale('es-es');
echo $client->translatePage($html, 'homepage');
```

### Locale Detection

With no locale set, `getLocale()` chooses the request's locale once per request,
from the first usable source in this order:

1. **The URL**: a `?locale=` query parameter, the first path segment (`/fr/pricing`)
   or the subdomain (`fr.example.com`).
2. **A cookie or session value** named `locale`.
3. **`Accept-Language`**, negotiated against the project's locales.
4. Otherwise the project's base locale.

Every candidate is checked against the locales the project serves (its base and
target locales); an unsupported one is skipped, never served. `fr` or `fr-be`
matches a project serving `fr-fr`. The response gets the `Vary` header the choice
depended on: `Cookie` or `Accept-Language`, and none when the URL decided, so a CDN
never serves one visitor's language to the next. The SDK never writes a cookie.

```php
// Explicit locale
$client->setLocale('fr-ca');

// Or let the request decide
$locale = $client->getLocale();

// Where your app keeps the value: parameter and cookie names, or your own resolver
// for the URL and cookie steps (its answer is checked like any other)
$client = new Client($key, $project, ['request_locale' => [
    'query_param' => 'lang',
    'cookie' => 'site_lang',
    // 'resolver' => fn (array $request) => ['locale' => $request['query']['l'] ?? null, 'from' => 'url'],
    // A framework that sets Vary on its own response: take it from the result instead
    // 'send_vary' => false,
]]);

// Chained usage
echo (new Client())
    ->setLocale('de-de')
    ->translatePage($html, 'contact');
```

### How It Works

The page is walked into **units**: every element that does not contain block
elements of its own — a paragraph, a list item, a heading, and equally an `<img>`,
`<input>`, `<button>` or `<a>` sitting directly under a container. A unit's phrases
are its own translatable attributes first (`title`, `alt`, `placeholder`, … in a
fixed order), then its text in document order.

1. **A unit whose one phrase is its one text node** registers as a simple phrase:
   - `<p>Hello</p>` → phrase "Hello"
   - `<p><strong>Hello World</strong></p>` → phrase "Hello World" (inline formatting preserved)
   - `<p><a href="#">Click here</a></p>` → phrase "Click here"
   - `<p><svg>…<text>Label</text></svg></p>` → phrase "Label" (the drawing is kept)
2. **Any other unit** registers as a content block:
   - `<p><strong>Hello</strong> World</p>` → content block with phrases ["Hello", "World"]
   - `<nav><a>Home</a><a>About</a></nav>` → content block with phrases ["Home", "About"]
   - `<p title="Tooltip">Hello</p>` → content block with phrases ["Tooltip", "Hello"]
   - `<img alt="Logo">` → content block with phrases ["Logo"] (an attribute has no text node to write into)
3. **Head section** is processed for translatable meta tags
4. **`translate="no"`** attribute is respected to skip elements
5. **Script/style** tags are never processed

The same content yields the same shape in every Langsys SDK, because a phrase and
a block holding the same words are two catalog entries with two ids.

### Example Output

```php
// Input HTML
$html = '<!DOCTYPE html>
<html lang="en">
<head>
    <title>Welcome</title>
    <meta name="description" content="A great website">
</head>
<body>
    <h1>Welcome</h1>
    <p>Get started today</p>
</body>
</html>';

$client->setLocale('es-es');
echo $client->translatePage($html, 'homepage');

// Output (with translations available):
// <!DOCTYPE html>
// <html lang="es-es">
// <head>
//     <meta charset="utf-8">
//     <title>Bienvenido</title>
//     <meta name="description" content="Un gran sitio web">
// </head>
// <body>
//     <h1>Bienvenido</h1>
//     <p>Comienza hoy</p>
// </body>
// </html>
```

### Read-Only Keys

With a read-only API key, `translatePage()` will:
- Translate content that has existing translations
- Silently skip registration of new phrases (no errors)
- Fall back to original content for untranslated items

### Per-Section Categories with `data-langsys-category`

By default, all phrases on a page use the category passed to `translatePage()`. You can override this for specific sections using the `data-langsys-category` attribute:

```html
<!-- Default category is 'homepage' -->
<body>
    <header data-langsys-category="header">
        <h1>Welcome</h1>  <!-- Registered under category: header -->
        <nav>
            <a>Home</a>   <!-- Registered under category: header -->
            <a>About</a>  <!-- Registered under category: header -->
        </nav>
    </header>

    <main>
        <p>Hello World</p>  <!-- Registered under category: homepage (default) -->

        <section data-langsys-category="widgets/contact">
            <h2>Contact Us</h2>  <!-- Registered under category: widgets/contact -->
            <p>Get in touch</p>  <!-- Registered under category: widgets/contact -->
        </section>
    </main>

    <footer data-langsys-category="footer">
        <p>Copyright 2024</p>  <!-- Registered under category: footer -->
    </footer>
</body>
```

```php
// All content uses its respective category
$client->setLocale('es-es');
echo $client->translatePage($html, 'homepage');
```

**Key behaviors:**
- Categories are **inherited** - child elements use their parent's category unless they specify their own
- Any element can have `data-langsys-category` - not just block elements
- Empty string (`data-langsys-category=""`) falls back to `__uncategorized__`
- This allows reusable components (headers, footers, widgets) to have consistent translations across pages

### Selector-Based Category Mapping

For more flexible category assignment, use CSS selectors to map elements to categories:

```php
$client->setLocale('es-es');
echo $client->translatePage($html, 'homepage', [
    // Navigation links get their own category
    'nav li' => ['category' => 'Navigation'],

    // Multiple selectors for the same category
    '.action, .btn' => ['category' => 'UI Elements'],

    // Override even parent's data-langsys-category
    'section.contact' => [
        'category' => 'Contact Form',
        'overrideParentElementCategory' => true,
    ],
]);
```

**Supported selectors:**
- Tag: `div`, `p`, `li`
- Class: `.btn`, `.primary`
- ID: `#submit`
- Tag + class: `div.button`, `p.special`
- Attribute: `[data-type]`, `[type="submit"]`, `[class^="btn-"]`
- Descendant: `nav li`
- Child: `ul > li`
- Multiple: `button, .btn` (comma-separated)

**Priority order (highest to lowest):**
1. Selector with `overrideParentElementCategory: true`
2. Element's `data-langsys-category` attribute
3. Inherited category from parent
4. Selector with `overrideParentElementCategory: false`
5. Default category parameter
6. `__uncategorized__`

**About `overrideParentElementCategory`:**
This option is most often desired to be `true`, ensuring selector-matched elements always use their assigned category. The default is `false` to require an explicit decision for each selector, since enabling it overrides any `data-langsys-category` attributes set in the HTML. When `false`, the HTML attribute takes precedence over the selector rule.

**Recommended starting configuration:**

```php
$selectorCategories = [
    // Navigation links and menus
    'nav a, nav button, .nav-link, .menu-item' => [
        'category' => 'Navigation',
        'overrideParentElementCategory' => true,
    ],

    // Buttons and interactive UI elements
    'button, input[type="submit"], input[type="button"], .btn, .button' => [
        'category' => 'UI Elements',
        'overrideParentElementCategory' => true,
    ],

    // Error and validation messages
    '.error, .error-message, .validation-message, [role="alert"]' => [
        'category' => 'Errors',
        'overrideParentElementCategory' => true,
    ],

    // Footer (legal, copyright, policies)
    'footer, .footer' => [
        'category' => 'Footer',
        'overrideParentElementCategory' => true,
    ],
];
```

### Forcing Content Block with `data-langsys-contentblock`

By default, `translatePage()` decomposes nested content into individual phrases where possible. Use `data-langsys-contentblock` to treat an element and all its children as a single content block:

```html
<!-- Without attribute: "Contact Us" and "Fill out the form" would be separate phrases -->
<!-- With attribute: entire section is registered as one content block -->
<section data-langsys-contentblock="true">
    <h2>Contact Us</h2>
    <p>Fill out the form below</p>
</section>
```

**What the value means** (either spelling, `data-langsys-contentblock` or `data-ls-contentblock`;
trimmed, case-insensitive):

- **No value, `""`, `"true"`, `"1"` or `"yes"`** declares a block. The SDK registers the element
  as one content block and, when it renders it, sets the attribute to the block's id.
- **`"false"` or `"0"`** opts out: the element is walked as ordinary markup.
- **Any other value** is a block id that a renderer already stamped. The element renders from
  the catalog entry under that id, or stays in the source language if the catalog holds none,
  and nothing is registered for it.

Every rendered block host carries `data-ls-contentblock="<id>"`, the id it was rendered from, so
the block can be identified from the page in a browser inspector.

**Use cases:**
- Complex widgets that should be translated as a unit
- Content where phrase order and context is critical
- Reusable components with interdependent text

**A marked element inside other content is its own unit.** An element carrying
`data-langsys-contentblock` or `data-langsys-phrase` inside a paragraph, a content
block or another marked element contributes none of its words to what surrounds
it: it is registered and translated on its own, on `translatePage()` and
`translateContentBlock()` alike. So `<p>See <span data-langsys-phrase>our <b>new</b>
plans</span> today</p>` registers the block `["See", "today"]` and the phrase
`our {m0o}new{m0c} plans`, and the same words never register twice.

## Fetching Translations

### Get All Translations

Returns translations grouped by category:

```php
$translations = $client->getTranslations('es-es');

// Structure:
// [
//     'UI' => [
//         'Home' => 'Inicio',
//         'About' => 'Acerca de',
//     ],
//     '__uncategorized__' => [
//         'Welcome' => 'Bienvenido',
//     ]
// ]
```

### Translate a Single Phrase

The `translate()` method both translates AND automatically queues new phrases for registration:

```php
// Set locale first (or auto-detect from browser)
$client->setLocale('es-es');

// Basic usage - uses current locale
$text = $client->translate('Home');

// With explicit locale
$text = $client->translate('Home', 'es-es');

// With category
$text = $client->translate('Home', 'es-es', 'UI');

// Using current locale with category
$text = $client->translate('Home', null, 'UI');

// For content block phrases
$text = $client->translate('Menu Item', 'es-es', 'Navigation', 'content-block-id');

// With placeholder values - see Placeholder Interpolation below
$text = $client->translate('Hello, {name}!', null, 'UI', null, ['name' => 'Sarah']);
```

If the phrase doesn't exist in translations, it will be:
1. Queued for registration (if write key)
2. Returned as-is (fallback to original)
3. Added to in-memory cache to prevent re-queueing in same request

### Placeholder Interpolation

Pass dynamic values via the `$params` argument rather than building the string
yourself. Placeholders use `{name}`, the same canonical form the Langsys JS SDKs
store, so a shared catalog renders identically from a PHP backend and a JS frontend.

> **⚠️ Never pre-format strings before translating.**
>
> ```php
> // WRONG - registers "Hello, Sarah!", "Hello, Ahmed!", "Hello, Priya!", ...
> $client->translate(sprintf('Hello, %s!', $user->name));
>
> // RIGHT - registers "Hello, {name}!" exactly once
> $client->translate('Hello, {name}!', null, null, null, ['name' => $user->name]);
> ```
>
> `translate()` auto-registers any phrase it doesn't recognise, so building the
> string yourself creates a **new catalog entry for every distinct runtime value**.
> That pollutes the catalog every Langsys SDK shares, is billed as translatable
> words, and can't be undone by fixing the code afterwards. The SDK cannot detect
> this at runtime — an interpolated string is indistinguishable from an authored
> one — so this is the one rule worth remembering.

Placeholders work in phrases, content blocks and whole pages:

```php
$client->translate('Hello, {name}!', null, 'UI', null, ['name' => 'Sarah']);
$client->translateContentBlock('<p>Welcome back, {name}</p>', 'homepage', ['name' => 'Sarah']);
$client->translatePage($html, 'homepage', [], ['name' => 'Sarah']);
```

In `translateContentBlock()`, placeholders resolve in text nodes **and** in
translatable attributes (`placeholder`, `alt`, `title`, …).

In `translatePage()`, placeholders resolve in text nodes, in the `<head>`
(`<title>`, meta description, `og:*`, `twitter:*`), and in translatable
attributes, including a lone `<input placeholder="Search {site}">` beside a
paragraph.

#### Behaviour

| Case | Result |
|------|--------|
| Unknown key | Left **verbatim** (`{name}` stays `{name}`) — missing data stays visible |
| Key present but `null` | Also left verbatim |
| `{ name }` | Whitespace tolerated |
| String value | Passed through untouched — the **opt-out** from number formatting (IDs, codes) |
| Int / float | Formatted for the locale (`1234.5` → `1.234,5` in de-DE) |
| Bool | `"true"` / `"false"` |
| `DateTimeInterface` | Locale-formatted, medium date style |
| Phrase not translated yet | Fallback text still interpolates, so users never see raw `{name}` |

The phrase registered with Langsys always keeps its placeholders — interpolation
only affects what your code receives.

#### Excluding content from translation

Two spellings, both author-facing and equivalent:

```html
<code translate="no">rm -rf /</code>
<div data-notrans>Internal build id: 4f2a91</div>
```

`translate="no"` is the standard HTML attribute. `data-notrans` is an alias for
hosts whose templating strips unknown bare attributes, or where `translate`
collides with another tool.

For `data-notrans`, presence alone is intent — the bare attribute works like any
boolean HTML attribute — and only an explicit `"false"` or `"0"` opts out
(case-insensitive). Excluded subtrees are never extracted, never registered, and
never rendered over.

This is also the right way to tell `translatePage()` that a subtree is **already
translated** — for example content resolved server-side by another mechanism.
Without it, page translation looks up the already-translated string as a source
phrase, misses, and registers it, putting translated text into the catalog as a
new source phrase.

#### Keeping a run together with `data-langsys-phrase`

By default, page translation splits at tag boundaries, so this becomes **two**
catalog entries — `"Based on {n}"` and `"reviews"`:

```html
<p>Based on {n} <strong>reviews</strong></p>
```

That puts the count in a different phrase from the noun it inflects, and no
plural rule can reach across the boundary. In Russian, Arabic or Polish the noun
form depends on the number, so a correct translation is impossible.

Mark the element to register the whole run as **one** phrase:

```html
<p data-langsys-phrase>Based on {n} <strong>reviews</strong></p>
```

The catalog then receives a single entry with markup tokens:

```
Based on {n} {m0o}reviews{m0c}
```

`{m0o}` / `{m0c}` are the opening and closing positions of the first element.
Translators may **move** them, and the markup is rebuilt where the tokens end up:

```
На основе {n, plural, one {# {m0o}отзыва{m0c}} other {# {m0o}отзывов{m0c}}}
  n=1 → На основе 1 <strong>отзыва</strong>
  n=3 → На основе 3 <strong>отзывов</strong>
```

Notes:

- This is the same wire format as the JS SDK's `<Phrase>` component, so entries
  registered by either SDK are usable by the other.
- Tokens are valid ICU argument names, which is what lets a markup-bearing
  phrase carry a plural at all.
- Element attributes (classes, `href`) are preserved — only the position is
  taken from the translation.
- Presence alone enables it; `data-langsys-phrase="false"` (or `"0"`) opts out.
- If a translation drops, unbalances or misnumbers the tokens, the text is
  rendered without the markup rather than failing. The rendered page never
  contains literal `{m0o}`/`{m0c}` tokens on any path.

**If you server-render with `translatePage()` and then hydrate with a JS SDK**,
use a JS SDK version whose tokenizer recognises `data-langsys-phrase` (it skips
subtrees carrying either that or its own `data-ls-phrase`). The marker survives
into the rendered HTML by design, so a JS tokenizer that does not recognise it
will walk into a subtree this SDK has already handled and re-register it **split
at the tag boundaries** — silently undoing the keep-together guarantee for
exactly the content that needed it. This only applies when a JS tokenizer walks
server-rendered nodes; a JS app rendering its own components from the catalog
never sees this SDK's DOM.

#### Translated pages are marked as resolved

A page rendered in a language other than your project's base language is output,
not source. `translatePage()` says so on the root element:

```html
<html lang="es-es" data-ls-resolved="es-es">
```

Anything that walks that page later — a Langsys JS SDK hydrating it, or a middleware
translating the response again — registers none of its text, so Spanish never enters
the catalog as a source phrase. The page still translates and keeps its content-block
ids. A page rendered in the base language is source and carries no marker, so it stays
discoverable. A marker you put on the root yourself, in either spelling
(`data-ls-resolved` or `data-langsys-resolved`), is left as it is.

**A client-side app mounted inside a translated page** renders source text of its own,
which should still be discovered. Opt its mount point back out:

```html
<div id="app" data-ls-resolved="false"></div>
```

The nearest marked ancestor decides, and only `false` or `0` opt out.

#### Plurals (ICU MessageFormat)

Full ICU is supported, so plural categories are correct per language — Russian's
four, Arabic's six:

```php
$client->translate(
    '{n, plural, one {# item} other {# items}}',
    'ru-RU', null, null, ['n' => 21]
);
```

Requires the `intl` extension. If `intl` is disabled at runtime the SDK logs a
warning naming the phrase and falls back to simple `{name}` substitution rather
than failing the render. Malformed ICU also falls back rather than throwing.

### Translate a Content Block

The `translateContentBlock()` method translates HTML content AND automatically queues what is new for registration. The fragment is one unit, read the way the page walk reads one: a fragment whose one phrase is its one text node, such as `<p>Hello</p>`, is looked up, registered and rendered as the phrase "Hello", written back into that text node; anything else is a content block:

```php
$client->setLocale('es-es');

$html = '<p><a href="#">Click here</a> to learn more</p>';

// Basic usage
$translated = $client->translateContentBlock($html);

// With category
$translated = $client->translateContentBlock($html, 'homepage');

// With placeholder values
$translated = $client->translateContentBlock('<p>Hi {name}</p>', 'homepage', ['name' => 'Sarah']);
```

This uses the same phrase extraction logic as `registerContentBlock()`, ensuring consistent behavior between translation and registration: `registerContentBlock('<p>Hello</p>')` registers the phrase "Hello". Passing a custom id names a content block explicitly, and it registers as one whatever its shape.

### Automatic Registration (Queuing)

The SDK automatically queues new phrases and content blocks during translation and flushes them at the end of the request:

```php
$client->setLocale('es-es');

// These queue new items automatically:
$client->translate('New phrase');
$client->translateContentBlock('<p>New content</p>');
$client->translatePage($html);

// Flush manually, or let the shutdown handler do it:
$result = $client->flushPendingRegistrations();
// Returns: ['phrases' => 5, 'content_blocks' => 2, 'success' => true, ...]
```

The shutdown flush is **best-effort**: it does not run when the process is killed
(out of memory, a hard timeout), and a long-lived worker (Octane, Swoole, a queue
worker) may never reach it. Call `flushPendingRegistrations()` yourself at the end
of each request or job, then `resetRequestState()`; the reset drops anything still
queued, so one request's phrases never go out with another's.

**When a send fails** the items stay queued and the SDK backs off: the next flush
waits 3 seconds, then 6, doubling up to 5 minutes, and the first successful send
resets the wait. The wait belongs to the `Client`, so on a long-lived worker it
carries across requests, and a failing endpoint is not asked again by every request.
`retained` in the result counts what is still queued; `dropped` counts what nothing
will send.

**A phrase ending in an ellipsis** (`…` or `...`) is logged at debug level, since
upstream code may have cut a longer text short. It is not registered only when a
longer phrase starting with the same text is already known in its category, so
`Loading…` registers normally.

**Queue Management:**

```php
// Check for pending items
if ($client->hasPendingRegistrations()) {
    // ...
}

// Get pending items
$phrases = $client->getPendingPhrases();
$blocks = $client->getPendingContentBlocks();

// Clear without sending to API
$client->clearPendingRegistrations();
```

> **Note**: With a key that may not write, the flush sends nothing and reports the
> items as `dropped`. A key whose type writes but that the server answers with
> `write_enabled: false` (an `ip_write` key used from an address off its allow-list,
> for example) is reported once, as a warning, so a misconfigured integration is
> not silent.

### Get Translation Statistics

```php
$resource = $client->translations();
$stats = $resource->getStats('es-es');

echo "Total words: " . $stats['words'];
echo "Untranslated: " . $stats['untranslated'];
```

### Get Full Response with Metadata

```php
$resource = $client->translations();
$response = $resource->getFlat('es-es');

// Response includes:
// - status: bool
// - words: int (total translatable words)
// - untranslated: int (words not yet translated)
// - data: array (the translations)
```

## Registering Phrases

> **Note**: Requires a write-type API key.

### Register Simple Phrases

```php
$client->registerPhrases([
    'New Feature',
    'Click Here',
    'Learn More',
]);
```

### Register Phrases with Categories

```php
$client->registerPhrases([
    ['phrase' => 'Dashboard', 'category' => 'Navigation'],
    ['phrase' => 'Settings', 'category' => 'Navigation'],
    ['phrase' => 'Submit', 'category' => 'Forms'],
]);
```

### Register Phrases with Translatable Flag

```php
$client->registerPhrases([
    [
        'phrase' => 'Copyright 2024',
        'category' => 'Footer',
        'translatable' => false,  // Mark as non-translatable
    ],
]);
```

### Register Content Blocks

Content blocks group multiple phrases together (useful for HTML content). Phrases are automatically extracted from the HTML:

```php
// Simple usage - just pass HTML content
$client->registerContentBlock(
    '<nav><a>Home</a><a>About</a></nav>'
);
// Phrases auto-extracted: ['Home', 'About']
// customId auto-generated from content hash

// With category
$client->registerContentBlock(
    '<nav><a>Home</a><a>About</a></nav>',
    'Navigation'  // Category
);

// With category and label
$client->registerContentBlock(
    '<nav><a>Home</a><a>About</a></nav>',
    'Navigation',           // Category
    'Main Navigation Menu'  // Label
);

// With explicit customId (for stable ID to update content later)
$client->registerContentBlock(
    '<nav><a>Home</a><a>About</a></nav>',
    'Navigation',
    'Main Navigation Menu',
    'main-menu-001'  // Custom ID
);
```

**Auto-extraction supports:**
- Text nodes
- Button and submit input values
- Select option text
- Use `translate="no"` attribute to exclude elements from extraction

**URL Resolution:**
Relative URLs in `src`, `srcset`, and `poster` attributes are automatically converted to absolute URLs when content blocks are registered. The base URL is determined from:
1. The `base_url` config option (if set)
2. The `LANGSYS_BASE_URL` environment variable (if set)
3. Auto-detected from `$_SERVER['HTTP_HOST']` and protocol headers

**Default translatable attributes:**

| Category | Attributes |
|----------|------------|
| Standard HTML | `placeholder`, `alt`, `title`, `label` |
| ARIA | `aria-label`, `aria-placeholder`, `aria-description`, `aria-valuetext`, `aria-roledescription` |
| Validation | `data-error`, `data-error-message`, `data-validation-message`, `data-invalid-message`, `data-required-message`, `data-pattern-message` |
| Framework | `data-confirm`, `data-tooltip`, `data-title`, `data-content`, `data-original-title`, `data-bs-title`, `data-bs-content`, `data-loading-text`, `data-success-message`, `data-warning-message`, `data-empty-message`, `data-placeholder` |

```php
// Complex example with attributes
$client->registerContentBlock(
    '<form>
        <input placeholder="Your email" title="Enter email address">
        <button type="submit">Subscribe</button>
        <span translate="no">Do not translate this</span>
    </form>',
    'Forms'
);
// Extracted: ['Your email', 'Enter email address', 'Subscribe']
// Note: "Do not translate this" is skipped due to translate="no"
```

### Custom Translatable Attributes

You can customize which HTML attributes are extracted for translation:

```php
// Add your own attributes to the defaults
$client->addTranslatableAttributes(['data-label', 'data-tooltip', 'data-i18n']);

// Or replace the defaults entirely
$client->setTranslatableAttributes(['data-label', 'data-text']);

// Reset to defaults
$client->resetTranslatableAttributes();

// Check current attributes
$attrs = $client->getTranslatableAttributes();
```

This is useful when your application uses custom data attributes for translatable content:

```php
$client->addTranslatableAttributes(['data-label']);

$client->registerContentBlock(
    '<div data-label="Custom Label">Visible Text</div>',
    'UI'
);
// Extracted: ['Custom Label', 'Visible Text']
```

### Bulk Registration Methods

```php
$resource = $client->translatableItems();

// Register multiple phrases with same category
$resource->createPhrasesWithCategory(
    ['Error 404', 'Error 500', 'Something went wrong'],
    'Errors'
);

// Register from a category map
$resource->createFromMap([
    'Navigation' => ['Home', 'About', 'Contact'],
    'Forms' => ['Submit', 'Cancel', 'Reset'],
    'Messages' => ['Success', 'Error', 'Warning'],
]);
```

## Syncing Phrases

The `sync()` method compares local phrases against the remote API and registers any new ones:

```php
// Define phrases used in your application
$localPhrases = [
    ['phrase' => 'Home', 'category' => 'UI'],
    ['phrase' => 'About', 'category' => 'UI'],
    ['phrase' => 'New Feature', 'category' => 'UI'],
];

// Sync with remote
$result = $client->sync($localPhrases, 'es-es');

// Result contains:
// - translations: array (current translations)
// - new_phrases: array (phrases that were new)
// - synced: bool (true if new phrases were registered)

echo "New phrases found: " . count($result['new_phrases']);
echo "Synced: " . ($result['synced'] ? 'Yes' : 'No');
```

> **Note**: If using a read-only API key, `sync()` will identify new phrases but won't register them (`synced` will be `false`).

## Caching

The SDK caches translations to reduce API calls. Three cache drivers are available:

### File Cache (Default)

```php
use Langsys\SDK\Cache\FileCache;

$client = new Client(null, null, [
    'cache' => new FileCache('/path/to/cache', 3600),
]);
```

### Redis Cache

```php
use Langsys\SDK\Cache\RedisCache;

// With connection options
$client = new Client(null, null, [
    'cache' => new RedisCache([
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => 'secret',
        'database' => 0,           // Redis database number
        'prefix' => 'langsys::',   // Key prefix (default: 'langsys::')
    ]),
]);

// Or with existing Redis instance
$redis = new Redis();
$redis->connect('127.0.0.1');

$client = new Client(null, null, [
    'cache' => new RedisCache($redis, 'myapp:langsys::', 3600),
]);

// Or use the cache_driver option with redis settings
$client = new Client('api-key', 'project-id', [
    'cache_driver' => 'redis',
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'database' => 1,
        'prefix' => 'myapp::langsys::',
    ],
]);
```

### No Caching

```php
use Langsys\SDK\Cache\NullCache;

$client = new Client(null, null, [
    'cache' => new NullCache(),
]);
```

### Cache Management

```php
// Clear cache for specific locale
$client->clearCache('es-es');

// Clear all cache
$client->clearCache();

// Bypass cache for a single request
$translations = $client->getTranslations('es-es', false);

// Clear cache on initialization
$client = new Client('key', 'project', ['cache_clear' => true]);
```

## Logging

The SDK logs to a file in JSON Lines format when a writable file path is configured. Without one, warnings and errors still go to PHP's error log, prefixed `[langsys]`, so a failed registration or an unreachable API is never recorded nowhere; nothing below warning is written there. Pass `'error_log' => false` to turn that off.

### Enable Logging

```php
// Via constructor options
$client = new Client('key', 'project', [
    'log_path' => '/var/log/langsys.log',
    'log_level' => 'debug',  // debug, info, warning, error
]);

// Via environment variables
putenv('LANGSYS_LOG_PATH=/var/log/langsys.log');
putenv('LANGSYS_LOG_LEVEL=info');
$client = new Client();
```

### Log Levels

| Level | Description |
|-------|-------------|
| `debug` | Detailed debugging info (cache hits/misses, request details) |
| `info` | General operational events (API requests completed, authorization) |
| `warning` | Non-critical issues (a key whose type writes but that the server refuses, a translation that fails to format) |
| `error` | Errors (API request failures, exceptions) |

### What Gets Logged

- **API Requests**: Method, URL, status code, duration (ms)
- **Cache Operations**: Cache hits, misses, sets, deletes
- **Authorization**: Success/failure, key type
- **Registration**: Phrases and content blocks queued/flushed

### Log Context Variables

Each log entry includes a `context` object with relevant data. Here are the context variables for each log message:

**API Requests:**

| Message | Level | Context Variables |
|---------|-------|-------------------|
| `API request starting` | debug | `method`, `url`, `request_body_size`* |
| `API request completed` | info | `method`, `url`, `status_code`, `duration_ms` |
| `API request redirect` | warning | `method`, `url`, `status_code`, `duration_ms` |
| `API request error` | error | `method`, `url`, `status_code`, `duration_ms`, `response_body`**, `payload`*** |
| `API request failed` | error | `method`, `url`, `error`, `errno`, `http_code`, `duration_ms`, `payload`*** |

\* `request_body_size` only included for POST requests
\** `response_body` truncated to 1000 characters
\*** `payload` contains full request body (not truncated); only included for POST requests on errors

**Cache Operations:**

| Message | Level | Context Variables |
|---------|-------|-------------------|
| `Cache hit` | debug | `key`, `source` (`memory`, `file`, or `redis`) |
| `Cache miss` | debug | `key`, `reason` (`not_found`, `expired`, `invalid`) |
| `Translations cache hit` | debug | `locale`, `source` |
| `Translations cache miss` | debug | `locale` |
| `Cache set` | debug | `key`, `ttl` |
| `Cache delete` | debug | `key` |
| `Cache cleared` | debug | `keys_removed` |
| `Cache cleared on initialization` | info | (none) |

**Authorization:**

| Message | Level | Context Variables |
|---------|-------|-------------------|
| `Project authorized` | info | `project_id`, `key_type` |

**Translations:**

| Message | Level | Context Variables |
|---------|-------|-------------------|
| `Fetching translations` | debug | `locale` |

**Registration:**

| Message | Level | Context Variables |
|---------|-------|-------------------|
| `Phrase queued for registration` | debug | `phrase`, `category` |
| `Content block queued for registration` | debug | `custom_id`, `category`, `phrase_count` |
| `Pending registrations flushed` | info | `phrases`, `content_blocks`, `success` |
| `Cannot flush registrations: read-only key` | warning | (none) |

### Log Entry Format

Logs are written in JSON Lines format, one entry per line:

```json
{"timestamp":"2025-01-15T10:30:45.123456Z","level":"info","message":"API request completed","context":{"method":"GET","url":"https://api.langsys.dev/api/translations","status_code":200,"duration_ms":142.5}}
{"timestamp":"2025-01-15T10:30:45.125000Z","level":"debug","message":"Cache set","context":{"key":"translations_proj123_es-es","ttl":3600}}
{"timestamp":"2025-01-15T10:30:46.000000Z","level":"error","message":"API request error","context":{"method":"POST","url":"https://api.langsys.dev/api/translatable-items","status_code":500,"duration_ms":89.2,"response_body":"{\"error\":\"Internal Server Error\",\"message\":\"Database connection failed\"}"}}
{"timestamp":"2025-01-15T10:30:47.000000Z","level":"error","message":"API request failed","context":{"method":"POST","url":"https://api.langsys.dev/api/translatable-items","error":"Operation timed out after 30001 milliseconds","errno":28,"http_code":0,"duration_ms":30001.5}}
```

### Log Viewer

The SDK includes a built-in log viewer with a Flowbite/Tailwind UI:

```php
// Get the LogViewer instance
$viewer = $client->getLogViewer();

// Get log entries (optionally filter by minimum level)
$entries = $viewer->getEntries('warning');

// Get statistics by level
$stats = $viewer->getStats();
// ['total' => 100, 'debug' => 50, 'info' => 30, 'warning' => 15, 'error' => 5]

// Render as HTML page
$html = $viewer->render('info');

// Output directly to browser
$client->displayLogs();

// Clear the log file
$viewer->clear();

// Get file size
echo $viewer->getFormattedFileSize(); // "1.5 MB"
```

**URL Parameters for `displayLogs()`:**

| Parameter | Values | Default | Description |
|-----------|--------|---------|-------------|
| `level` | `debug`, `info`, `warning`, `error` | `debug` | Minimum log level to display |
| `format` | `json` | `html` | Return JSON data instead of HTML page |
| `action` | `clear` | - | Clear all log entries |
| `refresh` | Integer (seconds) | - | Legacy: auto-refresh page every N seconds |

Example URLs:
- `/logs.php` - Show all logs (debug+)
- `/logs.php?level=warning` - Show warnings and errors only
- `/logs.php?format=json` - Get logs as JSON (for AJAX)
- `/logs.php?action=clear` - Clear log file (returns JSON response)

The log viewer includes:
- **Realtime mode** - Toggle button to enable AJAX polling (updates every 2 seconds without page reload)
- **Dynamic filters** - Level filter buttons update instantly without page reload
- **Clear logs** - Button to clear all log entries with confirmation
- **Persistent settings** - Filter level and realtime mode are saved in localStorage
- **Hide messages** - Hide repetitive log messages from display
- Statistics cards showing counts by log level
- Expandable context for each entry with close button

## Server Messages

Validation errors and other messages your server sends can be translated like any
phrase. Each message travels as an entry with a fixed shape: `code` for your logic,
`template` for translation, `params` for its values, and `message` already filled
in. A translator sees one whole sentence, so gender and number come out right.

### Build entries

```php
use Langsys\SDK\Messages\ServerMessage;

$entry = ServerMessage::make('too_short', 'The password must be at least {min} characters.', ['min' => 12], 'password');

$entry->getMessage(); // "The password must be at least 12 characters."
$entry->toArray();    // field, code, message, template, params
```

Write everything translatable into the template, the field's label included. Use a
`{name}` marker only for a value that isn't translatable: a number, a date, what the
user typed.

### Render entries

```php
use Langsys\SDK\Messages\MessageSet;

$messages = MessageSet::fromResponse($responseBody); // finds entries wherever they sit

foreach ($messages->forField('password') as $entry) {
    echo $client->translateMessage($entry); // translated, or the server's message until it is
}
```

`translateMessage()` looks the template up under the `Errors` category and never uses
the filled message as a key. When your server sends an entry, pass it through
`$client->emitMessage($entry)`: a template the catalog doesn't have yet is registered
after the response.

### Register every template ahead of time

A message doesn't exist until something fails, so no page shows it for discovery. List
them from your code instead:

```php
// langsys-messages.php
use Langsys\SDK\Client;
use Langsys\SDK\Messages\ErrorClassSource;

return [
    'sources' => [new ErrorClassSource([App\Errors\QuotaExceeded::class])],
    'client' => function () { return new Client(); },
];
```

```bash
vendor/bin/langsys-messages             # lists templates; exits 1 naming each one it can't list
vendor/bin/langsys-messages --register  # registers the ones the catalog doesn't have yet
```

An error class declares `CODE` and `MESSAGE` constants and a public property for each
marker. Run the command in CI so a message that can't be registered ahead of time
fails the build.

## Migrating from Key-Based Translation Files

If your app calls translations by key (`checkout.submit`) and keeps its text in
language files, you can move to Langsys without rewriting a single call. Keep only
your source-language file, delete the others, and name it in the `migration` option:

```php
$client = new Client('your-api-key', 'your-project-id', [
    'migration' => [
        'files' => [
            ['path' => 'lang/en.json', 'format' => 'laravel'],
            'lang/en/checkout.php',
        ],
    ],
]);

echo $client->translate('checkout.submit'); // looks up "Place order" in your file
```

- **A key resolves to its text.** The text, not the key, is what gets registered and
  translated, so you can later replace the key with the text and delete the file with
  nothing else changing.
- **The key's namespace becomes the category.** `checkout.submit` registers under
  `checkout`, or under the category you pass.
- **Anything that isn't a key is text.** `translate('Pay now')` works as always, and
  registers exactly as written: `translate()` takes Langsys syntax.
- **A framework's own call converts its own syntax.** Text that reaches Laravel's
  `__()` or `trans_choice()` without being a key is written in Laravel's syntax, and
  `LegacyValue::fromCall($text, $replace, '__')` (or `'trans_choice'` with the
  number) turns it into the Langsys phrase and the params to render it with, the way
  Laravel reads it: only the placeholders the call passes convert, a `|` is a plural
  only through `trans_choice()`, and `:Name`/`:NAME` stay as written with a warning.
  So `__('Hello :name', ['name' => $n])` and `translate('Hello {name}')` are one phrase.
- **Placeholders are converted in every file.** `:name`, `{{name}}`, `{name}`, `%{name}`,
  `%(name)s` and `%(name)d` all become `{name}`, and `%%` is a literal `%`. A placeholder
  carrying number formatting (`%.2f`, `%(amount).2f`) or no name (`%s`) is registered as
  written, with a warning.
- **Plurals are converted by the file's format**: `laravel`, `vue-i18n`, `i18next` or
  `plain`. The frameworks read the same characters differently (`car | cars` is a
  plural in vue-i18n and plain text in Laravel), so each file says which one wrote it.
  A PHP array file is `laravel` unless you say otherwise; a JSON file is `plain`, which
  converts no plurals, so declare the format of any JSON file that holds them. Any other
  format, such as a Rails YAML file or a gettext `.po`, is refused when the `Client` is
  created, with an error naming the format and the file.
- **A JSON file can sit under a namespace**: `['path' => 'locales/en/cart.json',
  'namespace' => 'cart']` answers `cart.items.title` from `{"items": {"title": ...}}`, as
  a PHP array file answers keys under its own name.
- **Forms that can't be converted are registered as written, with a warning**: a
  capitalising placeholder like `:Name`, or a plural range with no exact equivalent.
- **A framework's own bundled strings go in `fallback_files`**, which answer only
  keys your `files` don't define. Package keys (`courier::messages.welcome`) resolve
  through `'namespaces' => ['courier' => ['files' => [...], 'fallback_files' => [...]]]`.

With no `migration` option nothing is read and nothing is looked up.

List what can't be migrated as it stands by adding
`new Langsys\SDK\Migration\LegacyKeysSource($client->getLegacyKeys())` to the sources
in your `langsys-messages.php`: it names each value it can't convert and each key
defined in more than one of your files.

## Catalog Snapshots

A snapshot is your catalog for chosen languages and categories, saved to a file, for
setups that shouldn't call the API while rendering: a mobile bundle, a first paint,
an offline or air-gapped deploy.

```bash
vendor/bin/langsys-snapshot --locale=es-es --locale=fr-fr --category=UI --category=Errors --out=catalog.snapshot.json
```

The command reads `LANGSYS_API_KEY` and `LANGSYS_PROJECT_ID`, or a `--config` file
returning `['client' => ...]`. It keeps exactly what the API returns for the
categories you name.

```php
use Langsys\SDK\Snapshot\Snapshot;

$snapshot = Snapshot::load('catalog.snapshot.json');
$spanish = $snapshot->catalog('es-es'); // category => entries, as the API returns them
```

A snapshot is a cache, never a source. **To refresh one, export it again.** Don't edit
it: every snapshot carries a checksum of its contents, and `Snapshot::load()` refuses
one that has changed since it was exported.

Every Langsys SDK writes and reads the same snapshot format, `langsys-catalog-snapshot`
version 1, so a snapshot exported here loads in another SDK and the other way round.
It carries the project's base locale (`$snapshot->baseLocale()`) along with the catalog.
A load that fails says why: `SnapshotException::getReason()` is `format`, `version`,
`missing-member` or `checksum`.

## Error Handling

The SDK throws specific exceptions for different error types:

```php
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Exception\AuthenticationException;
use Langsys\SDK\Exception\ValidationException;
use Langsys\SDK\Exception\ApiException;

try {
    $client = new Client();
    $translations = $client->getTranslations('es-es');
} catch (AuthenticationException $e) {
    // 401 - Invalid or expired API key
    echo "Auth failed: " . $e->getMessage();
} catch (ValidationException $e) {
    // 422 - Invalid request data
    echo "Validation failed: " . $e->getMessage();
    print_r($e->getErrors());

    // The error's code and its message entries, when the API sends them
    echo $e->getErrorCode();
    foreach ($e->getServerMessages()->forField('name') as $entry) {
        echo $client->translateMessage($entry);
    }
} catch (ApiException $e) {
    // Other API errors (4xx, 5xx)
    echo "API error: " . $e->getMessage();
    echo "HTTP code: " . $e->getHttpStatusCode();
} catch (LangsysException $e) {
    // Base exception (configuration errors, etc.)
    echo "Error: " . $e->getMessage();
}
```

## Integration Example

Create a helper function for use in your templates:

```php
<?php
// helpers.php

use Langsys\SDK\Client;

function __($phrase, $category = '__uncategorized__')
{
    static $client = null;
    static $locale = null;

    if ($client === null) {
        $client = new Client();
        $locale = $_SESSION['locale'] ?? 'en-us';
    }

    return $client->translate($phrase, $locale, $category);
}

// Usage in templates:
echo __('Home', 'UI');
echo __('Welcome');
```

## Project Information

Get information about the connected project:

```php
$project = $client->getProject();

echo $project['id'];           // Project UUID
echo $project['title'];        // Project name
echo $project['base_locale'];  // e.g., "en-us"
echo $project['key_type'];     // "read" or "write"

print_r($project['target_locales']);  // ["es-es", "fr-ca", ...]
print_r($project['default_locales']); // Default locale per language
```

## Utilities

The SDK includes utility methods for fetching countries, dial codes, and locales.

### Countries

```php
$utils = $client->utilities();

// Get paginated list of countries (names in Spanish)
$response = $utils->getCountries('es-es', ['page' => 1, 'records_per_page' => 25, 'order_by' => 'label:ASC']);
// Returns: ['status' => true, 'page' => 1, 'data' => [['label' => 'Costa Rica', 'code' => 'CR'], ...]]

// Get all countries without pagination
$countries = $utils->getAllCountries('en-us');
// Returns: [['label' => 'Costa Rica', 'code' => 'CR'], ...]

// Get countries as select options (code => label)
$options = $utils->getCountrySelectOptions('en-us');
// Returns: ['CR' => 'Costa Rica', 'US' => 'United States', ...]
```

### Dial Codes

```php
$utils = $client->utilities();

// Get paginated list of dial codes
$response = $utils->getDialCodes('en-us');
// Returns: ['status' => true, 'data' => [['country_code' => 'CR', 'dial_code' => '506', 'name' => 'Costa Rica (+506)'], ...]]

// Get all dial codes without pagination
$dialCodes = $utils->getAllDialCodes('en-us');

// Get dial codes as select options
$options = $utils->getDialCodeSelectOptions('en-us');
// Returns: ['CR' => 'Costa Rica (+506)', 'US' => 'United States (+1)', ...]
```

### Locales

```php
$utils = $client->utilities();

// Get locales grouped by language
$response = $utils->getLocalesGrouped(['en-us']);
// Returns: ['status' => true, 'data' => ['en-us' => ['Spanish' => [['code' => 'es-cr', 'name' => 'Spanish (Costa Rica)'], ...]]]]

// Get flat list of locales
$response = $utils->getLocalesFlat(['en-us']);
// Returns: ['status' => true, 'data' => ['en-us' => [['code' => 'es-cr', 'name' => 'Spanish (Costa Rica)'], ...]]]

// Get detailed locale information
$response = $utils->getLocalesDetailed(['en-us']);

// Get simple list for a single display locale
$locales = $utils->getLocaleList('en-us');
// Returns: [['code' => 'es-cr', 'name' => 'Spanish (Costa Rica)'], ...]

// Get locales as select options
$options = $utils->getLocaleSelectOptions('en-us');
// Returns: ['es-cr' => 'Spanish (Costa Rica)', 'fr-ca' => 'French (Canada)', ...]

// Include project's target locales
$options = $utils->getLocaleSelectOptions('en-us', true);
```

### Building Form Dropdowns

```php
$utils = $client->utilities();

// Country dropdown
echo '<select name="country">';
foreach ($utils->getCountrySelectOptions('en-us') as $code => $name) {
    echo "<option value=\"$code\">$name</option>";
}
echo '</select>';

// Phone dial code dropdown
echo '<select name="dial_code">';
foreach ($utils->getDialCodeSelectOptions('en-us') as $code => $name) {
    echo "<option value=\"$code\">$name</option>";
}
echo '</select>';

// Language/locale dropdown
echo '<select name="locale">';
foreach ($utils->getLocaleSelectOptions('en-us') as $code => $name) {
    echo "<option value=\"$code\">$name</option>";
}
echo '</select>';
```

## Testing

The SDK includes a comprehensive test suite using PHPUnit.

### Running Tests

```bash
# Install dev dependencies
composer install

# Run all tests
vendor/bin/phpunit

# Run specific test file
vendor/bin/phpunit tests/ConfigTest.php

# Run with coverage report (requires Xdebug)
vendor/bin/phpunit --coverage-text
```

The contract tests in `tests/Contract` run against a shared double of the Langsys API
(`tests/contract-fixture/`) and need Node 18 or later; without Node they are skipped.


### Test Structure

```
tests/
├── bootstrap.php                    # Test autoloader
├── ConfigTest.php                   # Configuration tests
├── ClientTest.php                   # Main client tests
├── Mock/
│   └── MockHttpClient.php           # Mock HTTP client for testing
├── Cache/
│   ├── NullCacheTest.php            # Null cache tests
│   ├── FileCacheTest.php            # File cache tests
│   └── RedisCacheTest.php           # Redis cache tests (skipped if no ext)
├── Html/
│   ├── HtmlParserTest.php           # HTML phrase extraction tests
│   ├── HeadHandlerTest.php          # Head section translation tests
│   └── PageTranslatorTest.php       # Full page translation tests
├── Locale/
│   └── LocaleDetectorTest.php       # Locale detection tests
├── Resources/
│   ├── TranslationsTest.php         # Translations resource tests
│   ├── TranslatableItemsTest.php    # TranslatableItems resource tests
│   └── UtilitiesTest.php            # Utilities resource tests
├── Log/
│   ├── LoggerTest.php               # Logger tests (JSON Lines, level filtering)
│   ├── NullLoggerTest.php           # NullLogger tests
│   └── LogViewerTest.php            # LogViewer tests
└── Exception/
    └── ExceptionTest.php            # Exception class tests
```

### Test Coverage

- **350 tests** covering all public methods
- **765 assertions** validating functionality
- Redis tests automatically skip if the extension is unavailable
- Tests use a mock HTTP client to avoid real API calls

## License

MIT
