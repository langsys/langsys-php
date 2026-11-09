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

    public function testAPageNamingTheMoveAfterAnUnmovedCallStillSendsIt(): void
    {
        $previous = self::blockId('Header', ['Hello', 'world']);
        $client = $this->client(['Header' => ['Buy now' => 'Comprá ya', $previous => ['Hello' => 'Hola', 'world' => 'mundo']]]);
        $client->translate('Buy now', null, 'Checkout');
        $client->translateContentBlock('<p>Hello <b>world</b></p>', 'Checkout');
        $client->translate('Untouched', null, 'Checkout');

        $page = $client->translatePage('<html><body><p>Buy now</p><p>Hello <b>world</b></p></body></html>', $this->moved('Checkout', 'Header'));
        $this->assertStringContainsString('Comprá ya', $page);

        $client->flushPendingRegistrations();
        $sent = [];
        foreach ($this->sent() as $item) {
            $sent[$item['type'] === 'phrase' ? $item['phrase'] : $item['custom_id']] = isset($item['previous_category']) ? $item['previous_category'] : null;
        }
        $this->assertSame(['Buy now' => 'Header', 'Untouched' => null, self::blockId('Checkout', ['Hello', 'world']) => 'Header'], $sent, 'each sent once; the page adds the move only to what it rendered');
    }

    public function testARichLineFilledFromItsEarlierKeyStillTakesALaterMove(): void
    {
        $client = $this->client(['Checkout' => ['Call {m0o}ACME{m0c} now' => 'Llamá a {m0o}ACME{m0c} ya']]);
        $line = 'Call <span translate="no">ACME</span> now';
        $client->translateRich($line, null, 'Checkout');
        $client->translateRich($line, null, $this->moved('Checkout', 'Header'));
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

    /**
     * The README's and the skill's example: a selector on the menu's list
     * items, whose links are inline and never matched themselves.
     */
    public function testTheDocumentedNavLiSelectorMovesTheMenu(): void
    {
        $client = $this->client(['Header' => ['Home' => 'Inicio']]);

        $page = $client->translatePage(
            '<html><body><nav><ul><li><a href="#">Home</a></li><li><a href="#">About</a></li></ul></nav><p>Welcome</p></body></html>',
            $this->moved('home', 'landing'),
            ['nav li' => ['category' => 'Navigation', 'previous_category' => 'Header', 'overrideParentElementCategory' => true]]
        );

        $this->assertStringContainsString('Inicio', $page);
        $client->flushPendingRegistrations();
        $sent = [];
        foreach ($this->sent() as $item) {
            $sent[$item['category'] . '/' . $item['phrase']] = isset($item['previous_category']) ? $item['previous_category'] : null;
        }
        $this->assertSame('Header', $sent['Navigation/Home']);
        $this->assertSame('Header', $sent['Navigation/About']);
        $this->assertSame('landing', $sent['home/Welcome']);
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

    // 8.7.1: a category taken from configuration moves by configuration

    public function testARenamedKeyGroupMovesEveryLineItRegisters(): void
    {
        $file = $this->langFile('checkout.php', ['buy' => 'Buy now', 'pay' => 'Pay', 'back' => 'Back']);
        $other = $this->langFile('account.php', ['save' => 'Save']);
        // A runtime key in the group, so only the file reaches 'Pay' and 'Back';
        // one literal call names its own previous category.
        $hits = $this->hits("__('checkout.buy');\n__(\"checkout.\$k\");\n__('checkout.back');\n__('account.save');");
        $hits[2]['previous_category'] = 'Footer';

        $plan = Planner::offline($hits, ['files' => [$file, $other]], [], ['previous_categories' => ['checkout' => 'header']]);

        $items = [];
        foreach ($plan->items as $item) {
            $items[$item['category'] . '/' . $item['phrase']] = isset($item['previous_category']) ? $item['previous_category'] : null;
        }
        ksort($items);
        $this->assertSame([
            'account/Save' => null,
            'checkout/Back' => 'Footer',
            'checkout/Buy now' => 'header',
            'checkout/Pay' => 'header',
        ], $items, 'every checkout line moves, the one naming its own keeps it, another group does not move');
    }

    public function testAMapEntryNamingTheSameCategoryOrUncategorisedReadsAsWritten(): void
    {
        $hits = $this->hits("__('Loose');\n__('Other');");

        $plan = Planner::offline($hits, null, [], ['previous_categories' => ['' => 'Legacy', 'nothing' => 'nothing']]);

        $items = array_column($plan->items, null, 'phrase');
        $this->assertSame('Legacy', $items['Loose']['previous_category'], "'' names the uncategorised items");
        $this->assertSame('Legacy', $items['Other']['previous_category']);

        $plan = Planner::offline($hits, null, [], ['previous_categories' => ['__uncategorized__' => '']]);
        $this->assertCount(2, $plan->items);
        foreach ($plan->items as $item) {
            $this->assertArrayNotHasKey('previous_category', $item, 'uncategorised to uncategorised is no move');
        }
    }

    public function testPlanSyncPassesTheMapThroughToTheRegistration(): void
    {
        $file = $this->langFile('checkout.php', ['pay' => 'Pay']);
        $client = $this->mockClient([], ['migration' => ['files' => [$file]]], ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us', 'target_locales' => ['es-es']]);

        $client->applySync($client->planSync([], [], ['previous_categories' => ['checkout' => 'header']]));

        $sent = array_column($this->sent(), null, 'phrase');
        $this->assertSame('checkout', $sent['Pay']['category']);
        $this->assertSame('header', $sent['Pay']['previous_category']);
    }

    public function testAMappedMoveThatLeavesAUseBehindIsWarned(): void
    {
        $file = $this->langFile('checkout.php', ['buy' => 'Buy now']);
        $hits = $this->hits("__('Buy now');");

        $plan = Planner::offline($hits, ['files' => [$file]], [], ['previous_categories' => ['checkout' => '']]);

        $this->assertCount(1, $plan->leftBehind);
        $this->assertSame('checkout', $plan->leftBehind[0]['category']);
        $this->assertSame(['app.php:2'], $plan->leftBehind[0]['left_behind']);
    }

    private function messagesClient(array $catalog, array $options)
    {
        $client = $this->mockClient($catalog, $options);
        $client->setLocale('es-cr');

        return $client;
    }

    public function testARenamedMessagesCategoryRendersFromThePreviousOneAndRegistersTheMove(): void
    {
        $client = $this->messagesClient(['Errors' => ['The name is required.' => 'El nombre es obligatorio.']], [
            'messages_category' => 'Messages',
            'messages_previous_category' => 'Errors',
        ]);
        $message = \Langsys\SDK\Messages\ServerMessage::make('required', 'The name is required.');

        $this->assertSame('El nombre es obligatorio.', $client->translateMessage($message));
        $this->assertSame('El nombre es obligatorio.', $client->translateMessage($message), 'the queued miss still renders the previous translation');

        $client->flushPendingRegistrations();
        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('Messages', $sent[0]['category']);
        $this->assertSame('Errors', $sent[0]['previous_category']);
    }

    public function testTheMessagesCategoryWinsOnceItHasATranslation(): void
    {
        $client = $this->messagesClient([
            'Messages' => ['The name is required.' => 'Nuevo'],
            'Errors' => ['The name is required.' => 'Viejo'],
        ], ['messages_category' => 'Messages', 'messages_previous_category' => 'Errors']);

        $this->assertSame('Nuevo', $client->translateMessage(\Langsys\SDK\Messages\ServerMessage::make('required', 'The name is required.')));
        $client->flushPendingRegistrations();
        $this->assertSame([], $this->sent(), 'listed under the messages category, so nothing registers');
    }

    public function testWithoutAPreviousMessagesCategoryNothingChanges(): void
    {
        $client = $this->messagesClient(['Errors' => ['The name is required.' => 'Viejo']], ['messages_category' => 'Messages']);

        $this->assertSame('The name is required.', $client->translateMessage(\Langsys\SDK\Messages\ServerMessage::make('required', 'The name is required.')));
        $client->flushPendingRegistrations();
        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertArrayNotHasKey('previous_category', $sent[0]);
    }

    public function testEmitMessageRegistersTheMove(): void
    {
        $client = $this->messagesClient([], ['messages_category' => 'Messages', 'messages_previous_category' => '']);

        $client->emitMessage(\Langsys\SDK\Messages\ServerMessage::make('required', 'The name is required.'));
        $client->flushPendingRegistrations();

        $sent = $this->sent();
        $this->assertCount(1, $sent);
        $this->assertSame('', $sent[0]['previous_category'], "'' says the templates had no category");
    }

    public function testTheListingCommandRegistersTemplatesWithThePreviousMessagesCategory(): void
    {
        $client = $this->messagesClient(['Messages' => ['Listed.' => 'Listado.']], ['messages_category' => 'Messages', 'messages_previous_category' => 'Errors']);
        $catalog = new \Langsys\SDK\Messages\MessageCatalog();
        $catalog->add('Listed.', 'App\\A');
        $catalog->add('The name is required.', 'App\\A');

        $result = \Langsys\SDK\Messages\MessageCatalogCommand::register($catalog, $client);

        $this->assertSame(['registered' => 1, 'skipped' => 1], $result);
        $sent = $this->sent();
        $this->assertSame('The name is required.', $sent[0]['phrase']);
        $this->assertSame('Messages', $sent[0]['category']);
        $this->assertSame('Errors', $sent[0]['previous_category']);
    }

    public function testThePreviousMessagesCategoryComesFromTheEnvironmentAndEmptyIsUnset(): void
    {
        putenv('LANGSYS_MESSAGES_PREVIOUS_CATEGORY=Errors');
        try {
            $this->assertSame('Errors', (new \Langsys\SDK\Config())->getMessagesPreviousCategory());
            $this->assertSame('', (new \Langsys\SDK\Config(['messages_previous_category' => '']))->getMessagesPreviousCategory(), 'the option wins, and its empty value is a value');
            putenv('LANGSYS_MESSAGES_PREVIOUS_CATEGORY=');
            $this->assertNull((new \Langsys\SDK\Config())->getMessagesPreviousCategory());
        } finally {
            putenv('LANGSYS_MESSAGES_PREVIOUS_CATEGORY');
        }
        $this->assertNull((new \Langsys\SDK\Config())->getMessagesPreviousCategory());
    }

    public function testCallsComeBeforeTheMapAndConflictingCallsAreReportedInReadOrder(): void
    {
        $file = $this->langFile('checkout.php', ['a' => 'Alpha', 'b' => 'Beta', 'c' => 'Gamma', 'd' => 'Delta']);
        $first = (new SourceScanner())->scan("<?php\n__('checkout.a');\n__('checkout.b');\n__('checkout.c');", 'a.php');
        $second = (new SourceScanner())->scan("<?php\n__('checkout.a');", 'b.php');
        $first[0]['previous_category'] = 'nav';       // Alpha: conflicts with b.php's 'side'
        $first[1]['previous_category'] = '';          // Beta: '' names a move
        $first[2]['previous_category'] = 'checkout';  // Gamma: its own category names none
        $second[0]['previous_category'] = 'side';

        // b.php's hit arrives first, but a.php is read first.
        $plan = Planner::offline(array_merge($second, $first), ['files' => [$file]], [], ['previous_categories' => ['checkout' => 'header']]);

        $items = array_column($plan->items, null, 'phrase');
        $this->assertSame('nav', $items['Alpha']['previous_category'], 'the first in path and line order wins');
        $this->assertSame('', $items['Beta']['previous_category'], "a call naming '' outranks the map");
        $this->assertSame('header', $items['Gamma']['previous_category'], 'a call naming its own category names none, so the map applies');
        $this->assertSame('header', $items['Delta']['previous_category'], 'a line no call names takes the map');

        $this->assertCount(1, $plan->moveConflicts);
        $this->assertSame('Alpha', $plan->moveConflicts[0]['phrase']);
        $this->assertSame(['nav', 'side'], $plan->moveConflicts[0]['previous_categories']);
        $this->assertSame(['a.php:2 => nav', 'b.php:2 => side'], $plan->moveConflicts[0]['origins']);
        $this->assertTrue($plan->failsStrict(), 'a conflict fails a strict sync');
    }

    public function testAgreeingCallsAreNoConflictAndANullMapValueIsNoMove(): void
    {
        $file = $this->langFile('checkout.php', ['a' => 'Alpha', 'b' => 'Beta']);
        $hits = $this->hits("__('checkout.a');\n__('checkout.a');");
        $hits[0]['previous_category'] = 'nav';
        $hits[1]['previous_category'] = 'nav';

        $plan = Planner::offline($hits, ['files' => [$file]], [], ['previous_categories' => ['checkout' => null]]);

        $items = array_column($plan->items, null, 'phrase');
        $this->assertSame('nav', $items['Alpha']['previous_category']);
        $this->assertArrayNotHasKey('previous_category', $items['Beta'], 'a null value is no move');
        $this->assertSame([], $plan->moveConflicts);
        $this->assertFalse($plan->failsStrict());
    }

    public function testTheConfiguredMapMovesAPhraseAtRender(): void
    {
        $client = $this->mockClient(['header' => ['Pay' => 'Pagar'], 'Legacy' => ['Loose' => 'Suelto']], [
            'previous_categories' => ['checkout' => 'header', '' => 'Legacy'],
        ]);
        $client->setLocale('es-cr');

        $this->assertSame('Pagar', $client->translate('Pay', null, 'checkout'), 'an explicit string category takes the map');
        $this->assertSame('Suelto', $client->translate('Loose'), 'so does a literal with no category');
        $this->assertSame('Pay', $client->translate('Pay', null, ['category' => 'checkout', 'previous_category' => 'nav']), 'a call naming its own move outranks the map');
        $this->assertSame('Total', $client->translate('Total', null, 'other'), 'an unmapped category does not move');

        $client->flushPendingRegistrations();
        $sent = [];
        foreach ($this->sent() as $item) {
            $sent[$item['category'] . '/' . $item['phrase']] = isset($item['previous_category']) ? $item['previous_category'] : null;
        }
        $this->assertSame(['checkout/Pay' => 'header', '/Loose' => 'Legacy', 'other/Total' => null], $sent, 'the first call queued Pay with the map entry');
    }

    public function testTheConfiguredMapMovesAKeysGroupAtRender(): void
    {
        $file = $this->langFile('checkout.php', ['pay' => 'Pay']);
        $client = $this->mockClient(['header' => ['Pay' => 'Pagar']], [
            'migration' => ['files' => [$file]],
            'previous_categories' => ['checkout' => 'header'],
        ]);
        $client->setLocale('es-cr');

        $this->assertSame(['text' => 'Pagar', 'from' => 'catalog'], $client->resolve('checkout.pay'));
        $client->flushPendingRegistrations();
        $sent = $this->sent();
        $this->assertSame('checkout', $sent[0]['category']);
        $this->assertSame('header', $sent[0]['previous_category']);
    }

    public function testPlanSyncDefaultsToTheConfiguredMap(): void
    {
        $file = $this->langFile('checkout.php', ['pay' => 'Pay']);
        $auth = ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us', 'target_locales' => ['es-es']];
        $client = $this->mockClient([], ['migration' => ['files' => [$file]], 'previous_categories' => ['checkout' => 'header']], $auth);

        $items = array_column($client->planSync([])->items, null, 'phrase');
        $this->assertSame('header', $items['Pay']['previous_category']);

        $items = array_column($client->planSync([], [], ['previous_categories' => []])->items, null, 'phrase');
        $this->assertArrayNotHasKey('previous_category', $items['Pay'], 'an explicit option replaces the configured map');
    }

    public function testTheMessagesPreviousCategoryWinsOverTheMapAndTheMapCoversMessages(): void
    {
        $catalog = ['Errors' => ['The name is required.' => 'De Errors'], 'Old' => ['The name is required.' => 'De Old']];
        $message = \Langsys\SDK\Messages\ServerMessage::make('required', 'The name is required.');

        $mapOnly = $this->messagesClient($catalog, ['messages_category' => 'Messages', 'previous_categories' => ['Messages' => 'Old']]);
        $this->assertSame('De Old', $mapOnly->translateMessage($message), 'the map covers the messages category');

        $both = $this->messagesClient($catalog, ['messages_category' => 'Messages', 'messages_previous_category' => 'Errors', 'previous_categories' => ['Messages' => 'Old']]);
        $this->assertSame('De Errors', $both->translateMessage($message), 'messages_previous_category wins');
        $this->assertSame('Errors', $both->getConfig()->getMessagesPreviousCategory());
    }

    public function testInASyncTheMessagesPreviousCategoryWinsOverTheMapEntryForTheMessagesCategory(): void
    {
        $file = $this->langFile('Messages.php', ['required' => 'The name is required.']);
        $auth = ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us', 'target_locales' => ['es-es']];
        $client = $this->mockClient([], ['migration' => ['files' => [$file]], 'messages_category' => 'Messages', 'messages_previous_category' => 'Errors'], $auth);

        $items = array_column($client->planSync([], [], ['previous_categories' => ['Messages' => 'Old']])->items, null, 'phrase');

        $this->assertSame('Messages', $items['The name is required.']['category']);
        $this->assertSame('Errors', $items['The name is required.']['previous_category']);
    }

    public function testAKeyCallsOwnMoveIsReadAgainstItsGroup(): void
    {
        $file = $this->langFile('checkout.php', ['pay' => 'Pay', 'back' => 'Back']);
        $client = $this->mockClient(['header' => ['Pay' => 'Pagar', 'Back' => 'Volver'], '__uncategorized__' => ['Pay' => 'Pago suelto']], [
            'migration' => ['files' => [$file]],
            'previous_categories' => ['checkout' => 'header'],
        ]);
        $client->setLocale('es-cr');

        $this->assertSame('Pago suelto', $client->resolve('checkout.pay', null, ['previous_category' => ''])['text'], "'' names a move, which outranks the map");
        $this->assertSame('Volver', $client->resolve('checkout.back', null, ['previous_category' => 'checkout'])['text'], 'naming its own group names none, so the map applies');

        $client->flushPendingRegistrations();
        $sent = array_column($this->sent(), null, 'phrase');
        $this->assertSame('', $sent['Pay']['previous_category'], 'the same move a sync of that hit plans');
        $this->assertSame('header', $sent['Back']['previous_category']);
    }

    public function testAnExplicitSyncMapIsNotOverriddenByTheConfiguredMapsMessagesEntry(): void
    {
        $file = $this->langFile('Messages.php', ['required' => 'The name is required.']);
        $auth = ['key_type' => 'write', 'write_enabled' => true, 'base_locale' => 'en-us', 'target_locales' => ['es-es']];
        $client = $this->mockClient([], ['migration' => ['files' => [$file]], 'messages_category' => 'Messages', 'previous_categories' => ['Messages' => 'Old']], $auth);

        $default = array_column($client->planSync([])->items, null, 'phrase');
        $this->assertSame('Old', $default['The name is required.']['previous_category'], 'the configured map by default');

        $none = array_column($client->planSync([], [], ['previous_categories' => []])->items, null, 'phrase');
        $this->assertArrayNotHasKey('previous_category', $none['The name is required.']);

        $other = array_column($client->planSync([], [], ['previous_categories' => ['Messages' => 'X']])->items, null, 'phrase');
        $this->assertSame('X', $other['The name is required.']['previous_category']);
    }

    public function testAnOfflinePlanTakesTheMessagesPreviousCategoryOverTheMap(): void
    {
        $file = $this->langFile('Messages.php', ['oops' => 'Oops']);
        $second = $this->hits("__('Messages.oops');");
        $second[0]['previous_category'] = 'Legacy';

        $plan = Planner::offline($second, ['files' => [$file]], [], [
            'previous_categories' => ['Messages' => 'Old'],
            'messages_category' => 'Messages',
            'messages_previous_category' => 'Errors',
        ]);

        $this->assertSame('Legacy', $plan->items[0]['previous_category'], "the call's own move still comes first");

        $plan = Planner::offline($this->hits("__('Messages.oops');"), ['files' => [$file]], [], [
            'previous_categories' => ['Messages' => 'Old'],
            'messages_category' => 'Messages',
            'messages_previous_category' => 'Errors',
        ]);
        $this->assertSame('Errors', $plan->items[0]['previous_category'], 'messages_previous_category wins over the map entry offline');

        $plan = Planner::offline($this->hits("__('Messages.oops');"), ['files' => [$file]], [], ['messages_category' => 'Messages']);
        $this->assertArrayNotHasKey('previous_category', $plan->items[0], 'no move without one');
    }

    public function testAConflictOriginNamingNoCategoryShowsIt(): void
    {
        $file = $this->langFile('checkout.php', ['a' => 'Alpha']);
        $hits = $this->hits("__('checkout.a');\n__('checkout.a');");
        $hits[0]['previous_category'] = '';
        $hits[1]['previous_category'] = 'nav';

        $plan = Planner::offline($hits, ['files' => [$file]]);

        $this->assertSame(["app.php:2 => ''", 'app.php:3 => nav'], $plan->moveConflicts[0]['origins']);
    }
}
