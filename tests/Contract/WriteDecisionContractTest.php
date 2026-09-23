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

    /**
     * An absence is evidence only where the double would accept the write
     * (CONF-2). The session learns it may not write; the server then starts
     * allowing the key; this request still holds back, and a session that learns
     * the new answer registers - the control in the drifted world.
     */
    public function testAReadKeyHoldsBackAfterTheServerWouldAcceptItsWrite(): void
    {
        $this->seedProject(['k-drift' => ['type' => 'read']]);
        $client = $this->client('k-drift');
        $client->setLocale('es-es');
        $this->assertFalse($client->canWrite());

        $this->seedProject(['k-drift' => ['type' => 'write']]);
        $client->translate('Held back');
        $client->flushPendingRegistrations();
        $this->assertSame([], $this->registeredPhrases(), 'the request that learned it may not write holds back');

        $this->registerOneMiss('k-drift');
        $this->assertSame([[null, 'New phrase']], $this->registeredPhrases(), 'control: in the drifted world a session that learns it may write does');
    }

    public function testTheSameIpWriteKeyRegistersOnlyFromAnAllowListedAddress(): void
    {
        foreach ([['127.0.0.1'], ['127.0.0.0/8']] as $allowList) {
            $this->seedProject(['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => $allowList]]);
            $this->registerOneMiss('k-ip');
            $this->assertSame([[null, 'New phrase']], $this->registeredPhrases(), 'allow-listed: ' . implode(',', $allowList));
        }

    }

    /**
     * The same key off its allow-list holds back - shown where the double would
     * accept the write, by widening the list after the session learned its answer.
     */
    public function testTheSameIpWriteKeyOffItsListHoldsBackAfterTheListWidens(): void
    {
        $this->seedProject(['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => ['10.1.2.3']]]);
        $client = $this->client('k-ip');
        $client->setLocale('es-es');
        $this->assertFalse($client->canWrite());

        $this->seedProject(['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => ['10.1.2.3', '127.0.0.1']]]);
        $client->translate('Held back');
        $client->flushPendingRegistrations();
        $this->assertSame([], $this->registeredPhrases(), 'the request that learned it is off the list holds back');

        $this->registerOneMiss('k-ip');
        $this->assertSame([[null, 'New phrase']], $this->registeredPhrases(), 'control: in the drifted world the same key registers');
    }

    public function testAPreCapabilityServerFallsBackToThePlainWriteArmOnly(): void
    {
        $legacy = ['legacy_omit_capability' => true];

        $this->seedProject(['k-write' => ['type' => 'write']], [], $legacy);
        $this->registerOneMiss('k-write');
        $this->assertSame([[null, 'New phrase']], $this->registeredPhrases(), 'a write key may fall back to its key type');

        // A read key does not fall back to a write - held back where the double
        // would accept it: the key becomes a write key after the session learned.
        $this->seedProject(['k-read' => ['type' => 'read']], [], $legacy);
        $held = $this->client('k-read');
        $held->setLocale('es-es');
        $this->assertFalse($held->canWrite());
        $this->seedProject(['k-read' => ['type' => 'write']], [], $legacy);
        $held->translate('Held back');
        $held->flushPendingRegistrations();
        $this->assertSame([], $this->registeredPhrases(), 'a read key does not');
        $this->registerOneMiss('k-read');
        $this->assertSame([[null, 'New phrase']], $this->registeredPhrases(), 'control: in the drifted world it registers');

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
