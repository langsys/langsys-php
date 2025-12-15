<?php

namespace Langsys\SDK\Tests\Log;

use Langsys\SDK\Log\Logger;
use Langsys\SDK\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class LoggerTest extends TestCase
{
    /**
     * @var string
     */
    protected $logPath;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/langsys-test-log-' . uniqid() . '.log';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function testImplementsLoggerInterface(): void
    {
        $logger = new Logger($this->logPath);
        $this->assertInstanceOf(LoggerInterface::class, $logger);
    }

    public function testWritesJsonLines(): void
    {
        $logger = new Logger($this->logPath);
        $logger->info('Test message', ['key' => 'value']);
        unset($logger); // Force flush

        $content = file_get_contents($this->logPath);
        $lines = array_filter(explode("\n", $content));
        $this->assertCount(1, $lines);

        $entry = json_decode($lines[0], true);
        $this->assertEquals('info', $entry['level']);
        $this->assertEquals('Test message', $entry['message']);
        $this->assertEquals(['key' => 'value'], $entry['context']);
        $this->assertArrayHasKey('timestamp', $entry);
    }

    public function testLogLevelFiltering(): void
    {
        $logger = new Logger($this->logPath, 'warning');
        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->warning('Warning message');
        $logger->error('Error message');
        unset($logger);

        $content = file_get_contents($this->logPath);
        $lines = array_filter(explode("\n", $content));
        $this->assertCount(2, $lines); // Only warning and error

        $levels = [];
        foreach ($lines as $line) {
            $entry = json_decode($line, true);
            $levels[] = $entry['level'];
        }
        $this->assertContains('warning', $levels);
        $this->assertContains('error', $levels);
        $this->assertNotContains('debug', $levels);
        $this->assertNotContains('info', $levels);
    }

    public function testCreatesDirectoryIfNeeded(): void
    {
        $nestedPath = sys_get_temp_dir() . '/langsys-nested-' . uniqid() . '/subdir/test.log';
        $logger = new Logger($nestedPath);
        $logger->info('Test');
        unset($logger);

        $this->assertFileExists($nestedPath);

        // Cleanup
        unlink($nestedPath);
        rmdir(dirname($nestedPath));
        rmdir(dirname(dirname($nestedPath)));
    }

    public function testTimestampFormat(): void
    {
        $logger = new Logger($this->logPath);
        $logger->info('Test');
        unset($logger);

        $content = file_get_contents($this->logPath);
        $entry = json_decode(trim($content), true);

        // Verify ISO 8601 format with microseconds
        $this->assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}Z$/',
            $entry['timestamp']
        );
    }

    public function testAllLogLevels(): void
    {
        $logger = new Logger($this->logPath);
        $logger->debug('Debug');
        $logger->info('Info');
        $logger->warning('Warning');
        $logger->error('Error');
        unset($logger);

        $content = file_get_contents($this->logPath);
        $lines = array_filter(explode("\n", $content));
        $this->assertCount(4, $lines);
    }

    public function testLogMethod(): void
    {
        $logger = new Logger($this->logPath);
        $logger->log('info', 'Custom level message', ['custom' => true]);
        unset($logger);

        $content = file_get_contents($this->logPath);
        $entry = json_decode(trim($content), true);

        $this->assertEquals('info', $entry['level']);
        $this->assertEquals('Custom level message', $entry['message']);
        $this->assertEquals(['custom' => true], $entry['context']);
    }

    public function testEmptyContext(): void
    {
        $logger = new Logger($this->logPath);
        $logger->info('No context');
        unset($logger);

        $content = file_get_contents($this->logPath);
        $entry = json_decode(trim($content), true);

        $this->assertArrayNotHasKey('context', $entry);
    }

    public function testGetters(): void
    {
        $logger = new Logger($this->logPath, 'warning');

        $this->assertEquals($this->logPath, $logger->getLogPath());
        $this->assertEquals('warning', $logger->getMinLevel());
    }

    public function testInfoLevelFiltering(): void
    {
        $logger = new Logger($this->logPath, 'info');
        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->warning('Warning message');
        unset($logger);

        $content = file_get_contents($this->logPath);
        $lines = array_filter(explode("\n", $content));
        $this->assertCount(2, $lines); // info and warning, not debug
    }

    public function testErrorLevelFiltering(): void
    {
        $logger = new Logger($this->logPath, 'error');
        $logger->debug('Debug message');
        $logger->info('Info message');
        $logger->warning('Warning message');
        $logger->error('Error message');
        unset($logger);

        $content = file_get_contents($this->logPath);
        $lines = array_filter(explode("\n", $content));
        $this->assertCount(1, $lines); // Only error
    }
}
