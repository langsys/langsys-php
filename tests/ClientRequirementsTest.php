<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Tests\Format\SpyLogger;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the runtime requirement guard.
 *
 * Composer enforces PHP >= 7.4 and ext-intl, but the documented manual
 * autoload.php install bypasses Composer entirely, so those users get no gate
 * at all. The guard warns (never throws) through both the SDK logger and
 * trigger_error, the latter because a manual install usually has no log path
 * configured.
 */
class ClientRequirementsTest extends TestCase
{
    /**
     * @var \ReflectionProperty
     */
    protected $warnedFlag;

    protected function setUp(): void
    {
        $this->warnedFlag = new \ReflectionProperty(Client::class, 'requirementsWarned');
        $this->warnedFlag->setAccessible(true);
    }

    protected function tearDown(): void
    {
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

    public function testMissingIntlIsReportedToTheLogger()
    {
        if (extension_loaded('intl')) {
            $this->markTestSkipped('This test covers the missing-intl warning');
        }

        $this->warnedFlag->setValue(null, false);

        $logger = new SpyLogger();

        // trigger_error would otherwise be converted into a test error.
        $previous = set_error_handler(function () {
            return true;
        }, E_USER_WARNING);

        try {
            $this->makeClient(['logger' => $logger]);
        } finally {
            set_error_handler($previous);
        }

        $warnings = $logger->messagesAt('warning');

        $this->assertNotEmpty($warnings, 'Missing ext-intl must be reported');
        $this->assertStringContainsString('ext-intl', $warnings[0]);
    }

    public function testRequirementWarningAlsoRaisesAPhpWarning()
    {
        if (extension_loaded('intl')) {
            $this->markTestSkipped('This test covers the missing-intl warning');
        }

        $this->warnedFlag->setValue(null, false);

        $raised = [];
        $previous = set_error_handler(function ($errno, $errstr) use (&$raised) {
            $raised[] = $errstr;
            return true;
        }, E_USER_WARNING);

        try {
            $this->makeClient();
        } finally {
            set_error_handler($previous);
        }

        $this->assertNotEmpty($raised, 'A PHP warning must surface even without SDK logging configured');
        $this->assertStringContainsString('ext-intl', $raised[0]);
    }

    public function testWarningIsEmittedOnlyOncePerProcess()
    {
        if (extension_loaded('intl')) {
            $this->markTestSkipped('This test covers the missing-intl warning');
        }

        $this->warnedFlag->setValue(null, false);

        $raised = [];
        $previous = set_error_handler(function ($errno, $errstr) use (&$raised) {
            $raised[] = $errstr;
            return true;
        }, E_USER_WARNING);

        try {
            $this->makeClient();
            $this->makeClient();
            $this->makeClient();
        } finally {
            set_error_handler($previous);
        }

        $this->assertCount(1, $raised, 'Constructing many Clients must not spam the error log');
    }

    /**
     * The guard must never prevent the SDK from working - a missing extension
     * degrades gracefully rather than breaking construction.
     */
    public function testGuardNeverThrows()
    {
        $this->warnedFlag->setValue(null, false);

        $previous = set_error_handler(function () {
            return true;
        }, E_USER_WARNING);

        try {
            $client = $this->makeClient();
            $this->assertInstanceOf(Client::class, $client);
        } finally {
            set_error_handler($previous);
        }
    }
}
