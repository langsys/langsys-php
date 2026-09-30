<?php

namespace Langsys\SDK\Tests\Sync;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Sync\SourceScanner;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * FRM-2: a sync decides each phrase against the catalog - already there, new
 * with the translations the language files hold, or new alone - and a
 * translate() call registers nothing at runtime.
 */
class SyncPlanTest extends TestCase
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
            $this->dir = sys_get_temp_dir() . '/langsys-sync-' . bin2hex(random_bytes(6));
            mkdir($this->dir);
        }
        $path = $this->dir . '/' . $name;
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }
        file_put_contents($path, is_array($contents) ? '<?php return ' . var_export($contents, true) . ';' : $contents);

        return $path;
    }

    private function client(array $catalog, array $options = [], $keyType = 'write')
    {
        $this->http = new MockHttpClient();
        $this->http->setResponse('GET', 'authorize-project/project-id', ['data' => [
            'key_type' => $keyType, 'write_enabled' => $keyType === 'write', 'base_locale' => 'en-us', 'target_locales' => ['es-es'],
        ]]);
        $this->http->setResponse('GET', 'translations', ['data' => $catalog]);
        $this->http->setResponse('POST', 'translatable-items', ['data' => ['human_translations_saved' => 0, 'human_translations_skipped' => 0]]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false] + $options);
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

    private function hits($code)
    {
        return (new SourceScanner())->scan("<?php\n" . $code, 'app.php');
    }

    private function byPhrase($plan)
    {
        $out = [];
        foreach ($plan->items as $item) {
            $out[$item['phrase']] = [$item['category'], $item['status'], $item['translations']];
        }

        return $out;
    }

    private function sent()
    {
        $items = [];
        foreach ($this->http->getRequests() as $request) {
            if ($request['method'] === 'POST') {
                $items = array_merge($items, $request['data']['translatable_items']);
            }
        }

        return $items;
    }

    /**
     * The spec's test: a literal call in neither the catalog nor the files
     * registers alone; a base-language line absent from the catalog registers
     * with its other languages' lines; a phrase in the catalog registers
     * nothing; a key-style call under its group, a literal call uncategorised.
     */
    public function testEachPhraseIsDecidedAgainstTheCatalog(): void
    {
        $client = $this->client(['__uncategorized__' => ['Known' => 'Conocido']], ['migration' => ['files' => [$this->file('en/checkout.php', ['submit' => 'Place order', 'cancel' => 'Cancel order'])]]]);

        $plan = $client->planSync(
            $this->hits("__('Brand new'); __('Known'); __('checkout.submit');"),
            ['es-es' => ['files' => [$this->file('es/checkout.php', ['submit' => 'Realizar pedido'])]]]
        );

        $this->assertSame([
            'Brand new' => [null, 'new', []],
            'Known' => [null, 'in_catalog', []],
            'Place order' => ['checkout', 'with_translations', ['es-es' => 'Realizar pedido']],
            'Cancel order' => ['checkout', 'new', []],
        ], $this->byPhrase($plan));
        $this->assertSame(['in_catalog' => 1, 'with_translations' => 1, 'new' => 2], $plan->counts());
    }

    public function testApplyRegistersOnlyWhatTheCatalogLacks(): void
    {
        $client = $this->client(['__uncategorized__' => ['Known' => 'Conocido']], ['migration' => ['files' => [$this->file('en/checkout.php', ['submit' => 'Place order'])]]]);
        $plan = $client->planSync($this->hits("__('Brand new'); __('Known');"), ['es-es' => ['files' => [$this->file('es/checkout.php', ['submit' => 'Realizar pedido'])]]]);

        $result = $client->applySync($plan);

        $this->assertTrue($result['success']);
        $this->assertSame([
            ['type' => 'phrase', 'phrase' => 'Brand new', 'category' => null, 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'Place order', 'category' => 'checkout', 'translatable' => true, 'translations' => ['es-es' => 'Realizar pedido']],
        ], $this->sent());
        $this->assertSame([2, 1], [$result['registered'], $result['translations']]);
    }

    /**
     * A non-literal call is reported with its file and line, never
     * registered, and fails a strict run.
     */
    public function testANonLiteralCallIsReportedNotRegistered(): void
    {
        $client = $this->client([]);

        $plan = $client->planSync($this->hits("__(\$x);\n__('Fine');"));

        $this->assertSame([['file' => 'app.php', 'line' => 2, 'entry_point' => '__']], $plan->reported);
        $this->assertSame(['Fine'], array_column($plan->items, 'phrase'));
        $this->assertTrue($plan->failsStrict());
        $this->assertFalse($client->planSync($this->hits("__('Fine');"))->failsStrict());
    }

    /**
     * A literal converts by its call's rules - only passed placeholders
     * become placeholders - and a key registers once, as a lookup of it does,
     * its translation converted the same way.
     */
    public function testACallConvertsByItsOwnRules(): void
    {
        $client = $this->client([], ['migration' => ['files' => [$this->file('en/cart.php', ['apples' => ':count apple|:count apples'])]]]);

        $plan = $client->planSync(
            $this->hits("__('Hello :name, note:done', ['name' => \$n]); trans_choice('cart.apples', \$n); __('Tally :count|:count', ['count' => \$n]);"),
            ['es-es' => ['files' => [$this->file('es/cart.php', ['apples' => ':count manzana|:count manzanas'])]]]
        );

        $this->assertSame([
            'Hello {name}, note:done' => [null, 'new', []],
            '{count, plural, one {# apple} other {# apples}}' => ['cart', 'with_translations', ['es-es' => '{count, plural, one {# manzana} other {# manzanas}}']],
            'Tally {count}|{count}' => [null, 'new', []],
        ], $this->byPhrase($plan), 'a key registers once, as its lookup does');
    }

    /**
     * A literal's translation in a Laravel file converts by the call's rules
     * too: a placeholder the call does not pass stays as written.
     */
    public function testALiteralsTranslationConvertsByTheCallsRules(): void
    {
        $client = $this->client([]);

        $plan = $client->planSync(
            $this->hits("__('Note :done, :name', ['name' => \$n]);"),
            ['es-es' => ['files' => [['path' => $this->file('es.json', '{"Note :done, :name": "Nota :done, :name"}'), 'format' => 'laravel']]]]
        );

        $this->assertSame(['Note :done, {name}' => [null, 'with_translations', ['es-es' => 'Nota :done, {name}']]], $this->byPhrase($plan));
    }

    public function testAReadKeyAppliesNothingAndSaysWhy(): void
    {
        $client = $this->client([], [], 'read');

        $result = $client->applySync($client->planSync($this->hits("__('New');")));

        $this->assertSame([false, 'not_write_enabled'], [$result['success'], $result['reason']]);
        $this->assertSame([], $this->sent());
    }

    // The runtime switch

    /**
     * With runtime registration off, a translate() call with a phrase the
     * catalog lacks registers nothing, nor does an emitted server message;
     * the page walk and a rendered fragment still collect.
     */
    public function testWithRuntimeRegistrationOffACallRegistersNothing(): void
    {
        $client = $this->client(['__uncategorized__' => []], ['runtime_registration' => false]);

        $client->translate('Missing phrase');
        $client->emitMessage(new \Langsys\SDK\Messages\ServerMessage('required', 'The email is required.', 'The {field} is required.', ['field' => 'email']));
        $this->assertFalse($client->hasPendingRegistrations());

        $client->translatePage('<html><body><p>Walked text</p></body></html>');
        $client->translateContentBlock('<p>Fragment text</p>');
        $this->assertSame(['Walked text', 'Fragment text'], array_values(array_column($client->getPendingPhrases(), 'phrase')));
    }

    public function testControlByDefaultACallRegisters(): void
    {
        $client = $this->client(['__uncategorized__' => []]);

        $client->translate('Missing phrase');

        $this->assertSame(['Missing phrase'], array_values(array_column($client->getPendingPhrases(), 'phrase')));
    }
}
