<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Tests\Support\SpyLogger;

/**
 * REG-8 and OBS-1 against the contract fixture: a failed send stays queued
 * and backs off, read from what the API holds; and a key whose type writes,
 * answered not write-enabled by the API, is reported once.
 */
class LifecycleContractTest extends ContractTestCase
{
    protected static $seed = 'lifecycle';

    /** @var SpyLogger */
    private $logger;

    private function clocked(World $world, $role)
    {
        $this->logger = new SpyLogger();
        $client = new class ($world->key($role), $world->project(), ['api_url' => self::$baseUrl, 'cache' => new NullCache(), 'logger' => $this->logger, 'error_log' => false]) extends Client {
            public $now = 1000.0;

            protected function currentTime()
            {
                return $this->now;
            }
        };
        $client->setLocale('es-es');

        return $client;
    }

    private function phrases(World $world, $role)
    {
        return $this->held($world->key($role), $world->project(), 'en-us')['phrases'];
    }

    /**
     * REG-8: a refused send keeps its queue and waits 3s, doubling on the
     * next failure. A flush inside the wait sends nothing - shown by the
     * faults having run out, so a send would now be accepted - and reports
     * the items as retained; after the wait the same queue registers.
     */
    public function testAFailedSendStaysQueuedAndBacksOff(): void
    {
        $world = $this->world('writer', ['faults' => [['method' => 'POST', 'path' => '/translatable-items', 'status' => 500, 'times' => 2]]]);
        $client = $this->clocked($world, 'write');
        $client->translate('Kept');

        $this->assertSame('send_failed', $client->flushPendingRegistrations()['reason']);
        $client->now += 3;
        $this->assertSame('send_failed', $client->flushPendingRegistrations()['reason'], 'the second send fails too');

        $client->now += 5.5;
        $inside = $client->flushPendingRegistrations();
        $this->assertSame(['backing_off', 1], [$inside['reason'], $inside['retained']], 'inside the doubled 6s wait');
        $this->assertSame([], $this->phrases($world, 'write'), 'nothing was sent inside the wait');

        $client->now += 0.5;
        $this->assertTrue($client->flushPendingRegistrations()['success']);
        $this->assertSame(['__uncategorized__' => ['Kept']], $this->phrases($world, 'write'), 'the retained queue registered');
    }

    /**
     * The first success resets the wait: a later failure waits 3s again.
     */
    public function testTheFirstSuccessResetsTheWait(): void
    {
        $world = $this->world('writer', ['faults' => [['method' => 'POST', 'path' => '/translatable-items', 'status' => 500]]]);
        $client = $this->clocked($world, 'write');

        $client->translate('First');
        $client->flushPendingRegistrations();
        $client->now += 3;
        $this->assertTrue($client->flushPendingRegistrations()['success']);

        $this->update($world, ['faults' => [['method' => 'POST', 'path' => '/translatable-items', 'status' => 500]]]);
        $client->translate('Second');
        $client->flushPendingRegistrations();
        $client->now += 3;
        $this->assertTrue($client->flushPendingRegistrations()['success'], 'the wait is 3s again, not 6s');
        $this->assertSame(['__uncategorized__' => ['First', 'Second']], $this->phrases($world, 'write'));
    }

    private function capabilityWarnings()
    {
        return array_values(array_filter($this->logger->entries, function ($entry) {
            return $entry['level'] === 'warning' && strpos($entry['message'], 'cannot register new text') !== false;
        }));
    }

    private function missAndFlushAcrossRequests(Client $client)
    {
        foreach (['One', 'Two', 'Three'] as $miss) {
            $client->translate($miss);
            $client->flushPendingRegistrations();
        }
        $client->resetRequestState();
        $client->setLocale('es-es');
        $client->authorize(true);
        $client->translate('Four');
        $client->flushPendingRegistrations();
    }

    /**
     * OBS-1: an ip_write key the API answers not write-enabled - off its
     * allow-list - is reported once, naming the key type, across misses,
     * flushes, a new request and a forced authorization.
     */
    public function testAKeyWhoseTypeWritesButMayNotIsReportedOnce(): void
    {
        $world = $this->world('off-list');

        $client = $this->clocked($world, 'ip');
        $this->missAndFlushAcrossRequests($client);

        $warnings = $this->capabilityWarnings();
        $this->assertCount(1, $warnings);
        $this->assertSame('ip_write', $warnings[0]['context']['key_type']);
        $this->assertSame([], $this->phrases($world, 'ip'));

        $this->update($world, ['caller' => $world->moved]);
        $writer = $this->clocked($world, 'ip');
        $this->missAndFlushAcrossRequests($writer);
        $this->assertSame([], $this->capabilityWarnings(), 'control: the same key on its list writes, and reports nothing');
        $this->assertNotSame([], $this->phrases($world, 'ip'));
    }

    public function testAReadKeyIsNotReported(): void
    {
        $world = $this->world('reader');

        $this->missAndFlushAcrossRequests($this->clocked($world, 'read'));

        $this->assertSame([], $this->capabilityWarnings());
    }
}
