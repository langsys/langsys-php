<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the runtime requirement guard.
 *
 * Composer enforces PHP >= 7.4 and ext-intl, but the documented manual
 * autoload.php install bypasses Composer entirely, so those users get no gate at
 * all. The guard warns through the SDK logger and the PHP error log - the latter
 * because a manual install usually has no log path configured.
 *
 * It must NEVER throw. A missing ext-intl degrades to simple placeholder
 * substitution; taking down a working site to prevent degraded plurals would be
 * a far worse trade.
 */
class ClientRequirementsTest extends TestCase
{
    /**
     * @var \ReflectionProperty
     */
    protected $warnedFlag;

    /**
     * @var string|null
     */
    protected $errorLogFile;

    /**
     * @var string|false
     */
    protected $originalErrorLog;

    protected function setUp(): void
    {
        $this->warnedFlag = new \ReflectionProperty(Client::class, 'requirementsWarned');
        $this->warnedFlag->setAccessible(true);
    }

    protected function tearDown(): void
    {
        $this->stopCapturingErrorLog();

        // Restore the suppressed state the bootstrap set up, so later tests
        // are unaffected.
        $this->warnedFlag->setValue(null, true);
    }

    protected function makeClient(array $options = [])
    {
        return new Client('test-api-key', 'project-id', array_merge([
            'cache' => new NullCache(),
        ], $options));
    }

    /**
     * Redirect error_log() into a temp file so it can be asserted on.
     *
     * @return void
     */
    protected function startCapturingErrorLog()
    {
        $this->errorLogFile = tempnam(sys_get_temp_dir(), 'langsys-errlog-');
        $this->originalErrorLog = ini_get('error_log');
        ini_set('error_log', $this->errorLogFile);
    }

    /**
     * @return string
     */
    protected function capturedErrorLog()
    {
        if ($this->errorLogFile === null || !is_file($this->errorLogFile)) {
            return '';
        }

        return (string) file_get_contents($this->errorLogFile);
    }

    /**
     * @return void
     */
    protected function stopCapturingErrorLog()
    {
        if ($this->errorLogFile === null) {
            return;
        }

        ini_set('error_log', $this->originalErrorLog === false ? '' : $this->originalErrorLog);

        if (is_file($this->errorLogFile)) {
            @unlink($this->errorLogFile);
        }

        $this->errorLogFile = null;
    }

    protected function requireMissingIntl()
    {
        if (extension_loaded('intl')) {
            $this->markTestSkipped('This test covers the missing-intl warning');
        }
    }

    public function testMissingIntlIsReportedToTheLogger()
    {
        $this->requireMissingIntl();

        $this->warnedFlag->setValue(null, false);
        $this->startCapturingErrorLog();

        $logger = new SpyLogger();
        $this->makeClient(['logger' => $logger]);

        $warnings = $logger->messagesAt('warning');

        $this->assertNotEmpty($warnings, 'Missing ext-intl must be reported');
        $this->assertStringContainsString('ext-intl', $warnings[0]);
    }

    public function testRequirementWarningReachesThePhpErrorLog()
    {
        $this->requireMissingIntl();

        $this->warnedFlag->setValue(null, false);
        $this->startCapturingErrorLog();

        $this->makeClient();

        $this->assertStringContainsString(
            'ext-intl',
            $this->capturedErrorLog(),
            'The warning must surface even when SDK logging is not configured'
        );
    }

    public function testWarningIsEmittedOnlyOncePerProcess()
    {
        $this->requireMissingIntl();

        $this->warnedFlag->setValue(null, false);
        $this->startCapturingErrorLog();

        $this->makeClient();
        $this->makeClient();
        $this->makeClient();

        $this->assertSame(
            1,
            substr_count($this->capturedErrorLog(), 'ext-intl'),
            'Constructing many Clients must not spam the error log'
        );
    }

    public function testErrorLogWarningCanBeDisabled()
    {
        $this->requireMissingIntl();

        $this->warnedFlag->setValue(null, false);
        $this->startCapturingErrorLog();

        $logger = new SpyLogger();
        $this->makeClient(['logger' => $logger, 'warn_runtime_requirements' => false]);

        $this->assertStringNotContainsString('ext-intl', $this->capturedErrorLog());

        // The logger leg still fires - a host framework that surfaces the SDK
        // logger itself should not lose the diagnosis entirely.
        $this->assertNotEmpty($logger->messagesAt('warning'));
    }

    /**
     * Regression test for a defect reported by the Laravel wrapper.
     *
     * This previously used trigger_error(), which routes through the installed
     * error handler. Laravel's HandleExceptions converts PHP errors into thrown
     * ErrorExceptions, so on any Laravel host without ext-intl, CONSTRUCTING THE
     * CLIENT THREW instead of degrading - inverting the entire intent of the
     * guard and breaking the render it was meant to protect.
     *
     * error_log() cannot be intercepted by an error handler, so this holds for
     * any framework that escalates errors, not just Laravel.
     */
    public function testGuardNeverThrowsUnderAnErrorHandlerThatConvertsErrorsToExceptions()
    {
        $this->warnedFlag->setValue(null, false);
        $this->startCapturingErrorLog();

        // Mirrors Illuminate\Foundation\Bootstrap\HandleExceptions.
        set_error_handler(function ($level, $message, $file = '', $line = 0) {
            throw new \ErrorException($message, 0, $level, $file, $line);
        });

        try {
            $client = $this->makeClient();
            $this->assertInstanceOf(Client::class, $client);
        } finally {
            restore_error_handler();
        }
    }

    /**
     * The guard must never prevent the SDK from working.
     */
    public function testGuardNeverThrows()
    {
        $this->warnedFlag->setValue(null, false);
        $this->startCapturingErrorLog();

        $client = $this->makeClient();

        $this->assertInstanceOf(Client::class, $client);
    }
}
