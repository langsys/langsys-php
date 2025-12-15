<?php

namespace Langsys\SDK\Cache;

use Redis;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Log\LoggerInterface;
use Langsys\SDK\Log\NullLogger;

/**
 * Redis-based cache implementation.
 * Requires the phpredis extension.
 */
class RedisCache implements CacheInterface
{
    /**
     * @var Redis
     */
    protected $redis;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var int
     */
    protected $defaultTtl;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Create a new RedisCache instance.
     *
     * @param Redis|array $redis Redis instance or connection options
     * @param string $prefix Key prefix
     * @param int $defaultTtl Default TTL in seconds
     * @param LoggerInterface $logger
     * @throws LangsysException
     */
    public function __construct($redis, $prefix = 'langsys::', $defaultTtl = 3600, $logger = null)
    {
        $this->logger = $logger !== null ? $logger : new NullLogger();

        if ($redis instanceof Redis) {
            $this->redis = $redis;
            $this->prefix = $prefix;
        } elseif (is_array($redis)) {
            $this->redis = $this->createConnection($redis);
            // Allow prefix to be set via options array
            $this->prefix = isset($redis['prefix']) ? $redis['prefix'] : $prefix;
        } else {
            throw new LangsysException('Redis parameter must be a Redis instance or connection options array');
        }

        $this->defaultTtl = $defaultTtl;
    }

    /**
     * Create a Redis connection from options.
     *
     * @param array $options
     * @return Redis
     * @throws LangsysException
     */
    protected function createConnection(array $options)
    {
        if (!extension_loaded('redis')) {
            throw new LangsysException('The phpredis extension is required for Redis caching');
        }

        $redis = new Redis();

        $host = isset($options['host']) ? $options['host'] : '127.0.0.1';
        $port = isset($options['port']) ? $options['port'] : 6379;
        $timeout = isset($options['timeout']) ? $options['timeout'] : 0;

        if (!$redis->connect($host, $port, $timeout)) {
            throw new LangsysException('Failed to connect to Redis');
        }

        if (isset($options['password']) && !empty($options['password'])) {
            if (!$redis->auth($options['password'])) {
                throw new LangsysException('Redis authentication failed');
            }
        }

        if (isset($options['database'])) {
            $redis->select($options['database']);
        }

        return $redis;
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        $value = $this->redis->get($this->prefix . $key);

        if ($value === false) {
            $this->logger->debug('Cache miss', ['key' => $key, 'reason' => 'not_found']);
            return null;
        }

        $this->logger->debug('Cache hit', ['key' => $key]);
        $decoded = json_decode($value, true);
        return $decoded !== null ? $decoded : $value;
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        if ($ttl === null) {
            $ttl = $this->defaultTtl;
        }

        $encoded = json_encode($value);

        if ($ttl > 0) {
            $result = $this->redis->setex($this->prefix . $key, $ttl, $encoded);
        } else {
            $result = $this->redis->set($this->prefix . $key, $encoded);
        }

        if ($result) {
            $this->logger->debug('Cache set', ['key' => $key, 'ttl' => $ttl]);
        } else {
            $this->logger->warning('Cache set failed', ['key' => $key]);
        }

        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return $this->redis->exists($this->prefix . $key) > 0;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $result = $this->redis->del($this->prefix . $key) > 0;
        $this->logger->debug('Cache delete', ['key' => $key, 'success' => $result]);
        return $result;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $keys = $this->redis->keys($this->prefix . '*');

        if (empty($keys)) {
            $this->logger->debug('Cache cleared', ['keys_removed' => 0]);
            return true;
        }

        $result = $this->redis->del($keys) > 0;
        $this->logger->debug('Cache cleared', ['keys_removed' => count($keys)]);
        return $result;
    }
}
