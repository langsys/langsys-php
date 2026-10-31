# Rendering on a server

## A whole page: `translatePage()`

```php
ob_start();
// ... the app renders the page ...
$html = ob_get_clean();

$client = new Client();
echo $client->translatePage($html, 'Homepage');   // the request's locale
```

The arguments are `($html, $category = null, array $selectorCategories = [], array $params = [])`; the selectors and categories are in [primitives.md](./primitives.md), the params in [interpolation.md](./interpolation.md). The page is read as a browser reads it, from its byte-order mark, its declared charset or its bytes, and written back in UTF-8 with every charset it declares rewritten to UTF-8. Scripts and styles come back byte for byte.

- The body is walked into units, each a phrase or a content block, and what the catalog lacks is queued ([writing.md](./writing.md)).
- In the head, the `<title>` and the `description`, `keywords`, `author`, `og:title`, `og:description`, `og:site_name`, `twitter:title` and `twitter:description` metas are translated, and `og:locale` is set to the page's locale. The `<html>` gets `lang`. A document without a head gets the one a browser implies, declaring UTF-8; a fragment gets none.
- Each rendered block host carries its id ([primitives.md](./primitives.md)).
- A page whose catalog can't be read comes back as given ([catalog.md](./catalog.md)).

`translatePage()` takes no locale argument: set one with `setLocale()`, or let the request decide ([locales.md](./locales.md)).

## A translated page is marked resolved

A page rendered in a language other than the project's base is output, not source:

```html
<html lang="es-es" data-ls-resolved="es-es">
```

Anything that walks it later, such as a JavaScript SDK hydrating it or a middleware translating the response again, registers none of its text, so Spanish never enters the catalog as a source phrase. A page rendered in the base language carries no marker and stays discoverable. A marker you put on the root yourself, in either spelling, is left as it is.

A page your app translated some other way gets the same marker without being translated again:

```php
$html = $client->markResolved($html, 'es-es');   // the <html> tag, or each top-level element of a fragment
```

**A client-side app mounted inside a translated page** renders source text of its own, which should still be discovered. Opt its mount point out: `<div id="app" data-ls-resolved="false"></div>`. The nearest marked ancestor decides, and only `false` or `0` opt out.

## A JavaScript SDK on the same page

A PHP backend and a JavaScript frontend on one project are two SDKs rendering into one catalog. The entry skill's two-languages.md is the checklist: the same text gets the same id in both, and each piece of content has one owner.

What this SDK guarantees on its side:

- **Ids, tokens and placeholders match the JavaScript SDKs'**, so a block or a phrase registered by either renders from the other.
- **`data-langsys-phrase` survives into the rendered HTML.** A JavaScript SDK whose tokenizer recognises it (it skips subtrees carrying it or its own `data-ls-phrase`) leaves it alone. One that doesn't walks into it and re-registers the run split at its tags. Check the JavaScript SDK's version before relying on keep-together runs in a hydrated page.
- **A JavaScript app rendering its own components from the catalog never sees this SDK's DOM**, so none of this applies to it.

## Long-lived workers

A `Client` sends its queue when it is freed, or from a shutdown function when the process ends. A worker that keeps one `Client` across requests (Octane, Swoole, RoadRunner, a queue worker) reaches neither until it exits, and would carry one request's write decision, catalogs and locale into the next. After each request or job:

```php
$client->flushPendingRegistrations();
$client->resetRequestState();
```

The reset drops anything still queued, so flush first. It clears the write decision, the queue, the catalogs read for the request and the locale, one set with `setLocale()` included: a worker that serves one fixed locale calls `setLocale()` again after each reset. A failed send's backoff belongs to the `Client` and carries across requests on purpose, so a failing endpoint isn't asked again by every request.

**Choose each request's locale from that request.** In Swoole or RoadRunner the superglobals and `header()` don't belong to the request being served, so `getLocale()` would read stale or empty values and its `Vary` would go nowhere. Pass the request, and set the response's header yourself:

```php
$choice = $client->resolveRequestLocale([
    'path' => $request->path, 'host' => $request->host, 'query' => $request->query,
    'cookies' => $request->cookies, 'accept_language' => $request->header('Accept-Language'),
], ['send_vary' => false]);
$client->setLocale($choice['locale']);
if ($choice['vary'] !== null) {
    $response->header('Vary', $choice['vary']);
}
```

A killed process (out of memory, a hard timeout) sends nothing it still had queued. The misses register again on a later render.

## PHP-FPM: the send happens before the response closes

Without a worker, the queue goes out from the shutdown function, which PHP runs before it closes the connection: the visitor's page finishes loading only after the send, and up to 30 seconds later when the API hangs. Frameworks that end the response first (Laravel's terminate step) avoid this. In plain PHP, end it yourself:

```php
echo $client->translatePage($html, 'Homepage');
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();   // the visitor has the page
}
$client->flushPendingRegistrations();
```
