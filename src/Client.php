<?php

namespace Langsys\SDK;

use Langsys\SDK\Cache\CacheInterface;
use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Cache\RedisCache;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Format\Interpolator;
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
     * Whether THIS request may register content, as computed by the server.
     *
     * Deliberately not part of $projectData and never written to $this->cache:
     * the server derives it from the caller's address and any write grant, so
     * the same key legitimately answers true for one request and false for the
     * next. Persisting it would apply one caller's decision to every later
     * request sharing the cache. Null means "not yet resolved this request".
     *
     * @var bool|null
     */
    protected $writeEnabled = null;

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
     * @var Interpolator|null Placeholder interpolator (lazily created)
     */
    protected $interpolator;

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

        // Composer enforces the PHP version and ext-intl, but the documented
        // manual autoload.php install bypasses Composer entirely - so check at
        // runtime too, or those users hit an obscure failure inside the ICU code
        // with nothing pointing at the cause. Host frameworks that surface the
        // SDK logger themselves can silence the error_log leg.
        if (array_key_exists('warn_runtime_requirements', $options)) {
            $this->warnRuntimeRequirements = (bool) $options['warn_runtime_requirements'];
        }

        $this->checkRuntimeRequirements();

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
     * Minimum supported PHP version (mirrors composer.json).
     */
    const MIN_PHP_VERSION = '7.4';

    /**
     * @var bool Whether runtime requirement warnings have already been emitted.
     */
    protected static $requirementsWarned = false;

    /**
     * @var bool Whether to write requirement warnings to the PHP error log.
     */
    protected $warnRuntimeRequirements = true;

    /**
     * Warn about unmet runtime requirements.
     *
     * Deliberately warns rather than throws: a manual install on an older PHP
     * may still work for basic translation, and a missing ext-intl degrades
     * gracefully to simple placeholder substitution rather than breaking the
     * render. The goal is that the operator is TOLD, not stopped.
     *
     * Warnings go to both the SDK logger and trigger_error(), because a manual
     * install typically has no log path configured, which would make a
     * logger-only warning invisible to exactly the users who need it.
     *
     * @return void
     */
    protected function checkRuntimeRequirements()
    {
        // Once per process - this runs on every Client construction.
        if (self::$requirementsWarned) {
            return;
        }

        self::$requirementsWarned = true;

        if (version_compare(PHP_VERSION, self::MIN_PHP_VERSION, '<')) {
            $this->warnRequirement(
                'Langsys SDK requires PHP ' . self::MIN_PHP_VERSION . ' or higher; running ' . PHP_VERSION
                    . '. Installed without Composer? The version constraint is not enforced on that path.',
                ['required' => self::MIN_PHP_VERSION, 'actual' => PHP_VERSION]
            );
        }

        if (!extension_loaded('intl')) {
            $this->warnRequirement(
                'Langsys SDK: ext-intl is not loaded. ICU plurals and locale-aware number/date '
                    . 'formatting are unavailable; placeholders fall back to simple substitution.',
                ['missing_extension' => 'intl']
            );
        }
    }

    /**
     * Emit an unmet-requirement warning through both channels.
     *
     * Uses error_log() rather than trigger_error(). trigger_error() goes through
     * the installed error handler, and frameworks routinely convert PHP errors
     * into exceptions - Laravel's HandleExceptions turns E_USER_WARNING into a
     * thrown ErrorException - which inverted this warning into a fatal that
     * broke Client construction on any host without ext-intl. That is the exact
     * opposite of the intent. error_log() writes straight to the log and cannot
     * be escalated by any error handler.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    protected function warnRequirement($message, array $context = [])
    {
        $this->logger->warning($message, $context);

        if (!$this->warnRuntimeRequirements) {
            return;
        }

        // Surfaces in the PHP error log even when SDK logging is disabled,
        // which is the normal case for a manual (non-Composer) install.
        error_log($message);
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

        $cacheKey = $this->authCacheKey();

        if (!$force) {
            $cached = $this->cache->get($cacheKey);
            if ($cached !== null) {
                // Entries written by older SDK versions may still carry the
                // write decision. Drop it rather than trust a value computed
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

            if (array_key_exists('write_enabled', $data)) {
                // Present: authoritative, and it wins over key_type in both
                // directions. This is the only path that can answer for an
                // 'ip_write' key. Captured, then stripped so it can never reach
                // $this->cache - a file/Redis store shared by every request on
                // the host, and by the fleet on Redis.
                $this->writeEnabled = (bool) $data['write_enabled'];
                unset($data['write_enabled']);
            } else {
                // Absent means an API too old to compute the flag. That API also
                // has no 'ip_write' keys and no write grants, so key_type is not
                // a lossy proxy there - it is the complete answer. Failing closed
                // here would silently disable registration for every deployment
                // until the server ships the flag.
                $keyTypeForFallback = isset($data['key_type']) ? $data['key_type'] : null;
                $this->writeEnabled = ($keyTypeForFallback === self::KEY_TYPE_WRITE);
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
     * Cache key for the authorization payload.
     *
     * Scoped by project AND by which key asked. The response depends on the API
     * key - key_type, and the capability derived from it - so a project-only key
     * lets two keys on one host share an entry: a read key inherits a write
     * key's cached key_type and believes it may register, and in the other
     * direction a write key inherits a read key's and silently stops
     * discovering. The default cache is a shared temp directory, so "two keys on
     * one host" is an ordinary deployment (a read key rendering, a write key in
     * a sync job), not a corner case.
     *
     * The key is HASHED and truncated: cache keys land on shared filesystems and
     * in Redis keyspaces, and raw key material must not.
     *
     * @return string
     */
    protected function authCacheKey()
    {
        return 'auth_' . $this->config->getProjectId()
            . '_' . substr(hash('sha256', (string) $this->config->getApiKey()), 0, 12);
    }

    /**
     * Sync the batch limit from project data to the TranslatableItems resource.
     *
     * @return void
     */
    protected function syncBatchLimit()
    {
        // The server nests this: langsys_settings.translatable_items.batch_limit.
        // Reading one level short silently kept the SDK on its own default, so
        // the server-provided limit never applied - and if the server lowers it,
        // oversized batches are REJECTED and registration fails wholesale.
        // Confirmed against the spec (REG-9), LangsysSettingsResource, and a live
        // authorize-project response.
        if (isset($this->projectData['langsys_settings']['translatable_items']['batch_limit'])) {
            $this->translatableItems->setBatchLimit(
                $this->projectData['langsys_settings']['translatable_items']['batch_limit']
            );
        }
    }

    /**
     * Check if the API key has write permissions.
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
     * a read key write-enabled. If grant support is ever added, 'read' must stop
     * short-circuiting and resolve per request like 'ip_write'. Pinned by
     * tests/Http/HttpClientTest.php::testNoWriteGrantHeaderIsSent.
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
     * Resolve a caller-supplied category to the local catalog key.
     *
     * The catalog keys uncategorised items under '__uncategorized__', so an
     * empty or null category has to become the sentinel on the way in. Left
     * unnormalised, a lookup under '' misses forever while registration writes
     * the phrase as uncategorised - so the same phrase is re-registered on every
     * request and never converges. Mirrors the JS SDKs'
     * `category || '__uncategorized__'`.
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

            // A hit of the WRONG SHAPE is a miss, not a hit.
            //
            // This cache is shared by every request on the host and, on Redis,
            // by the fleet - so it sees truncated writes, key collisions and
            // formats written by other SDK versions. A non-array here reached
            // the catalog lookup and raised a TypeError out of translate(),
            // which is a 500 on a customer page.
            //
            // Treated as a miss AND invalidated: degrading on every call while a
            // poisoned entry sits there for the rest of its TTL is the lesser
            // fix. Deleting it lets the next request repopulate from the API.
            if ($cached !== null && !is_array($cached)) {
                $this->logger->warning('Discarding malformed translations cache entry', [
                    'locale' => $locale,
                    'cache_key' => $cacheKey,
                    'type' => gettype($cached),
                ]);
                $this->cache->delete($cacheKey);
                $cached = null;
            }

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
     * Translate a phrase.
     *
     * This method both translates the phrase AND queues it for registration if
     * it doesn't exist in translations. Pending registrations are automatically
     * flushed at the end of the request, or you can call flushPendingRegistrations()
     * manually.
     *
     * IMPORTANT: pass dynamic values via $params rather than building the string
     * yourself. `translate(sprintf('Hello, %s!', $name))` registers a NEW catalog
     * phrase for every distinct value ("Hello, Sarah!", "Hello, Ahmed!", ...),
     * polluting the catalog shared with the JS SDKs. `translate('Hello, {name}!',
     * null, null, null, ['name' => $name])` registers one reusable phrase.
     *
     * @param string $phrase The phrase to translate
     * @param string|null $locale Locale code (defaults to getLocale() if not set)
     * @param string $category Category (default: '__uncategorized__')
     * @param string|null $contentBlockId Content block custom_id (for content block phrases)
     * @param array $params Placeholder values, e.g. ['name' => 'Sarah']
     * @return string The translation, or the original phrase if not found
     */
    public function translate($phrase, $locale = null, $category = '__uncategorized__', $contentBlockId = null, array $params = [])
    {
        // Use set locale if not provided
        if ($locale === null) {
            $locale = $this->getLocale();
            if ($locale === null) {
                // Can't translate without locale, but placeholders still resolve.
                return $this->interpolate($phrase, $params, null);
            }
        }

        $category = $this->normalizeCategory($category);

        try {
            $translations = $this->getTranslations($locale);
        } catch (\Throwable $e) {
            // \Throwable, not \Exception: an \Error (TypeError from a
            // wrong-shaped cache hit, ValueError, ArithmeticError) is not an
            // Exception, so an \Exception-only catch is an ENUMERATION of the
            // failures we happened to think of - and the ones we didn't take the
            // render down. WIRE-4 says this call must never throw; the catch has
            // to be as wide as the promise.
            //
            // This method sits on every render path, so the API being
            // unreachable must degrade to source text rather than turn a working
            // page into a 500. Nothing is queued: a failed catalog fetch cannot
            // tell a miss from a hit, and registering on a guess would turn every
            // outage into a write storm on the paths already failing.
            $this->logger->error('Translation lookup failed - returning source phrase', [
                'phrase' => $phrase,
                'category' => $category,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return $this->interpolate($phrase, $params, $locale);
        }

        $categoryTranslations = isset($translations[$category]) ? $translations[$category] : [];

        // Handle content block phrase lookup (don't queue - content block handles its own registration)
        if ($contentBlockId !== null) {
            if (isset($categoryTranslations[$contentBlockId][$phrase])) {
                return $this->interpolate($categoryTranslations[$contentBlockId][$phrase], $params, $locale);
            }
            return $this->interpolate($phrase, $params, $locale);
        }

        // Regular phrase lookup
        if (array_key_exists($phrase, $categoryTranslations)) {
            $value = $categoryTranslations[$phrase];
            // If it's an array (content block ID collision), return original phrase
            if (is_array($value)) {
                return $this->interpolate($phrase, $params, $locale);
            }
            // A registered-but-untranslated phrase comes back present with a
            // NULL value. Both null and '' mean "no translation yet", so fall
            // back to the source phrase - returning the value would hand the
            // caller null from a method that contracts to return a string.
            return $this->interpolate(($value === null || $value === '') ? $phrase : $value, $params, $locale);
        }

        // Phrase not found - queue the RAW phrase (placeholders intact) for
        // registration, then interpolate only what we return to the caller.
        $this->queuePhraseForRegistration($phrase, $category);

        return $this->interpolate($phrase, $params, $locale);
    }

    /**
     * Get the placeholder interpolator.
     *
     * @return Interpolator
     */
    public function getInterpolator()
    {
        if ($this->interpolator === null) {
            $this->interpolator = new Interpolator($this->logger);
        }

        return $this->interpolator;
    }

    /**
     * Interpolate placeholder values into a string.
     *
     * @param string $text
     * @param array $params
     * @param string|null $locale
     * @return string
     */
    protected function interpolate($text, array $params, $locale)
    {
        // Deliberately NOT short-circuiting on empty params. A catalog value can
        // contain an ICU construct the caller knows nothing about - the backend
        // promotes a plain {name} into a gendered select for locales that need
        // one - so "no params" is precisely the case that needs recovery, not
        // the case that can skip it. Returning early here shipped raw
        // MessageFormat source to the page. The interpolator has its own fast
        // path for text with no construct in it.
        return $this->getInterpolator()->interpolate($text, $params, $locale);
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
     * @param array $params Placeholder values applied page-wide
     * @return string Translated HTML
     */
    public function translatePage($html, $category = null, array $selectorCategories = [], array $params = [])
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

        return $this->pageTranslator->translate($html, $locale, $category, $selectorCategories, $params);
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
     * @param array $params Placeholder values applied to text nodes and translatable attributes
     * @return string Translated HTML
     */
    public function translateContentBlock($html, $category = '__uncategorized__', array $params = [])
    {
        if (empty($html)) {
            return $html;
        }

        $locale = $this->getLocale();
        if ($locale === null) {
            // Can't translate without locale, but placeholders still resolve.
            return empty($params)
                ? $html
                : $this->applyBlockTranslations($html, [], new HtmlParser($this->translatableItems->getTranslatableAttributes()), $params, null);
        }

        $category = $this->normalizeCategory($category);

        // Parse HTML and extract phrases
        $parser = new HtmlParser($this->translatableItems->getTranslatableAttributes());
        $phrases = $parser->extractPhrases($html);

        if (empty($phrases)) {
            return $html; // No translatable content
        }

        // Placeholders inside a content block are part of the phrase text, so the
        // raw HTML is what gets registered; params only affect what we render.

        // Generate customId for this content block
        $customId = $parser->generateCustomId($category, $phrases);

        // Get translations. As in translate(), an unreachable API degrades to
        // the source HTML rather than throwing into the caller's render.
        try {
            $translations = $this->getTranslations($locale);
        } catch (\Throwable $e) {
            // \Throwable for the same reason as translate() - see the note there.
            $this->logger->error('Content block lookup failed - returning source HTML', [
                'custom_id' => $customId,
                'category' => $category,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return empty($params)
                ? $html
                : $this->applyBlockTranslations($html, [], $parser, $params, $locale);
        }

        $categoryTranslations = isset($translations[$category]) ? $translations[$category] : [];

        // Current id first, then the shapes this SDK produced before the
        // JSON-form change - a block registered by an older SDK is still in the
        // catalog, filed under the old key.
        $blockTranslations = $this->resolveContentBlockTranslations(
            $categoryTranslations, $category, $phrases, $customId, $parser
        );

        if ($blockTranslations === null) {
            // Content block doesn't exist - queue for registration
            $this->queueContentBlockForRegistration($html, $category, $customId, $phrases);

            // No translations yet, but placeholders still resolve against the original.
            return empty($params)
                ? $html
                : $this->applyBlockTranslations($html, [], $parser, $params, $locale);
        }

        // Apply translations to HTML. A legacy-resolved block reaches here
        // WITHOUT having been queued: queuing is exactly what would create a
        // second block under the new id and strand these translations.
        return $this->applyBlockTranslations($html, $blockTranslations, $parser, $params, $locale);
    }

    /**
     * Resolve a content block's translations: current id first, then legacy.
     *
     * The single resolution path for content blocks. PageTranslator and
     * translateContentBlock BOTH route through this - duplicating the rules in
     * two places is what let the page path miss the legacy fallback, and the
     * same duplication previously let it drift on bookkeeping and on
     * presence-vs-structure checks.
     *
     * @param array $categoryTranslations Catalog slice for this category
     * @param string $category
     * @param array $phrases Phrases extracted from this block, in order
     * @param string $customId Current-form id
     * @param HtmlParser $parser
     * @return array|null Translation map, or null when the block is genuinely new
     */
    public function resolveContentBlockTranslations(array $categoryTranslations, $category, array $phrases, $customId, HtmlParser $parser)
    {
        if (array_key_exists($customId, $categoryTranslations) && is_array($categoryTranslations[$customId])) {
            return $categoryTranslations[$customId];
        }

        return $this->resolveLegacyContentBlock($categoryTranslations, $category, $phrases, $parser);
    }

    /**
     * Find a content block under a pre-JSON-form (legacy) id.
     *
     * Lookup only: legacy ids are never registered, never emitted and never
     * written back. A hit means the block predates the id change and its
     * translations are filed under the old key.
     *
     * The phrase set is verified before the block is accepted. All three known
     * ways two ids can coincide - the old form's unescaped '|' delimiter, the
     * JS SDK's truncating hash, and a joined string that happens to spell a
     * JSON document - are collisions over DIFFERENT content, so none survives
     * comparing the content itself. That check is the guard, and it must fail
     * toward "no match": attaching the wrong translations is visible to a
     * reader, whereas silently serving nothing looks exactly like a block that
     * was never registered.
     *
     * @param array $categoryTranslations Catalog slice for this category
     * @param string $category
     * @param array $phrases Phrases extracted from this block, in order
     * @param HtmlParser $parser
     * @return array|null Translation map, or null when there is no legacy block
     */
    protected function resolveLegacyContentBlock(array $categoryTranslations, $category, array $phrases, HtmlParser $parser)
    {
        foreach ($parser->legacyCustomIds($category, $phrases) as $legacyId) {
            if (!array_key_exists($legacyId, $categoryTranslations)) {
                continue;
            }

            $candidate = $categoryTranslations[$legacyId];

            if (!is_array($candidate) || empty($candidate)) {
                continue;
            }

            // Guard: the block we found must be THIS block. Compare the content
            // the id was supposed to encode, not the id.
            if (!$this->legacyBlockMatchesPhrases($candidate, $phrases)) {
                $this->logger->warning('Legacy content block id resolved to different content - ignoring', [
                    'legacy_custom_id' => $legacyId,
                    'category' => $category,
                ]);
                continue;
            }

            $this->logger->info('Content block resolved under a legacy id', [
                'legacy_custom_id' => $legacyId,
                'category' => $category,
            ]);

            return $candidate;
        }

        return null;
    }

    /**
     * Whether a candidate legacy block carries exactly this block's phrases.
     *
     * Compares the phrase set rather than order: the catalog returns a block as
     * a phrase-keyed map, so ordering is not recoverable from it. Every known
     * collision differs in the phrases themselves, so set equality is enough to
     * separate them.
     *
     * @param array $candidate Catalog block, keyed by source phrase
     * @param array $phrases
     * @return bool
     */
    protected function legacyBlockMatchesPhrases(array $candidate, array $phrases)
    {
        // array_keys() int-casts numeric-string keys, so an all-numeric phrase
        // set would never compare equal to the string phrases extracted from the
        // markup. Cast both sides back to strings before comparing.
        $found = array_map('strval', array_keys($candidate));
        $expected = array_map('strval', array_values(array_unique($phrases)));

        sort($found);
        sort($expected);

        return $found === $expected;
    }

    /**
     * Apply translations to an HTML content block.
     *
     * @param string $html Original HTML
     * @param array $translations Map of [phrase => translation]
     * @param HtmlParser $parser HTML parser instance
     * @param array $params Placeholder values
     * @param string|null $locale Locale for placeholder formatting
     * @return string Translated HTML
     */
    protected function applyBlockTranslations($html, array $translations, HtmlParser $parser, array $params = [], $locale = null)
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
        $this->walkAndTranslateBlock($doc->documentElement, $translations, $parser->getTranslatableAttributes(), $params, $locale);

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
     * @param array $params Placeholder values
     * @param string|null $locale Locale for placeholder formatting
     * @return void
     */
    protected function walkAndTranslateBlock(\DOMNode $node, array $translations, array $translatableAttributes, array $params = [], $locale = null)
    {
        // Handle text nodes
        if ($node instanceof \DOMText) {
            $normalizedText = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($normalizedText !== '') {
                $translated = isset($translations[$normalizedText]) ? $translations[$normalizedText] : null;

                // Fall back to the original text so placeholders still resolve
                // in blocks that have no translation yet.
                if ($translated === null || $translated === '') {
                    $translated = $normalizedText;
                }

                $translated = $this->interpolate($translated, $params, $locale);

                if ($translated !== $normalizedText) {
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
            // Skip elements excluded from translation entirely.
            if (HtmlParser::isTranslationExcluded($node)) {
                return;
            }

            // Translate attributes
            foreach ($translatableAttributes as $attr) {
                if ($node->hasAttribute($attr)) {
                    $this->translateAttributeValue($node, $attr, $translations, $params, $locale);
                }
            }

            // Handle button/input values
            $tagName = strtolower($node->tagName);
            if ($tagName === 'button' && $node->hasAttribute('value')) {
                $this->translateAttributeValue($node, 'value', $translations, $params, $locale);
            }
            if ($tagName === 'input' && $node->hasAttribute('value')) {
                $type = strtolower($node->getAttribute('type'));
                if ($type === 'submit' || $type === 'button') {
                    $this->translateAttributeValue($node, 'value', $translations, $params, $locale);
                }
            }
        }

        // Recurse into children
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                $this->walkAndTranslateBlock($child, $translations, $translatableAttributes, $params, $locale);
            }
        }
    }

    /**
     * Translate a single attribute value, then interpolate placeholders.
     *
     * Falls back to the original attribute value when no translation exists so
     * placeholders still resolve in not-yet-translated blocks.
     *
     * @param \DOMElement $node
     * @param string $attr Attribute name
     * @param array $translations Translation map
     * @param array $params Placeholder values
     * @param string|null $locale
     * @return void
     */
    protected function translateAttributeValue(\DOMElement $node, $attr, array $translations, array $params, $locale)
    {
        $value = $node->getAttribute($attr);

        if ($value === '') {
            return;
        }

        $translated = isset($translations[$value]) ? $translations[$value] : null;

        if ($translated === null || $translated === '') {
            $translated = $value;
        }

        $translated = $this->interpolate($translated, $params, $locale);

        if ($translated !== $value) {
            $node->setAttribute($attr, $translated);
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
    /**
     * Queue a phrase for registration at end of request.
     *
     * Public because PageTranslator routes page registration through the same
     * queue: the two entry points used to differ, with translate() deferring and
     * translatePage() issuing blocking HTTP calls mid-render.
     *
     * @param string $phrase
     * @param string|null $category
     * @return void
     */
    public function queuePhraseForRegistration($phrase, $category)
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
    /**
     * Queue a content block for registration at end of request.
     *
     * @param string $html
     * @param string|null $category
     * @param string $customId
     * @param array $phrases
     * @return void
     */
    public function queueContentBlockForRegistration($html, $category, $customId, array $phrases)
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
     * 'skipped' counts items never sent, and splits into 'dropped' (discarded;
     * nothing will retry them) and 'retained' (still queued; a later flush can
     * send them). The two need opposite responses from a caller.
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

        // Skip if we can't write
        try {
            if (!$this->canWrite()) {
                $pendingCount = count($this->pendingPhrases) + count($this->pendingContentBlocks);
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
            $pendingCount = count($this->pendingPhrases) + count($this->pendingContentBlocks);
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
