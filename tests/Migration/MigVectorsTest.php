<?php

namespace Langsys\SDK\Tests\Migration;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Migration\LegacyValue;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * The shared key-migration vectors (MIG-2, MIG-4, MIG-7), authored by
 * langsys-js-typescript and adopted byte-identically
 * (`2d57cdd9:tests/fixtures/mig-vectors.json`, blob
 * `822dcc82ba9ddfef593d9cf173bbd6748de02947`).
 *
 * This core reads laravel and plain, its ecosystem's set, and vue-i18n and
 * i18next besides; rows in any of those formats run here. Rows for another
 * ecosystem's formats (rails-i18n, gettext) and entry points (I18n.t, gettext,
 * the JS bridges) are that ecosystem's, `n/a (format)` here, and a file in a
 * format this core does not read is refused at load.
 */
class MigVectorsTest extends TestCase
{
    const READ = ['laravel', 'plain', 'vue-i18n', 'i18next'];

    const OWN_ENTRY_POINTS = ['t', '__', 'trans_choice'];

    /**
     * @var string|null
     */
    private $dir;

    protected function tearDown(): void
    {
        if ($this->dir !== null) {
            $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($files as $file) {
                $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
            }
            rmdir($this->dir);
        }
    }

    private static function fixture()
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/fixtures/mig-vectors.json'), true);
    }

    private static function rows($section, callable $runsHere)
    {
        $rows = [];
        foreach (self::fixture()[$section] as $row) {
            if ($runsHere($row)) {
                $rows[$row['id']] = [$row];
            }
        }

        return $rows;
    }

    public function valueProvider(): array
    {
        return self::rows('value_conversion', function ($row) {
            return in_array($row['format'], self::READ, true);
        });
    }

    public function pluralProvider(): array
    {
        return self::rows('plural_forms', function ($row) {
            return in_array($row['format'], self::READ, true);
        });
    }

    public function callProvider(): array
    {
        return self::rows('calls', function ($row) {
            return in_array($row['entry_point'], self::OWN_ENTRY_POINTS, true);
        });
    }

    public function resolutionProvider(): array
    {
        return self::rows('resolution', function ($row) {
            foreach ($row['files'] as $file) {
                if (isset($file['format']) && !in_array($file['format'], self::READ, true)) {
                    return false;
                }
            }

            return true;
        });
    }

    public function refusalProvider(): array
    {
        return self::rows('refusals', function () {
            return true;
        });
    }

    /**
     * @dataProvider valueProvider
     */
    public function testValueConversion(array $row): void
    {
        $converted = LegacyValue::convert($row['value'], $row['format']);

        $this->assertSame($row['expected'], $converted['text']);
        $this->assertSame($row['recognised'], $converted['recognised']);
    }

    /**
     * @dataProvider pluralProvider
     */
    public function testPluralForms(array $row): void
    {
        $converted = LegacyValue::fromPluralForms($row['forms']);

        $this->assertSame($row['recognised'], $converted['recognised']);
        if ($row['expected'] !== null) {
            $this->assertSame($row['expected'], $converted['text']);
        }
    }

    /**
     * @dataProvider callProvider
     */
    public function testCalls(array $row): void
    {
        $this->assertSame($row['expected'], $this->phraseForCall($row)['text']);
        $this->assertSame($row['recognised'], $this->phraseForCall($row)['recognised']);

        if (isset($row['same_phrase_as'])) {
            $other = null;
            foreach (self::fixture()['calls'] as $candidate) {
                if ($candidate['id'] === $row['same_phrase_as']) {
                    $other = $candidate;
                }
            }
            $this->assertSame($this->phraseForCall($other)['text'], $this->phraseForCall($row)['text'], 'one phrase, one id');
        }
    }

    /**
     * The phrase a call registers: translate() takes Langsys syntax as written;
     * Laravel's entry points convert through the helper their hooks use.
     */
    private function phraseForCall(array $row)
    {
        if ($row['entry_point'] === 't') {
            $client = $this->client(['files' => [$this->write([['name' => 'en.json', 'data' => []]])[0]]]);
            $client->translate($row['text'], null, 'UI', null, $row['params']);
            $queued = array_values($client->getPendingPhrases());

            return ['text' => $queued[0]['phrase'], 'recognised' => true];
        }

        $count = isset($row['params']['count']) ? $row['params']['count'] : null;

        return LegacyValue::fromCall($row['text'], $row['params'], $row['entry_point'], $count);
    }

    /**
     * @dataProvider resolutionProvider
     */
    public function testResolution(array $row): void
    {
        $entries = $this->write($row['files']);
        $client = $this->client(['files' => $entries]);

        $resolved = $client->resolveLegacyKey($row['key'], isset($row['category_arg']) ? $row['category_arg'] : null);

        if ($row['expected'] === null) {
            $this->assertNull($resolved);

            return;
        }

        $this->assertNotNull($resolved, 'the key resolves');
        $this->assertSame($row['expected']['phrase'], $resolved['phrase']);
        $this->assertSame($row['expected']['category'], $resolved['category']);
        $this->assertSame($row['expected']['file'], substr($resolved['file'], strlen($this->dir) + 1));
        $this->assertSame($row['expected']['recognised'], $resolved['recognised']);
    }

    /**
     * @dataProvider refusalProvider
     */
    public function testRefusals(array $row): void
    {
        $entry = $this->dir() . '/' . $row['file']['name'];
        $config = ['files' => [isset($row['file']['format']) ? ['path' => $entry, 'format' => $row['file']['format']] : $entry]];

        if (in_array('php', $row['supported_by'], true)) {
            $this->assertInstanceOf(Client::class, $this->client($config));

            return;
        }

        try {
            $this->client($config);
            $this->fail('a ' . $row['format'] . ' file is refused at load');
        } catch (LangsysException $e) {
            $this->assertStringContainsString($row['format'], $e->getMessage(), 'the error names the format');
            $this->assertStringContainsString($row['file']['name'], $e->getMessage(), 'and the file');
            if (isset($row['hint'])) {
                $this->assertStringContainsString($row['hint'], $e->getMessage(), 'and points a .mo at its .po');
            }
        }
    }

    private function dir()
    {
        if ($this->dir === null) {
            $this->dir = sys_get_temp_dir() . '/langsys-mig-' . bin2hex(random_bytes(6));
            mkdir($this->dir);
        }

        return $this->dir;
    }

    /**
     * Write a row's files and return the migration entries naming them.
     */
    private function write(array $files)
    {
        $entries = [];
        foreach ($files as $file) {
            $path = $this->dir() . '/' . $file['name'];
            if (!is_dir(dirname($path))) {
                mkdir(dirname($path), 0777, true);
            }
            file_put_contents($path, json_encode($file['data'], JSON_UNESCAPED_UNICODE | JSON_FORCE_OBJECT));

            $entry = ['path' => $path];
            foreach (['format', 'namespace'] as $option) {
                if (isset($file[$option])) {
                    $entry[$option] = $file[$option];
                }
            }
            $entries[] = $entry;
        }

        return $entries;
    }

    private function client(array $migration)
    {
        $http = new MockHttpClient();
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'read', 'write_enabled' => false]]);
        $http->setResponse('GET', 'translations', ['data' => ['UI' => []]]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'migration' => $migration, 'error_log' => false]);
        $reflection = new \ReflectionClass($client);
        foreach (['http', 'translations', 'translatableItems'] as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            if ($property === 'http') {
                $prop->setValue($client, $http);
                continue;
            }
            $resource = $prop->getValue($client);
            $inner = (new \ReflectionClass($resource))->getProperty('http');
            $inner->setAccessible(true);
            $inner->setValue($resource, $http);
        }
        $client->setLocale('es-es');

        return $client;
    }
}
