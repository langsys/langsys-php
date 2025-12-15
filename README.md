# Langsys PHP SDK

Official PHP SDK for the [Langsys](https://langsys.dev) Translation API. Manage translations, register phrases, and sync your application's translatable content.

## Requirements

- PHP 5.6 or higher (tested up to PHP 8.4)
- cURL extension
- JSON extension
- Redis extension (optional, for Redis caching)

## Installation

### Via Composer (Recommended)

```bash
composer require langsys/php-sdk
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
| `LANGSYS_LOG_PATH` | No | - | Path to log file (logging disabled if not set) |
| `LANGSYS_LOG_LEVEL` | No | `info` | Minimum log level: `debug`, `info`, `warning`, `error` |

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

The SDK can auto-detect the visitor's locale from the browser:

```php
// Explicit locale
$client->setLocale('fr-ca');

// Or let it auto-detect from HTTP_ACCEPT_LANGUAGE
$locale = $client->getLocale(); // Returns detected locale or project's base_locale

// Chained usage
echo (new Client())
    ->setLocale('de-de')
    ->translatePage($html, 'contact');
```

### How It Works

1. **Single-phrase blocks** are registered as simple phrases:
   - `<p>Hello</p>` → phrase "Hello"
   - `<p><strong>Hello World</strong></p>` → phrase "Hello World" (inline formatting preserved)
   - `<p><a href="#">Click here</a></p>` → phrase "Click here"
2. **Multi-phrase blocks** are registered as content blocks:
   - `<p><strong>Hello</strong> World</p>` → content block with phrases ["Hello", "World"]
   - `<nav><a>Home</a><a>About</a></nav>` → content block with phrases ["Home", "About"]
   - `<p><input placeholder="Email"></p>` → content block (phrase from attribute)
3. **Head section** is processed for translatable meta tags
4. **`translate="no"`** attribute is respected to skip elements
5. **Script/style** tags are never processed

The key distinction: if a block element contains exactly **one phrase** that matches its text content, it's a simple phrase. Multiple phrases (from text nodes or attributes) = content block.

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

**Truthy values:** `"true"`, `"1"`, `"yes"`, or any non-empty string (but not `"false"`, `"0"`, or `""`)

**Use cases:**
- Complex widgets that should be translated as a unit
- Content where phrase order and context is critical
- Reusable components with interdependent text

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
```

If the phrase doesn't exist in translations, it will be:
1. Queued for registration (if write key)
2. Returned as-is (fallback to original)
3. Added to in-memory cache to prevent re-queueing in same request

### Translate a Content Block

The `translateContentBlock()` method translates HTML content AND automatically queues new content blocks for registration:

```php
$client->setLocale('es-es');

$html = '<p><a href="#">Click here</a> to learn more</p>';

// Basic usage
$translated = $client->translateContentBlock($html);

// With category
$translated = $client->translateContentBlock($html, 'homepage');
```

This uses the same phrase extraction logic as `registerContentBlock()`, ensuring consistent behavior between translation and registration.

### Automatic Registration (Queuing)

The SDK automatically queues new phrases and content blocks during translation and flushes them at the end of the request:

```php
$client->setLocale('es-es');

// These queue new items automatically:
$client->translate('New phrase');
$client->translateContentBlock('<p>New content</p>');
$client->translatePage($html);

// Items are flushed automatically via PHP shutdown handler
// Or flush manually:
$result = $client->flushPendingRegistrations();
// Returns: ['phrases' => 5, 'content_blocks' => 2, 'success' => true]
```

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

> **Note**: With a read-only API key, items are queued but silently skipped during flush (no errors).

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

The SDK includes optional file-based logging in JSON Lines format. Logging is disabled by default and only enabled when a valid writable file path is configured.

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
| `warning` | Non-critical issues (read-only key can't register phrases) |
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
