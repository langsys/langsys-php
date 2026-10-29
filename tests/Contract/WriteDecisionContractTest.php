<?php

namespace Langsys\SDK\Tests\Contract;

/**
 * GATE-1 against the contract fixture: whether a session registers is the
 * API's computed `write_enabled`, read from what the API then holds. Each case
 * calls from an address of its own, so an allow-list either holds that address
 * or does not.
 */
class WriteDecisionContractTest extends ContractTestCase
{
    protected static $seed = 'write-decision';

    private function registerOneMiss(World $world, $role)
    {
        $client = $this->client($world->key($role), $world->project());
        $client->setLocale('es-es');
        $client->translate('New phrase');
        $client->flushPendingRegistrations();

        return $client;
    }

    private function registered(World $world, $role)
    {
        return self::anyPhrase($this->held($world->key($role), $world->project(), 'en-us'));
    }

    public function testAWriteKeyRegisters(): void
    {
        $world = $this->world('writer');

        $this->assertTrue($this->registerOneMiss($world, 'write')->canWrite());
        $this->assertSame(['New phrase'], $this->registered($world, 'write'));
    }

    /**
     * An absence is evidence only where the API would accept the write
     * (CONF-2). The session learns it may not write; the key then becomes a
     * write key; this request still holds back, and a session that learns the
     * new answer registers - the control in the drifted world.
     */
    public function testAReadKeyHoldsBackAfterTheServerWouldAcceptItsWrite(): void
    {
        $world = $this->world('reader');
        $client = $this->client($world->key('read'), $world->project());
        $client->setLocale('es-es');
        $this->assertFalse($client->canWrite());

        $this->editKey($world, 'read', ['type' => 'write']);
        $client->translate('Held back');
        $client->flushPendingRegistrations();
        $this->assertSame([], $this->registered($world, 'read'), 'the request that learned it may not write holds back');

        $this->registerOneMiss($world, 'read');
        $this->assertSame(['New phrase'], $this->registered($world, 'read'), 'control: in the drifted world a session that learns it may write does');
    }

    public function testTheSameIpWriteKeyRegistersOnlyFromAnAllowListedAddress(): void
    {
        foreach (['listed-exact' => 'its own address', 'listed-range' => 'a range holding its address'] as $name => $listed) {
            $world = $this->world($name);
            $this->registerOneMiss($world, 'ip');
            $this->assertSame(['New phrase'], $this->registered($world, 'ip'), "allow-listed by $listed");
        }
    }

    /**
     * The same key off its allow-list holds back - shown where the API would
     * accept the write, by moving the case to an address the list holds after
     * the session learned its answer.
     */
    public function testTheSameIpWriteKeyOffItsListHoldsBackAfterTheListWidens(): void
    {
        $world = $this->world('off-list');
        $client = $this->client($world->key('ip'), $world->project());
        $client->setLocale('es-es');
        $this->assertFalse($client->canWrite());

        $this->update($world, ['caller' => $world->moved]);
        $client->translate('Held back');
        $client->flushPendingRegistrations();
        $this->assertSame([], $this->registered($world, 'ip'), 'the request that learned it is off the list holds back');

        $this->registerOneMiss($world, 'ip');
        $this->assertSame(['New phrase'], $this->registered($world, 'ip'), 'control: from an address on the list the same key registers');
    }
}
