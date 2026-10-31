<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Tests\Contract\SeedFile;
use PHPUnit\Framework\TestCase;

/**
 * The seed files the SDK test clone reads (tests/contract-seeds/*.json) are
 * their world modules rendered, and stay inside this SDK's ranges on the clone,
 * since one file the clone's seeder refuses stops the reseed for every SDK
 * sharing it. `LANGSYS_WRITE_SEEDS=1` writes each file again from its module.
 *
 * Needs no clone: it reads only this repository.
 */
class CloneSeedFilesTest extends TestCase
{
    /**
     * @return SeedFile[]
     */
    private static function files()
    {
        $files = [];
        foreach (glob(SeedFile::directory() . '/*.php') as $module) {
            $files[] = SeedFile::load(basename($module, '.php'));
        }

        return $files;
    }

    public function testEachSeedFileIsItsModuleRendered(): void
    {
        $files = self::files();
        $this->assertNotSame([], $files);

        foreach ($files as $file) {
            $path = SeedFile::directory() . '/' . $file->slug . '.json';

            if (getenv('LANGSYS_WRITE_SEEDS')) {
                file_put_contents($path, $file->json());
            }

            $this->assertFileExists($path, "{$file->slug}.json is missing: write it with LANGSYS_WRITE_SEEDS=1");
            $this->assertSame($file->json(), file_get_contents($path), "{$file->slug}.json differs from its module: write it with LANGSYS_WRITE_SEEDS=1");
        }
    }

    public function testEverySeedFileHasAModule(): void
    {
        foreach (glob(SeedFile::directory() . '/*.json') as $json) {
            $this->assertFileExists(substr($json, 0, -5) . '.php', basename($json) . ' has no module, so nothing renders it');
        }
    }

    /**
     * Ids, raw keys and token ids are unique across every SDK's folders, and
     * each SDK keeps to its own ranges (langsys docs/sdk-test-clone.md).
     */
    public function testEveryIdIsUniqueAndInsideThisSdksRanges(): void
    {
        $seen = ['index' => [], 'id' => [], 'key' => [], 'token id' => [], 'token secret' => [], 'device' => []];
        $claim = function ($kind, $value, $where) use (&$seen) {
            $this->assertArrayNotHasKey($value, $seen[$kind], "$where repeats the $kind $value, also in " . (isset($seen[$kind][$value]) ? $seen[$kind][$value] : ''));
            $seen[$kind][$value] = $where;
        };

        foreach (self::files() as $file) {
            $claim('index', (string) $file->index, $file->slug);

            foreach ($file->document['projects'] as $project) {
                $where = "{$file->slug} project {$project['id']}";
                $this->assertMatchesRegularExpression('/^7700[0-9a-f]{4}-[0-9a-f]{4}-4000-8000-[0-9a-f]{12}$/', $project['id'], $where);
                $claim('id', $project['id'], $where);

                list($tokenId, $secret) = explode('|', $project['user']['token'], 2);
                $this->assertGreaterThanOrEqual(120000, (int) $tokenId, $where);
                $this->assertLessThanOrEqual(129999, (int) $tokenId, $where);
                $this->assertStringNotContainsString('|', $secret, $where);
                $claim('token id', $tokenId, $where);
                $claim('token secret', $secret, $where);
                $claim('device', $project['user']['device_id'], $where);
            }

            foreach ($file->document['keys'] as $key) {
                $where = "{$file->slug} key {$key['id']}";
                $this->assertMatchesRegularExpression('/^7700[0-9a-f]{4}-[0-9a-f]{4}-4000-8000-[0-9a-f]{12}$/', $key['id'], $where);
                $this->assertStringStartsWith('php-', $key['key'], $where);
                $claim('id', $key['id'], $where);
                $claim('key', $key['key'], $where);

                foreach (isset($key['ip_allowlist']) ? $key['ip_allowlist'] : [] as $entry) {
                    $this->assertMatchesRegularExpression('#^10\.77\.\d{1,3}\.\d{1,3}(/\d+)?$#', $entry, "$where allow-lists an address outside 10.77.0.0/16");
                }
            }
        }
    }
}
