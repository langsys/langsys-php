<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Sync\Planner;
use Langsys\SDK\Sync\SourceScanner;
use Langsys\SDK\Tests\Support\BuildsMockClient;
use PHPUnit\Framework\TestCase;

/**
 * MOVE-1..MOVE-5: content that moved between categories names the category
 * it came from, sends it on the registration a miss triggers, never lets it
 * into identity, and renders from it until the new category is in the catalog.
 *
 * Every absence below is paired with the registration or render that proves
 * the path ran.
 */
class MoveTest extends TestCase
{
    use BuildsMockClient;

    /** @var string|null */
    private $dir;

    protected function tearDown(): void
    {
        if ($this->dir === null) {
            return;
        }
        foreach (glob($this->dir . '/*/*') as $file) {
            unlink($file);
        }
        foreach (glob($this->dir . '/*') as $sub) {
            rmdir($sub);
        }
        rmdir($this->dir);
    }

    private function moved($category, $previous)
    {
        return ['category' => $category, 'previous_category' => $previous];
    }

    /**
     * Every item POSTed to translatable-items.
     *
     * @return array[]
     */
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

    private function client(array $catalog)
    {
        $client = $this->mockClient($catalog);
        $client->setLocale('es-cr');

        return $client;
    }

    private static function blockId($category, array $tokens)
    {
        return (new HtmlParser())->generateCustomId($category, $tokens);
    }

    // MOVE-1

    public function testAMovedPhraseRegistersWithItsPreviousCategory(): void
    {
        $client = $this->client(['Header' => ['Sign in' => 'Ingresar']]);
        $client->translate('Buy now', null, $this->moved('Checkout', 'Header'));
        $client->translate('No category before', null, $this->moved('Checkout', ''));
        $client->translate('Did not move', null, 'Checkout');
        $client->flushPendingRegistrations();

        $sent = array_column($this->sent(), null, 'phrase');
        $this->assertCount(3, $sent, 'all three misses registered');
        $this->assertSame('Checkout', $sent['Buy now']['category']);
        $this->assertSame('Header', $sent['Buy now']['previous_category']);
        $this->assertSame('', $sent['No category before']['previous_category'], "'' is a value: it had no category before");
        $this->assertSame('Checkout', $sent['Did not move']['category']);
        $this->assertArrayNotHasKey('previous_category', $sent['Did not move']);
    }

    public function testAPreviousCategoryEqualToTheCategoryIsNoMove(): void
    {
        $client = $this->client([]);
        $client->translate('Same', null, $this->moved('Checkout', 'Checkout'));
        $client->translate('Uncategorised both', null, $this->moved(null, ''));
        $client->translate('Null previous', null, $this->moved('Checkout', null));
        $client->flushPendingRegistrations();

        $sent = $this->sent();
        $this->assertCount(3, $sent);
        foreach ($sent as $item) {
            $this->assertArrayNotHasKey('previous_category', $item, $item['phrase']);
        }
    }

    public function testResolveAndTranslateRichTakeTheMove(): void
    {
        $client = $this->client(['Header' => ['Buy now' => 'Comprá ya', 'Read the {m0o}terms{m0c}' => 'Leé los {m0o}términos{m0c}']]);

        $this->assertSame(['text' => 'Comprá ya', 'from' => 'catalog'], $client->resolve('Buy now', null, $this->moved('Checkout', 'Header')));
        $rich = $client->translateRich('Read the <a href="/t">terms</a>', null, $this->moved('Checkout', 'Header'));
        $this->assertSame('Leé los <a href="/t">términos</a>', $rich['html']);

        $client->flushPendingRegistrations();
        $sent = array_column($this->sent(), 'previous_category', 'phrase');
        $this->assertSame(['Buy now' => 'Header', 'Read the {m0o}terms{m0c}' => 'Header'], $sent);
    }

    public function testRegisterContentBlockAndRegisterPhrasesSendIt(): void
    {
        $client = $this->client([]);
        $client->registerContentBlock('<p>One</p><p>Two</p>', $this->moved('Checkout', 'Header'));
        $client->registerPhrases([['phrase' => 'Three', 'category' => 'Checkout', 'previous_category' => 'Header'], ['phrase' => 'Four', 'category' => 'Checkout']]);

        $sent = $this->sent();
        $this->assertCount(3, $sent);
        $this->assertSame('content_block', $sent[0]['type']);
        $this->assertSame('Header', $sent[0]['previous_category']);
        $this->assertSame('Header', $sent[1]['previous_category']);
        $this->assertArrayNotHasKey('previous_category', $sent[2]);
    }

