<?php

namespace Langsys\SDK\Tests\Contract;

/**
 * MIG-3 against the contract fixture: in the legacy-key mode what the server
 * accepts is the key's source value under the key's namespace - never the key.
 */
class MigrationContractTest extends ContractTestCase
{
    protected static $seed = 'migration';

    public function testTheServerHoldsTheSourceValueNeverTheKey(): void
    {
        $file = sys_get_temp_dir() . '/langsys-mig-contract-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($file, json_encode(['checkout' => ['submit' => 'Place order']]));

        try {
            $world = $this->world('writer');
            $client = $this->client($world->key('write'), $world->project(), null, ['migration' => ['files' => [$file]]]);
            $client->setLocale('es-es');
            $client->translate('checkout.submit');
            $client->translate('Pay now');
            $client->flushPendingRegistrations();

            $this->assertSame(['__uncategorized__' => ['Pay now'], 'checkout' => ['Place order']], $this->held($world->key('write'), $world->project(), 'en-us')['phrases']);
        } finally {
            @unlink($file);
        }
    }
}
