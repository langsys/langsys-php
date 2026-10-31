<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Exception\ValidationException;

/**
 * MIG-9 against the contract fixture: an import registers each key's phrase
 * with its existing translations, which the server stores as human ones,
 * asserted on what the server accepted and serves back.
 */
class ImportContractTest extends ContractTestCase
{
    protected static $seed = 'import';

    /** @var string|null */
    private $dir;

    protected function tearDown(): void
    {
        if ($this->dir === null) {
            return;
        }
        foreach (glob($this->dir . '/*/*') ?: [] as $file) {
            unlink($file);
        }
        foreach (glob($this->dir . '/*') ?: [] as $sub) {
            rmdir($sub);
        }
        rmdir($this->dir);
    }

    private function file($name, array $values)
    {
        if ($this->dir === null) {
            $this->dir = sys_get_temp_dir() . '/langsys-import-contract-' . bin2hex(random_bytes(4));
        }
        $path = $this->dir . '/' . $name;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, '<?php return ' . var_export($values, true) . ';');

        return $path;
    }

    private function importing(World $world)
    {
        return $this->client($world->key('write'), $world->project(), null, ['migration' => ['files' => [$this->file('en/checkout.php', ['submit' => 'Place order', 'cancel' => 'Cancel order'])]]]);
    }

    /**
     * The spec's test: a key with an existing translation registers the
     * phrase, the translation is stored on it, and a later catalog read
     * serves it.
     */
    public function testAKeysTranslationIsStoredWithItsPhrase(): void
    {
        $world = $this->world('importing');
        $client = $this->importing($world);

        $result = $client->importLegacyTranslations(['es-es' => ['files' => [$this->file('es/checkout.php', ['submit' => 'Realizar pedido'])]]]);

        $this->assertTrue($result['success']);
        $this->assertSame([1, 0], [$result['human_translations_saved'], $result['human_translations_skipped']]);

        $this->assertSame(['checkout' => ['Cancel order' => null, 'Place order' => 'Realizar pedido']], $this->sorted($this->catalog($world->key('write'), $world->project(), 'es-es')), 'a key with no translation registers alone');

        $next = $this->client($world->key('write'), $world->project());
        $this->assertSame('Realizar pedido', $next->translations()->getTranslationMap('es-es')['checkout']['Place order']);
    }

    private function sorted(array $catalog)
    {
        foreach ($catalog as $category => $entries) {
            ksort($entries, SORT_STRING);
            $catalog[$category] = $entries;
        }

        return $catalog;
    }

    /**
     * A locale the project does not target - the base locale included - is
     * refused before anything is sent, and the API refuses one that is sent
     * anyway before it writes anything, naming the entry.
     */
    public function testANonTargetLocaleIsRefusedBeforeAnythingIsWritten(): void
    {
        $world = $this->world('importing');
        $client = $this->importing($world);

        foreach (['it-it', 'en-us'] as $locale) {
            try {
                $client->importLegacyTranslations([$locale => ['files' => [$this->file($locale . '/checkout.php', ['submit' => 'x'])]]]);
                $this->fail($locale . ' is refused');
            } catch (LangsysException $e) {
                $this->assertStringContainsString($locale, $e->getMessage());
            }
        }
        $this->assertSame([], $this->held($world->key('write'), $world->project(), 'en-us')['phrases'], 'nothing was sent');

        try {
            $client->translations();
            $items = (new \ReflectionClass($client))->getProperty('translatableItems');
            $items->setAccessible(true);
            $items->getValue($client)->importPhrases([['phrase' => 'Sent anyway', 'category' => null, 'translations' => ['it-it' => 'Inviato']]]);
            $this->fail('the API refuses a non-target locale');
        } catch (ValidationException $e) {
            $this->assertSame(
                [['translatable_items.0.translations.0.locale', 'invalid_option']],
                array_map(function ($entry) {
                    return [$entry['field'], $entry['code']];
                }, $e->getErrors())
            );
        }
        $this->assertSame([], $this->held($world->key('write'), $world->project(), 'en-us')['phrases'], 'the API wrote nothing');
    }

    /**
     * Past the plan's human-translated words the rest are left for machine
     * translation, and the result says how many.
     */
    public function testTheQuotaSplitIsReported(): void
    {
        $world = $this->world('capped');
        $client = $this->importing($world);

        $result = $client->importLegacyTranslations(['es-es' => ['files' => [$this->file('es/checkout.php', ['submit' => 'Realizar pedido', 'cancel' => 'Cancelar pedido'])]]]);

        $this->assertTrue($result['success']);
        $this->assertSame([1, 1], [$result['human_translations_saved'], $result['human_translations_skipped']]);
        $served = $this->catalog($world->key('write'), $world->project(), 'es-es')['checkout'];
        $this->assertSame(1, count(array_filter($served, 'is_string')), 'one translation is stored, the other left for machine translation');
    }

    /**
     * A key that may not write imports nothing and says so.
     */
    public function testAReadKeyImportsNothing(): void
    {
        $world = $this->world('reader');
        $client = $this->client($world->key('read'), $world->project(), null, ['migration' => ['files' => [$this->file('en/checkout.php', ['submit' => 'Place order'])]]]);

        $result = $client->importLegacyTranslations(['es-es' => ['files' => [$this->file('es/checkout.php', ['submit' => 'Realizar pedido'])]]]);

        $this->assertSame([false, 'not_write_enabled'], [$result['success'], $result['reason']]);
        $this->assertSame([], $this->held($world->key('read'), $world->project(), 'en-us')['phrases']);
    }
}
