<?php

namespace Langsys\SDK\Log;

/**
 * The logger used when no other is configured: warnings and errors go to PHP's
 * error log, and nothing below warning is written.
 *
 * REG-10: a failed registration is always logged. Without a configured sink a
 * failed send, a failed catalog read or a key that cannot write would otherwise
 * be recorded nowhere. The error log is the one place every PHP deployment
 * already reads; `'error_log' => false` turns this off.
 */
class ErrorLogLogger implements LoggerInterface
{
    const LEVELS = ['warning', 'error'];

    public function debug($message, array $context = [])
    {
        $this->log('debug', $message, $context);
    }

    public function info($message, array $context = [])
    {
        $this->log('info', $message, $context);
    }

    public function warning($message, array $context = [])
    {
        $this->log('warning', $message, $context);
    }

    public function error($message, array $context = [])
    {
        $this->log('error', $message, $context);
    }

    public function log($level, $message, array $context = [])
    {
        if (!in_array($level, self::LEVELS, true)) {
            return;
        }

        $line = '[langsys] ' . strtoupper($level) . ': ' . $message;

        if ($context !== []) {
            $encoded = json_encode($context, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR);
            if ($encoded !== false) {
                $line .= ' ' . $encoded;
            }
        }

        error_log($line);
    }
}
