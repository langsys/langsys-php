<?php

namespace Langsys\SDK\Log;

/**
 * No-op logger implementation.
 *
 * Used when logging is disabled (no log_path configured).
 * All methods are empty and do nothing.
 */
class NullLogger implements LoggerInterface
{
    /**
     * {@inheritdoc}
     */
    public function debug($message, array $context = [])
    {
        // No-op
    }

    /**
     * {@inheritdoc}
     */
    public function info($message, array $context = [])
    {
        // No-op
    }

    /**
     * {@inheritdoc}
     */
    public function warning($message, array $context = [])
    {
        // No-op
    }

    /**
     * {@inheritdoc}
     */
    public function error($message, array $context = [])
    {
        // No-op
    }

    /**
     * {@inheritdoc}
     */
    public function log($level, $message, array $context = [])
    {
        // No-op
    }
}