    // MOVE-2

    public function testThePreviousCategoryIsNeverPartOfABlocksIdentity(): void
    {
        $html = '<p>Hello <b>world</b></p>';
        $current = self::blockId('Checkout', ['Hello', 'world']);

        $moved = $this->client([]);
        $moved->translateContentBlock($html, $this->moved('Checkout', 'Header'));
        $plain = $this->client([]);
        $plain->translateContentBlock($html, 'Checkout');

        $this->assertSame([$current], array_keys($moved->getPendingContentBlocks()));
        $this->assertSame(array_keys($plain->getPendingContentBlocks()), array_keys($moved->getPendingContentBlocks()));
        $this->assertSame(['Checkout::Buy now'], array_keys($this->queuedPhrase('Buy now', $this->moved('Checkout', 'Header'))));
    }

    private function queuedPhrase($phrase, $category)
    {
        $client = $this->client([]);
        $client->translate($phrase, null, $category);

        return $client->getPendingPhrases();
    }

    // MOVE-3

    public function testAnItemTheCatalogHasUnderItsCategoryIsNotRegisteredAgain(): void
    {
        $client = $this->client(['Checkout' => ['Buy now' => 'Comprar']]);

        $this->assertSame('Comprar', $client->translate('Buy now', null, $this->moved('Checkout', 'Header')));
        $client->translate('Pay', null, $this->moved('Checkout', 'Header'));
        $client->flushPendingRegistrations();

        $this->assertSame(['Pay'], array_column($this->sent(), 'phrase'), 'the miss registered; the hit did not');
    }

    public function testTheFirstPreviousCategoryNamedIsKept(): void
    {
        $client = $this->client([]);
        $client->translate('Buy now', null, $this->moved('Checkout', 'Header'));
        $client->translate('Buy now', null, 'Checkout');
        $client->translate('Pay', null, 'Checkout');
        $client->translate('Pay', null, $this->moved('Checkout', 'Footer'));
        $client->translate('Pay', null, $this->moved('Checkout', 'Header'));
        $client->flushPendingRegistrations();

        $sent = array_column($this->sent(), 'previous_category', 'phrase');
        $this->assertSame(['Buy now' => 'Header', 'Pay' => 'Footer'], $sent, 'sent once each; a later call naming none never clears it');
    }

    public function testAMovedBlockQueuedTwiceKeepsTheFirstPreviousCategory(): void
    {
        $client = $this->client([]);
        $client->translateContentBlock('<p>Hello <b>world</b></p>', 'Checkout');
        $client->translateContentBlock('<p>Hello <b>world</b></p>', $this->moved('Checkout', 'Header'));
        $client->flushPendingRegistrations();

        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('Header', $sent[0]['previous_category']);
    }

    // MOVE-4

    public function testAPhraseRendersFromItsPreviousCategoryWhileItRegistersUnderTheNewOne(): void
    {
        $client = $this->client(['Header' => ['Buy now' => 'Comprá ya']]);

        $this->assertSame('Comprá ya', $client->translate('Buy now', null, $this->moved('Checkout', 'Header')));
        $this->assertSame('Comprá ya', $client->translate('Buy now', null, $this->moved('Checkout', 'Header')), 'a second render in the request, now queued, matches the first');
        $this->assertSame('Buy now', $client->translate('Buy now', null, 'Checkout'), 'a call that names no move reads only its own category');

        $client->flushPendingRegistrations();
        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame(['phrase' => 'Buy now', 'category' => 'Checkout', 'previous_category' => 'Header'], array_intersect_key($sent[0], array_flip(['phrase', 'category', 'previous_category'])));
    }

    public function testTheCurrentCategoryOutranksThePreviousOne(): void
    {
        $client = $this->client(['Checkout' => ['Buy now' => 'Comprar', 'Pay' => null], 'Header' => ['Buy now' => 'Comprá ya', 'Pay' => 'Pagá']]);

        $this->assertSame('Comprar', $client->translate('Buy now', null, $this->moved('Checkout', 'Header')));
        $this->assertSame('Pagá', $client->translate('Pay', null, $this->moved('Checkout', 'Header')), 'present with no translation reads the previous category');
    }

