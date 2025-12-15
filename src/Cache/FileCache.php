<?php

namespace Langsys\SDK\Cache;

use Langsys\SDK\Log\LoggerInterface;
use Langsys\SDK\Log\NullLogger;

/**
 * File-based cache implementation.
 */
class FileCache implements CacheInterface
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @var int
     */
    protected $defaultTtl;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Create a new FileCache instance.
     *
     * @param string $path Directory path for cache files
     * @param int $defaultTtl Default TTL in seconds
     * @param LoggerInterface $logger
     */
    public function __construct($path, $defaultTtl = 3600, $logger = null)
    {
        $this->path = rtrim($path, '/\\');
        $this->defaultTtl = $defaultTtl;
        $this->logger = $logger !== null ? $logger : new NullLogger();

        if (!is_dir($this->path)) {
            mkdir($this->path, 0755, true);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function get($key)
    {
        $file = $this->getFilePath($key);

        if (!file_exists($file)) {
            $this->logger->debug('Cache miss', ['key' => $key, 'reason' => 'not_found']);
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            $this->logger->debug('Cache miss', ['key' => $key, 'reason' => 'read_error']);
            return null;
        }

        $data = json_decode($content, true);
        if ($data === null || !isset($data['expires']) || !isset($data['value'])) {
            $this->logger->debug('Cache miss', ['key' => $key, 'reason' => 'invalid_data']);
            return null;
        }

        if ($data['expires'] !== 0 && $data['expires'] < time()) {
            $this->logger->debug('Cache miss', ['key' => $key, 'reason' => 'expired']);
            $this->delete($key);
            return null;
        }

        $this->logger->debug('Cache hit', ['key' => $key]);
        return $data['value'];
    }

    /**
     * {@inheritdoc}
     */
    public function set($key, $value, $ttl = null)
    {
        if ($ttl === null) {
            $ttl = $this->defaultTtl;
        }

        $file = $this->getFilePath($key);
        $data = [
            'expires' => $ttl > 0 ? time() + $ttl : 0,
            'value' => $value,
        ];

        $result = file_put_contents($file, json_encode($data), LOCK_EX);

        if ($result !== false) {
            $this->logger->debug('Cache set', ['key' => $key, 'ttl' => $ttl]);
        } else {
            $this->logger->warning('Cache set failed', ['key' => $key]);
        }

        return $result !== false;
    }

    /**
     * {@inheritdoc}
     */
    public function has($key)
    {
        return $this->get($key) !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function delete($key)
    {
        $file = $this->getFilePath($key);

        if (file_exists($file)) {
            $result = unlink($file);
            $this->logger->debug('Cache delete', ['key' => $key, 'success' => $result]);
            return $result;
        }

        return true;
    }

    /**
     * {@inheritdoc}
     */
    public function clear()
    {
        $files = glob($this->path . '/*.cache');

        if ($files === false) {
            return false;
        }

        foreach ($files as $file) {
            unlink($file);
        }

        $this->logger->debug('Cache cleared', ['files_removed' => count($files)]);
        return true;
    }

    /**
     * Get the file path for a cache key.
     *
     * @param string $key
     * @return string
     */
    protected function getFilePath($key)
    {
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return $this->path . '/' . $safeKey . '.cache';
    }
}
