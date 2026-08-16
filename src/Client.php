<?php

namespace Langsys\SDK;

use Langsys\SDK\Cache\CacheInterface;
use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Cache\RedisCache;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Html\PageTranslator;
use Langsys\SDK\Http\HttpClient;
use Langsys\SDK\Locale\LocaleDetector;
use Langsys\SDK\Log\Logger;
use Langsys\SDK\Log\LoggerInterface;
use Langsys\SDK\Log\LogViewer;
use Langsys\SDK\Log\NullLogger;
use Langsys\SDK\Resources\Translations;
use Langsys\SDK\Resources\TranslatableItems;
use Langsys\SDK\Resources\Utilities;

/**
 * Main client for the Langsys SDK.
 */
class Client
{
    /**
     * Key types whose write capability is fully determined by the type itself.
     * 'ip_write' is deliberately absent - its answer varies by caller.
     */
    const KEY_TYPE_READ = 'read';
    const KEY_TYPE_WRITE = 'write';

    /**
     * Local sentinel for "no category".
     */
    const UNCATEGORIZED = '__uncategorized__';

    /**
     * @var Config
     */
    protected $config;

    /**
     * @var HttpClient
     */
    protected $http;

    /**
     * @var CacheInterface
     */
    protected $cache;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var array|null Project authorization data
     */
    protected $projectData;

    /**
     * Whether THIS request may register content, as computed by the server.
     *
     * Deliberately not part of $projectData and never written to $this->cache:
     * the server derives it from the caller's IP and any write grant, so the
     * same key legitimately answers true for one request and false for the
     * next. Persisting it would apply one caller's decision to every later
     * request sharing the cache. Null means "not yet resolved this request".
     *
     * @var bool|null
     */
    protected $writeEnabled = null;

    /**
     * @var Translations
     */
    protected $translations;

    /**
     * @var TranslatableItems
     */
    protected $translatableItems;

    /**
     * @var Utilities
     */
    protected $utilities;

    /**
     * @var string|null Target locale for translations
     */
    protected $locale;

    /**
     * @var PageTranslator|null Page translator instance
     */
    protected $pageTranslator;

    /**
     * @var array Pending phrases to register (queued during translate calls)
     */
    protected $pendingPhrases = [];

    /**
     * @var array Pending content blocks to register (queued during translateContentBlock calls)
     */
    protected $pendingContentBlocks = [];

    /**
     * @var bool Whether shutdown handler has been registered
     */
    protected $shutdownRegistered = false;

    /**
     * @var array In-memory translations cache (survives across getTranslations calls within same request)
     */
    protected $translationsMemoryCache = [];

    /**
     * Create a new Langsys Client.
     *
     * @param string|null $apiKey API key (or null to use env var)
     * @param string|null $projectId Project ID (or null to use env var)
     * @param array $options Additional options
     * @throws LangsysException
     */
    public function __construct($apiKey = null, $projectId = null, array $options = [])
    {
        // Build config options
        $configOptions = $options;

        if ($apiKey !== null) {
            $configOptions['api_key'] = $apiKey;
        }

        if ($projectId !== null) {
            $configOptions['project_id'] = $projectId;
        }

        $this->config = new Config($configOptions);

        // Validate required config
        if (!$this->config->hasApiKey()) {
            throw new LangsysException('API key is required. Set LANGSYS_API_KEY environment variable or pass it to the constructor.');
        }

        if (!$this->config->hasProjectId()) {
            throw new LangsysException('Project ID is required. Set LANGSYS_PROJECT_ID environment variable or pass it to the constructor.');
        }

        // Initialize logger
        $this->logger = $this->initializeLogger($options);

        // Initialize HTTP client
        $this->http = new HttpClient($this->config, $this->logger);

        // Initialize cache
        $this->cache = $this->initializeCache($options);

        // Clear cache if requested
        if (!empty($options['cache_clear'])) {
            $this->cache->clear();
            $this->logger->info('Cache cleared on initialization');
        }

        // Initialize resources
        $this->translations = new Translations($this->http, $this->config->getProjectId(), $this->logger);
        $this->translatableItems = new TranslatableItems($this->http, $this->config->getProjectId(), $this->logger);
        $this->utilities = new Utilities($this->http, $this->config->getProjectId(), $this->logger);
    }

    /**
     * Initialize the logger based on configuration.
     *
     * @param array $options
     * @return LoggerInterface
     */
    protected function initializeLogger(array $options)
    {
        // Allow passing a logger instance directly
        if (isset($options['logger']) && $options['logger'] instanceof LoggerInterface) {
            return $options['logger'];
        }

        // Check if logging is enabled
        if (!$this->config->isLoggingEnabled()) {
            return new NullLogger();
        }

        $logPath = $this->config->getLogPath();
        $logLevel = $this->config->getLogLevel();

        // Validate that the directory is writable
        $dir = dirname($logPath);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return new NullLogger();
        }
        if (!is_writable($dir)) {
            return new NullLogger();
        }

