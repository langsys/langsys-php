<?php

namespace Langsys\SDK\Tests\Contract;

/**
 * MIG-3 against the contract fixture: in the legacy-key mode what the server
 * accepts is the key's source value under the key's namespace - never the key.
 */
class MigrationContractTest extends ContractTestCase
{
    public function testTheServerHoldsTheSourceValueNeverTheKey(): void
    {
        $file = sys_get_temp_dir() . '/langsys-mig-contract-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($file, json_encode(['checkout' => ['submit' => 'Place order']]));

        try {
            $this->seedProject(['k-write' => ['type' => 'write']]);
            $client = $this->client('k-write', null, ['migration' => ['files' => [$file]]]);
            $client->setLocale('es-es');
            $client->translate('checkout.submit');
            $client->translate('Pay now');
            $client->flushPendingRegistrations();

            $this->assertEqualsCanonicalizing([['checkout', 'Place order'], [null, 'Pay now']], $this->registeredPhrases());
        } finally {
            @unlink($file);
        }
    }
}
