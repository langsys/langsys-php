<?php

namespace Langsys\SDK;

use Langsys\SDK\Cache\CacheInterface;
use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Cache\RedisCache;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Format\Interpolator;
use Langsys\SDK\Html\Canonical;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Html\PageTranslator;
use Langsys\SDK\Http\HttpClient;
use Langsys\SDK\Locale\LocaleDetector;
use Langsys\SDK\Locale\RequestLocale;
use Langsys\SDK\Log\Logger;
use Langsys\SDK\Log\ErrorLogLogger;
use Langsys\SDK\Log\LoggerInterface;
use Langsys\SDK\Log\LogViewer;
use Langsys\SDK\Log\NullLogger;
use Langsys\SDK\Messages\ServerMessage;
use Langsys\SDK\Migration\LegacyKeys;
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
     * Consecutive failed sends, and when the next send may be tried (REG-8).
     * Held by this object, not by the request: under a long-lived runtime the
     * clock outlives resetRequestState(), so a failing endpoint is not asked
     * again by every request the worker serves.
     *
     * @var int
     */
    protected $sendFailures = 0;

    /**
     * @var float
     */
    protected $nextSendAt = 0.0;

    /**
     * Where the app keeps the request's locale (RequestLocale::DEFAULTS).
     *
     * @var array
     */
    protected $requestLocaleOptions = [];

    /**
     * This request's resolved locale, when none was set (SRV-6).
     *
     * @var array|null
     */
    protected $requestLocale = null;

    /**
     * Whether an unusable write capability has been reported (OBS-1).
     *
     * @var bool
     */
    protected $unusableCapabilityReported = false;

    /** First backoff after a failed send, in seconds; it doubles up to the ceiling. */
    const SEND_BACKOFF_INITIAL = 3;

    /** Longest wait between send attempts, in seconds. */
    const SEND_BACKOFF_CEILING = 300;

    /**
     * @var array In-memory translations cache (survives across getTranslations calls within same request)
     */
    protected $translationsMemoryCache = [];

    /**
     * Locales whose catalog fetch has already failed during THIS request.
     *
     * A failed fetch is not cacheable - writing anything for it is how a bad
     * response blanks a project for a whole TTL - but it should not be retried
     * once per phrase either. Without this, a 200-phrase page during an outage
     * or a malformed-response incident issues 200 requests, each one waiting on
     * the same broken dependency, turning a degraded page into a slow one and
     * adding load to a service already in trouble.
     *
     * Held for a bounded window per locale (CACHE-2): 3s after the first
     * failure, doubling on each consecutive one to five minutes, cleared by the
     * first success - REG-8's clock on the read side. It belongs to this object,
     * not the request, so resetRequestState() keeps it: under a long-lived
     * runtime every request during an outage would otherwise wait on the failing
     * call again, and the window, not the request, is what ends the latch. It is
     * never written to the shared cache.
     *
     * @var array<string, array{failures: int, until: float}>
     */
    protected $translationFetchFailures = [];

    /**
     * @var Interpolator|null Placeholder interpolator (lazily created)
     */
    protected $interpolator;

    /**
     * @var LegacyKeys|false|null Legacy-key resolver: null until first asked, false when the mode is off
     */
    protected $legacyKeys;

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
        if (isset($options['request_locale']) && is_array($options['request_locale'])) {
            $this->requestLocaleOptions = $options['request_locale'];
        }

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

        // With no log file, warnings and errors still go somewhere (REG-10):
        // PHP's error log, unless the app turns that off.
        $fallback = (isset($options['error_log']) && $options['error_log'] === false)
            ? new NullLogger()
            : new ErrorLogLogger();

        // Check if logging is enabled
        if (!$this->config->isLoggingEnabled()) {
            return $fallback;
        }

        $logPath = $this->config->getLogPath();
        $logLevel = $this->config->getLogLevel();

        // Validate that the directory is writable
        $dir = dirname($logPath);
        if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
            return $fallback;
        }
        if (!is_writable($dir)) {
            return $fallback;
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
            $this->reportUnusableCapability();
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
     * OBS-1: a key whose type is meant to write, answered write_enabled false,
     * is otherwise completely silent - nothing is sent and nothing fails - so
     * say so once for the life of this object, never per miss.
     *
     * @return void
     */
    protected function reportUnusableCapability()
    {
        $keyType = isset($this->projectData['key_type']) ? $this->projectData['key_type'] : null;

        if ($this->unusableCapabilityReported || $this->writeEnabled !== false
            || !in_array($keyType, [self::KEY_TYPE_WRITE, 'ip_write'], true)) {
            return;
        }

        $this->unusableCapabilityReported = true;

        $this->logger->warning('This key cannot register new text: its type writes, but the server answered write_enabled false, so nothing new will reach the catalog. For an ip_write key, check that this server\'s address is on the key\'s allow-list.', [
            'project_id' => $this->config->getProjectId(),
            'key_type' => $keyType,
        ]);
    }

    /**
     * Whether a value has the shape of a catalog: a map of category => map.
     *
     * Checked to depth 2, not depth 1. A top-level array whose SLICES are
     * scalars passes an is_array() test and then raises a TypeError the moment
     * anything indexes into a slice - which is outside the entry-point catch,
     * so it reaches the caller as a 500. The earlier guard tested only the top
     * level and was therefore satisfied by a value that still broke every
     * render.
     *
     * Deliberately not recursive past depth 2: a slice's VALUES are legitimately
     * mixed (string translation, null for untranslated, array for a content
     * block), so there is nothing further to constrain.
     *
     * @param mixed $value
     * @return bool
     */
    protected function isCatalogShape($value)
    {
        if (!is_array($value)) {
            return false;
        }

        foreach ($value as $slice) {
            if (!is_array($slice)) {
                return false;
            }
        }

        return true;
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
     * between requests. Flush pending registrations first - this drops what is
     * still queued rather than sending it.
     *
     * @return $this
     */
    public function resetRequestState()
    {
        // REG-8: the queue belongs to the request that collected it. Sending one
        // request's phrases with another's would carry request data across the
        // boundary; the next render collects its own misses again. The backoff
        // clock is not request state and stays.
        $unsent = count($this->pendingPhrases) + count($this->pendingContentBlocks);
        if ($unsent > 0) {
            $this->logger->debug('Unsent registrations dropped at the request boundary', ['count' => $unsent]);
        }
        $this->pendingPhrases = [];
        $this->pendingContentBlocks = [];

        $this->writeEnabled = null;
        $this->requestLocale = null;
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
        $locale = LocaleDetector::normalize($locale);
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
            // formats written by other SDK versions. A bad entry here reached
            // the catalog lookup and raised a TypeError out of translate(),
            // which is a 500 on a customer page. Checked to depth 2: an array
            // of SCALAR slices satisfies is_array() and then breaks at the
            // first index into a slice, past the entry-point catch.
            //
            // Treated as a miss AND invalidated: degrading on every call while a
            // poisoned entry sits there for the rest of its TTL is the lesser
            // fix. Deleting it lets the next request repopulate from the API.
            if ($cached !== null && !$this->isCatalogShape($cached)) {
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

        // Inside a failed fetch's window? Fail the same way again without
        // asking the API. Re-raised rather than answered with [], because an
        // empty catalog is a VALUE and callers would cache it; the whole point
        // is that a failure produces no value at all.
        if (isset($this->translationFetchFailures[$memoryKey])
            && $this->currentTime() < $this->translationFetchFailures[$memoryKey]['until']) {
            throw new LangsysException(sprintf(
                'Translations fetch for %s failed recently; retrying after its backoff window',
                $locale
            ));
        }

        $this->logger->debug('Translations cache miss', ['locale' => $locale]);

        // Fetch from API
        try {
            $translations = $this->translations->getTranslationMap($locale);
        } catch (\Throwable $e) {
            $failures = isset($this->translationFetchFailures[$memoryKey])
                ? $this->translationFetchFailures[$memoryKey]['failures'] + 1
                : 1;
            $this->translationFetchFailures[$memoryKey] = [
                'failures' => $failures,
                'until' => $this->currentTime() + $this->backoffDelay($failures),
            ];
            throw $e;
        }

        unset($this->translationFetchFailures[$memoryKey]);

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
        // Legacy-key mode (MIG-2): the argument is a key first. A hit makes the
        // key's source value the phrase - never the key - and its namespace the
        // category unless the caller chose one. A miss is literal source text,
        // except a package key, which never registers as a key-shaped phrase.
        $legacy = $contentBlockId === null ? $this->getLegacyKeys() : null;

        if ($legacy !== null && is_string($phrase)) {
            $entry = $legacy->resolve($phrase);

            if ($entry === null) {
                if (LegacyKeys::isPackageKey($phrase)) {
                    $this->logger->debug('A package key is not in the migration source files; nothing is registered', ['argument' => $phrase]);

                    return $phrase;
                }

                $this->logger->debug('Not a key in the migration source files; treated as source text', ['argument' => $phrase]);
            } else {
                if (!$entry['recognised']) {
                    $this->logger->warning('A migration source value is registered as written: it ' . $entry['issue'], ['key' => $entry['key'], 'file' => $entry['file']]);
                }

                $phrase = $entry['phrase'];

                if (($category === null || $category === '' || $category === self::UNCATEGORIZED) && $entry['category'] !== null) {
                    $category = $entry['category'];
                }
            }
        }

        return $this->translateSource($phrase, $locale, $category, $contentBlockId, $params);
    }

    /**
     * Look a source phrase up in the catalog, queue it when it is new, and
     * render it: translate() once any legacy key has been resolved, and the
     * phrase a one-phrase content-block fragment registers as (TOK-6).
     *
     * @param string $phrase
     * @param string|null $locale
     * @param string|null $category
     * @param string|null $contentBlockId
     * @param array $params
     * @return string
     */
    protected function translateSource($phrase, $locale, $category, $contentBlockId, array $params)
    {
        // TOK-2: a code-registered key drops the C0 controls on lookup and on
        // register alike, as every DOM path does.
        if (is_string($phrase)) {
            $phrase = Canonical::stripControls($phrase);
        }

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
     * The legacy-key resolver, or null when the migration mode is off (MIG-1).
     * Created on first use; files are read on the first lookup, never here.
     *
     * @return LegacyKeys|null
     */
    public function getLegacyKeys()
    {
        if ($this->legacyKeys === null) {
            $migration = $this->config->getMigration();
            $this->legacyKeys = $migration === null ? false : new LegacyKeys($migration);
        }

        return $this->legacyKeys === false ? null : $this->legacyKeys;
    }

    /**
     * Resolve a legacy key without translating it: its source phrase, the
     * category it registers under (the caller's, when given), the key and the
     * file that answered. Null when the mode is off or no file holds the key.
     *
     * @param string $key
     * @param string|null $category
     * @return array{phrase: string, category: string|null, key: string, file: string}|null
     */
    public function resolveLegacyKey($key, $category = null)
    {
        $legacy = $this->getLegacyKeys();
        $entry = $legacy === null ? null : $legacy->resolve($key);

        if ($entry === null) {
            return null;
        }

        return [
            'phrase' => $entry['phrase'],
            'category' => ($category === null || $category === '' || $category === self::UNCATEGORIZED) ? $entry['category'] : $category,
            'key' => $entry['key'],
            'file' => $entry['file'],
        ];
    }

    /**
     * Render a server message entry (MSG-5, MSG-6).
     *
     * The entry's TEMPLATE is looked up under the messages category and the
     * translation is filled from the entry's params, so a count param renders
     * through the catalog's ICU like any other phrase. With no translation - a
     * miss, a template registered but not yet translated, a failed lookup, no
     * locale - the entry's own message comes back verbatim. The server already
     * filled it; filling the template again here would format its numbers for
     * this locale and disagree with what the server sent. The message is never
     * used as a lookup key: it is filled, possibly localised, text.
     *
     * A miss queues the template for registration, as any rendered phrase does.
     *
     * @param ServerMessage|array $entry An entry, or its wire form
     * @param string|null $locale
     * @return string
     */
    public function translateMessage($entry, $locale = null)
    {
        if (is_array($entry)) {
            $parsed = ServerMessage::fromArray($entry);

            if ($parsed === null) {
                return (isset($entry['message']) && is_string($entry['message'])) ? $entry['message'] : '';
            }

            $entry = $parsed;
        }

        if (!$entry instanceof ServerMessage) {
            return '';
        }

        if ($locale === null) {
            $locale = $this->messageLocale();

            if ($locale === null) {
                return $entry->getMessage();
            }
        }

        $category = $this->normalizeCategory($this->config->getMessagesCategory());
        $found = $this->lookupMessageTemplate($entry->getTemplate(), $category, $locale);

        if ($found === null) {
            return $entry->getMessage();
        }

        list($listed, $translation) = $found;

        if (!$listed) {
            $this->queuePhraseForRegistration($entry->getTemplate(), $category);

            return $entry->getMessage();
        }

        if ($translation === null) {
            return $entry->getMessage();
        }

        return $this->interpolate($translation, $entry->getParams(), $locale);
    }

    /**
     * Note that the server is sending an entry (MSG-8).
     *
     * A template the catalog does not list under the messages category is queued
     * on the existing registration path: it is sent after the response, once, and
     * only if this request's key may write. Nothing is sent here. When the catalog
     * cannot be read, or there is no locale to read it in, nothing is queued -
     * a miss cannot be told from a hit, and registering on a guess would turn an
     * outage into a write on every failing request.
     *
     * @param ServerMessage $message
     * @return ServerMessage The same entry, so a caller can emit it inline
     */
    public function emitMessage(ServerMessage $message)
    {
        $locale = $this->messageLocale();

        if ($locale === null) {
            return $message;
        }

        $category = $this->normalizeCategory($this->config->getMessagesCategory());
        $found = $this->lookupMessageTemplate($message->getTemplate(), $category, $locale);

        if ($found !== null && !$found[0]) {
            $this->queuePhraseForRegistration($message->getTemplate(), $category);
        }

        return $message;
    }

    /**
     * The locale a server message is read in, or null. Resolving it can reach the
     * network (the project's base locale), and a render path must not throw.
     *
     * @return string|null
     */
    protected function messageLocale()
    {
        try {
            return $this->getLocale();
        } catch (\Throwable $e) {
            $this->logger->error('Could not resolve a locale for a server message', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Whether the catalog lists a template, and its translation.
     *
     * @param string $template
     * @param string $category
     * @param string $locale
     * @return array|null [bool $listed, string|null $translation], or null when the catalog could not be read
     */
    protected function lookupMessageTemplate($template, $category, $locale)
    {
        $template = Canonical::stripControls($template);

        try {
            $translations = $this->getTranslations($locale);
        } catch (\Throwable $e) {
            $this->logger->error('Server message lookup failed - using the message the server sent', [
                'template' => $template,
                'category' => $category,
                'locale' => $locale,
                'error' => $e->getMessage(),
            ]);

            return null;
        }

        $categoryTranslations = (isset($translations[$category]) && is_array($translations[$category])) ? $translations[$category] : [];

        if (!array_key_exists($template, $categoryTranslations)) {
            return [false, null];
        }

        $value = $categoryTranslations[$template];

        return [true, (is_string($value) && $value !== '') ? $value : null];
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
            $phrase = Canonical::stripControls(is_string($phraseData) ? $phraseData : $phraseData['phrase']);
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
        $locale = LocaleDetector::normalize($locale);

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
     * The locale set with setLocale(), or else this request's locale as
     * resolveRequestLocale() chooses it, once per request.
     *
     * @return string|null The locale, or null if unable to determine
     */
    public function getLocale()
    {
        if ($this->locale !== null) {
            return $this->locale;
        }

        if ($this->requestLocale === null) {
            $this->requestLocale = $this->resolveRequestLocale();
        }

        return $this->requestLocale['locale'];
    }

    /**
     * Choose this request's locale (SRV-6): the URL, then a cookie or session
     * value, then Accept-Language negotiated against the project's locales,
     * else the base locale. Every candidate is validated against the project's
     * base and target locales, and an unsupported one falls through. Sends the
     * Vary header the choice requires, and never writes a cookie.
     *
     * @param array|null $request path, host, query, cookies, session,
     *                            accept_language; read from PHP's superglobals when null
     * @param array|null $options Overrides the `request_locale` option
     * @return array{locale: string|null, source: string, vary: string|null}
     */
    public function resolveRequestLocale(array $request = null, array $options = null)
    {
        try {
            $project = $this->getProject();
        } catch (\Throwable $e) {
            $this->logger->error('Could not read the project\'s locales to choose the request locale', ['error' => $e->getMessage()]);

            return ['locale' => null, 'source' => 'none', 'vary' => null];
        }

        $base = isset($project['base_locale']) ? $project['base_locale'] : null;
        $served = array_merge($base === null ? [] : [$base], isset($project['target_locales']) && is_array($project['target_locales']) ? $project['target_locales'] : []);

        $result = RequestLocale::resolve(
            $served,
            $base,
            $request !== null ? $request : $this->currentRequest(),
            $options !== null ? $options : $this->requestLocaleOptions
        );

        if ($result['vary'] !== null) {
            $this->sendVaryHeader($result['vary']);
        }

        return $result;
    }

    /**
     * The request as RequestLocale reads it, from PHP's superglobals.
     *
     * @return array
     */
    protected function currentRequest()
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';

        return [
            'path' => (string) parse_url($uri, PHP_URL_PATH),
            'host' => isset($_SERVER['HTTP_HOST']) ? (string) $_SERVER['HTTP_HOST'] : null,
            'query' => $_GET,
            'cookies' => $_COOKIE,
            'session' => (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION) && is_array($_SESSION)) ? $_SESSION : [],
            'accept_language' => isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? (string) $_SERVER['HTTP_ACCEPT_LANGUAGE'] : null,
        ];
    }

    /**
     * Add a Vary header, keeping any the app already sent.
     *
     * @param string $value
     * @return void
     */
    protected function sendVaryHeader($value)
    {
        if (!headers_sent()) {
            header('Vary: ' . $value, false);
        }
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
     * A marked host inside the fragment - a phrase marker, or a content-block
     * marker that is not an opt-out - is excised from the fragment's phrases
     * and translated as a unit of its own (MARK-4).
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

        // Which hosts are nested is read from the fragment as written: the
        // render stamps a single host with the block's id, and that stamp is
        // this unit's identity, not a nested declaration.
        $source = strpos($html, 'data-ls-') === false && strpos($html, 'data-langsys-') === false ? null : $this->fragmentDocument($html);
        $sourceRoot = $source === null ? null : $source->getElementsByTagName('div')->item(0);

        $rendered = $this->translateBlockUnit($html, $category, $params, false);

        if ($sourceRoot === null || $this->outermostMarkedHosts($sourceRoot) === []) {
            return $rendered;
        }

        $elements = array_values(array_filter(iterator_to_array($sourceRoot->childNodes), function ($node) {
            return $node instanceof \DOMElement;
        }));
        $singleUnmarkedHost = count($elements) === 1 && !HtmlParser::isMarkedHost($elements[0]);

        return $this->renderNestedHostsIn($rendered, $category, $params, $singleUnmarkedHost);
    }

    /**
     * Translate one fragment as one unit, leaving any marked host inside it
     * untouched.
     *
     * @param string $html
     * @param string|null $category
     * @param array $params
     * @param bool $declared Whether a content-block marker declared this unit a
     *                       block; a declaration outranks the TOK-6 shape
     * @return string
     */
    protected function translateBlockUnit($html, $category, array $params, $declared)
    {
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
        $unit = $parser->fragmentUnit($html);
        $phrases = $unit['tokens'];

        if (empty($phrases)) {
            return $html; // No translatable content
        }

        // TOK-6: a fragment whose one token is its one text node is a phrase,
        // looked up, registered and rendered as one, and written back into
        // that text node in place.
        if (!$declared && HtmlParser::isPhraseUnit($unit)) {
            $rendered = $this->translateSource($phrases[0], $locale, $category, null, $params);

            return $this->replaceFragmentText($html, $phrases[0], $rendered);
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

            // Deliberately NOT stamped: the lookup failed, so we do not know
            // that this id is the one the catalog holds. Stamping a guess would
            // publish an identity claim built on an outage.
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

            // No translations yet, but the block still HAS an identity, and
            // stamping it is most valuable here: this is the block a later
            // reader would otherwise re-derive and re-register. Routed through
            // applyBlockTranslations even with no params, which the early
            // return used to skip.
            return $this->applyBlockTranslations($html, [], $parser, $params, $locale, $customId);
        }

        // Apply translations to HTML. A legacy-resolved block reaches here
        // WITHOUT having been queued: queuing is exactly what would create a
        // second block under the new id and strand these translations.
        return $this->applyBlockTranslations($html, $blockTranslations, $parser, $params, $locale, $customId);
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
    /**
     * Stamp the resolved id onto a rendered block's host element (MARK-1).
     *
     * The point is that the identity survives the round trip. Once the served
     * HTML carries its own id, a later reader - this SDK, a JS core hydrating
     * over it, anything - reads the id instead of re-deriving it from the text,
     * and a block whose text was edited or canonicalised differently is still
     * recognised as the same block rather than registered afresh.
     *
     * Stamped ONLY when the fragment has exactly one element root. A multi-root
     * fragment has no single host to carry the identity, and picking the first
     * child would claim the whole block's id for one of its siblings - which
     * then reads as that sibling's identity the next time anything parses it.
     * Silence is the honest outcome there.
     *
     * Never overwrites an existing marker: if the block already carries one,
     * that value is another writer's identity claim and outranks ours.
     *
     * @param \DOMElement $wrapper
     * @param string|null $customId
     * @return void
     */
    protected function stampContentBlockId($wrapper, $customId)
    {
        if ($customId === null || $customId === '') {
            return;
        }

        // Whitespace-only text nodes at the edges do not count as siblings.
        // A template that emits "<strong>Buy now</strong>\n" produces a
        // trailing text node, and counting it made the fragment look
        // multi-node - so the ordinary template-emitted shape silently stopped
        // being stamped. Real text beside the element still blocks stamping;
        // that is the case this guard exists for.
        $elements = [];
        $significant = 0;
        foreach ($wrapper->childNodes as $child) {
            if ($child instanceof \DOMElement) {
                $elements[] = $child;
                $significant++;
                continue;
            }

            if ($child instanceof \DOMText && \Langsys\SDK\Html\Whitespace::collapse($child->textContent) === '') {
                continue;
            }

            $significant++;
        }

        // The fragment must be a single node ENTIRE, not merely a single
        // element among text siblings. Counting elements alone stamped
        // `Buy <strong>now</strong>` on the <strong>, giving it the id of the
        // WHOLE fragment (["Buy","now"]) while that element's own subtree
        // derives ["now"] - a false identity claim in the served bytes, which
        // any later reader then believes.
        if (count($elements) !== 1 || $significant !== 1) {
            $this->logger->debug('Not stamping a content block id: the fragment has no single host element', [
                'custom_id' => $customId,
                'element_roots' => count($elements),
                'significant_nodes' => $significant,
            ]);

            return;
        }

        $host = $elements[0];

        foreach (HtmlParser::CONTENT_BLOCK_MARKERS as $existing) {
            if ($host->hasAttribute($existing)) {
                return;
            }
        }

        $host->setAttribute(HtmlParser::CONTENT_BLOCK_STAMP, $customId);
    }

    /**
     * Render every marked host in a rendered fragment as its own unit (MARK-4).
     *
     * @param string $html
     * @param string|null $category
     * @param array $params
     * @param bool $belowSingleHost Whether to start below the fragment's one
     *                              element, which the render may have stamped
     * @return string
     */
    protected function renderNestedHostsIn($html, $category, array $params, $belowSingleHost)
    {
        $doc = $this->fragmentDocument($html);
        $wrapper = $doc === null ? null : $doc->getElementsByTagName('div')->item(0);

        if ($wrapper === null) {
            return $html;
        }

        $root = $wrapper;
        if ($belowSingleHost) {
            foreach ($wrapper->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $root = $child;
                    break;
                }
            }
        }

        $this->renderNestedHosts($root, $category, $params);

        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return $result;
    }

    /**
     * Render each outermost marked host below $root: a content-block host as a
     * declared block of its content, a phrase host as one tokenized phrase.
     * A host's own nested hosts are rendered first, so a phrase host keeps
     * them translated when its markup is rebuilt.
     *
     * @param \DOMElement $root
     * @param string|null $category
     * @param array $params
     * @return void
     */
    protected function renderNestedHosts(\DOMElement $root, $category, array $params)
    {
        foreach ($this->outermostMarkedHosts($root) as $host) {
            $hostCategory = $host->hasAttribute('data-langsys-category') ? $host->getAttribute('data-langsys-category') : $category;

            if (HtmlParser::isPhraseMarked($host)) {
                $this->renderNestedHosts($host, $hostCategory, $params);
                $this->renderTokenizedHost($host, $hostCategory, $params);
                continue;
            }

            $inner = '';
            foreach ($host->childNodes as $child) {
                $inner .= $host->ownerDocument->saveHTML($child);
            }

            $rendered = $this->translateBlockUnit($inner, $hostCategory, $params, true);
            $this->replaceChildrenWithHtml($host, $rendered);
            $this->renderNestedHosts($host, $hostCategory, $params);
        }
    }

    /**
     * Marked hosts below $root that no other marked host below $root contains,
     * skipping subtrees excluded from translation or that are not prose.
     *
     * @param \DOMElement $root
     * @return \DOMElement[]
     */
    protected function outermostMarkedHosts(\DOMElement $root)
    {
        $found = [];

        foreach ($root->childNodes as $child) {
            if (!$child instanceof \DOMElement
                || HtmlParser::isTranslationExcluded($child)
                || in_array(strtolower($child->nodeName), HtmlParser::NON_PROSE_ELEMENTS, true)) {
                continue;
            }

            if (HtmlParser::isMarkedHost($child)) {
                $found[] = $child;
                continue;
            }

            foreach ($this->outermostMarkedHosts($child) as $host) {
                $found[] = $host;
            }
        }

        return $found;
    }

    /**
     * Translate a phrase-marked host as one tokenized phrase: its markup
     * becomes tokens, the phrase is looked up and registered like any other,
     * and the host's children are rebuilt from the translation. Its own
     * translatable attributes, and those of its unmarked descendants, are
     * phrases of their own.
     *
     * @param \DOMElement $host
     * @param string|null $category
     * @param array $params
     * @return void
     */
    protected function renderTokenizedHost(\DOMElement $host, $category, array $params)
    {
        $tokenizer = new \Langsys\SDK\Html\MarkupTokenizer();
        $encoded = $tokenizer->encode($host);

        if ($encoded['text'] !== '') {
            $slots = $encoded['slots'];
            $rendered = $this->translateSource($encoded['text'], null, $category, null, array_merge($params, $tokenizer->tokenParams(count($slots))));

            if ($tokenizer->hasTokens($rendered)) {
                $rendered = (string) preg_replace('/\{m\d+[oc]\}/', '', $rendered);
                $slots = [];
            }

            $nodes = $tokenizer->render($rendered, $slots, $host->ownerDocument);

            while ($host->firstChild !== null) {
                $host->removeChild($host->firstChild);
            }

            foreach ($nodes as $node) {
                $host->appendChild($node);
            }
        }

        $targets = [$host];
        $walk = function (\DOMElement $element) use (&$walk, &$targets) {
            foreach ($element->childNodes as $child) {
                if ($child instanceof \DOMElement && !HtmlParser::isMarkedHost($child)) {
                    $targets[] = $child;
                    $walk($child);
                }
            }
        };
        $walk($host);

        foreach ($targets as $target) {
            foreach ($this->translatableItems->getTranslatableAttributes() as $attr) {
                if (!$target->hasAttribute($attr)) {
                    continue;
                }

                $value = \Langsys\SDK\Html\Canonical::phrase($target->getAttribute($attr));
                if ($value !== '') {
                    $target->setAttribute($attr, $this->translateSource($value, null, $category, null, $params));
                }
            }
        }
    }

    /**
     * @param string $html
     * @return \DOMDocument|null
     */
    protected function fragmentDocument($html)
    {
        $internalErrors = libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        $doc->encoding = 'UTF-8';
        $loaded = $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return $loaded ? $doc : null;
    }

    /**
     * @param \DOMElement $element
     * @param string $html
     * @return void
     */
    protected function replaceChildrenWithHtml(\DOMElement $element, $html)
    {
        $source = $this->fragmentDocument($html);
        $wrapper = $source === null ? null : $source->getElementsByTagName('div')->item(0);

        if ($wrapper === null) {
            return;
        }

        while ($element->firstChild !== null) {
            $element->removeChild($element->firstChild);
        }

        foreach ($wrapper->childNodes as $child) {
            $element->appendChild($element->ownerDocument->importNode($child, true));
        }
    }

    /**
     * Whether a node sits inside a marked host below $root.
     *
     * @param \DOMNode $node
     * @param \DOMElement $root
     * @return bool
     */
    protected function insideMarkedHost(\DOMNode $node, \DOMElement $root)
    {
        for ($parent = $node->parentNode; $parent !== null && !$parent->isSameNode($root); $parent = $parent->parentNode) {
            if ($parent instanceof \DOMElement && HtmlParser::isMarkedHost($parent)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Write a rendered phrase into the one text node of a fragment that holds
     * it, keeping the node's surrounding whitespace and every element around it.
     *
     * @param string $html
     * @param string $phrase The canonical text the node holds
     * @param string $rendered
     * @return string
     */
    protected function replaceFragmentText($html, $phrase, $rendered)
    {
        $internalErrors = libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        $doc->encoding = 'UTF-8';
        $doc->loadHTML('<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        $wrapper = $doc->getElementsByTagName('div')->item(0);
        if ($wrapper === null) {
            return $html;
        }

        $xpath = new \DOMXPath($doc);
        foreach ($xpath->query('.//text()', $wrapper) as $node) {
            if (\Langsys\SDK\Html\Canonical::phrase($node->textContent) !== $phrase || $this->insideMarkedHost($node, $wrapper)) {
                continue;
            }

            $leading = preg_match('/^' . \Langsys\SDK\Html\Whitespace::JS_WHITESPACE . '/u', $node->textContent) ? ' ' : '';
            $trailing = preg_match('/' . \Langsys\SDK\Html\Whitespace::JS_WHITESPACE . '$/u', $node->textContent) ? ' ' : '';
            $node->textContent = $leading . $rendered . $trailing;
            break;
        }

        $result = '';
        foreach ($wrapper->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }

        return $result;
    }

    protected function applyBlockTranslations($html, array $translations, HtmlParser $parser, array $params = [], $locale = null, $customId = null)
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

        $this->stampContentBlockId($wrapper, $customId);

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
            // Must normalise IDENTICALLY to HtmlParser, which registered these
            // phrases: this is the lookup side, so any divergence is a
            // permanent miss that re-registers on every render.
            $normalizedText = \Langsys\SDK\Html\Canonical::phrase($node->textContent);
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
                    $leadingSpace = preg_match('/^' . \Langsys\SDK\Html\Whitespace::JS_WHITESPACE . '/u', $node->textContent) ? ' ' : '';
                    $trailingSpace = preg_match('/' . \Langsys\SDK\Html\Whitespace::JS_WHITESPACE . '$/u', $node->textContent) ? ' ' : '';
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

        // Recurse into children. A marked host is its own unit (MARK-4) and is
        // rendered on its own, never with this block's translations.
        if ($node->hasChildNodes()) {
            foreach ($node->childNodes as $child) {
                if ($child instanceof \DOMElement && HtmlParser::isMarkedHost($child)) {
                    continue;
                }

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
        // Canonicalised, because this is the LOOKUP side of the attribute pair.
        // HtmlParser::extractAttributePhrases() collapses before registering,
        // so an alt wrapped across source lines is filed under the collapsed
        // form; looking up the raw value could never find it.
        $value = \Langsys\SDK\Html\Canonical::phrase($node->getAttribute($attr));

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
        $phrase = Canonical::stripControls($phrase);
        $key = $category . '::' . $phrase;

        // Skip if already queued
        if (isset($this->pendingPhrases[$key])) {
            return;
        }

        if ($this->isTruncationOfAKnownPhrase($phrase, $category)) {
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
     * REG-11: a phrase ending in an ellipsis may be an upstream truncation of a
     * longer one. It is always reported at debug, and suppressed only on the
     * second signal - a longer phrase already known in its category that starts
     * with the same text - since "Loading..." alone is legitimate copy.
     *
     * Known means the catalog this request read plus what it has queued, which
     * is where the full form of a truncated teaser appears first.
     *
     * @param string $phrase
     * @param string $category
     * @return bool Whether to skip registering it
     */
    protected function isTruncationOfAKnownPhrase($phrase, $category)
    {
        if (!preg_match('/^(.*?)\s*(?:\x{2026}|\.\.\.)$/us', $phrase, $m)) {
            return false;
        }

        $prefix = $m[1];
        $longer = null;

        if ($prefix !== '') {
            foreach ($this->translationsMemoryCache as $catalog) {
                foreach (isset($catalog[$category]) && is_array($catalog[$category]) ? $catalog[$category] : [] as $known => $unused) {
                    $known = (string) $known;
                    if ($known !== $phrase && strlen($known) > strlen($prefix) && strpos($known, $prefix) === 0) {
                        $longer = $known;
                        break 2;
                    }
                }
            }
        }

        $this->logger->debug($longer === null
            ? 'A phrase ends in an ellipsis; if the text was truncated before reaching the SDK, register the full text instead'
            : 'A phrase ending in an ellipsis was not registered: a longer phrase starting with the same text is already known, so this looks like a truncation of it', [
            'phrase' => $phrase,
            'category' => $category,
            'longer' => $longer,
        ]);

        return $longer !== null;
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

        // REG-8: after a failed send, wait before asking the endpoint again.
        // The queue stays for a flush after the wait.
        if ($this->currentTime() < $this->nextSendAt) {
            $pendingCount = count($this->pendingPhrases) + count($this->pendingContentBlocks);
            $this->logger->debug('Flush deferred - backing off after a failed send', [
                'pending' => $pendingCount,
                'retry_in_seconds' => round($this->nextSendAt - $this->currentTime(), 1),
            ]);
            $result['skipped'] = $pendingCount;
            $result['retained'] = $pendingCount;
            $result['success'] = false;
            return $result;
        }

        // Skip if we can't write
        try {
            if (!$this->canWrite()) {
                $pendingCount = count($this->pendingPhrases) + count($this->pendingContentBlocks);
                // Debug, not warning: a key that may not write never sends, by
                // design, and would otherwise log on every request with misses.
                // A write-type key refused by the server is reported once
                // (OBS-1).
                $this->logger->debug('Flush skipped - this request may not write', [
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
        } catch (\Throwable $e) {
            $pendingCount = count($this->pendingPhrases) + count($this->pendingContentBlocks);
            $this->logger->error('Flush failed - authorization error', [
                'error' => $e->getMessage(),
                'pending' => $pendingCount,
            ]);
            // Left queued deliberately: a manual flush can still retry these.
            $result['skipped'] = $pendingCount;
            $result['retained'] = $pendingCount;
            $result['success'] = false;
            $this->noteSendFailure();
            return $result;
        }

        // Register phrases in a single batch
        if (!empty($this->pendingPhrases)) {
            try {
                $phrases = array_values($this->pendingPhrases);
                $this->translatableItems->createPhrases($phrases);
                $result['phrases'] = count($phrases);
                $this->pendingPhrases = [];
            // \Throwable, not \Exception: this usually runs from the shutdown
            // handler, after the response is sent, where an \Error escaping is
            // a fatal - appended to the page on some SAPIs, and invisible on
            // the rest. Registration is best-effort by design, so EVERY way
            // this can fail has to end up in $result rather than unwinding.
            } catch (\Throwable $e) {
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
            } catch (\Throwable $e) {
                $this->logger->error('Failed to register content blocks', [
                    'count' => count($this->pendingContentBlocks),
                    'error' => $e->getMessage(),
                ]);
                $result['skipped'] += count($this->pendingContentBlocks);
                $result['retained'] += count($this->pendingContentBlocks);
                $result['success'] = false;
            }
        }

        if ($result['success']) {
            $this->sendFailures = 0;
            $this->nextSendAt = 0.0;
        } else {
            $this->noteSendFailure();
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
            } catch (\Throwable $e) {
                // Ignore cache clear errors
            }
        }

        return $result;
    }

    /**
     * Record a failed send and set when the next may be tried: 3s, doubling,
     * up to five minutes.
     *
     * @return void
     */
    protected function noteSendFailure()
    {
        $this->sendFailures++;
        $this->nextSendAt = $this->currentTime() + $this->backoffDelay($this->sendFailures);
    }

    /**
     * The wait after the Nth consecutive failure: 3s, doubling, to five
     * minutes. Shared by the send side (REG-8) and the catalog read (CACHE-2).
     *
     * @param int $failures
     * @return float Seconds
     */
    protected function backoffDelay($failures)
    {
        return (float) min(self::SEND_BACKOFF_INITIAL * pow(2, $failures - 1), self::SEND_BACKOFF_CEILING);
    }

    /**
     * @return float Seconds since the epoch
     */
    protected function currentTime()
    {
        return microtime(true);
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
