<?php

namespace Langsys\SDK\Cache;

/**
 * No-op cache implementation for when caching is disabled.
 */
class NullCache implements CacheInterface
{
    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = 3600)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return false;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        return true;
    }
}
