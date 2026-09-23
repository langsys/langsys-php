<?php

namespace Langsys\SDK\Tests\Locale;

use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Html\PageTranslator;
use Langsys\SDK\Messages\ServerMessage;
use Langsys\SDK\Tests\Support\BuildsMockClient;
use PHPUnit\Framework\TestCase;

/**
 * WIRE-3: a locale is lowercase `xx-yy` on the wire and in every internal key,
 * whichever public entry point it arrived through. `es-ES` from a host
 * application must reach the same catalog entry as `es-es`, not fetch it twice.
 */
class LocaleAtEveryEntryPointTest extends TestCase
{
    use BuildsMockClient;

    public function testTranslateReadsOneCatalogWhateverTheLocalesSpelling(): void
    {
        $client = $this->mockClient(['__uncategorized__' => ['Save' => 'Guardar']]);
        $client->setLocale('es-es');

        $this->assertSame('Guardar', $client->translate('Save'));
        $this->assertSame('Guardar', $client->translate('Save', 'es-ES'));
        $this->assertSame('Guardar', $client->translate('Save', 'es_ES'));

        $this->assertSame(['es-es'], $this->catalogLocalesRequested());
    }

    public function testGetTranslationsSendsTheLowercaseForm(): void
    {
        $client = $this->mockClient(['__uncategorized__' => ['Save' => 'Guardar']]);

        $client->getTranslations('ES_es');
        $client->getTranslations('es-es');

        $this->assertSame(['es-es'], $this->catalogLocalesRequested());
    }

    public function testTranslateMessageReadsTheSameCatalog(): void
    {
        $client = $this->mockClient(['Errors' => ['The name is required.' => 'El nombre es obligatorio.']]);
        $client->setLocale('es-es');
        $entry = ServerMessage::make('required', 'The name is required.', [], 'name');

        $this->assertSame('El nombre es obligatorio.', $client->translateMessage($entry, 'es-ES'));
        $this->assertSame('El nombre es obligatorio.', $client->translateMessage($entry));
        $this->assertSame(['es-es'], $this->catalogLocalesRequested());
    }

    public function testThePageTranslatorSendsAndWritesTheLowercaseForm(): void
    {
        $client = $this->mockClient(['__uncategorized__' => ['Save' => 'Guardar']]);

        $page = (new PageTranslator($client))->translate('<html><body><p>Save</p></body></html>', 'es-ES');

        $this->assertStringContainsString('<html lang="es-es">', $page);
        $this->assertStringContainsString('<p>Guardar</p>', $page);
        $this->assertSame(['es-es'], $this->catalogLocalesRequested());
    }

    public function testSyncSendsTheLowercaseForm(): void
    {
        $client = $this->mockClient(['__uncategorized__' => []]);

        $client->sync(['Save'], 'es-ES');

        $this->assertNotEmpty($this->catalogLocalesRequested());
        $this->assertSame(['es-es'], array_values(array_unique($this->catalogLocalesRequested())));
    }

    public function testTheTranslationsResourceSendsTheLowercaseForm(): void
    {
        $client = $this->mockClient(['__uncategorized__' => []]);

        $client->translations()->getFlat('es-ES');
        $client->translations()->getData('ES_ES');

        $this->assertSame(['es-es', 'es-es'], $this->catalogLocalesRequested());
    }

    public function testClearCacheClearsTheEntryAnySpellingNames(): void
    {
        $dir = sys_get_temp_dir() . '/langsys-locale-test-' . bin2hex(random_bytes(4));
        $cache = new FileCache($dir);

        try {
            $client = $this->mockClient(['__uncategorized__' => ['Save' => 'Guardar']], ['cache' => $cache]);
            $client->getTranslations('es-es');

            $client->clearCache('ES-ES');
            $client->getTranslations('es-es');

            $this->assertSame(['es-es', 'es-es'], $this->catalogLocalesRequested(), 'the cached entry was cleared, so the catalog is fetched again');
        } finally {
            $cache->clear();
            @rmdir($dir);
        }
    }
}
