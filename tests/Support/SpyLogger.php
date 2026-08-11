<?php

namespace Langsys\SDK\Tests\Support;

use Langsys\SDK\Log\LoggerInterface;

/**
 * Records log calls so tests can assert on them.
 *
 * Lives in its own PSR-4 file so any test file can be run individually - it was
 * previously declared inside InterpolatorTest.php, which made
 * ClientRequirementsTest unrunnable on its own.
 */
class SpyLogger implements LoggerInterface
{
    public $entries = [];

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
        $this->entries[] = ['level' => $level, 'message' => $message, 'context' => $context];
    }

    /**
     * @param string $level
     * @return array
     */
    public function messagesAt($level)
    {
        $out = [];

        foreach ($this->entries as $entry) {
            if ($entry['level'] === $level) {
                $out[] = $entry['message'];
            }
        }

        return $out;
    }
}