        return new Logger($logPath, $logLevel);
    }

    /**
     * Initialize the cache based on configuration.
     *
     * @param array $options
     * @return CacheInterface
     */
    protected function initializeCache(array $options)
    {
        // Allow passing a cache instance directly
        if (isset($options['cache']) && $options['cache'] instanceof CacheInterface) {
            return $options['cache'];
        }

        $driver = $this->config->getCacheDriver();
        $ttl = $this->config->getCacheTtl();

        switch ($driver) {
            case 'redis':
                $redisOptions = isset($options['redis']) ? $options['redis'] : [];
                return new RedisCache($redisOptions, 'langsys::', $ttl, $this->logger);

            case 'none':
            case 'null':
                return new NullCache();

            case 'file':
            default:
                return new FileCache($this->config->getCachePath(), $ttl, $this->logger);
        }
    }

    /**
     * Authorize and get project information.
     *
     * Note that the returned array never contains 'write_enabled'. That flag is
     * per-request and is held separately so it cannot be cached - use
     * canWrite() to read it.
     *
     * @param bool $force Force re-authorization even if cached
     * @return array Project data including key_type
     */
    public function authorize($force = false)
    {
        if ($this->projectData !== null && !$force) {
            $this->logger->debug('Authorization from memory', [
                'project_id' => $this->config->getProjectId(),
            ]);
            return $this->projectData;
        }

        $cacheKey = 'auth_' . $this->config->getProjectId();

        if (!$force) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                // Entries written by older SDK versions still carry the write
                // decision. Drop it rather than trust a value that was computed
                // for whichever request happened to populate the cache.
                if (is_array($cached)) {
                    unset($cached['write_enabled']);
                }
                $this->projectData = $cached;
                $this->syncBatchLimit();
                $this->logger->debug('Authorization from cache', [
                    'project_id' => $this->config->getProjectId(),
                ]);
                return $this->projectData;
            }
        }

        $response = $this->http->get('authorize-project/' . $this->config->getProjectId());

        if (isset($response['data'])) {
            $data = $response['data'];

            // Capture the server's per-request write decision, then strip it so
            // it can never reach $this->cache (a file/Redis cache shared by
            // every request on the host, and by the fleet on Redis).
            if (array_key_exists('write_enabled', $data)) {
                // Present: authoritative, and it wins over key_type in both
                // directions. This is the only path that can answer for an
                // 'ip_write' key.
                $this->writeEnabled = (bool) $data['write_enabled'];
                unset($data['write_enabled']);
            } else {
                // Absent means an API too old to compute the flag. That API
                // also has no 'ip_write' keys and no write grants, so on it
                // key_type is not a lossy proxy - it is the complete answer.
                // Failing closed here would silently disable registration for
                // every deployment until the server ships the flag.
                $keyType = isset($data['key_type']) ? $data['key_type'] : null;
                $this->writeEnabled = ($keyType === self::KEY_TYPE_WRITE);
                $this->logger->info('Authorization response carries no write_enabled flag - falling back to key_type', [
                    'project_id' => $this->config->getProjectId(),
                    'key_type' => $keyType,
                    'write_enabled' => $this->writeEnabled,
                ]);
            }

            $this->projectData = $data;
            $this->syncBatchLimit();
            $this->cache->set($cacheKey, $this->projectData);
            $keyType = isset($this->projectData['key_type']) ? $this->projectData['key_type'] : 'unknown';
            $this->logger->info('Project authorized', [
                'project_id' => $this->config->getProjectId(),
                'key_type' => $keyType,
                'write_enabled' => $this->writeEnabled,
            ]);
            return $this->projectData;
        }

        return $response;
    }

    /**
     * Resolve a caller-supplied category to the local catalog key.
     *
     * The catalog keys uncategorised items under '__uncategorized__', so an
     * empty or null category has to become the sentinel on the way in. Left
     * unnormalised, a lookup under '' misses forever while registration writes
     * the phrase as uncategorised - so the same phrase is re-registered on
     * every request and never converges. Mirrors the JS SDKs'
     * `category || '__uncategorized__'`.
     *
     * The opposite conversion happens on the wire, where the sentinel becomes
     * an absent category - see TranslatableItems::normalizeCategory().
     *
     * @param string|null $category
     * @return string
     */
    protected function normalizeCategory($category)
    {
        if ($category === null || $category === '') {
            return self::UNCATEGORIZED;
        }

        return $category;
    }

    /**
     * Sync the batch limit from project data to the TranslatableItems resource.
     *
     * @return void
     */
    protected function syncBatchLimit()
    {
        if (isset($this->projectData['langsys_settings']['translatable_items']['batch_limit'])) {
            $this->translatableItems->setBatchLimit($this->projectData['langsys_settings']['translatable_items']['batch_limit']);
        }
    }

    /**
     * Whether this request may register content.
     *
     * The server's write_enabled flag is authoritative whenever it is present.
     * It is never persisted (see authorize()), so a cache hit arrives without
     * it - and only an 'ip_write' key actually needs a fresh call to recover
     * it, because that is the one type whose answer varies by caller.
     *
     * Resolved at most once per request and held in memory only.
     *
     * @return bool
     */
    public function canWrite()
    {
        if ($this->writeEnabled === null) {
            $this->resolveWriteDecision();
        }

        return $this->writeEnabled === true;
    }

    /**
     * Work out whether this request may write, with the fewest possible calls.
     *
     * A 'write' key always may and a 'read' key never may, so for those two the
     * cached key_type is a complete answer and no network call is warranted -
     * without this, a read-only deployment with unregistered content pays a
     * blocking authorization round-trip on every single render, forever.
     *
     * The read-key shortcut holds only while this SDK sends no write grant: the
     * server's gate is `type-allows-write OR valid-grant`, so a grant could make
     * a read key write-enabled. If grant support is ever added (GRANT-1..4),
     * 'read' must stop short-circuiting and resolve per request like 'ip_write'.
     *
     * @return void
     */
    protected function resolveWriteDecision()
    {
        // Memory or cache - deliberately not forced.
        $data = $this->authorize();

        if ($this->writeEnabled !== null) {
            // authorize() went to the network and already answered.
            return;
        }

        $keyType = isset($data['key_type']) ? $data['key_type'] : null;

        if ($keyType === self::KEY_TYPE_WRITE || $keyType === self::KEY_TYPE_READ) {
            $this->writeEnabled = ($keyType === self::KEY_TYPE_WRITE);
            return;
        }

        // 'ip_write', or a type this SDK predates: the answer belongs to this
        // request and only the server can give it.
        $this->authorize(true);

        if ($this->writeEnabled === null) {
            // Unexpected response shape. Settle so we don't re-request per call.
            $this->writeEnabled = false;
        }
    }

    /**
     * Clear state that belongs to a single request.
     *
     * Under a long-lived runtime (Octane, Swoole, RoadRunner, a queue worker)
     * this Client can outlive the request it was built for, which would carry
     * one caller's write decision into the next caller's request. Call this
     * between requests. Flush pending registrations first - this does not send
     * them.
     *
     * @return $this
     */
    public function resetRequestState()
    {
        $this->writeEnabled = null;
        $this->translationsMemoryCache = [];

        return $this;
    }

    /**
     * Get the key type (read or write).
     *
     * @return string|null
     */
    public function getKeyType()
    {
        $data = $this->authorize();
        return isset($data['key_type']) ? $data['key_type'] : null;
    }

    /**
     * Get project information.
     *
     * @return array
     */
    public function getProject()
    {
        return $this->authorize();
    }

    /**
     * Get translations for a locale.
     *
     * Uses a three-tier cache: in-memory (request-scoped) → file/redis → API.
     * Items added via translate()/translateContentBlock() are added to the in-memory
     * cache immediately, avoiding re-registration within the same request.
     *
     * @param string $locale Locale code (e.g., 'es-es')
     * @param bool $useCache Whether to use cache
     * @return array [category => [phrase => translation]]
     */
    public function getTranslations($locale, $useCache = true)
    {
        $memoryKey = $locale;
        $cacheKey = 'translations_' . $this->config->getProjectId() . '_' . $locale;

        // Check in-memory cache first (fastest, includes queued items)
        if ($useCache && isset($this->translationsMemoryCache[$memoryKey])) {
            $this->logger->debug('Translations cache hit', [
                'locale' => $locale,
                'source' => 'memory',
            ]);
            return $this->translationsMemoryCache[$memoryKey];
        }

        // Check file/redis cache
        if ($useCache) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                // Store in memory cache for this request
                $this->translationsMemoryCache[$memoryKey] = $cached;
                $this->logger->debug('Translations cache hit', [
                    'locale' => $locale,
                    'source' => 'persistent',
                ]);
                return $cached;
            }
        }

        $this->logger->debug('Translations cache miss', ['locale' => $locale]);

        // Fetch from API
        $translations = $this->translations->getTranslationMap($locale);

        // Store in both caches
        if ($useCache) {
            $this->cache->set($cacheKey, $translations);
        }
        $this->translationsMemoryCache[$memoryKey] = $translations;

        return $translations;
    }

    /**
     * Translate a phrase. Mirrors the JS SDKs' `t(phrase, category?, params?)`
     * exactly: the phrase comes first (it is the lookup key AND the
     * base-language default), the category second, interpolation params third.
     *
     * The one deliberate divergence from langsys-js-typescript /
     * langsys-js-react is `$locale`: a browser client reads an ambient, reactive
     * locale, but a stateless PHP request must be able to pass the locale per
     * call. It is the trailing argument and defaults to getLocale()/setLocale().
     *
     * Like the JS overload, calling translate($phrase, $params) (an array in the
     * category slot) is treated as "no category, with params".
     *
     * This method both translates the phrase AND queues it for registration if
     * it doesn't exist. Pending registrations flush at the end of the request,
     * or via flushPendingRegistrations(). Curly-brace placeholders in the
     * resulting string are interpolated from $params via Interpolator (ICU
     * MessageFormat when ext-intl is present, simple {name} substitution
     * otherwise).
     *
     * @param string $phrase The phrase to translate (and the lookup key)
     * @param string|array $category Category, or the params array (JS-style overload)
     * @param array|null $params Interpolation params, e.g. array('name' => 'Sarah')
     * @param string|null $locale Locale code (defaults to getLocale() if not set)
     * @return string The (interpolated) translation, or the (interpolated) phrase if not found
     */
    public function translate($phrase, $category = '__uncategorized__', $params = null, $locale = null)
    {
        // Mirror the JS overload t(phrase, params): an array in the category slot
        // means "no category, with params" (optionally followed by a locale).
        if (is_array($category)) {
            if ($locale === null && is_string($params)) {
                $locale = $params;
            }
            $params = $category;
            $category = self::UNCATEGORIZED;
        }

        $category = $this->normalizeCategory($category);

        // Locale is the one deliberate server-side divergence from the JS SDKs.
        if ($locale === null) {
            $locale = $this->getLocale();
            if ($locale === null) {
                // Can't translate without a locale - still interpolate the source.
                return is_array($params) ? Interpolator::interpolate($phrase, $params, null) : $phrase;
            }
        }

        try {
            $translations = $this->getTranslations($locale);
        } catch (\Exception $e) {
            // This method sits on every render path, so the API being
            // unreachable must degrade to source text rather than convert a
            // working page into a 500. Nothing is queued: a failed catalog
            // fetch cannot tell a miss from a hit, and guessing would
            // re-register phrases that already exist.
            $this->logger->error('Translation lookup failed - returning source phrase', [
                'phrase' => $phrase,
                'category' => $category,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return is_array($params) ? Interpolator::interpolate($phrase, $params, $locale) : $phrase;
        }

        $categoryTranslations = isset($translations[$category]) ? $translations[$category] : [];

        if (array_key_exists($phrase, $categoryTranslations)) {
            $value = $categoryTranslations[$phrase];
            // An array here is a content-block id collision, not a phrase translation.
            if (is_array($value)) {
                $result = $phrase;
            } else {
                // Translation, or the original phrase when empty/null (the base
                // locale and not-yet-translated phrases come back as null).
                $result = ($value === null || $value === '') ? $phrase : $value;
            }
        } else {
            // Phrase not found - queue for registration.
            $this->queuePhraseForRegistration($phrase, $category);
            $result = $phrase;
        }

        if (is_array($params)) {
            return Interpolator::interpolate($result, $params, $locale);
        }

        return $result;
    }

    /**
     * Look up a single phrase inside a registered content block. Mirrors the JS
     * SDK's Translations.lookupContent(category, customId, token). Never queues a
     * missing token - content blocks manage their own registration.
     *
     * @param string $category
     * @param string $contentBlockId Content block custom_id
     * @param string $phrase
     * @param string|null $locale Locale code (defaults to getLocale() if not set)
     * @return string|null The translation, or null if the block or token is missing
     */
    public function lookupContent($category, $contentBlockId, $phrase, $locale = null)
    {
        $category = $this->normalizeCategory($category);

        if ($locale === null) {
            $locale = $this->getLocale();
            if ($locale === null) {
                return null;
            }
        }

        try {
            $translations = $this->getTranslations($locale);
        } catch (\Exception $e) {
            // Same contract as translate(): degrade rather than throw. The
            // documented "block or token missing" return covers this.
            $this->logger->error('Content lookup failed', [
                'content_block_id' => $contentBlockId,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $categoryTranslations = isset($translations[$category]) ? $translations[$category] : [];

        if (isset($categoryTranslations[$contentBlockId][$phrase])) {
            return $categoryTranslations[$contentBlockId][$phrase];
        }

        return null;
    }

    /**
     * Register new phrases with the API.
     *
     * @param array $phrases Array of phrases (strings or arrays with phrase, category, translatable)
     * @return array API response
     * @throws LangsysException If API key doesn't have write permissions
     */
    public function registerPhrases(array $phrases)
    {
        if (!$this->canWrite()) {
            throw new LangsysException('Cannot register phrases: API key does not have write permissions');
        }

        return $this->translatableItems->createPhrases($phrases);
    }

    /**
     * Register a content block with the API.
     *
     * Phrases are automatically extracted from the HTML content.
     * Relative URLs (src, srcset, poster) are automatically resolved to absolute
     * URLs using the configured base_url or detected from $_SERVER.
     *
     * @param string $content HTML content of the content block
     * @param string|null $category Category for the content block
     * @param string|null $label Human-readable label for the content block
     * @param string|null $customId Custom ID (auto-generated from content hash if null)
     * @return array API response
     * @throws LangsysException If API key doesn't have write permissions
     */
    public function registerContentBlock($content, $category = null, $label = null, $customId = null)
    {
        if (!$this->canWrite()) {
            throw new LangsysException('Cannot register content block: API key does not have write permissions');
        }

        // Resolve relative URLs before registration
        $content = $this->resolveContentBlockUrls($content);

        return $this->translatableItems->createContentBlock($content, $category, $label, $customId);
    }

    /**
     * Resolve relative URLs in content block HTML.
     *
     * @param string $html HTML content
     * @return string HTML with resolved URLs
     */
    protected function resolveContentBlockUrls($html)
    {
        $baseUrl = $this->config->getBaseUrl();
        if ($baseUrl === null) {
            return $html;
        }

        $parser = new HtmlParser($this->translatableItems->getTranslatableAttributes());
        return $parser->resolveRelativeUrls($html, $baseUrl);
    }

    /**
     * Sync local phrases with the remote API.
     *
     * This method:
     * 1. Fetches existing translations
     * 2. Compares with local phrases
     * 3. POSTs any new phrases (if write key)
     * 4. Returns updated translations
     *
     * @param array $localPhrases Array of local phrases [['phrase' => '...', 'category' => '...'], ...]
     * @param string $locale Locale to sync (for fetching existing translations)
     * @return array Sync result with 'translations', 'new_phrases', and 'synced' keys
     */
    public function sync(array $localPhrases, $locale)
    {
        // Get existing translations
        $translations = $this->getTranslations($locale, false);

        // Build a set of existing phrases
        $existingPhrases = [];
        foreach ($translations as $category => $items) {
            foreach ($items as $phrase => $translation) {
                if (is_array($translation)) {
                    // Content block
                    foreach ($translation as $blockPhrase => $blockTranslation) {
                        $existingPhrases[$category . '::' . $blockPhrase] = true;
                    }
                } else {
                    $existingPhrases[$category . '::' . $phrase] = true;
                }
            }
        }

        // Find new phrases
        $newPhrases = [];
        foreach ($localPhrases as $phraseData) {
            $phrase = is_string($phraseData) ? $phraseData : $phraseData['phrase'];
            $category = is_string($phraseData)
                ? '__uncategorized__'
                : (isset($phraseData['category']) ? $phraseData['category'] : '__uncategorized__');

            $key = $category . '::' . $phrase;

            if (!isset($existingPhrases[$key])) {
                $newPhrases[] = is_string($phraseData)
                    ? ['phrase' => $phrase, 'category' => $category]
                    : $phraseData;
            }
        }

        $result = [
            'translations' => $translations,
            'new_phrases' => $newPhrases,
            'synced' => false,
        ];

        // If we have new phrases and can write, register them
        if (!empty($newPhrases) && $this->canWrite()) {
            $this->registerPhrases($newPhrases);
            $result['synced'] = true;

            // Invalidate cache and fetch fresh translations
            $cacheKey = 'translations_' . $this->config->getProjectId() . '_' . $locale;
            $this->cache->delete($cacheKey);
            $result['translations'] = $this->getTranslations($locale, false);
        }

        return $result;
    }

    /**
     * Clear the translation cache.
     *
     * @param string|null $locale Specific locale to clear, or null for all
     * @return bool
     */
    public function clearCache($locale = null)
    {
        if ($locale !== null) {
            // Clear in-memory cache for this locale
            unset($this->translationsMemoryCache[$locale]);

            $cacheKey = 'translations_' . $this->config->getProjectId() . '_' . $locale;
            return $this->cache->delete($cacheKey);
        }

        // Clear all in-memory cache
        $this->translationsMemoryCache = [];

        return $this->cache->clear();
    }

    /**
     * Get the Translations resource for advanced usage.
     *
     * @return Translations
     */
    public function translations()
    {
        return $this->translations;
    }

    /**
     * Get the TranslatableItems resource for advanced usage.
     *
     * @return TranslatableItems
     */
    public function translatableItems()
    {
        return $this->translatableItems;
    }

    /**
     * Get the Utilities resource for countries, locales, and dial codes.
     *
     * @return Utilities
     */
    public function utilities()
    {
        return $this->utilities;
    }

    /**
     * Get the cache instance.
     *
     * @return CacheInterface
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * Get the logger instance.
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Get a log viewer instance.
     *
     * Returns null if logging is not enabled.
     *
     * @param int $maxEntries Maximum entries to display (0 = unlimited)
     * @return LogViewer|null
     */
    public function getLogViewer($maxEntries = 500)
    {
        if (!$this->config->isLoggingEnabled()) {
            return null;
        }

        return new LogViewer($this->config->getLogPath(), $maxEntries);
    }

    /**
     * Display the log viewer page.
     *
     * Outputs HTML directly to the browser. Returns false if logging is not enabled.
     * Level filter is read from ?level= query parameter (default: debug).
     *
     * @return bool Whether the log viewer was displayed
     */
    public function displayLogs()
    {
        $viewer = $this->getLogViewer();

        if ($viewer === null) {
            return false;
        }

        $viewer->display();
        return true;
    }

    /**
     * Get the config instance.
     *
     * @return Config
     */
    public function getConfig()
    {
        return $this->config;
    }

    /**
     * Get the translatable attributes used for HTML content block parsing.
     *
     * @return array
     */
    public function getTranslatableAttributes()
    {
        return $this->translatableItems->getTranslatableAttributes();
    }

    /**
     * Set the translatable attributes for HTML content block parsing.
     *
     * This replaces all default attributes. Use addTranslatableAttributes()
     * to add to the defaults instead.
     *
     * @param array $attributes Array of attribute names (e.g., ['placeholder', 'alt', 'data-custom'])
     * @return $this
     */
    public function setTranslatableAttributes(array $attributes)
    {
        $this->translatableItems->setTranslatableAttributes($attributes);
        return $this;
    }

    /**
     * Add additional translatable attributes to the default list.
     *
     * @param array $attributes Array of attribute names to add
     * @return $this
     */
    public function addTranslatableAttributes(array $attributes)
    {
        $this->translatableItems->addTranslatableAttributes($attributes);
        return $this;
    }

    /**
     * Reset translatable attributes to the default list.
     *
     * @return $this
     */
    public function resetTranslatableAttributes()
    {
        $this->translatableItems->resetTranslatableAttributes();
        return $this;
    }

    /**
     * Set the target locale for translations.
     *
     * @param string $locale Locale code (e.g., 'es-es', 'fr-ca')
     * @return $this
     */
    public function setLocale($locale)
    {
        $this->locale = LocaleDetector::normalize($locale);
        return $this;
    }

    /**
     * Get the current target locale.
     *
     * If no locale has been set, attempts to auto-detect from browser
     * HTTP_ACCEPT_LANGUAGE header. Falls back to project's base_locale if
     * browser detection fails.
     *
     * @return string|null The locale, or null if unable to determine
     */
    public function getLocale()
    {
        if ($this->locale !== null) {
            return $this->locale;
        }

        // Try to detect from browser
        $detected = LocaleDetector::fromBrowser();
        if ($detected !== null) {
            return $detected;
        }

        // Fall back to project's base locale
        try {
            $project = $this->getProject();
            if (isset($project['base_locale'])) {
                return $project['base_locale'];
            }
        } catch (\Exception $e) {
            // Ignore - return null
        }

        return null;
    }

    /**
     * Translate an entire HTML page.
     *
     * Parses the HTML document, extracts translatable content (head meta tags,
     * text blocks, content blocks), registers new phrases if write permission,
     * and returns the translated HTML with fallback to source content.
     *
     * Optionally, CSS selectors can be mapped to categories for fine-grained control:
     * ```php
     * $client->translatePage($html, 'homepage', [
     *     'button, .btn, .button' => [
     *         'category' => 'UI Elements',
     *         'overrideParentElementCategory' => true,
     *     ],
     *     'nav a' => ['category' => 'Navigation'],
     * ]);
     * ```
     *
     * @param string $html Full HTML document
     * @param string|null $category Page category/name (e.g., 'homepage', 'contact')
     * @param array $selectorCategories Map of CSS selector => category config
     * @return string Translated HTML
     */
    public function translatePage($html, $category = null, array $selectorCategories = [])
    {
        $locale = $this->getLocale();
        if ($locale === null) {
            // No locale - return original HTML
            return $html;
        }

        if ($this->pageTranslator === null) {
            $this->pageTranslator = new PageTranslator(
                $this,
                $this->translatableItems->getTranslatableAttributes()
            );
        }

        return $this->pageTranslator->translate($html, $locale, $category, $selectorCategories);
    }

    /**
     * Translate an HTML content block.
     *
     * This method extracts phrases from the HTML, looks up translations for each
     * phrase, applies them, and returns the translated HTML. If the content block
     * doesn't exist in translations, it's queued for registration.
     *
     * @param string $html HTML content block
     * @param string $category Category for the content block (default: '__uncategorized__')
     * @return string Translated HTML
     */
    public function translateContentBlock($html, $category = self::UNCATEGORIZED)
    {
        if (empty($html)) {
            return $html;
        }

        $category = $this->normalizeCategory($category);

        $locale = $this->getLocale();
        if ($locale === null) {
            return $html; // Can't translate without locale
        }

        // Parse HTML and extract phrases
        $parser = new HtmlParser($this->translatableItems->getTranslatableAttributes());
        $phrases = $parser->extractPhrases($html);

        if (empty($phrases)) {
            return $html; // No translatable content
        }

        // Generate customId for this content block
        $customId = $parser->generateCustomId($category, $phrases);

        // Get translations. As in translate(), an unreachable API degrades to
        // the source HTML rather than throwing into the caller's render.
        try {
            $translations = $this->getTranslations($locale);
        } catch (\Exception $e) {
            $this->logger->error('Content block lookup failed - returning source HTML', [
                'custom_id' => $customId,
                'category' => $category,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return $html;
        }

        $categoryTranslations = isset($translations[$category]) ? $translations[$category] : [];

        // Check if content block exists
        if (!array_key_exists($customId, $categoryTranslations) ||
            !is_array($categoryTranslations[$customId])) {
            // Content block doesn't exist - queue for registration
            $this->queueContentBlockForRegistration($html, $category, $customId, $phrases);
            return $html; // Return original (no translations yet)
        }

        // Get content block translations
        $blockTranslations = $categoryTranslations[$customId];

        // Apply translations to HTML
        return $this->applyBlockTranslations($html, $blockTranslations, $parser);
    }

    /**
     * Apply translations to an HTML content block.
     *
     * @param string $html Original HTML
     * @param array $translations Map of [phrase => translation]
     * @param HtmlParser $parser HTML parser instance
     * @return string Translated HTML
     */
    protected function applyBlockTranslations($html, array $translations, HtmlParser $parser)
    {
        // Use DOMDocument to properly apply translations
        $internalErrors = libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        $doc->encoding = 'UTF-8';
        $wrapped = '<?xml encoding="UTF-8"><div>' . $html . '</div>';
        $doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        // Walk DOM and apply translations
        $this->walkAndTranslateBlock($doc->documentElement, $translations, $parser->getTranslatableAttributes());

        // Extract inner HTML of the wrapper div
        $wrapper = $doc->getElementsByTagName('div')->item(0);
        if ($wrapper === null) {
            return $html;
        }

        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return $result;
    }

    /**
     * Walk DOM and apply translations to text nodes and attributes.
     *
     * @param \DOMNode $node Node to process
     * @param array $translations Translation map
     * @param array $translatableAttributes Attributes to translate
     * @return void
     */
    protected function walkAndTranslateBlock(\DOMNode $node, array $translations, array $translatableAttributes)
    {
        // Handle text nodes
        if ($node instanceof \DOMText) {
            $normalizedText = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($normalizedText !== '' && isset($translations[$normalizedText])) {
                $translated = $translations[$normalizedText];
                if ($translated !== '' && $translated !== $normalizedText) {
                    // Preserve whitespace pattern
                    $leadingSpace = preg_match('/^\s/', $node->textContent) ? ' ' : '';
                    $trailingSpace = preg_match('/\s$/', $node->textContent) ? ' ' : '';
                    $node->textContent = $leadingSpace . $translated . $trailingSpace;
                }
            }
            return;
        }

        // Handle element nodes
        if ($node instanceof \DOMElement) {
            // Skip translate="no" or data-notrans
            if ($node->getAttribute('translate') === 'no' || $node->getAttribute('data-notrans')) {
                return;
            }

            // Translate attributes
            foreach ($translatableAttributes as $attr) {
                if ($node->hasAttribute($attr)) {
                    $value = $node->getAttribute($attr);
                    if ($value !== '' && isset($translations[$value])) {
                        $translated = $translations[$value];
                        if ($translated !== '' && $translated !== null) {
                            $node->setAttribute($attr, $translated);
                        }
                    }
                }
            }

            // Handle button/input values
            $tagName = strtolower($node->tagName);
            if ($tagName === 'button' && $node->hasAttribute('value')) {
                $value = $node->getAttribute('value');
                if ($value !== '' && isset($translations[$value])) {
                    $translated = $translations[$value];
                    if ($translated !== '') {
                        $node->setAttribute('value', $translated);
                    }
                }
            }
            if ($tagName === 'input' && $node->hasAttribute('value')) {
                $type = strtolower($node->getAttribute('type'));
                if ($type === 'submit' || $type === 'button') {
                    $value = $node->getAttribute('value');
                    if ($value !== '' && isset($translations[$value])) {
                        $translated = $translations[$value];
                        if ($translated !== '') {
                            $node->setAttribute('value', $translated);
                        }
                    }
                }
            }
        }

        // Recurse into children
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->walkAndTranslateBlock($child, $translations, $translatableAttributes);
            }
        }
    }

    /**
     * Queue a phrase for registration.
     *
     * Also adds to in-memory cache so subsequent translate() calls for the
     * same phrase don't re-queue it.
     *
     * @param string $phrase The phrase to register
     * @param string $category Category for the phrase
     * @return void
     */
    protected function queuePhraseForRegistration($phrase, $category)
    {
        $key = $category . '::' . $phrase;

        // Skip if already queued
        if (isset($this->pendingPhrases[$key])) {
            return;
        }

        $this->pendingPhrases[$key] = [
            'phrase' => $phrase,
            'category' => $category,
        ];

        $this->logger->debug('Phrase queued for registration', [
            'phrase' => $phrase,
            'category' => $category,
        ]);

        // Add to in-memory cache (empty string = no translation yet)
        // This prevents re-queueing the same phrase within the same request
        $locale = $this->getLocale();
        if ($locale !== null) {
            if (!isset($this->translationsMemoryCache[$locale])) {
                $this->translationsMemoryCache[$locale] = [];
            }
            if (!isset($this->translationsMemoryCache[$locale][$category])) {
                $this->translationsMemoryCache[$locale][$category] = [];
            }
            // Only add if not already present (don't overwrite existing translations)
            if (!array_key_exists($phrase, $this->translationsMemoryCache[$locale][$category])) {
                $this->translationsMemoryCache[$locale][$category][$phrase] = '';
            }
        }

        // Register shutdown handler on first queue
        $this->registerShutdownHandler();
    }

    /**
     * Queue a content block for registration.
     *
     * Also adds to in-memory cache so subsequent translateContentBlock() calls for the
     * same content don't re-queue it.
     *
     * @param string $html HTML content
     * @param string $category Category
     * @param string $customId Custom ID for the block
     * @param array $phrases Extracted phrases
     * @return void
     */
    protected function queueContentBlockForRegistration($html, $category, $customId, array $phrases)
    {
        // Skip if already queued
        if (isset($this->pendingContentBlocks[$customId])) {
            return;
        }

        // Resolve relative URLs before queuing
        $html = $this->resolveContentBlockUrls($html);

        $this->pendingContentBlocks[$customId] = [
            'html' => $html,
            'category' => $category,
            'customId' => $customId,
            'phrases' => $phrases,
        ];

        $this->logger->debug('Content block queued for registration', [
            'custom_id' => $customId,
            'category' => $category,
            'phrase_count' => count($phrases),
        ]);

        // Add to in-memory cache (empty strings = no translations yet)
        // This prevents re-queueing the same content block within the same request
        $locale = $this->getLocale();
        if ($locale !== null) {
            if (!isset($this->translationsMemoryCache[$locale])) {
                $this->translationsMemoryCache[$locale] = [];
            }
            if (!isset($this->translationsMemoryCache[$locale][$category])) {
                $this->translationsMemoryCache[$locale][$category] = [];
            }
            // Only add if not already present (don't overwrite existing translations)
            if (!array_key_exists($customId, $this->translationsMemoryCache[$locale][$category])) {
                // Create empty translation map for all phrases
                $emptyTranslations = [];
                foreach ($phrases as $phrase) {
                    $emptyTranslations[$phrase] = '';
                }
                $this->translationsMemoryCache[$locale][$category][$customId] = $emptyTranslations;
            }
        }

        // Register shutdown handler on first queue
        $this->registerShutdownHandler();
    }

    /**
     * Register shutdown handler to auto-flush pending registrations.
     *
     * @return void
     */
    protected function registerShutdownHandler()
    {
        if ($this->shutdownRegistered) {
            return;
        }

        $this->shutdownRegistered = true;

        // Register shutdown function to flush at end of request
        $client = $this;
        register_shutdown_function(function () use ($client) {
            $client->flushPendingRegistrations();
        });
    }

    /**
     * Flush all pending phrase and content block registrations to the API.
     *
     * This is called automatically at the end of the request, but you can
     * call it manually if needed.
     *
     * 'success' means every queued item was accepted by the API. It is false
     * whenever work was discarded or failed - a skipped write is not a
     * successful one, and a caller checking this must not be told otherwise.
     *
     * 'skipped' counts items that were never sent, and splits into:
     *   - 'dropped'  - discarded; nothing will retry them, they are gone
     *   - 'retained' - still queued; a later flush() can send them
     * The distinction matters because the two need opposite responses from a
     * caller, and 'skipped' alone cannot tell them apart.
     *
     * @return array ['phrases' => count, 'content_blocks' => count, 'skipped' => count, 'dropped' => count, 'retained' => count, 'success' => bool]
     */
    public function flushPendingRegistrations()
    {
        $result = [
            'phrases' => 0,
            'content_blocks' => 0,
            'skipped' => 0,
            'dropped' => 0,
            'retained' => 0,
            'success' => true,
        ];

        // Skip if nothing to register
        if (empty($this->pendingPhrases) && empty($this->pendingContentBlocks)) {
            return $result;
        }

        $pendingCount = count($this->pendingPhrases) + count($this->pendingContentBlocks);

        // Skip if we can't write
        try {
            if (!$this->canWrite()) {
                $this->logger->warning('Flush skipped - this request may not write', [
                    'pending_phrases' => count($this->pendingPhrases),
                    'pending_content_blocks' => count($this->pendingContentBlocks),
                ]);
                // Nothing can send these, so drop them - but report it, rather
                // than returning a success-shaped result for discarded work.
                $this->pendingPhrases = [];
                $this->pendingContentBlocks = [];
                $result['skipped'] = $pendingCount;
                $result['dropped'] = $pendingCount;
                $result['success'] = false;
                return $result;
            }
        } catch (\Exception $e) {
            $this->logger->error('Flush failed - authorization error', [
                'error' => $e->getMessage(),
                'pending' => $pendingCount,
            ]);
            // Left queued deliberately: a manual flush can still retry these.
            $result['skipped'] = $pendingCount;
            $result['retained'] = $pendingCount;
            $result['success'] = false;
            return $result;
        }

        // Register phrases in a single batch
        if (!empty($this->pendingPhrases)) {
            try {
                $phrases = array_values($this->pendingPhrases);
                $this->translatableItems->createPhrases($phrases);
                $result['phrases'] = count($phrases);
                $this->pendingPhrases = [];
            } catch (\Exception $e) {
                $this->logger->error('Failed to register phrases', [
                    'count' => count($this->pendingPhrases),
                    'error' => $e->getMessage(),
                ]);
                $result['skipped'] += count($this->pendingPhrases);
                $result['retained'] += count($this->pendingPhrases);
                $result['success'] = false;
            }
        }

        // Register content blocks in a single batch
        if (!empty($this->pendingContentBlocks)) {
            try {
                $blocks = array_values($this->pendingContentBlocks);
                $this->translatableItems->createContentBlocks($blocks);
                $result['content_blocks'] = count($blocks);
                $this->pendingContentBlocks = [];
            } catch (\Exception $e) {
                $this->logger->error('Failed to register content blocks', [
                    'count' => count($this->pendingContentBlocks),
                    'error' => $e->getMessage(),
                ]);
                $result['skipped'] += count($this->pendingContentBlocks);
                $result['retained'] += count($this->pendingContentBlocks);
                $result['success'] = false;
            }
        }

        // Clear translation cache if we registered anything
        if ($result['phrases'] > 0 || $result['content_blocks'] > 0) {
            $this->logger->info('Pending registrations flushed', [
                'phrases' => $result['phrases'],
                'content_blocks' => $result['content_blocks'],
                'success' => $result['success'],
            ]);
            try {
                $locale = $this->getLocale();
                if ($locale !== null) {
                    $this->clearCache($locale);
                }
            } catch (\Exception $e) {
                // Ignore cache clear errors
            }
        }

        return $result;
    }

    /**
     * Check if there are pending registrations.
     *
     * @return bool
     */
    public function hasPendingRegistrations()
    {
        return !empty($this->pendingPhrases) || !empty($this->pendingContentBlocks);
    }

    /**
     * Get pending phrases (for debugging/testing).
     *
     * @return array
     */
    public function getPendingPhrases()
    {
        return $this->pendingPhrases;
    }

    /**
     * Get pending content blocks (for debugging/testing).
     *
     * @return array
     */
    public function getPendingContentBlocks()
    {
        return $this->pendingContentBlocks;
    }

    /**
     * Clear pending registrations without sending to API.
     *
     * @return void
     */
    public function clearPendingRegistrations()
    {
        $this->pendingPhrases = [];
        $this->pendingContentBlocks = [];
    }
}
