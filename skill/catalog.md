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

A translation made in the Translation Manager reaches a cached catalog when its entry expires. After a deploy that must switch at once, or to see a change now:

```php
$client->clearCache('es-es');                 // one locale
$client->clearCache();                        // every locale
$client->getTranslations('es-es', false);     // bypass the cache for one read
new Client($key, $project, ['cache_clear' => true]);   // clear when the client is built
```

## When the API can't be reached

Rendering never fails because of the API:

- `translate()`, `resolve()`, `translateRich()`, `translateContentBlock()` and `translatePage()` render from what they have: a snapshot, the miss fallback, else the source. A page whose catalog can't be read comes back as given. `translate()` and `resolve()` say so once, at debug.
- Nothing is queued while no catalog can be read, since no miss can be decided: the flush reports `catalog_unavailable` ([writing.md](./writing.md)).
- A failed fetch isn't retried by every call: the next waits 3 seconds, doubling to 5 minutes.

For a render that must not depend on the API at all, seed the client from a snapshot ([migration.md](./migration.md)).
