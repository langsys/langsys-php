<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Messages\MessageCatalog;
use Langsys\SDK\Messages\MessageCatalogCommand;
use Langsys\SDK\Messages\ServerMessage;

/**
 * Content that moved between categories, against an API on the SDK test clone
 * that acts on `previous_category` (spec CONF-2): MOVE-1, MOVE-3, MOVE-4 and
 * MOVE-5, and MSG-6's renamed messages category.
 *
 * Content translated under `Header` is rendered under `Checkout`, naming
 * `Header` as its previous category, through each surface that takes a
 * category. While `Checkout` holds nothing, each renders `Header`'s
 * translation (MOVE-4), stamping a host it filled from it (MOVE-5). Each
 * registers under `Checkout` (MOVE-1), read back as the API answers it: the
 * item `Checkout` now serves holds `Header`'s translation as its own, which the
 * API copies only onto an item registered with that previous category. The
 * catalog's data form tells a copy from a translation-memory match of the same
 * text in another category, which it flags `memory_translated`, and the
 * control, an item translated under `Header` and registered under `Checkout`
 * naming no previous category, holds no translation of its own. Once the
 * catalog holds the moved items, a render queues nothing (MOVE-3).
 */
class MoveContractTest extends ContractTestCase
{
    protected static $seed = 'move-categories';

    const BLOCK = '<div><p>Moved block</p><p>Second paragraph</p></div>';

    const PAGE = '<html><body><section data-langsys-category="Checkout" data-langsys-previous-category="Header"><p>Add to cart</p></section></body></html>';

    private static function moved($previous = 'Header')
    {
        return ['category' => 'Checkout', 'previous_category' => $previous];
    }

    private static function blockId($category)
    {
        return (new HtmlParser())->generateCustomId($category, ['Moved block', 'Second paragraph']);
    }

    public function testMovedContentRendersFromItsPreviousCategoryAndTheApiMovesItsTranslations(): void
    {
        $world = $this->world('moved');
        $this->assertSame([], $this->served($world, 'Checkout'), 'Checkout holds nothing before the move');

        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('es-es');

        // MOVE-4: every surface renders the previous category's translation.
        $this->assertSame('Comprá ya', $client->translate('Buy now', null, self::moved()));
        $this->assertSame(['text' => 'Ingresá', 'from' => 'catalog'], $client->resolve('Sign in', null, self::moved()));
        $this->assertSame('Contáctanos', $client->translate('Contact us', null, self::moved('')), "'' reads the no-category entries");
        $this->assertSame('Not moved', $client->translate('Not moved', null, 'Checkout'), 'the control names no move and renders its source');

        // MOVE-5: a host filled from the previous category keeps its current id and says where it was filled from.
        $block = $client->translateContentBlock(self::BLOCK, self::moved());
        $this->assertStringContainsString('Bloque movido', $block);
        $this->assertStringContainsString('Segundo párrafo', $block);
        $this->assertStringContainsString('data-ls-contentblock="' . self::blockId('Checkout') . '"', $block);
        $this->assertStringContainsString('data-ls-previous-contentblock="' . self::blockId('Header') . '"', $block);

        $page = $client->translatePage(self::PAGE);
        $this->assertStringContainsString('<p data-ls-previous-category="Header">Agregar al carrito</p>', $page);

        $result = $client->flushPendingRegistrations();
        $this->assertTrue($result['success'], json_encode($result));
        $this->assertSame(['phrases' => 5, 'content_blocks' => 1], array_intersect_key($result, ['phrases' => 0, 'content_blocks' => 0]));

        // MOVE-1: the API holds each item under Checkout with Header's translation as its own.
        $checkout = $this->servedOnceRegistered($world, 'Checkout', ['Buy now', 'Sign in', 'Contact us', 'Add to cart', 'Not moved'], [self::blockId('Checkout')]);
        $this->assertSame('Comprá ya', self::own(self::phraseRow($checkout, 'Buy now')));
        $this->assertSame('Ingresá', self::own(self::phraseRow($checkout, 'Sign in')));
        $this->assertSame('Contáctanos', self::own(self::phraseRow($checkout, 'Contact us')), "'' moved it from the no-category entries");
        $this->assertSame('Agregar al carrito', self::own(self::phraseRow($checkout, 'Add to cart')), 'the page path names the move from its markup');
        $this->assertSame(['Moved block' => 'Bloque movido', 'Second paragraph' => 'Segundo párrafo'], self::ownBlock(self::blockRow($checkout, self::blockId('Checkout'))));
        $this->assertNull(self::own(self::phraseRow($checkout, 'Not moved')), 'the same API, given no previous category, copies nothing');
    }

