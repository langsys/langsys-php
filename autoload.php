<?php

/**
 * Standalone autoloader for use without Composer.
 *
 * Usage:
 *   require_once '/path/to/langsys-php/autoload.php';
 *   $client = new \Langsys\SDK\Client('api-key', 'project-id');
 */

spl_autoload_register(function ($class) {
    // Only handle Langsys\SDK namespace
    $prefix = 'Langsys\\SDK\\';
    $prefixLength = strlen($prefix);

    if (strncmp($prefix, $class, $prefixLength) !== 0) {
        return;
    }

    // Get the relative class name
    $relativeClass = substr($class, $prefixLength);

    // Convert namespace separators to directory separators
    $file = __DIR__ . '/src/' . str_replace('\\', '/', $relativeClass) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
