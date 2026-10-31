<?php

namespace Langsys\SDK\Tests\Snapshot;

use Langsys\SDK\Snapshot\Snapshot;
use Langsys\SDK\Snapshot\SnapshotException;
use Langsys\SDK\Tests\Support\BuildsMockClient;
use PHPUnit\Framework\TestCase;

/**
 * SNAP-3: a snapshot is a cache Langsys produces - never hand-edited, never
 * authoritative. A snapshot changed by hand is refused on load, and the refresh
 * path is a new export.
 */
class SnapshotTest extends TestCase
{
    use BuildsMockClient;

    private function exported()
    {
        $catalog = ['UI' => ['Save' => 'Guardar'], 'Errors' => ['Oops' => 'Uy']];
        $client = $this->mockClient($catalog);
        $this->http->setResponse('GET', 'translations/data', ['data' => $catalog, 'write_enabled' => true]);

        return Snapshot::export($client, ['es-es'], ['UI']);
    }

    public function testAnExportedSnapshotLoadsBack(): void
    {
        $snapshot = $this->exported();
        $loaded = Snapshot::load($snapshot->toJson());

        $this->assertSame(['UI' => ['Save' => 'Guardar']], $loaded->catalog('es-es'));
        $this->assertSame(['es-es'], $loaded->locales());
        $this->assertSame(['UI'], $loaded->categories());
        $this->assertSame('project-id', $loaded->projectId());
    }

    public function testASnapshotChangedByHandIsRefused(): void
    {
        $json = $this->exported()->toJson();
        $edited = str_replace('Guardar', 'Salvar', $json);
        $this->assertNotSame($json, $edited);

        $this->expectException(SnapshotException::class);
        $this->expectExceptionMessage('export it again');
        Snapshot::load($edited);
    }

    public function testSomethingThatIsNotASnapshotIsRefused(): void
    {
        foreach (['not json', json_encode(['UI' => ['Save' => 'Guardar']]), json_encode(['format' => 'langsys-catalog-snapshot', 'version' => 99])] as $input) {
            try {
                Snapshot::load($input);
                $this->fail('accepted: ' . $input);
            } catch (SnapshotException $e) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testALocaleTheSnapshotDoesNotHoldHasNoCatalog(): void
    {
        $this->assertNull($this->exported()->catalog('fr-fr'));
        $this->assertSame(['UI' => ['Save' => 'Guardar']], $this->exported()->catalog('ES_es'), 'locales are read in any spelling');
    }

    public function testASnapshotNamesAtLeastOneLocaleAndOneCategory(): void
    {
        $client = $this->mockClient([]);

        foreach ([[[], ['UI']], [['es-es'], []]] as list($locales, $categories)) {
            try {
                Snapshot::export($client, $locales, $categories);
                $this->fail('exported with ' . json_encode([$locales, $categories]));
            } catch (SnapshotException $e) {
                $this->assertStringContainsString('at least one locale and at least one category', $e->getMessage());
            }
        }
    }

    public function testASnapshotCanBeWrittenAndReadFromAFile(): void
    {
        $path = sys_get_temp_dir() . '/langsys-snapshot-' . bin2hex(random_bytes(4)) . '.json';

        try {
            $this->exported()->writeTo($path);
            $this->assertSame(['UI' => ['Save' => 'Guardar']], Snapshot::load($path)->catalog('es-es'));
        } finally {
            @unlink($path);
        }
    }

    /**
     * The export reads the flat catalog GET /translations returns, the one an
     * SDK seeds - not the data format, which is shaped differently.
     */
    public function testTheExportReadsTheFlatCatalog(): void
    {
        $client = $this->mockClient(['UI' => ['Save' => 'Guardar']]);
        $this->http->setResponse('GET', 'translations/data', ['data' => ['UI' => ['Save' => 'WRONG: the data format']]]);

        $this->assertSame(['UI' => ['Save' => 'Guardar']], Snapshot::export($client, ['es-es'], ['UI'])->catalog('es-es'));
    }

    /**
     * Keys order by code point, which PHP's own comparison does not do for two
     * numeric keys: "10" comes before "9".
     */
    public function testNumericKeysOrderByCodePoint(): void
    {
        $canonical = Snapshot::canonical([
            'project_id' => 'p', 'generated_at' => 't', 'base_locale' => 'en',
            'locales' => ['en'], 'categories' => ['UI'],
            'catalog' => ['en' => ['UI' => [9 => 'nine', 10 => 'ten', 'a' => 'letter']]],
        ]);

        $this->assertStringContainsString('{"10":"ten","9":"nine","a":"letter"}', $canonical);
    }
}