    public function testOnceTheCatalogHoldsTheMovedItemsARenderQueuesNothing(): void
    {
        $world = $this->world('moved');

        $first = $this->client($world->key('write'), $world->project());
        $first->setLocale('es-es');
        $first->translate('Buy now', null, self::moved());
        $first->translateContentBlock(self::BLOCK, self::moved());
        $first->translatePage(self::PAGE);
        $this->assertSame(['phrases' => 2, 'content_blocks' => 1], array_intersect_key($first->flushPendingRegistrations(), ['phrases' => 0, 'content_blocks' => 0]));
        $this->servedOnceRegistered($world, 'Checkout', ['Buy now', 'Add to cart'], [self::blockId('Checkout')]);

        $later = $this->client($world->key('write'), $world->project());
        $later->setLocale('es-es');
        $this->assertSame('Comprá ya', $later->translate('Buy now', null, self::moved()));
        $block = $later->translateContentBlock(self::BLOCK, self::moved());
        $this->assertStringContainsString('Bloque movido', $block);
        $this->assertStringNotContainsString('data-ls-previous-contentblock', $block, 'filled from its current category now');
        $page = $later->translatePage(self::PAGE);
        $this->assertStringContainsString('Agregar al carrito', $page);
        $this->assertStringNotContainsString('data-ls-previous-category', $page);

        $this->assertFalse($later->hasPendingRegistrations(), 'an item the catalog holds under its current category is not registered again: ' . json_encode([$later->getPendingPhrases(), $later->getPendingContentBlocks()]));
        $this->assertSame(['phrases' => 0, 'content_blocks' => 0], array_intersect_key($later->flushPendingRegistrations(), ['phrases' => 0, 'content_blocks' => 0]));
    }

    public function testARenamedKeyGroupMovesEveryLineTheSyncRegisters(): void
    {
        $dir = sys_get_temp_dir() . '/langsys-move-contract-' . bin2hex(random_bytes(4));
        mkdir($dir . '/en', 0777, true);
        $checkout = $dir . '/en/checkout.php';
        $account = $dir . '/en/account.php';
        file_put_contents($checkout, "<?php return ['pay' => 'Pay', 'back' => 'Back'];");
        file_put_contents($account, "<?php return ['back' => 'Back'];");
        $options = ['migration' => ['files' => [$checkout, $account]], 'previous_categories' => ['checkout' => 'header']];

        try {
            $world = $this->world('renamed-group');

            // Rendering a checkout key the catalog holds only under header shows header's translation.
            $reader = $this->client($world->key('read'), $world->project(), null, $options);
            $reader->setLocale('es-es');
            $this->assertSame(['text' => 'Pagar', 'from' => 'catalog'], $reader->resolve('checkout.pay'));
            $this->assertSame(['text' => 'Back', 'from' => 'source'], $reader->resolve('account.back'), 'an unmapped group does not move');

            // A sync with no call naming either checkout line moves both by the map.
            $writer = $this->client($world->key('write'), $world->project(), null, $options);
            $writer->setLocale('en-us');
            $plan = $writer->planSync([]);
            $writer->applySync($plan);

            $served = $this->servedOnceRegistered($world, 'checkout', ['Pay', 'Back'], []);
            $this->assertSame('Pagar', self::own(self::phraseRow($served, 'Pay')));
            $this->assertSame('Volver', self::own(self::phraseRow($served, 'Back')));
            $unmapped = $this->servedOnceRegistered($world, 'account', ['Back'], []);
            $this->assertNull(self::own(self::phraseRow($unmapped, 'Back')), 'the account line, in no mapped group, is registered and copies nothing');
        } finally {
            @unlink($checkout);
            @unlink($account);
            @rmdir($dir . '/en');
            @rmdir($dir);
        }
    }

