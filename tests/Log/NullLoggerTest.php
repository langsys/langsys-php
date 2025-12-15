<?php

namespace Langsys\SDK\Tests\Log;

use Langsys\SDK\Log\NullLogger;
use Langsys\SDK\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class NullLoggerTest extends TestCase
{
    public function testImplementsLoggerInterface(): void
    {
        $logger = new NullLogger();
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testAllMethodsAreNoOp(): void
    {
        $logger = new NullLogger();

        // These should all complete without error
        $logger->debug('Test');
        $logger->info('Test', ['key' => 'value']);
        $logger->warning('Test');
        $logger->error('Test');
        $logger->log('custom', 'Test');

        $this->assertTrue(true); // If we get here, all methods worked
    }

    public function testCanBeUsedAsLoggerInterface(): void
    {
        $logger = new NullLogger();

        // Ensure it can be type-hinted as LoggerInterface
        $this->assertInstanceOf(LoggerInterface::class, $logger);

        // Test all interface methods
        $logger->debug('debug message');
        $logger->info('info message');
        $logger->warning('warning message');
        $logger->error('error message');
        $logger->log('info', 'generic message');

        // No exceptions means success
        $this->assertTrue(true);
    }
}
