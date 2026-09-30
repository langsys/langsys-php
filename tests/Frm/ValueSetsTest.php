<?php

namespace Langsys\SDK\Tests\Frm;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Messages\ValueSets;
use Langsys\SDK\Sync\SourceScanner;
use Langsys\SDK\Tests\Frm\Fixtures\Category;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * FRM-7: a declared set of translatable values is written into its sentence
 * as display words, enumerated at sync, and looked up at runtime; a value
 * added since the last sync registers after the response, and nothing else
 * does.
 */
class ValueSetsTest extends TestCase
{
    /** @var MockHttpClient */
    private $http;

    protected function setUp(): void
    {
        Category::$rows = ['Books', 'Music'];
    }

    private function enums()
    {
        if (PHP_VERSION_ID < 80100) {
            $this->markTestSkipped('Backed enums need PHP 8.1');
        }

        require_once __DIR__ . '/Fixtures/ValueSetEnums.php';
    }

    private function client(array $catalog, array $declarations, array $options = [])
    {
        $this->http = new MockHttpClient();
        $this->http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us', 'target_locales' => ['es-es']]]);
        $this->http->setResponse('GET', 'translations', ['data' => $catalog]);
        $this->http->setResponse('POST', 'translatable-items', ['data' => ['human_translations_saved' => 0, 'human_translations_skipped' => 0]]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false, 'value_sets' => $declarations] + $options);
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
        $client->setLocale('es-es');

        return $client;
    }

    /**
     * An enum with a label() uses it, one without uses its value, and a
     * class implementing the contract gives its rows.
     */
    public function testEveryKindOfDeclarationGivesItsDisplayWords(): void
    {
        $this->enums();

        $sets = new ValueSets([\Langsys\SDK\Tests\Frm\Fixtures\OrderStatus::class, \Langsys\SDK\Tests\Frm\Fixtures\Size::class, Category::class, \Langsys\SDK\Tests\Frm\Fixtures\Undeclared::class]);

        $this->assertSame(['status' => ['Shipped', 'On hold'], 'size' => ['Small', 'Large'], 'category' => ['Books', 'Music']], $sets->sets());
    }

    /**
     * The spec's sync test: one written-in sentence per value, with label()
     * where defined, and no template.
     */
    public function testSyncRegistersOneSentencePerValue(): void
    {
        $this->enums();
        $client = $this->client(['__uncategorized__' => []], [\Langsys\SDK\Tests\Frm\Fixtures\OrderStatus::class]);

        $plan = $client->planSync((new SourceScanner())->scan("<?php __('The order is :status.', ['status' => \$s]); __('Hi :name', ['name' => \$n]);", 'app.php'));

        $this->assertSame(['The order is Shipped.', 'The order is On hold.', 'Hi {name}'], array_column($plan->items, 'phrase'));
    }

    /**
     * At runtime a declared value renders the translation of its written-in
     * sentence - given as the case, its value or its display word - and never
     * looks up the template.
     *
     * @dataProvider declaredValueProvider
     */
    public function testADeclaredValueRendersItsSentence($value): void
    {
        $this->enums();
        $value = is_callable($value) ? $value() : $value;
        $client = $this->client(['__uncategorized__' => ['The order is On hold.' => 'El pedido está en espera.', 'The order is {status}.' => 'WRONG']], [\Langsys\SDK\Tests\Frm\Fixtures\OrderStatus::class]);

        $this->assertSame(['text' => 'El pedido está en espera.', 'from' => 'catalog'], $client->resolve('The order is {status}.', null, null, ['status' => $value]));
    }

    public function declaredValueProvider(): array
    {
        return [
            'the case' => [function () {
                return \Langsys\SDK\Tests\Frm\Fixtures\OrderStatus::OnHold;
            }],
            'its backing value' => ['on_hold'],
            'its display word' => ['On hold'],
        ];
    }

    /**
     * The spec's runtime test: a value added after the sync registers its
     * sentence after the first response that shows it, even with runtime
     * registration off, and not before - it is queued, not sent.
     */
    public function testAValueAddedSinceTheSyncRegistersAfterTheResponse(): void
    {
        $client = $this->client(['__uncategorized__' => ['In Books' => 'En libros', 'In Music' => 'En música']], [Category::class], ['runtime_registration' => false]);
        Category::$rows[] = 'Games';

        $this->assertSame('In Games', $client->translate('In {category}', null, '__uncategorized__', null, ['category' => 'Games']));

        $this->assertSame(['In Games'], array_values(array_column($client->getPendingPhrases(), 'phrase')), 'queued for the flush after the response');
        $this->assertSame([], array_filter($this->http->getRequests(), function ($r) {
            return $r['method'] === 'POST';
        }), 'nothing sent during the request');
    }

    /**
     * A value from no set declared for its placeholder registers nothing and
     * renders as a placeholder; a declared value whose template was never
     * synced registers nothing either.
     */
    public function testAnythingElseRegistersNothingAtRuntime(): void
    {
        $client = $this->client(['__uncategorized__' => ['In Books' => 'En libros']], [Category::class], ['runtime_registration' => false]);

        $this->assertSame('In Ana', $client->translate('In {category}', null, '__uncategorized__', null, ['category' => 'Ana']));
        $this->assertSame('Hello Music', $client->translate('Hello {category}', null, '__uncategorized__', null, ['category' => 'Music']));
        $this->assertFalse($client->hasPendingRegistrations());
    }

    /**
     * A placeholder is linked to a set only by a declaration, never by name:
     * an undeclared enum's placeholder stays a placeholder.
     */
    public function testAPlaceholderIsNeverLinkedByName(): void
    {
        $this->enums();
        $sets = new ValueSets([\Langsys\SDK\Tests\Frm\Fixtures\Undeclared::class, Category::class]);

        $this->assertNull($sets->writeIn('Color {undeclared}', ['undeclared' => 'Blue']));
        $this->assertNull($sets->writeIn('Genre {genre}', ['genre' => 'Books']), 'a value of another placeholder\'s set');
        $this->assertSame(['Genre Books', []], $sets->writeIn('Genre {category}', ['category' => 'Books']));
    }

    /**
     * Membership is read when it is asked: a row added counts at once.
     */
    public function testMembershipIsReadAtCallTime(): void
    {
        $sets = new ValueSets([new Category()]);
        $this->assertNull($sets->displayWord('category', 'Games'));

        Category::$rows[] = 'Games';

        $this->assertSame('Games', $sets->displayWord('category', 'Games'));
    }
}
