---
name: langsys-php
description: The Langsys PHP core skill, for langsys/langsys-php and every Langsys PHP binding built on it. Part of the Langsys skill set, read in order - the entry skill langsys first, then this one, then the framework's own skill (langsys-laravel). Covers translate(), translateRich(), translatePage() and translateContentBlock(), the data-langsys-* attributes, whether a request registers (write_enabled, canWrite()), the Client's options, interpolation and ICU, value markers, server messages, key-based language files, sync and snapshots, the request's locale and Vary, reference lists, caching, and long-lived workers.
metadata:
  package: langsys/langsys-php
  version: 2.0.1
---

# Langsys PHP core

<!-- langsys-skill-preamble:start -->
**This skill is one part of the Langsys skill set.** Read the set in this order, and never skip a step:

1. **`langsys`**, the entry skill: what Langsys is, choosing the SDKs, the project, API keys and secrets, and the rules every Langsys SDK shares.
2. **The core skill of the SDK's language**: `langsys-typescript` for JavaScript and TypeScript, `langsys-php` for PHP. It holds what is true for every framework in that language.
3. **The framework's own skill**, such as `langsys-svelte` or `langsys-laravel`. It adds only what its framework changes.

The entry skill isn't installed with any SDK. If `langsys` isn't among your skills, read it at https://docs.langsys.dev/skill/langsys/. If you can't reach that page, tell the user to run `npx langsys-skill install`, which installs it with the skill of every Langsys package in the project.
<!-- langsys-skill-preamble:end -->

This is `langsys-php`, step 2 for PHP. It describes `langsys/langsys-php` at the version in its frontmatter. Check the installed version first (`composer show langsys/langsys-php`). If it differs, say so before relying on anything here.

`langsys/langsys-php` is the PHP SDK every Langsys PHP binding builds on: the API client, the catalog and its cache, phrase discovery and registration, the HTML reader that walks pages and fragments, interpolation and ICU, the request's locale, server messages, the key-based migration and sync. A binding's skill (`langsys-laravel`) reads this one first and adds only what its framework changes. Where a binding forwards a call, the behaviour is the one described here. The examples are plain PHP 7.4.

## The model

- **The source text is the key.** There are no catalog files and no message ids: `$client->translate('Save', null, 'UI')` looks up the phrase `Save` and returns it as-is until a translation exists.
- **Content is discovered as the app runs.** A request the API lets write registers each phrase and content block it misses, after the response, and the Translation Manager fills in the translations.
- **The catalog is fetched per project and locale**, held in memory for the request and in the cache (a file by default) across requests.
- **Untranslated text renders as its source**, so the base language always looks right. That is why most mistakes here are invisible: they show only when someone switches locale or reads what was registered in the Translation Manager.

## The rules that matter most

1. **Never build a phrase.** `translate(sprintf('Hello, %s!', $name))` registers one phrase per distinct value, permanently, in a catalog every SDK on the project shares. Pass values as params: `translate('Hello, {name}!', null, 'Greetings', null, ['name' => $name])`. [invariants.md](./invariants.md)
2. **The locale comes before the category.** `translate($phrase, $locale, $category, $contentBlockId, $params)`. Pass `null` for the locale to use the request's. A category in the second place is read as a locale, so the phrase renders as its source in every language. This is the opposite of the JavaScript SDKs' `t(phrase, category, params)`.
3. **Choose the call by asking whether a tag boundary belongs to the language or to the layout.** Plain text is `translate()`. One line with inline markup is `translateRich()`, or `data-langsys-phrase` on a page. A fragment is `translateContentBlock()`, a whole document `translatePage()`. [primitives.md](./primitives.md)
4. **The API decides whether a request registers, not the key's type.** An answer without `write_enabled: true` is read-only, even for a `write` key. The entry skill holds the concept; [writing.md](./writing.md) has how the SDK exposes it.
5. **A long-lived worker flushes and resets.** The automatic flush runs when a `Client` is freed or the process ends. A worker that keeps one `Client` calls `flushPendingRegistrations()` and then `resetRequestState()` after each request or job. [server-rendering.md](./server-rendering.md)
6. **Locale codes are lowercase `xx-yy`** (`es-es`, `zh-hant-tw`). `setLocale()` and every lookup accept `es_ES` or `es-ES` and normalise them; compare in the lowercase form. [locales.md](./locales.md)

## Where to read

| Read | When |
|---|---|
| [primitives.md](./primitives.md) | Before marking any content: the four calls, the `data-langsys-*` attributes, opting out, categories |
| [invariants.md](./invariants.md) | Before writing code: the rules other i18n habits break |
| [writing.md](./writing.md) | Phrases aren't registering: `canWrite()`, the queue, the flush result, explicit registration |
| [init-config.md](./init-config.md) | Building the `Client`: environment variables, options, a client without credentials, logging, exceptions |
| [interpolation.md](./interpolation.md) | Placeholders, how each value type renders, ICU plurals and select, declared value sets |
| [value-markers.md](./value-markers.md) | HTML a template engine printed values into; placeholder names |
| [messages.md](./messages.md) | Validation errors and other messages your server sends |
| [migration.md](./migration.md) | Moving off key-based language files: the `migration` option, the import, sync from source, the fallback, snapshots |
| [locales.md](./locales.md) | The request's locale, `Vary`, the project's locales, the country, currency and locale lists |
| [catalog.md](./catalog.md) | How the catalog is fetched and cached, and what happens when the API can't be reached |
| [server-rendering.md](./server-rendering.md) | Rendering a whole page, marking it resolved, a JavaScript SDK on the same page, long-lived workers |

## Classes

Everything starts from `Langsys\SDK\Client`. The other classes an app names:

| Class | Holds |
|---|---|
| `Langsys\SDK\Client` | Every translate call, registration, the request's locale, the reference lists, the queue |
| `Langsys\SDK\Cache\FileCache`, `RedisCache`, `NullCache` | The catalog cache, passed as the `cache` option ([catalog.md](./catalog.md)) |
| `Langsys\SDK\Messages\ServerMessage`, `MessageSet` | Server messages to send and to render ([messages.md](./messages.md)) |
| `Langsys\SDK\Snapshot\Snapshot` | A saved catalog to render from without the API ([migration.md](./migration.md)) |
| `Langsys\SDK\Sync\SourceScanner`, `Planner` | Sync from source code ([migration.md](./migration.md)) |
| `Langsys\SDK\Exception\*` | `LangsysException` and the four that extend it ([init-config.md](./init-config.md)) |

Two commands ship in `vendor/bin`: `langsys-messages` lists and registers server message templates ([messages.md](./messages.md)), and `langsys-snapshot` exports a snapshot ([migration.md](./migration.md)).

The package requires PHP 7.4 or later and the `curl`, `json`, `intl`, `dom`, `libxml`, `filter` and `tokenizer` extensions. Composer refuses a host without one.
