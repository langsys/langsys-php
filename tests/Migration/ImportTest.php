<?php

namespace Langsys\SDK\Tests\Migration;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Exception\ValidationException;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * MIG-9: a one-time import registers each key's source phrase together with
 * the translations the app already has, which the API stores as human.
 */
class ImportTest extends TestCase
{
    /** @var string|null */
    private $dir;

    /** @var MockHttpClient */
    private $http;

    protected function tearDown(): void
    {
        if ($this->dir === null) {
            return;
        }

        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($this->dir);
    }

    private function file($name, $contents)
    {
        if ($this->dir === null) {
            $this->dir = sys_get_temp_dir() . '/langsys-import-' . bin2hex(random_bytes(6));
            mkdir($this->dir);
        }

        $path = $this->dir . '/' . $name;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, is_array($contents) ? '<?php return ' . var_export($contents, true) . ';' : $contents);

        return $path;
    }

    private function client(array $migration, $keyType = 'write', $post = null)
    {
        $this->http = $post !== null ? $post : new MockHttpClient();
        $this->http->setResponse('GET', 'authorize-project/project-id', ['data' => [
            'key_type' => $keyType, 'write_enabled' => $keyType === 'write', 'base_locale' => 'en-us', 'target_locales' => ['es-es', 'fr-fr'],
        ]]);
        $this->http->setResponse('POST', 'translatable-items', ['data' => ['human_translations_saved' => 0, 'human_translations_skipped' => 0]]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false, 'migration' => $migration]);
        $reflection = new \ReflectionClass($client);
        foreach (['http', 'translations', 'translatableItems'] as $property) {
            $prop = $reflection->getProperty($property);
            $prop->setAccessible(true);
            if ($property === 'http') {
                $prop->setValue($client, $this->http);
                continue;
            }
            $resource = $prop->getValue($client);
            $inner = (new \ReflectionClass($resource))->getProperty('http');
            $inner->setAccessible(true);
            $inner->setValue($resource, $this->http);
        }

        return $client;
    }

    private function sent()
    {
        $items = [];
        foreach ($this->http->getRequests() as $request) {
            if ($request['method'] === 'POST' && $request['endpoint'] === 'translatable-items') {
                foreach ($request['data']['translatable_items'] as $item) {
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * The spec's test: a key with an existing translation registers the
     * phrase and sends the translation with it, converted the same way.
     */
    public function testAKeysTranslationIsSentWithItsPhrase(): void
    {
        $client = $this->client(['files' => [$this->file('en/checkout.php', ['submit' => 'Place order', 'hello' => 'Hello :name'])]]);
        $this->http->setResponse('POST', 'translatable-items', ['data' => ['human_translations_saved' => 2, 'human_translations_skipped' => 0]]);

        $result = $client->importLegacyTranslations([
            'es-es' => ['files' => [$this->file('es/checkout.php', ['submit' => 'Realizar pedido', 'hello' => 'Hola :name'])]],
        ]);

        $this->assertSame([
            ['type' => 'phrase', 'phrase' => 'Place order', 'category' => 'checkout', 'translatable' => true, 'translations' => ['es-es' => 'Realizar pedido']],
            ['type' => 'phrase', 'phrase' => 'Hello {name}', 'category' => 'checkout', 'translatable' => true, 'translations' => ['es-es' => 'Hola {name}']],
        ], $this->sent());
        $this->assertTrue($result['success']);
        $this->assertSame([2, 2, 2, 0], [$result['phrases'], $result['translations'], $result['human_translations_saved'], $result['human_translations_skipped']]);
    }

    /**
     * A plural converts to ICU on both sides, each by its own file's format.
     */
    public function testAPluralIsImportedAsIcu(): void
    {
        $client = $this->client(['files' => [['path' => $this->file('en.json', '{"apples": "one apple | {count} apples"}'), 'format' => 'vue-i18n']]]);

        $client->importLegacyTranslations(['es-es' => ['files' => [['path' => $this->file('es.json', '{"apples": "una manzana | {count} manzanas"}'), 'format' => 'vue-i18n']]]]);

        $this->assertSame('{count, plural, =1 {one apple} other {# apples}}', $this->sent()[0]['phrase']);
        $this->assertSame(['es-es' => '{count, plural, =1 {una manzana} other {# manzanas}}'], $this->sent()[0]['translations']);
    }

    /**
     * A value that does not convert registers its phrase as a lookup of the
     * key would - Laravel reads a "|" in a file as a plural only through
     * trans_choice() - and its translation is not guessed: it is left for
     * machine translation and listed.
     */
    public function testAnUnconvertedValueImportsAsItsLookupDoes(): void
    {
        $client = $this->client(['files' => [$this->file('en/cart.php', ['apples' => 'one apple|:count apples'])]]);

        $result = $client->importLegacyTranslations(['es-es' => ['files' => [$this->file('es/cart.php', ['apples' => 'una manzana|:count manzanas'])]]]);

        $this->assertSame($client->resolveLegacyKey('cart.apples')['phrase'], $this->sent()[0]['phrase']);
        $this->assertArrayNotHasKey('translations', $this->sent()[0]);
        $this->assertSame([['key' => 'cart.apples', 'locale' => 'es-es', 'reason' => 'not_converted']], $result['skipped']);
    }

    /**
     * A locale the project does not target is refused before anything is
     * sent.
     */
    public function testANonTargetLocaleIsRefusedBeforeAnythingIsSent(): void
    {
        $client = $this->client(['files' => [$this->file('en.json', '{"Save": "Save"}')]]);

        try {
            $client->importLegacyTranslations(['it-it' => ['files' => [$this->file('it.json', '{"Save": "Salva"}')]]]);
            $this->fail('a non-target locale is refused');
        } catch (LangsysException $e) {
            $this->assertStringContainsString('it-it', $e->getMessage());
        }

        $this->assertSame([], $this->sent());
    }

    /**
     * Missing, empty and unconvertible values are not translations: the
     * phrase still registers, the locale is left for machine translation,
     * and each is listed.
     */
    public function testWhatIsNotATranslationIsSkippedAndListed(): void
    {
        $client = $this->client(['files' => [$this->file('en.json', '{"a": "Alpha", "b": "Beta", "c": "Gamma"}')]]);

        $result = $client->importLegacyTranslations([
            'es-es' => ['files' => [$this->file('es.json', '{"b": "   ", "c": "Gama"}')]],
            'fr-fr' => ['files' => [['path' => $this->file('fr.json', '{"a": "Alpha %.2f", "c": "Gamma"}'), 'format' => 'plain']]],
        ]);

        $this->assertSame([
            ['type' => 'phrase', 'phrase' => 'Alpha', 'category' => null, 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'Beta', 'category' => null, 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'Gamma', 'category' => null, 'translatable' => true, 'translations' => ['es-es' => 'Gama', 'fr-fr' => 'Gamma']],
        ], $this->sent());
        $this->assertSame([
            ['key' => 'a', 'locale' => 'es-es', 'reason' => 'missing'],
            ['key' => 'a', 'locale' => 'fr-fr', 'reason' => 'not_converted'],
            ['key' => 'b', 'locale' => 'es-es', 'reason' => 'empty'],
            ['key' => 'b', 'locale' => 'fr-fr', 'reason' => 'missing'],
        ], $result['skipped']);
    }

    /**
     * Only phrase items carry translations: an import sends no content
     * block, so no block translation can be sent.
     */
    public function testOnlyPhraseItemsAreSent(): void
    {
        $client = $this->client(['files' => [$this->file('en.json', '{"x": "<p>One</p><p>Two</p>"}')]]);

        $client->importLegacyTranslations(['es-es' => ['files' => [$this->file('es.json', '{"x": "<p>Uno</p><p>Dos</p>"}')]]]);

        $this->assertSame(['phrase'], array_values(array_unique(array_column($this->sent(), 'type'))));
    }

    /**
     * Translations past the plan's human-translated words are reported, and
     * counts add up across batches.
     */
    public function testQuotaSkipsAreReportedAcrossBatches(): void
    {
        $client = $this->client(['files' => [$this->file('en.json', '{"a": "A", "b": "B", "c": "C"}')]]);
        $items = (new \ReflectionClass($client))->getProperty('translatableItems');
        $items->setAccessible(true);
        $items->getValue($client)->setBatchLimit(2);
        $this->http->setResponse('POST', 'translatable-items', ['data' => ['human_translations_saved' => 1, 'human_translations_skipped' => 1]]);

        $result = $client->importLegacyTranslations(['es-es' => ['files' => [$this->file('es.json', '{"a": "A es", "b": "B es", "c": "C es"}')]]]);

        $posts = array_filter($this->http->getRequests(), function ($r) {
            return $r['method'] === 'POST';
        });
        $this->assertCount(2, $posts);
        $this->assertSame([2, 2], [$result['human_translations_saved'], $result['human_translations_skipped']]);
    }

    public function testAReadKeyImportsNothingAndSaysWhy(): void
    {
        $client = $this->client(['files' => [$this->file('en.json', '{"Save": "Save"}')]], 'read');

        $result = $client->importLegacyTranslations(['es-es' => ['files' => [$this->file('es.json', '{"Save": "Guardar"}')]]]);

        $this->assertFalse($result['success']);
        $this->assertSame('not_write_enabled', $result['reason']);
        $this->assertSame([], $this->sent());
    }

    public function testARefusedSendIsAFailureNotAnException(): void
    {
        $http = new class extends MockHttpClient {
            public function post($endpoint, array $data = [])
            {
                parent::post($endpoint, $data);

                throw new ValidationException('The locale es-es is not a target locale of this project.', ['translatable_items.0.translations' => ['invalid_option']]);
            }
        };
        $client = $this->client(['files' => [$this->file('en.json', '{"Save": "Save"}')]], 'write', $http);

        $result = $client->importLegacyTranslations(['es-es' => ['files' => [$this->file('es.json', '{"Save": "Guardar"}')]]]);

        $this->assertFalse($result['success']);
        $this->assertSame('send_failed', $result['reason']);
    }

    public function testWithoutAMigrationOptionThereIsNothingToImport(): void
    {
        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false]);

        $this->expectException(LangsysException::class);
        $client->importLegacyTranslations([]);
    }

    /**
     * keys() lists every key the files define, once: app files, fallback
     * files and namespaces.
     */
    public function testKeysListsEveryKeyOnce(): void
    {
        $keys = new \Langsys\SDK\Migration\LegacyKeys([
            'files' => [$this->file('en/auth.php', ['failed' => 'Wrong', 'nested' => ['a' => 'A']])],
            'fallback_files' => [$this->file('vendor/en/auth.php', ['failed' => 'Vendor wrong', 'throttle' => 'Slow down'])],
            'namespaces' => ['courier' => [$this->file('courier/en/messages.php', ['welcome' => 'Welcome'])]],
        ]);

        $this->assertSame(['auth.failed', 'auth.nested.a', 'auth.throttle', 'courier::messages.welcome'], $keys->keys());
    }
}
