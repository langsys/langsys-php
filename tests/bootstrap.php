<?php

/**
 * PHPUnit bootstrap file for Langsys SDK tests.
 */

// Ensure we have the autoloader
$autoloadFile = dirname(__DIR__) . '/vendor/autoload.php';

if (!file_exists($autoloadFile)) {
    echo "Please run 'composer install' before running tests.\n";
    exit(1);
}

require_once $autoloadFile;
