<?php

namespace Langsys\SDK\Log;

/**
 * Interface for logger implementations.
 *
 * Provides a simple logging contract with four severity levels.
 * Compatible with PHP 5.6+.
 */
interface LoggerInterface
{
    /**
     * Log a debug message.
     *
     * Detailed information for debugging purposes.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function debug($message, array $context = []);

    /**
     * Log an info message.
     *
     * Interesting events (e.g., API calls, cache operations).
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function info($message, array $context = []);

    /**
     * Log a warning message.
     *
     * Exceptional occurrences that are not errors.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function warning($message, array $context = []);

    /**
     * Log an error message.
     *
     * Runtime errors that do not require immediate action.
     *
     * @param string $message
     * @param array $context
     * @return void
     */
    public function error($message, array $context = []);

    /**
     * Log a message at a specific level.
     *
     * @param string $level One of: debug, info, warning, error
     * @param string $message
     * @param array $context
     * @return void
     */
    public function log($level, $message, array $context = []);
}