    public function testAnEmptyPreviousCategoryReadsTheNoCategoryBucket(): void
    {
        $client = $this->client(['__uncategorized__' => ['Buy now' => 'Comprá ya'], '' => ['Buy now' => 'wrong bucket']]);

        $this->assertSame('Comprá ya', $client->translate('Buy now', null, $this->moved('Checkout', '')));
    }

    public function testABlockRendersFromItsPreviousCategoryAndSaysSo(): void
    {
        $html = '<p>Hello <b>world</b></p>';
        $previous = self::blockId('Header', ['Hello', 'world']);
        $current = self::blockId('Checkout', ['Hello', 'world']);
        $client = $this->client(['Header' => [$previous => ['Hello' => 'Hola', 'world' => 'mundo']]]);

        $rendered = $client->translateContentBlock($html, $this->moved('Checkout', 'Header'));

        $this->assertStringContainsString('Hola', $rendered);
        $this->assertStringContainsString('mundo', $rendered);
        $this->assertStringContainsString('data-ls-contentblock="' . $current . '"', $rendered, 'MARK-1: its current identity');
        $this->assertStringContainsString('data-ls-previous-contentblock="' . $previous . '"', $rendered, 'MOVE-5: the identity it was filled from');

        $client->flushPendingRegistrations();
        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame($current, $sent[0]['custom_id']);
        $this->assertSame('Header', $sent[0]['previous_category']);
    }

    public function testABlockFilledFromItsCurrentCategoryCarriesNoPreviousStamp(): void
    {
        $current = self::blockId('Checkout', ['Hello', 'world']);
        $client = $this->client(['Checkout' => [$current => ['Hello' => 'Hola', 'world' => 'mundo']]]);

        $rendered = $client->translateContentBlock('<p>Hello <b>world</b></p>', $this->moved('Checkout', 'Header'));

        $this->assertStringContainsString('data-ls-contentblock="' . $current . '"', $rendered);
        $this->assertStringContainsString('Hola', $rendered);
        $this->assertStringNotContainsString('previous', $rendered);
    }

    public function testAOnePhraseFragmentFilledFromItsPreviousCategoryIsStamped(): void
    {
        $client = $this->client(['Header' => ['Buy now' => 'Comprá ya']]);

        $rendered = $client->translateContentBlock('<p>Buy now</p>', $this->moved('Checkout', 'Header'));

        $this->assertSame('<p data-ls-previous-category="Header">Comprá ya</p>', $rendered);
    }

    public function testAHostStampedWithAPreviousIdRendersFromIt(): void
    {
        $previous = self::blockId('Header', ['Hello', 'world']);
        $client = $this->client(['Header' => [$previous => ['Hello' => 'Hola', 'world' => 'mundo']]]);

        $rendered = $client->translateContentBlock('<div data-ls-contentblock="current-id" data-langsys-previous-contentblock="' . $previous . '"><p>Hello <b>world</b></p></div>', 'Checkout');

        $this->assertStringContainsString('Hola', $rendered);
        $client->flushPendingRegistrations();
        $sent = $this->sent();
        $this->assertCount(1, $sent, 'the current id the catalog lacks registers');
        $this->assertSame('current-id', $sent[0]['custom_id']);
        $this->assertSame('Header', $sent[0]['previous_category'], 'moved from the category holding the stamped id');
    }

    // Pages

    public function testAPageSectionNamesItsPreviousCategory(): void
    {
        $client = $this->client(['Header' => ['Buy now' => 'Comprá ya']]);

        $page = $client->translatePage('<html><body><section data-langsys-category="Checkout" data-langsys-previous-category="Header"><p>Buy now</p></section><p>Stays</p></body></html>', 'home');

        $this->assertStringContainsString('<p data-ls-previous-category="Header">Comprá ya</p>', $page);
        $client->flushPendingRegistrations();
        $sent = array_column($this->sent(), null, 'phrase');
        $this->assertSame('Checkout', $sent['Buy now']['category']);
        $this->assertSame('Header', $sent['Buy now']['previous_category']);
        $this->assertSame('home', $sent['Stays']['category']);
        $this->assertArrayNotHasKey('previous_category', $sent['Stays']);
    }