    public function testARenamedMessagesCategoryRendersFromThePreviousOneAndEveryRegistrationMovesIt(): void
    {
        $world = $this->world('renamed-messages');
        $renamed = ['messages_category' => 'Messages', 'messages_previous_category' => 'Errors'];
        $required = ServerMessage::make('required', 'The name is required.', [], 'name');

        $reader = $this->client($world->key('read'), $world->project(), null, $renamed);
        $reader->setLocale('es-es');
        $this->assertSame('El nombre es obligatorio.', $reader->translateMessage($required), 'the previous category renders while Messages holds nothing');

        // The listing command registers a listed template under Messages, naming Errors.
        $catalog = new MessageCatalog();
        $catalog->add('The email is invalid.', 'SampleApp\\SignupRequest', 'email');
        $lister = $this->client($world->key('write'), $world->project(), null, $renamed);
        $lister->setLocale('en-us');
        $this->assertSame(['registered' => 1, 'skipped' => 0], MessageCatalogCommand::register($catalog, $lister));

        // A template first emitted at runtime is registered the same way.
        $emitter = $this->client($world->key('write'), $world->project(), null, $renamed);
        $emitter->setLocale('es-es');
        $emitter->emitMessage($required);
        $emitter->flushPendingRegistrations();

        // The control: a client with no previous messages category.
        $unmoved = $this->client($world->key('write'), $world->project(), null, ['messages_category' => 'Messages']);
        $unmoved->setLocale('es-es');
        $unmoved->emitMessage(ServerMessage::make('expired', 'The code expired.'));
        $unmoved->flushPendingRegistrations();

        $messages = $this->servedOnceRegistered($world, 'Messages', ['The name is required.', 'The email is invalid.', 'The code expired.'], []);
        $this->assertSame('El nombre es obligatorio.', self::own(self::phraseRow($messages, 'The name is required.')));
        $this->assertSame('El correo no es válido.', self::own(self::phraseRow($messages, 'The email is invalid.')));
        $this->assertNull(self::own(self::phraseRow($messages, 'The code expired.')), 'registered naming no previous category, it copies nothing');
    }

    /**
     * What the catalog serves at es-es under `category`, in its data form, read
     * under the world's read key.
     *
     * @return array[]
     */
    private function served(World $world, $category)
    {
        $answer = $this->api('/translations/data?' . http_build_query(['project_id' => $world->project(), 'locale' => 'es-es']), ['key' => $world->key('read')]);
        $this->assertSame(200, $answer['status'], json_encode($answer['body']));

        $data = isset($answer['body']['data']) && is_array($answer['body']['data']) ? $answer['body']['data'] : [];

        return isset($data[$category]) && is_array($data[$category]) ? $data[$category] : [];
    }

    /**
     * The data form under `category` once it serves every named phrase and
     * block: the API moves a registration's translations before it answers,
     * and this waits a few seconds in case a clone defers the work.
     *
     * @return array[]
     */
    private function servedOnceRegistered(World $world, $category, array $phrases, array $blocks)
    {
        $deadline = microtime(true) + 15;
        do {
            $rows = $this->served($world, $category);
            $missing = array_merge(
                array_filter($phrases, function ($phrase) use ($rows) {
                    return self::phraseRow($rows, $phrase) === null;
                }),
                array_filter($blocks, function ($id) use ($rows) {
                    return self::blockRow($rows, $id) === null;
                })
            );
            if ($missing === []) {
                return $rows;
            }
            usleep(500000);
        } while (microtime(true) < $deadline);

        $this->fail("$category never served " . implode(', ', $missing) . ': ' . json_encode($rows, JSON_UNESCAPED_UNICODE));
    }

    private static function phraseRow(array $rows, $phrase)
    {
        foreach ($rows as $row) {
            if (isset($row['type'], $row['phrase']) && $row['type'] === 'phrase' && $row['phrase'] === $phrase) {
                return $row;
            }
        }

        return null;
    }

    private static function blockRow(array $rows, $id)
    {
        foreach ($rows as $row) {
            if (isset($row['type'], $row['custom_id']) && $row['type'] === 'content_block' && $row['custom_id'] === $id) {
                return $row;
            }
        }

        return null;
    }

    /** The translation an item holds itself, not a memory match from the same text in another category. */
    private static function own($row)
    {
        if ($row === null || !empty($row['memory_translated'])) {
            return null;
        }

        return isset($row['translation']) && $row['translation'] !== '' ? $row['translation'] : null;
    }

    /** @return array<string, string|null>|null */
    private static function ownBlock($row)
    {
        if ($row === null) {
            return null;
        }
        $own = [];
        foreach (isset($row['translations']) && is_array($row['translations']) ? $row['translations'] : [] as $phrase) {
            $own[$phrase['phrase']] = self::own($phrase);
        }
        ksort($own, SORT_STRING);

        return $own;
    }
}
