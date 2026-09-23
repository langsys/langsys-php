<?php

namespace Langsys\SDK\Tests\Contract;

/**
 * GATE-1 and GATE-8 against the contract fixture: whether a session registers
 * is the server's computed `write_enabled`, read from what the server accepted.
 * Tests connect from 127.0.0.1, so an allow-list either holds that address or
 * does not.
 */
class WriteDecisionContractTest extends ContractTestCase
{
    private function registerOneMiss($key)
    {
        $client = $this->client($key);
        $client->setLocale('es-es');
        $client->translate('New phrase');
        $client->flushPendingRegistrations();

        return $client;
    }

    public function testAWriteKeyRegisters(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']]);

        $this->assertTrue($this->registerOneMiss('k-write')->canWrite());
        $this->assertSame([[null, 'New phrase']], $this->registeredPhrases());
    }

    public function testAReadKeyRegistersNothing(): void
    {
        $this->seedProject(['k-read' => ['type' => 'read']]);

        $this->assertFalse($this->registerOneMiss('k-read')->canWrite());
        $this->assertSame([], $this->registeredPhrases());
    }

    public function testTheSameIpWriteKeyRegistersOnlyFromAnAllowListedAddress(): void
    {
        foreach ([['127.0.0.1'], ['127.0.0.0/8']] as $allowList) {
            $this->seedProject(['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => $allowList]]);
            $this->registerOneMiss('k-ip');
            $this->assertSame([[null, 'New phrase']], $this->registeredPhrases(), 'allow-listed: ' . implode(',', $allowList));
        }

        $this->seedProject(['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => ['10.1.2.3']]]);
        $this->assertFalse($this->registerOneMiss('k-ip')->canWrite());
        $this->assertSame([], $this->registeredPhrases(), 'the same key from an address not on its list');
    }

    public function testAPreCapabilityServerFallsBackToThePlainWriteArmOnly(): void
    {
        $legacy = ['legacy_omit_capability' => true];

        $this->seedProject(['k-write' => ['type' => 'write']], [], $legacy);
        $this->registerOneMiss('k-write');
        $this->assertSame([[null, 'New phrase']], $this->registeredPhrases(), 'a write key may fall back to its key type');

        $this->seedProject(['k-read' => ['type' => 'read']], [], $legacy);
        $this->registerOneMiss('k-read');
        $this->assertSame([], $this->registeredPhrases(), 'a read key does not');

        $this->seedProject(['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => ['127.0.0.1']]], [], $legacy);
        $this->assertFalse($this->registerOneMiss('k-ip')->canWrite());
        $this->assertSame([], $this->registeredPhrases(), 'ip_write never infers a write decision, even allow-listed');
    }

    /**
     * GATE-8's second constraint: the decision is re-read per request, never
     * latched at init. The same ip_write key gets no inferred write from a
     * server that omits the flag; once the server sends it, the next request's
     * decision follows the server.
     */
    public function testTheDecisionFollowsTheServerOnceItStartsSendingTheFlag(): void
    {
        $key = ['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => ['127.0.0.1']]];

        $this->seedProject($key, [], ['legacy_omit_capability' => true]);
        $client = $this->client('k-ip');
        $client->setLocale('es-es');
        $this->assertFalse($client->canWrite(), 'no flag, and ip_write never infers one');

        $this->seedProject($key);
        $client->resetRequestState();
        $client->translate('Next request');
        $client->flushPendingRegistrations();

        $this->assertTrue($client->canWrite());
        $this->assertSame([[null, 'Next request']], $this->registeredPhrases());
    }
}
