# The `Client` and its configuration

```php
use Langsys\SDK\Client;

$client = new Client();                                  // LANGSYS_API_KEY and LANGSYS_PROJECT_ID
$client = new Client($apiKey, $projectId, $options);    // null for either reads its variable
```

Build one `Client` per request, or one per worker that flushes and resets per request ([server-rendering.md](./server-rendering.md)). Building it sends nothing: the project is authorized on first use, and `authorize()` does it at once. `getProject()` returns the project: `id`, `title`, `base_locale`, `target_locales`, `default_locales` and `key_type`.

## Environment variables

| Variable | Default | |
|---|---|---|
| `LANGSYS_API_KEY` | none | The API key. Keep it in the environment, never in the repository |
| `LANGSYS_PROJECT_ID` | none | The project's UUID |
| `LANGSYS_API_URL` | `https://api.langsys.dev/api` | The API's base URL |
| `LANGSYS_CACHE_DRIVER` | `file` | `file`, `redis` or `none` |
| `LANGSYS_CACHE_PATH` | `langsys-cache` in the system temp directory | The file cache's directory |
| `LANGSYS_CACHE_TTL` | `3600` | Seconds a cached catalog is kept |
| `LANGSYS_BASE_URL` | the request's host | Makes relative URLs in registered blocks absolute |
| `LANGSYS_LOG_PATH` | none | A JSON Lines log file |
| `LANGSYS_LOG_LEVEL` | `info` | `debug`, `info`, `warning` or `error` |
| `LANGSYS_MESSAGES_CATEGORY` | `Errors` | The category of server message templates |

## Options

Each option overrides its variable where it has one.

| Option | Default | |
|---|---|---|
| `api_key` | `LANGSYS_API_KEY` | The API key, when the first argument is `null` |
| `project_id` | `LANGSYS_PROJECT_ID` | The project's UUID, when the second argument is `null` |
| `api_url` | `LANGSYS_API_URL` | The API's base URL |
| `cache_driver` | `LANGSYS_CACHE_DRIVER` | `file`, `redis` or `none` |
| `cache_path` | `LANGSYS_CACHE_PATH` | The file cache's directory |
| `cache_ttl` | `LANGSYS_CACHE_TTL` | Seconds a cached catalog is kept |
| `cache` | none | A `CacheInterface` to use instead of the driver ([catalog.md](./catalog.md)) |
| `redis` | `[]` | `host`, `port`, `password`, `database`, `timeout`, `prefix` for the `redis` driver |
| `cache_clear` | `false` | Clear the cache when the `Client` is built |
| `base_url` | `LANGSYS_BASE_URL` | Makes relative URLs in registered blocks absolute |
| `log_path` | `LANGSYS_LOG_PATH` | A JSON Lines log file |
| `log_level` | `LANGSYS_LOG_LEVEL` | The lowest level logged |
| `logger` | none | A `Langsys\SDK\Log\LoggerInterface` to use instead of `log_path` |
| `error_log` | `true` | `false` stops warnings and errors going to PHP's error log when there is no log file |
| `warn_runtime_requirements` | `true` | `false` stops the setup warnings below |
| `messages_category` | `LANGSYS_MESSAGES_CATEGORY` | The category of server message templates ([messages.md](./messages.md)) |
| `require_credentials` | `false` | Throw at first use when the key or project id is missing |
| `request_locale` | the URL, cookie, session and header steps | Where the request's locale is read from ([locales.md](./locales.md)) |
| `runtime_registration` | `true` | `false` registers from a sync only ([writing.md](./writing.md)) |
| `value_sets` | none | Classes declaring the values a placeholder takes ([interpolation.md](./interpolation.md)) |
| `snapshot` | none | A `Snapshot` to render from before the API ([migration.md](./migration.md)) |
| `migration` | none | Key-based language files to answer keys from ([migration.md](./migration.md)) |

## A client without credentials

Without an API key or a project id the client is **unconfigured**, so an app's tests, its CI and a fresh clone run without secrets:

```php
$client = new Client();       // neither variable set
$client->isConfigured();      // false
$client->translate('Hello, {name}', null, null, null, ['name' => 'Ana']);   // "Hello, Ana"
```

- Every phrase and block renders as its source with its values filled, and a page comes back as given.
- Nothing is fetched or queued. The reference lists are empty ([locales.md](./locales.md)).
- A miss fallback, a loaded snapshot and the migration files still answer.
- An explicit write, or the plan for one (`registerPhrases()`, `registerContentBlock()`, `sync()`, `planSync()`, `applySync()`, `importLegacyTranslations()`), throws a `ConfigurationException`.
- One warning per process names the missing settings.

In production, pass `'require_credentials' => true`. The client still builds, so a framework can create it while it boots, and its first use throws a `ConfigurationException` naming what is missing.

## Logging

With `log_path` (or `logger`), the SDK logs JSON Lines at `log_level` and above: API requests, cache hits and misses, authorization, and what was queued and flushed. Without either, warnings and errors still go to PHP's error log prefixed `[langsys]`, so a failed registration is never recorded nowhere; `'error_log' => false` turns that off.

The setup warnings, once per process (a client without credentials, a missing `intl`, a PHP below 7.4), go to PHP's error log even then. `'warn_runtime_requirements' => false` turns those off too.

`getLogViewer()` reads the log file back, and `displayLogs()` prints it as a page. Neither belongs on a public route.

## Exceptions

All extend `Langsys\SDK\Exception\LangsysException`:

| Exception | When |
|---|---|
| `AuthenticationException` | 401: the key is invalid or expired |
| `ValidationException` | 422: `getErrors()`, `getErrorCode()` and `getServerMessages()` ([messages.md](./messages.md)) |
| `ApiException` | Any other 4xx or 5xx: `getHttpStatusCode()` |
| `ConfigurationException` | A write on a client without credentials, or any use of one with `require_credentials` on |

The translate calls and the page render don't throw on an API failure: they render from what they have, down to the source ([catalog.md](./catalog.md)). `canWrite()` throws a `LangsysException` when the API can't answer.
