<?php

namespace Langsys\SDK;

/**
 * Configuration handler for the Langsys SDK.
 */
class Config
{
    /**
     * Default API base URL.
     */
    const DEFAULT_API_URL = 'https://api.langsys.dev/api';

    /**
     * Default cache TTL in seconds (1 hour).
     */
    const DEFAULT_CACHE_TTL = 3600;

    /**
     * @var string
     */
    protected $apiKey;

    /**
     * @var string
     */
    protected $projectId;

    /**
     * @var string
     */
    protected $apiUrl;

    /**
     * @var string
     */
    protected $cacheDriver;

    /**
     * @var string
     */
    protected $cachePath;

    /**
     * @var int
     */
    protected $cacheTtl;

    /**
     * @var string|null Base URL for resolving relative URLs in content blocks
     */
    protected $baseUrl;

    /**
     * @var string|null Path to log file
     */
    protected $logPath;

    /**
     * @var string Minimum log level
     */
    protected $logLevel;

    /**
     * Create a new Config instance.
     *
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->apiKey = isset($options['api_key'])
            ? $options['api_key']
            : $this->getEnv('LANGSYS_API_KEY');

        $this->projectId = isset($options['project_id'])
            ? $options['project_id']
            : $this->getEnv('LANGSYS_PROJECT_ID');

        $this->apiUrl = isset($options['api_url'])
            ? $options['api_url']
            : $this->getEnv('LANGSYS_API_URL', self::DEFAULT_API_URL);

        $this->cacheDriver = isset($options['cache_driver'])
            ? $options['cache_driver']
            : $this->getEnv('LANGSYS_CACHE_DRIVER', 'file');

        $this->cachePath = isset($options['cache_path'])
            ? $options['cache_path']
            : $this->getEnv('LANGSYS_CACHE_PATH', sys_get_temp_dir() . '/langsys-cache');

        $this->cacheTtl = isset($options['cache_ttl'])
            ? (int) $options['cache_ttl']
            : (int) $this->getEnv('LANGSYS_CACHE_TTL', self::DEFAULT_CACHE_TTL);

        $this->baseUrl = isset($options['base_url'])
            ? $options['base_url']
            : $this->getEnv('LANGSYS_BASE_URL');

        $this->logPath = isset($options['log_path'])
            ? $options['log_path']
            : $this->getEnv('LANGSYS_LOG_PATH');

        $this->logLevel = isset($options['log_level'])
            ? $options['log_level']
            : $this->getEnv('LANGSYS_LOG_LEVEL', 'info');
    }

    /**
     * Get environment variable value.
     *
     * @param string $name
     * @param mixed $default
     * @return mixed
     */
    protected function getEnv($name, $default = null)
    {
        $value = getenv($name);

        if ($value === false) {
            return $default;
        }

        return $value;
    }

    /**
     * Get the API key.
     *
     * @return string|null
     */
    public function getApiKey()
    {
        return $this->apiKey;
    }

    /**
     * Get the project ID.
     *
     * @return string|null
     */
    public function getProjectId()
    {
        return $this->projectId;
    }

    /**
     * Get the API base URL.
     *
     * @return string
     */
    public function getApiUrl()
    {
        return rtrim($this->apiUrl, '/');
    }

    /**
     * Get the cache driver name.
     *
     * @return string
     */
    public function getCacheDriver()
    {
        return $this->cacheDriver;
    }

    /**
     * Get the cache path for file-based caching.
     *
     * @return string
     */
    public function getCachePath()
    {
        return $this->cachePath;
    }

    /**
     * Get the cache TTL in seconds.
     *
     * @return int
     */
    public function getCacheTtl()
    {
        return $this->cacheTtl;
    }

    /**
     * Check if API key is configured.
     *
     * @return bool
     */
    public function hasApiKey()
    {
        return !empty($this->apiKey);
    }

    /**
     * Check if project ID is configured.
     *
     * @return bool
     */
    public function hasProjectId()
    {
        return !empty($this->projectId);
    }

    /**
     * Get the base URL for resolving relative URLs.
     *
     * Returns the configured base_url, or attempts to construct from $_SERVER
     * variables if not configured.
     *
     * @return string|null
     */
    public function getBaseUrl()
    {
        // Return configured base URL if set
        if ($this->baseUrl !== null && $this->baseUrl !== '') {
            return rtrim($this->baseUrl, '/');
        }

        // Try to construct from $_SERVER
        return $this->detectBaseUrlFromServer();
    }

    /**
     * Detect base URL from $_SERVER variables.
     *
     * @return string|null
     */
    protected function detectBaseUrlFromServer()
    {
        // Check if we're in a web context
        if (!isset($_SERVER['HTTP_HOST'])) {
            return null;
        }

        // Determine protocol
        $isHttps = false;
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $isHttps = true;
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
            $isHttps = true;
        } elseif (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
            $isHttps = true;
        }

        $protocol = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'];

        return $protocol . '://' . $host;
    }

    /**
     * Get the log file path.
     *
     * @return string|null
     */
    public function getLogPath()
    {
        return $this->logPath;
    }

    /**
     * Get the minimum log level.
     *
     * @return string
     */
    public function getLogLevel()
    {
        return $this->logLevel;
    }

    /**
     * Check if logging is enabled.
     *
     * Logging is enabled when a log path is configured.
     *
     * @return bool
     */
    public function isLoggingEnabled()
    {
        return $this->logPath !== null && $this->logPath !== '';
    }
}
