<?php

namespace Langsys\SDK\Tests\Snapshot;

use Langsys\SDK\Snapshot\Snapshot;
use Langsys\SDK\Snapshot\SnapshotException;
use PHPUnit\Framework\TestCase;

/**
 * The shared snapshot vectors (SNAP-1), authored by langsys-js-typescript and
 * adopted byte-identically (`a639ae8c:tests/fixtures/snapshot-vectors.json`,
 * blob `594bd77a0289abfdf608508ac93cc9f4c4f88459`): the exact canonical bytes
 * and checksum each document must produce, the loads refused by reason, and a
 * load from another JSON encoding.
 *
 * Documents are decoded as PHP decodes any JSON - an integer-like key becomes
 * an integer and an empty map an empty array - which is the case the
 * canonical writer has to get right.
 */
class SnapshotVectorsTest extends TestCase
{
    private static function fixture()
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/fixtures/snapshot-vectors.json'), true);
    }

    private static function rows($section)
    {
        $rows = [];
        foreach (self::fixture()[$section] as $row) {
            $rows[$row['id']] = [$row];
        }

        return $rows;
    }

    public function rowProvider(): array
    {
        return self::rows('rows');
    }

    public function refusalProvider(): array
    {
        return self::rows('refusals');
    }

    public function loadProvider(): array
    {
        return self::rows('loads');
    }

    /**
     * @dataProvider rowProvider
     */
    public function testTheCanonicalBytesAndChecksum(array $row): void
    {
        $payload = [];
        foreach (Snapshot::MEMBERS as $member) {
            $payload[$member] = $row['document'][$member];
        }

        $canonical = Snapshot::canonical($payload);

        $this->assertSame($row['canonical'], $canonical);
        $this->assertSame($row['checksum'], 'sha256:' . hash('sha256', $canonical));
        $this->assertSame($row['checksum'], $row['document']['checksum']);
    }

    /**
     * Each row's document loads, whatever encoder wrote it, and the file this
     * SDK writes back from it is one another core reads: same checksum, every
     * map an object.
     *
     * @dataProvider rowProvider
     */
    public function testEachDocumentLoadsAndRoundTrips(array $row): void
    {
        $snapshot = Snapshot::load(json_encode($row['document'], JSON_UNESCAPED_UNICODE));
        $written = $snapshot->toJson();

        $this->assertStringContainsString('"checksum":"' . $row['checksum'] . '"', $written);
        $this->assertStringNotContainsString(':[]', $written, 'a map is never written as an empty list');
        $this->assertInstanceOf(Snapshot::class, Snapshot::load($written));
    }

    /**
     * @dataProvider refusalProvider
     */
    public function testARefusalNamesItsReason(array $row): void
    {
        try {
            Snapshot::load(json_encode($row['document'], JSON_UNESCAPED_UNICODE));
            $this->fail('the load is refused');
        } catch (SnapshotException $e) {
            $this->assertSame($row['refuse'], $e->getReason());
            $this->assertStringContainsString($row['refuse'] === 'missing-member' ? 'missing the member' : $row['refuse'], $e->getMessage());
        }
    }

    /**
     * @dataProvider loadProvider
     */
    public function testAnyJsonEncodingLoads(array $row): void
    {
        $this->assertEquals($row['expect_catalog'], Snapshot::load(json_encode($row['document']))->catalog($row['locale']));
    }
}
