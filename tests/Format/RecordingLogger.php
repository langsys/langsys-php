<?php

namespace Langsys\SDK\Tests\Format;

use Langsys\SDK\Log\LoggerInterface;

/**
 * Captures log calls so a test can assert that a behaviour is observable, not
 * merely correct.
 */
class RecordingLogger implements LoggerInterface
{
    /** @var array list of [level, message, context] */
    public $records = [];

    public function log($level, $message, array $context = [])
    {
        $this->records[] = [$level, $message, $context];
    }

    public function debug($message, array $context = []) { $this->log('debug', $message, $context); }
    public function info($message, array $context = []) { $this->log('info', $message, $context); }
    public function warning($message, array $context = []) { $this->log('warning', $message, $context); }
    public function error($message, array $context = []) { $this->log('error', $message, $context); }
}