    public function testAPagesDefaultCategoryAndASelectorCanMove(): void
    {
        $client = $this->client(['old-home' => ['Welcome' => 'Bienvenido'], 'Header' => ['Sign in' => 'Ingresar']]);

        $page = $client->translatePage(
            '<html><head><title>Welcome</title></head><body><p>Welcome</p><nav><p>Sign in</p></nav></body></html>',
            $this->moved('home', 'old-home'),
            ['nav' => ['category' => 'Navigation', 'previous_category' => 'Header', 'overrideParentElementCategory' => true]]
        );

        $this->assertStringContainsString('<title>Bienvenido</title>', $page);
        $this->assertStringContainsString('>Bienvenido</p>', $page);
        $this->assertStringContainsString('>Ingresar</p>', $page);
        $client->flushPendingRegistrations();
        $sent = [];
        foreach ($this->sent() as $item) {
            $sent[$item['category'] . '/' . $item['phrase']] = isset($item['previous_category']) ? $item['previous_category'] : null;
        }
        $this->assertSame(['home/Welcome' => 'old-home', 'Navigation/Sign in' => 'Header'], $sent);
    }

    public function testAPageBlockRendersFromItsPreviousCategory(): void
    {
        $previous = self::blockId('Header', ['Hello', 'world']);
        $client = $this->client(['Header' => [$previous => ['Hello' => 'Hola', 'world' => 'mundo']]]);

        $page = $client->translatePage('<html><body><p data-langsys-category="Checkout" data-langsys-previous-category="Header">Hello <b>world</b></p></body></html>');

        $this->assertStringContainsString('Hola', $page);
        $this->assertStringContainsString('data-ls-previous-contentblock="' . $previous . '"', $page);
        $client->flushPendingRegistrations();
        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame(self::blockId('Checkout', ['Hello', 'world']), $sent[0]['custom_id']);
        $this->assertSame('Header', $sent[0]['previous_category']);
    }

    // Sync (FRM-2)

    private function hits($code)
    {
        return (new SourceScanner())->scan("<?php\n" . $code, 'app.php');
    }

    private function langFile($name, array $lines)
    {
        $this->dir = $this->dir ?: sys_get_temp_dir() . '/langsys-move-' . bin2hex(random_bytes(4));
        if (!is_dir($this->dir . '/en')) {
            mkdir($this->dir . '/en', 0777, true);
        }
        $path = $this->dir . '/en/' . $name;
        file_put_contents($path, '<?php return ' . var_export($lines, true) . ';');

        return $path;
    }

    public function testASyncHitCarriesItsPreviousCategoryToTheRegistration(): void
    {
        $client = $this->mockClient([], [], ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us', 'target_locales' => ['es-es']]);
        $hits = $this->hits("__('Buy now');\n__('Known');\n__('Stays');");
        $hits[0]['previous_category'] = 'Header';
        $hits[1]['previous_category'] = 'Header';
        $this->http->setResponse('GET', 'translations', ['data' => ['__uncategorized__' => ['Known' => 'Conocido']]]);

        $plan = $client->planSync($hits);
        $items = array_column($plan->items, null, 'phrase');
        $this->assertSame('Header', $items['Buy now']['previous_category']);
        $this->assertSame('in_catalog', $items['Known']['status']);

        $client->applySync($plan);
        $sent = array_column($this->sent(), null, 'phrase');
        $this->assertSame(['Buy now', 'Stays'], array_keys($sent), 'the item in the catalog is not sent again');
        $this->assertSame('Header', $sent['Buy now']['previous_category']);
        $this->assertArrayNotHasKey('previous_category', $sent['Stays']);
    }

    public function testASyncWarnsWhenAMoveLeavesAUseBehind(): void
    {
        $file = $this->langFile('checkout.php', ['buy' => 'Buy now', 'pay' => 'Pay']);
        $hits = $this->hits("__('checkout.buy');\n__('checkout.pay');\n__('Buy now');");
        $hits[0]['previous_category'] = '';
        $hits[1]['previous_category'] = '';

        $plan = Planner::offline($hits, ['files' => [$file]]);

        $this->assertSame([[
            'phrase' => 'Buy now',
            'category' => 'checkout',
            'previous_category' => '',
            'origins' => ['app.php:2', 'checkout.buy'],
            'left_behind' => ['app.php:4'],
        ]], $plan->leftBehind, 'Pay moved with no use left behind; Buy now still has one');
        $this->assertFalse($plan->failsStrict(), 'a warning, not a failure');
    }
}
