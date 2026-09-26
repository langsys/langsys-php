<?php

namespace Langsys\SDK\Tests\Snapshot;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Messages\ServerMessage;
use Langsys\SDK\Snapshot\Snapshot;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * SNAP-2: the core's seam for seeding its catalog from a snapshot. Seeded,
 * lookups read the snapshot with no fetch; a phrase it does not hold falls back
 * to the live catalog, and to source text offline; the live catalog outranks
 * it once read; registration is decided only against the live catalog. SRV-6:
 * offline, the snapshot gives the request-locale resolver its served set.
 */
class SnapshotSeedTest extends TestCase
{
    /**
     * @var MockHttpClient
     */
    private $http;

    private function snapshot(array $catalog = null)
    {
        $parser = new HtmlParser();
        $payload = [
            'project_id' => 'project-id',
            'generated_at' => '2026-09-25T00:00:00Z',
            'base_locale' => 'en-us',
            'locales' => ['es-es'],
            'categories' => ['Errors', 'UI'],
            'catalog' => ['es-es' => $catalog !== null ? $catalog : [
                'UI' => [
                    'Save' => 'Guardar',
                    'Welcome' => 'Bienvenido',
                    $parser->generateCustomId('UI', ['One', 'Two']) => ['One' => 'Uno', 'Two' => 'Dos'],
                ],
                'Errors' => ['The {field} is required.' => 'El {field} es obligatorio.'],
            ]],
        ];

        $document = ['format' => Snapshot::FORMAT, 'version' => Snapshot::VERSION] + $payload + ['checksum' => 'sha256:' . hash('sha256', Snapshot::canonical($payload))];

        return Snapshot::load(json_encode($document));
    }

