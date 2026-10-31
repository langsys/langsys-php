<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Messages\MessageSet;
use Langsys\SDK\Messages\MessageTemplate;
use Langsys\SDK\Messages\ServerMessage;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * The shared server-message vectors (MSG-1, MSG-4), authored by
 * langsys-js-typescript and adopted byte-identically
 * (`239166a6:tests/fixtures/server-message-vectors.json`, blob
 * `7333e3919dac43af81c6c20bfdba974efd79725b`): markers, fill, resolve and render,
 * plus canonical entries built from real framework messages - Laravel,
 * Pydantic, Django, DRF, ActiveModel - each of which fills to its own message.
 */
class ServerMessageVectorsTest extends TestCase
{
    private static function fixture()
    {
        return json_decode(file_get_contents(dirname(__DIR__) . '/fixtures/server-message-vectors.json'), true);
    }

    private static function rows($section)
    {
        $rows = [];
        foreach (self::fixture()[$section] as $index => $row) {
            $rows[isset($row['id']) ? $row['id'] : $section . ' ' . $index] = [$row];
        }

        return $rows;
    }

    public function markersProvider(): array
    {
        return self::rows('markers');
    }

    public function fillProvider(): array
    {
        return self::rows('fill');
    }

    public function resolveProvider(): array
    {
        return self::rows('resolve');
    }

    public function renderProvider(): array
    {
        return self::rows('render');
    }

    public function canonicalProvider(): array
    {
        return self::rows('canonical_entries');
    }

    /**
     * @dataProvider markersProvider
     */
    public function testMarkers(array $row): void
    {
        $this->assertSame($row['expected'], MessageTemplate::markers($row['template']));
    }

    /**
     * @dataProvider fillProvider
     */
    public function testFill(array $row): void
    {
        $this->assertSame($row['expected'], MessageTemplate::fill($row['template'], $row['params']));
    }

    /**
     * @dataProvider resolveProvider
     */
    public function testResolve(array $row): void
    {
        $options = isset($row['options']) ? $row['options'] : [];
        $before = $row['body'];

        $normalise = function (array $entry) {
            ksort($entry);

            return $entry;
        };

        $this->assertSame(
            array_map($normalise, $row['expected']),
            array_map($normalise, MessageSet::fromResponse($row['body'], $options)->toArray())
        );
        $this->assertSame($before, $row['body'], 'resolving never changes the body');
    }

    /**
     * Rendered through translateMessage(): the catalog's translation of the
     * template, filled from the entry's params, or the entry's message.
     *
     * @dataProvider renderProvider
     */
    public function testRender(array $row): void
    {
        $http = new class extends MockHttpClient {
            public $noCatalog = false;

            public function get($endpoint, array $params = [])
            {
                $response = parent::get($endpoint, $params);

                if ($this->noCatalog && $endpoint === 'translations') {
                    throw new LangsysException('No catalog is published');
                }

                return $response;
            }
        };
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'read', 'write_enabled' => false]]);
        $http->noCatalog = $row['catalog'] === null;
        $http->setResponse('GET', 'translations', ['data' => $row['catalog'] === null ? [] : $row['catalog']]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'messages_category' => $row['category'], 'error_log' => false]);
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
        $client->setLocale($row['locale']);

        $this->assertSame($row['expected'], $client->translateMessage($row['entry']));
    }

    /**
     * MSG-4: every canonical entry's template, filled from its params, is its
     * message.
     *
     * @dataProvider canonicalProvider
     */
    public function testACanonicalEntryFillsToItsMessage(array $entry): void
    {
        $this->assertSame($entry['message'], MessageTemplate::fill($entry['template'], isset($entry['params']) ? $entry['params'] : []));
        $this->assertNotNull(ServerMessage::fromArray($entry));
    }
}
