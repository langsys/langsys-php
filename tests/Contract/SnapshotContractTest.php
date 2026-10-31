<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Snapshot\Snapshot;

/**
 * SNAP-1 against the contract fixture: the export is a client-side filter of
 * the flat catalog `GET /translations` returns, by category, carrying exactly
 * what the API returns for the chosen categories and nothing else.
 */
class SnapshotContractTest extends ContractTestCase
{
    protected static $seed = 'snapshot';

    private function served(World $world, $locale)
    {
        $answer = $this->api('/translations?' . http_build_query(['project_id' => $world->project(), 'locale' => $locale, 'format' => 'flat']), ['key' => $world->key('read')]);
        $this->assertSame(200, $answer['status']);

        return $answer['body']['data'];
    }

    /** A slice by key, at every depth: the API promises no order. */
    private static function sorted(array $slice)
    {
        ksort($slice, SORT_STRING);
        foreach ($slice as $key => $value) {
            if (is_array($value)) {
                $slice[$key] = self::sorted($value);
            }
        }

        return $slice;
    }

    public function testTheSnapshotCarriesExactlyWhatTheApiReturnsForTheChosenCategories(): void
    {
        $world = $this->world('catalog');

        $snapshot = Snapshot::export($this->client($world->key('read'), $world->project()), ['es-es', 'fr-fr'], ['UI', 'Errors']);

        foreach (['es-es', 'fr-fr'] as $locale) {
            $served = $this->served($world, $locale);
            $this->assertSame(['Errors' => $served['Errors'], 'UI' => $served['UI']], $snapshot->catalog($locale), $locale);
        }
        $this->assertSame('en-us', $snapshot->baseLocale(), 'the project\'s base locale travels with the snapshot');
        $this->assertSame(['Errors', 'UI'], $snapshot->categories(), 'sorted');
        $this->assertSame(['Save' => 'Guardar', 'abc123' => ['One' => 'Uno', 'Two' => 'Dos']], self::sorted($snapshot->catalog('es-es')['UI']), 'a translated block as served');
        $this->assertSame(['Save' => 'Enregistrer', 'abc123' => ['One' => null, 'Two' => null]], self::sorted($snapshot->catalog('fr-fr')['UI']), 'an untranslated block as served');
        $this->assertSame(['Oops' => null], $snapshot->catalog('fr-fr')['Errors'], 'an untranslated phrase as served');
        $this->assertArrayNotHasKey('Billing', $snapshot->catalog('es-es'));
    }

    public function testNothingOutsideTheCatalogIsCarried(): void
    {
        $world = $this->world('catalog');

        $json = Snapshot::export($this->client($world->key('read'), $world->project()), ['es-es'], ['UI'])->toJson();

        foreach (['write_enabled', 'discovery_base_locale_only', 'untranslated_words'] as $field) {
            $this->assertStringNotContainsString($field, $json);
        }
    }

    public function testTheCommandWritesASnapshotThatLoads(): void
    {
        $world = $this->world('catalog');
        $out = sys_get_temp_dir() . '/langsys-snapshot-cli-' . bin2hex(random_bytes(4)) . '.json';
        $config = sys_get_temp_dir() . '/langsys-snapshot-config-' . bin2hex(random_bytes(4)) . '.php';
        file_put_contents($config, '<?php return ["client" => function () { return new \\Langsys\\SDK\\Client(' . var_export($world->key('read'), true) . ', ' . var_export($world->project(), true) . ', ["api_url" => ' . var_export(self::$baseUrl, true) . ', "cache" => new \\Langsys\\SDK\\Cache\\NullCache()]); }];');

        try {
            $bin = dirname(__DIR__, 2) . '/bin/langsys-snapshot';
            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' --config=' . escapeshellarg($config) . ' --locale=es-es --category=UI --category=Errors --out=' . escapeshellarg($out) . ' 2>&1', $output, $code);

            $this->assertSame(0, $code, implode("\n", $output));
            $this->assertSame(['Errors', 'UI'], array_keys(Snapshot::load($out)->catalog('es-es')));

            exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($bin) . ' --config=' . escapeshellarg($config) . ' --locale=es-es --out=' . escapeshellarg($out) . ' 2>&1', $noCategory, $failed);
            $this->assertSame(1, $failed, 'a snapshot names its categories');
        } finally {
            @unlink($out);
            @unlink($config);
        }
    }
}
