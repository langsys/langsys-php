<?php

namespace Langsys\SDK\Tests\Log;

use Langsys\SDK\Log\Logger;
use Langsys\SDK\Log\LogViewer;
use PHPUnit\Framework\TestCase;

class LogViewerTest extends TestCase
{
    /**
     * @var string
     */
    protected $logPath;

    /**
     * @var Logger
     */
    protected $logger;

    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/langsys-test-log-' . uniqid() . '.log';
        $this->logger = new Logger($this->logPath);
    }

    protected function tearDown(): void
    {
        // Close logger to flush file
        unset($this->logger);

        if (file_exists($this->logPath)) {
            unlink($this->logPath);
        }
    }

    public function testGetEntriesReturnsEmptyArrayWhenNoLogFile(): void
    {
        $viewer = new LogViewer('/non/existent/path.log');
        $this->assertEquals([], $viewer->getEntries());
    }

    public function testGetEntriesReturnsLogEntries(): void
    {
        $this->logger->info('Test message 1');
        $this->logger->warning('Test message 2');
        unset($this->logger);

        $viewer = new LogViewer($this->logPath);
        $entries = $viewer->getEntries();

        $this->assertCount(2, $entries);
        // Most recent first
        $this->assertEquals('warning', $entries[0]['level']);
        $this->assertEquals('info', $entries[1]['level']);
    }

    public function testGetEntriesFiltersByLevel(): void
    {
        $this->logger->debug('Debug message');
        $this->logger->info('Info message');
        $this->logger->warning('Warning message');
        $this->logger->error('Error message');
        unset($this->logger);

        $viewer = new LogViewer($this->logPath);

        // All levels
        $entries = $viewer->getEntries('debug');
        $this->assertCount(4, $entries);

        // Info and above
        $entries = $viewer->getEntries('info');
        $this->assertCount(3, $entries);

        // Warning and above
        $entries = $viewer->getEntries('warning');
        $this->assertCount(2, $entries);

        // Error only
        $entries = $viewer->getEntries('error');
        $this->assertCount(1, $entries);
    }

    public function testGetEntriesRespectsMaxEntries(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->logger->info("Message $i");
        }
        unset($this->logger);

        $viewer = new LogViewer($this->logPath, 5);
        $entries = $viewer->getEntries();

        $this->assertCount(5, $entries);
    }

    public function testGetStats(): void
    {
        $this->logger->debug('Debug 1');
        $this->logger->debug('Debug 2');
        $this->logger->info('Info 1');
        $this->logger->warning('Warning 1');
        $this->logger->error('Error 1');
        $this->logger->error('Error 2');
        unset($this->logger);

        $viewer = new LogViewer($this->logPath);
        $stats = $viewer->getStats();

        $this->assertEquals(6, $stats['total']);
        $this->assertEquals(2, $stats['debug']);
        $this->assertEquals(1, $stats['info']);
        $this->assertEquals(1, $stats['warning']);
        $this->assertEquals(2, $stats['error']);
    }

    public function testRenderReturnsHtml(): void
    {
        $this->logger->info('Test message', ['key' => 'value']);
        unset($this->logger);

        $viewer = new LogViewer($this->logPath);
        $html = $viewer->render();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Langsys SDK Logs', $html);
        $this->assertStringContainsString('Test message', $html);
        $this->assertStringContainsString('tailwindcss', $html);
        $this->assertStringContainsString('flowbite', $html);
    }

    public function testRenderShowsContext(): void
    {
        $this->logger->info('Message with context', ['url' => 'https://example.com', 'status' => 200]);
        unset($this->logger);

        $viewer = new LogViewer($this->logPath);
        $html = $viewer->render();

        $this->assertStringContainsString('https://example.com', $html);
        $this->assertStringContainsString('200', $html);
    }

    public function testRenderShowsEmptyState(): void
    {
        $viewer = new LogViewer($this->logPath);
        $html = $viewer->render();

        $this->assertStringContainsString('No log entries found', $html);
    }

    public function testClearRemovesLogContents(): void
    {
        $this->logger->info('Test message');
        unset($this->logger);

        $viewer = new LogViewer($this->logPath);
        $this->assertCount(1, $viewer->getEntries());

        $viewer->clear();
        $this->assertCount(0, $viewer->getEntries());
    }

    public function testGetFileSize(): void
    {
        $this->logger->info('Test message');
        unset($this->logger);

        $viewer = new LogViewer($this->logPath);
        $size = $viewer->getFileSize();

        $this->assertGreaterThan(0, $size);
    }

    public function testGetFormattedFileSize(): void
    {
        $this->logger->info('Test message');
        unset($this->logger);

        $viewer = new LogViewer($this->logPath);
        $formatted = $viewer->getFormattedFileSize();

        // Should be in bytes for small log
        $this->assertMatchesRegularExpression('/\d+ B/', $formatted);
    }

    public function testLevelFilterFromQueryString(): void
    {
        $this->logger->debug('Debug');
        $this->logger->info('Info');
        $this->logger->warning('Warning');
        unset($this->logger);

        $viewer = new LogViewer($this->logPath);

        // Render with warning filter
        $html = $viewer->render('warning');

        // Should show warning filter as active
        $this->assertStringContainsString('Warning+', $html);
    }

    public function testRenderJsonReturnsValidJson(): void
    {
        $this->logger->info('Test message', ['key' => 'value']);
        unset($this->logger);

        $viewer = new LogViewer($this->logPath);
        $json = $viewer->renderJson();

        $data = json_decode($json, true);
        $this->assertNotNull($data);
        $this->assertArrayHasKey('entries', $data);
        $this->assertArrayHasKey('stats', $data);
        $this->assertArrayHasKey('file_size', $data);
    }

    public function testRenderJsonRespectsLevelFilter(): void
    {
        $this->logger->debug('Debug');
        $this->logger->info('Info');
        $this->logger->warning('Warning');
        unset($this->logger);

        $viewer = new LogViewer($this->logPath);
        $data = json_decode($viewer->renderJson('warning'), true);

        // Only warning level entries
        $this->assertCount(1, $data['entries']);
        $this->assertEquals('warning', $data['entries'][0]['level']);
    }

    public function testRenderHasInteractiveFeatures(): void
    {
        $viewer = new LogViewer($this->logPath);
        $html = $viewer->render();

        // Realtime toggle
        $this->assertStringContainsString('realtime-toggle', $html);
        $this->assertStringContainsString('toggleRealtime()', $html);

        // Dynamic filter buttons
        $this->assertStringContainsString('setFilter(', $html);
        $this->assertStringContainsString('filter-btn', $html);

        // localStorage persistence
        $this->assertStringContainsString('localStorage', $html);

        // Clear button
        $this->assertStringContainsString('clearLogs()', $html);

        // Hide messages feature
        $this->assertStringContainsString('hideMessage(', $html);
        $this->assertStringContainsString('unhideMessage(', $html);
        $this->assertStringContainsString('hidden-panel', $html);
        $this->assertStringContainsString('STORAGE_KEY_HIDDEN', $html);
    }
}
