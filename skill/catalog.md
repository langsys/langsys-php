# The catalog: fetching, caching, outages

## What the catalog is

A locale's catalog is what the project has registered, by category, with its translation in that locale. The translate calls read it; you rarely touch it directly.

```php
$client->getTranslations('es-es');   // ['UI' => ['Home' => 'Inicio', ...], '__uncategorized__' => [...]]
```

`getTranslations()` returns a plural or select Langsys generated as ICU (`{count, plural, one {…} other {…}}`). Code that renders those strings itself formats them with `$client->getInterpolator()->interpolate($text, $params, $locale)`. Unlike the translate calls, it throws a `LangsysException` when the API answers something that isn't a catalog, and while a failed fetch's backoff is open: wrap direct calls in `try`.

`translations()` holds the raw endpoints, among them `getStats($locale)` (`words`, `untranslated`) and `getFlat($locale)`.

## Fetching and caching

A catalog is read in three tiers: memory for the request, then the cache across requests, then the API. A miss the request queued is added to memory at once, so it isn't queued twice.

| Cache | |
|---|---|
| `FileCache` | The default: a directory (`cache_path`), entries kept `cache_ttl` seconds (3600) |
| `RedisCache` | `'cache_driver' => 'redis'` with the `redis` option, or `new RedisCache($redis, 'myapp:langsys::', 3600)` around a connection you hold |
| `NullCache` | No cache: every request fetches |

```php
use Langsys\SDK\Cache\RedisCache;

$client = new Client($key, $project, ['cache' => new RedisCache(['host' => '127.0.0.1', 'port' => 6379, 'prefix' => 'langsys::'])]);
```

Use Redis, or another shared cache, when several servers or containers render: each `FileCache` directory fetches its own copy. A cache implements `Langsys\SDK\Cache\CacheInterface`.

**Redis connects when the cache is built.** With the `redis` driver, `new Client()` connects to Redis, and throws a `LangsysException` when Redis is unreachable or the `redis` extension is missing; `new RedisCache([...])` throws the same way in your own code. So a Redis outage fails every request that builds a client. Build it where that failure is handled, for example falling back to `FileCache` or `NullCache`, or pass a `\Redis` connection your app already manages.

A translation made in the Translation Manager reaches a cached catalog when its entry expires. After a deploy that must switch at once, or to see a change now, clear it once. Clearing every locale, and `cache_clear`, empties the whole store under its directory or prefix: the authorization, the reference lists, and any other project sharing the directory. `cache_clear` in the app's own configuration clears it on every request, so every request authorizes and fetches again. Keep it to a deploy step:

```php
$client->clearCache('es-es');                 // one locale
$client->clearCache();                        // every locale
$client->getTranslations('es-es', false);     // bypass the cache for one read
new Client($key, $project, ['cache_clear' => true]);   // clear when the client is built
```

## When the API can't be reached

Rendering never fails because of the API:

- `translate()`, `resolve()`, `translateRich()` and `translateContentBlock()` render from what they have: a snapshot, the miss fallback for a locale set or passed, else the source, with their params filled. `translate()` and `resolve()` say so once, at debug.
- A page whose catalog can't be read comes back exactly as given, `{name}` placeholders included ([interpolation.md](./interpolation.md)).
- Nothing is queued while no catalog can be read, since no miss can be decided: the flush reports `catalog_unavailable` ([writing.md](./writing.md)).
- A failed fetch isn't retried by every call: the next waits 3 seconds, doubling to 5 minutes. The wait belongs to the `Client`, so under PHP-FPM, where each request builds its own, every request asks a failing API once.

For a render that must not depend on the API at all, seed the client from a snapshot ([migration.md](./migration.md)).
