<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Snapshot\Snapshot;

/**
 * SNAP-1 against the contract fixture: the export is a client-side filter of
 * `GET /translations/data` by category, carrying exactly what the API returns
 * for the chosen categories and nothing else.
 */
class SnapshotContractTest extends ContractTestCase
{
    private function seedCatalog()
    {
        $this->seedProject(['k-read' => ['type' => 'read']], [
            'target_locales' => ['es-es', 'fr-fr'],
            'phrases' => [
                ['category' => 'UI', 'phrase' => 'Save', 'translations' => ['es-es' => 'Guardar', 'fr-fr' => 'Enregistrer']],
                ['category' => 'Errors', 'phrase' => 'Oops', 'translations' => ['es-es' => 'Uy']],
                ['category' => 'Billing', 'phrase' => 'Pay', 'translations' => ['es-es' => 'Pagar']],
                ['category' => null, 'phrase' => 'Loose'],
            ],
            'blocks' => [['category' => 'UI', 'custom_id' => 'abc123', 'phrases' => [['phrase' => 'One', 'translations' => ['es-es' => 'Uno']], ['phrase' => 'Two']]]],
        ]);
    }

    private function served($locale)
    {
        $context = stream_context_create(['http' => ['header' => "X-Authorization: k-read\r\n", 'ignore_errors' => true]]);
        $body = json_decode(file_get_contents(self::$baseUrl . '/translations/data?project_id=' . self::PROJECT . '&locale=' . $locale, false, $context), true);

        return $body['data'];
    }

    public function testTheSnapshotCarriesExactlyWhatTheApiReturnsForTheChosenCategories(): void
    {
        $this->seedCatalog();

        $snapshot = Snapshot::export($this->client('k-read'), ['es-es', 'fr-fr'], ['UI', 'Errors']);

        foreach (['es-es', 'fr-fr'] as $locale) {
            $served = $this->served($locale);
            $this->assertSame(['UI' => $served['UI'], 'Errors' => $served['Errors']], $snapshot->catalog($locale), $locale);
        }
        $this->assertSame(['Save' => 'Guardar', 'abc123' => ['One' => 'Uno', 'Two' => null]], $snapshot->catalog('es-es')['UI'], 'blocks and untranslated entries as served');
        $this->assertArrayNotHasKey('Billing', $snapshot->catalog('es-es'));
    }

    public function testNothingOutsideTheCatalogIsCarried(): void
    {
        $this->seedCatalog();

        $json = Snapshot::export($this->client('k-read'), ['es-es'], ['UI'])->toJson();

        foreach (['write_enabled', 'discovery_base_locale_only', 'untranslated_words'] as $field) {
            $this->assertStringNotContainsString($field, $json);
        }
    }

    public function testTheCommandWritesASnapshotThatLoads(): void
    {
        $this->seedCatalog();
        $out = sys_get_temp_dir() . '/langsys-snapshot-cli-' . bin2hex(random_bytes(4)) . '.json';
        $config = sys_get_temp_dir() . '/langsys-snapshot-config-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($config, '<?php return ["client" => function () { return new \\Langsys\\SDK\\Client("k-read", ' . var_export(self::PROJECT, true) . ', ["api_url" => ' . var_export(self::$baseUrl, true) . ', "cache" => new \\Langsys\\SDK\\Cache\\NullCache()]); }];');

        try {
            $bin = dirname(__DIR__, 2) . '/bin/langsys-snapshot';
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' --config=' . escapeshellarg($config) . ' --locale=es-es --category=UI --category=Errors --out=' . escapeshellarg($out) . ' 2>&1', $output, $code);

            $this->assertSame(0, $code, implode("\n", $output));
            $this->assertSame(['UI', 'Errors'], array_keys(Snapshot::load($out)->catalog('es-es')));

            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' --config=' . escapeshellarg($config) . ' --locale=es-es --out=' . escapeshellarg($out) . ' 2>&1', $noCategory, $failed);
            $this->assertSame(1, $failed, 'a snapshot names its categories');
        } finally {
            @unlink($out);
            @unlink($config);
        }
    }
}
