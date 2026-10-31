<?php

namespace Langsys\SDK\Tests\Mock;

use Langsys\SDK\Cache\CacheInterface;

/**
 * A cache whose mutating operations fail with an \Error.
 *
 * Reads behave like a permanent miss, so a client using this still functions;
 * only invalidation blows up. That is the shape needed to prove the seam around
 * the post-flush cache clear: a registration that the server ACCEPTED must not
 * be reported as failed because clearing the catalog afterwards raised.
 */
class ErrorThrowingCache implements CacheInterface
{
    public function get($key)
    {
        return null;
    }

    public function set($key, $value, $ttl = 3600)
    {
        return true;
    }

    public function has($key)
    {
        return false;
    }

    public function delete($key)
    {
        throw new \TypeError('an \Error from the cache, not an \Exception');
    }

    public function clear()
    {
        throw new \TypeError('an \Error from the cache, not an \Exception');
    }
}