    /**
     * @param array|null $live The live catalog, or null when the API is unreachable
     */
    private function client($live, array $options = [])
    {
        $this->http = new class extends MockHttpClient {
            public $offline = false;

            public function get($endpoint, array $params = [])
            {
                $response = parent::get($endpoint, $params);

                if ($this->offline) {
                    throw new LangsysException('The API is unreachable');
                }

                return $response;
            }
        };
        $this->http->offline = $live === null;
        $this->http->setResponse('GET', 'authorize-project/project-id', ['data' => [
            'key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us', 'target_locales' => ['es-es', 'fr-fr'],
        ]]);
        $this->http->setResponse('GET', 'translations', ['data' => $live === null ? [] : $live]);

        $client = new Client('test-api-key', 'project-id', array_merge(['cache' => new NullCache(), 'error_log' => false], $options));
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

    private function fetches(): int
    {
        return count(array_filter($this->http->getRequests(), function ($request) {
            return $request['method'] === 'GET' && $request['endpoint'] === 'translations';
        }));
    }

    public function testASeededPhraseRendersWithNoFetchAndNoRegistration(): void
    {
        $client = $this->client(['UI' => []])->useSnapshot($this->snapshot());
        $client->setLocale('es-es');

        $this->assertSame('Guardar', $client->translate('Save', null, 'UI'));
        $this->assertSame(0, $this->fetches());
        $this->assertFalse($client->hasPendingRegistrations(), 'the snapshot never decides a registration');
    }

    public function testTheSnapshotOptionSeedsTheSameWay(): void
    {
        $client = $this->client(['UI' => []], ['snapshot' => $this->snapshot()]);
        $client->setLocale('es-es');

        $this->assertSame('Guardar', $client->translate('Save', null, 'UI'));
        $this->assertSame(0, $this->fetches());
    }

    /**
     * A phrase the snapshot lacks falls back to the live catalog, which then
     * outranks the snapshot for every lookup and decides registrations.
     */
    public function testAMissFallsBackToTheLiveCatalogWhichThenOutranksTheSnapshot(): void
    {
        $client = $this->client(['UI' => ['Cancel' => 'Cancelar', 'Save' => 'Guardar (live)']])->useSnapshot($this->snapshot());
        $client->setLocale('es-es');

        $this->assertSame('Cancelar', $client->translate('Cancel', null, 'UI'));
        $this->assertSame(1, $this->fetches());
        $this->assertSame('Guardar (live)', $client->translate('Save', null, 'UI'), 'the live catalog outranks the snapshot once read');

        $client->translate('Brand new', null, 'UI');
        $this->assertSame(['Brand new'], array_values(array_column($client->getPendingPhrases(), 'phrase')), 'registration is decided against the live catalog');
    }

    public function testOfflineAPhraseTheSnapshotLacksIsSourceTextAndNothingIsQueued(): void
    {
        $client = $this->client(null)->useSnapshot($this->snapshot());
        $client->setLocale('es-es');

        $this->assertSame('Guardar', $client->translate('Save', null, 'UI'));
        $this->assertSame('Cancel', $client->translate('Cancel', null, 'UI'));
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testASeededBlockRendersAndIsStampedWithNoFetch(): void
    {
        $client = $this->client(['UI' => []])->useSnapshot($this->snapshot());
        $client->setLocale('es-es');

        $rendered = $client->translateContentBlock('<div><p>One</p><p>Two</p></div>', 'UI');

        $this->assertStringContainsString('<p>Uno</p><p>Dos</p>', $rendered);
        $this->assertStringContainsString('data-ls-contentblock="' . (new HtmlParser())->generateCustomId('UI', ['One', 'Two']) . '"', $rendered);
        $this->assertSame(0, $this->fetches());
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testAPageTheSnapshotHoldsRendersWithNoFetch(): void
    {
        $client = $this->client(['UI' => []])->useSnapshot($this->snapshot());
        $client->setLocale('es-es');

        $rendered = $client->translatePage('<html><body><p>Welcome</p><p>One <b>Two</b></p></body></html>', 'UI');

        $this->assertStringContainsString('Bienvenido', $rendered);
        $this->assertStringContainsString('Uno <b>Dos</b>', $rendered);
        $this->assertSame(0, $this->fetches());
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testAPageWithAUnitTheSnapshotLacksReadsTheLiveCatalog(): void
    {
        $client = $this->client(['UI' => ['Welcome' => 'Bienvenido (live)']])->useSnapshot($this->snapshot());
        $client->setLocale('es-es');

        $rendered = $client->translatePage('<html><body><p>Welcome</p><p>Brand new</p></body></html>', 'UI');

        $this->assertStringContainsString('Bienvenido (live)', $rendered);
        $this->assertSame(1, $this->fetches());
        $this->assertSame(['Brand new'], array_values(array_column($client->getPendingPhrases(), 'phrase')));
    }

    public function testOfflineAPageRendersWhatTheSnapshotHoldsAndQueuesNothing(): void
    {
        $client = $this->client(null)->useSnapshot($this->snapshot());
        $client->setLocale('es-es');

        $rendered = $client->translatePage('<html><body><p>Welcome</p><p>Brand new</p></body></html>', 'UI');

        $this->assertStringContainsString('Bienvenido', $rendered);
        $this->assertStringContainsString('Brand new', $rendered);
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testASeededServerMessageTemplateRendersWithNoFetch(): void
    {
        $client = $this->client(['Errors' => []])->useSnapshot($this->snapshot());
        $client->setLocale('es-es');

        $message = new ServerMessage('required', 'The email is required.', 'The {field} is required.', ['field' => 'email']);

        $this->assertSame('El email es obligatorio.', $client->translateMessage($message));
        $this->assertSame(0, $this->fetches());
    }

    // SRV-6 offline

    public function testOfflineASnapshotLocaleIsServedFromTheSnapshot(): void
    {
        $client = $this->client(null)->useSnapshot($this->snapshot());

        $result = $client->resolveRequestLocale(['query' => ['locale' => 'es-ES']], ['send_vary' => false]);

        $this->assertSame('es-es', $result['locale']);
        $this->assertSame('Guardar', (function () use ($client, $result) {
            $client->setLocale($result['locale']);

            return $client->translate('Save', null, 'UI');
        })(), 'and rendered in Spanish from the snapshot');
    }

    public function testOfflineALocaleTheSnapshotLacksFallsToItsBaseLocale(): void
    {
        $client = $this->client(null)->useSnapshot($this->snapshot());

        $this->assertSame('en-us', $client->resolveRequestLocale(['query' => ['locale' => 'fr-FR']], ['send_vary' => false])['locale']);
    }

    /**
     * Control: unseeded and offline, no served set is known.
     */
    public function testOfflineWithoutASnapshotNoLocaleIsResolved(): void
    {
        $client = $this->client(null);

        $this->assertNull($client->resolveRequestLocale(['query' => ['locale' => 'es-ES']], ['send_vary' => false])['locale']);
    }

    /**
     * Online, authorization's served set replaces the snapshot's.
     */
    public function testOnlineAuthorizationReplacesTheSnapshotsServedSet(): void
    {
        $client = $this->client(['UI' => []])->useSnapshot($this->snapshot());

        $this->assertSame('fr-fr', $client->resolveRequestLocale(['query' => ['locale' => 'fr-FR']], ['send_vary' => false])['locale']);
    }
}
