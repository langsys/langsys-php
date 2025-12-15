<?php

/**
 * Langsys PHP SDK - Basic Usage Examples
 *
 * Environment variables required:
 * - LANGSYS_API_KEY: Your Langsys API key
 * - LANGSYS_PROJECT_ID: Your project UUID
 *
 * Optional environment variables:
 * - LANGSYS_API_URL: API base URL (default: https://api.langsys.dev/api)
 * - LANGSYS_CACHE_DRIVER: Cache driver - file, redis, or none (default: file)
 * - LANGSYS_CACHE_PATH: Path for file cache (default: sys_get_temp_dir()/langsys-cache)
 * - LANGSYS_CACHE_TTL: Cache TTL in seconds (default: 3600)
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Langsys\SDK\Client;
use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Cache\RedisCache;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Exception\AuthenticationException;

// ============================================================
// Example 1: Basic initialization using environment variables
// ============================================================

try {
    // Uses LANGSYS_API_KEY and LANGSYS_PROJECT_ID from environment
    $client = new Client();

    // Check if API key is valid and get project info
    $project = $client->getProject();
    echo "Connected to project: " . $project['title'] . "\n";
    echo "Key type: " . $client->getKeyType() . "\n";

} catch (AuthenticationException $e) {
    echo "Authentication failed: " . $e->getMessage() . "\n";
} catch (LangsysException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================================
// Example 2: Initialization with explicit credentials
// ============================================================

try {
    $client = new Client(
        'your-api-key-here',
        'your-project-uuid-here',
        [
            'cache_driver' => 'file',
            'cache_path' => '/tmp/my-app-langsys-cache',
            'cache_ttl' => 7200, // 2 hours
        ]
    );
} catch (LangsysException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// ============================================================
// Example 3: Using different cache drivers
// ============================================================

// File cache (default)
$client = new Client(null, null, [
    'cache' => new FileCache('/path/to/cache', 3600),
]);

// Redis cache
$client = new Client(null, null, [
    'cache' => new RedisCache([
        'host' => '127.0.0.1',
        'port' => 6379,
        'password' => null,
        'database' => 0,
    ]),
]);

// No caching
$client = new Client(null, null, [
    'cache' => new NullCache(),
]);

// ============================================================
// Example 4: Getting translations
// ============================================================

// Get all translations for Spanish
$translations = $client->getTranslations('es-es');

// Structure: [category => [phrase => translation]]
// Example:
// [
//     'UI' => [
//         'Home' => 'Inicio',
//         'About' => 'Acerca de',
//     ],
//     '__uncategorized__' => [
//         'Welcome' => 'Bienvenido',
//     ]
// ]

foreach ($translations as $category => $phrases) {
    echo "Category: $category\n";
    foreach ($phrases as $phrase => $translation) {
        if (is_array($translation)) {
            // Content block
            echo "  Content Block ($phrase):\n";
            foreach ($translation as $blockPhrase => $blockTranslation) {
                echo "    - $blockPhrase => $blockTranslation\n";
            }
        } else {
            echo "  - $phrase => $translation\n";
        }
    }
}

// ============================================================
// Example 5: Translating individual phrases
// ============================================================

// Simple translation
$translated = $client->translate('Home', 'es-es', 'UI');
echo "Home in Spanish: $translated\n"; // "Inicio"

// With default category
$translated = $client->translate('Welcome', 'es-es');
echo "Welcome in Spanish: $translated\n"; // "Bienvenido"

// For content blocks
$translated = $client->translate('Content block phrase 1', 'es-es', 'UI', 'my-content-block-id');

// ============================================================
// Example 6: Registering new phrases (requires write key)
// ============================================================

if ($client->canWrite()) {
    // Register simple phrases
    $client->registerPhrases([
        'New Feature',
        'Click Here',
        'Learn More',
    ]);

    // Register phrases with categories
    $client->registerPhrases([
        ['phrase' => 'Dashboard', 'category' => 'Navigation'],
        ['phrase' => 'Settings', 'category' => 'Navigation'],
        ['phrase' => 'Submit', 'category' => 'Forms'],
    ]);

    // Register a content block
    $client->registerContentBlock(
        'main-menu-001',                    // custom_id
        '<nav><a>Home</a><a>About</a></nav>', // HTML content
        ['Home', 'About'],                   // phrases
        'Navigation',                        // category
        'Main Navigation Menu'               // label
    );

    echo "Phrases registered successfully!\n";
} else {
    echo "API key is read-only, cannot register phrases\n";
}

// ============================================================
// Example 7: Syncing local phrases with remote
// ============================================================

// Define local phrases used in your application
$localPhrases = [
    ['phrase' => 'Home', 'category' => 'UI'],
    ['phrase' => 'About', 'category' => 'UI'],
    ['phrase' => 'Contact', 'category' => 'UI'],
    ['phrase' => 'New Feature', 'category' => 'UI'], // This might be new
];

// Sync with remote
$result = $client->sync($localPhrases, 'es-es');

echo "New phrases found: " . count($result['new_phrases']) . "\n";
echo "Synced to remote: " . ($result['synced'] ? 'Yes' : 'No') . "\n";

// Get updated translations
$translations = $result['translations'];

// ============================================================
// Example 8: Using resource classes directly
// ============================================================

// Get translations resource
$translationsResource = $client->translations();

// Get full response with metadata
$response = $translationsResource->getFlat('es-es');
echo "Total words: " . $response['words'] . "\n";
echo "Untranslated: " . $response['untranslated'] . "\n";

// Get translation stats only
$stats = $translationsResource->getStats('es-es');
echo "Translation progress: " . (($stats['words'] - $stats['untranslated']) / $stats['words'] * 100) . "%\n";

// Get translatable items resource
$itemsResource = $client->translatableItems();

// Create phrases with same category
$itemsResource->createPhrasesWithCategory(
    ['Error 404', 'Error 500', 'Something went wrong'],
    'Errors'
);

// Create phrases from a category map
$itemsResource->createFromMap([
    'Navigation' => ['Home', 'About', 'Contact'],
    'Forms' => ['Submit', 'Cancel', 'Reset'],
    'Messages' => ['Success', 'Error', 'Warning'],
]);

// ============================================================
// Example 9: Cache management
// ============================================================

// Clear cache for specific locale
$client->clearCache('es-es');

// Clear all cache
$client->clearCache();

// Force fresh data (bypass cache)
$translations = $client->getTranslations('es-es', false);

// ============================================================
// Example 10: Integration helper function
// ============================================================

/**
 * Simple translation helper function for use in templates.
 */
function __($phrase, $category = '__uncategorized__')
{
    static $client = null;
    static $locale = null;

    if ($client === null) {
        $client = new Client();
        $locale = 'es-es'; // Set based on user preference
    }

    return $client->translate($phrase, $locale, $category);
}

// Usage in templates:
// echo __('Home', 'UI');
// echo __('Welcome');
