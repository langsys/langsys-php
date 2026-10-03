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

    private function storedPhrases()
    {
        $state = $this->fixture('GET', '/state');

        return isset($state['projects'][self::PROJECT]['phrases']) ? $state['projects'][self::PROJECT]['phrases'] : [];
    }

    private function importing(array $project = [])
    {
        $this->seedProject(['k-write' => ['type' => 'write']], $project + ['target_locales' => ['es-es', 'fr-fr']]);

        return $this->client('k-write', null, ['migration' => ['files' => [$this->file('en/checkout.php', ['submit' => 'Place order', 'cancel' => 'Cancel order'])]]]);
    }

    /**
     * The spec's test: a key with an existing translation registers the
     * phrase, the translation is stored on it, and a later catalog read
     * serves it.
     */
    public function testAKeysTranslationIsStoredWithItsPhrase(): void
    {
        $client = $this->importing();

        $result = $client->importLegacyTranslations(['es-es' => ['files' => [$this->file('es/checkout.php', ['submit' => 'Realizar pedido'])]]]);

        $this->assertTrue($result['success']);
        $this->assertSame([1, 0], [$result['human_translations_saved'], $result['human_translations_skipped']]);

        $stored = [];
        foreach ($this->storedPhrases() as $phrase) {
            $stored[$phrase['phrase']] = [$phrase['category'], $phrase['translations']];
        }
        $this->assertSame(['checkout', ['es-es' => 'Realizar pedido']], $stored['Place order']);
        $this->assertSame(['checkout', []], $stored['Cancel order'], 'a key with no translation registers alone');

        $next = $this->client('k-write');
        $this->assertSame('Realizar pedido', $next->translations()->getTranslationMap('es-es')['checkout']['Place order']);
    }

    /**
     * A locale the project does not target - the base locale included - is
     * refused before anything is sent, and the server refuses one that is
     * sent anyway before it writes anything.
     */
    public function testANonTargetLocaleIsRefusedBeforeAnythingIsWritten(): void
    {
        $client = $this->importing();

        foreach (['it-it', 'en-us'] as $locale) {
            try {
                $client->importLegacyTranslations([$locale => ['files' => [$this->file($locale . '/checkout.php', ['submit' => 'x'])]]]);
                $this->fail($locale . ' is refused');
            } catch (LangsysException $e) {
                $this->assertStringContainsString($locale, $e->getMessage());
            }
        }
        $this->assertSame([], $this->storedPhrases(), 'nothing was sent');

        try {
            $client->translations();
            $items = (new \ReflectionClass($client))->getProperty('translatableItems');
            $items->setAccessible(true);
            $items->getValue($client)->importPhrases([['phrase' => 'Sent anyway', 'category' => null, 'translations' => ['it-it' => 'Inviato']]]);
            $this->fail('the server refuses a non-target locale');
        } catch (ValidationException $e) {
            $this->assertSame(
                [['translatable_items.0.translations', 'invalid_option']],
                array_map(function ($entry) {
                    return [$entry['field'], $entry['code']];
                }, $e->getErrors())
            );
        }
        $this->assertSame([], $this->storedPhrases(), 'the server wrote nothing');
    }

    /**
     * Past the plan's human-translated words the rest are left for machine
     * translation, and the result says how many.
     */
    public function testTheQuotaSplitIsReported(): void
    {
        $client = $this->importing(['human_translation_word_limit' => 2]);

        $result = $client->importLegacyTranslations(['es-es' => ['files' => [$this->file('es/checkout.php', ['submit' => 'Realizar pedido', 'cancel' => 'Cancelar pedido'])]]]);

        $this->assertTrue($result['success']);
        $this->assertSame([1, 1], [$result['human_translations_saved'], $result['human_translations_skipped']]);
        $this->assertSame(2, $this->fixture('GET', '/state')['projects'][self::PROJECT]['human_translation_words_used']);
    }

    /**
     * A key that may not write imports nothing and says so.
     */
    public function testAReadKeyImportsNothing(): void
    {
        $this->seedProject(['k-read' => ['type' => 'read']], ['target_locales' => ['es-es']]);
        $client = $this->client('k-read', null, ['migration' => ['files' => [$this->file('en/checkout.php', ['submit' => 'Place order'])]]]);

        $result = $client->importLegacyTranslations(['es-es' => ['files' => [$this->file('es/checkout.php', ['submit' => 'Realizar pedido'])]]]);

        $this->assertSame([false, 'not_write_enabled'], [$result['success'], $result['reason']]);
        $this->assertSame([], $this->storedPhrases());
    }
}
