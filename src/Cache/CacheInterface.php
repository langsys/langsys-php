<?php

namespace Langsys\SDK\Cache;

/**
 * Interface for cache implementations.
 */
interface CacheInterface
{
    /**
     * Get a value from the cache.
     *
     * @param string $key
     * @return mixed|null Returns null if the key doesn't exist or is expired
     */
    public function get($key);

    /**
     * Store a value in the cache.
     *
     * @param string $key
     * @param mixed $value
     * @param int $ttl Time to live in seconds
     * @return bool
     */
    public function set($key, $value, $ttl = 3600);

    /**
     * Check if a key exists in the cache.
     *
     * @param string $key
     * @return bool
     */
    public function has($key);

    /**
     * Delete a key from the cache.
     *
     * @param string $key
     * @return bool
     */
    public function delete($key);

    /**
     * Clear all items from the cache.
     *
     * @return bool
     */
    public function clear();
}
