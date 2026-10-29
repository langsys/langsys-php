<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Html\HtmlParser;

/**
 * Rows whose property turns on what the server answers - the catalog's shapes,
 * the write decision across requests, the wire - proven against the contract
 * fixture. Where the API's idempotence would hide a duplicate registration,
 * the test reads the SDK's own queue under the API's real answer, never a
 * record kept by the fixture.
 */
class CatalogAndWireContractTest extends ContractTestCase
{
    protected static $seed = 'catalog-and-wire';

    /** @var string[] */
    private $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    private function sharedCache()
    {
        $dir = sys_get_temp_dir() . '/langsys-contract-' . bin2hex(random_bytes(4));
        $this->dirs[] = $dir;

        return [new FileCache($dir), $dir];
    }

    /**
     * GATE-3: the write decision lives for one request. Two requests share a
     * cache; between them the case moves off the key's allow-list, and the
     * second request follows the API rather than the first request's answer.
     */
    public function testTheWriteDecisionIsNotCarriedIntoTheNextRequest(): void
    {
        list($cache) = $this->sharedCache();
        $world = $this->world('on-list');

        $this->assertTrue($this->client($world->key('ip'), $world->project(), $cache)->canWrite());

        $this->update($world, ['caller' => $world->moved]);
        $second = $this->client($world->key('ip'), $world->project(), $cache);
        $second->setLocale('es-es');
        $second->translate('Second request');
        $second->flushPendingRegistrations();

        $this->assertFalse($second->canWrite());
        $this->assertSame([], self::anyPhrase($this->held($world->key('ip'), $world->project(), 'en-us')));
    }

    /**
     * GATE-4: the flag the server sends is never part of anything cached. The
     * control is that the decision really came from the flag: an ip_write key
     * can only be answered by it.
     */
    public function testNothingCachedCarriesTheWriteDecision(): void
    {
        list($cache, $dir) = $this->sharedCache();
        $world = $this->world('on-list-hello');

        $client = $this->client($world->key('ip'), $world->project(), $cache);
        $client->setLocale('es-es');
        $this->assertTrue($client->canWrite(), 'control: the decision came from the server flag');
        $this->assertSame('Hola', $client->translate('Hello'));

        $files = glob($dir . '/*') ?: [];
        $this->assertNotEmpty($files, 'control: the authorization and the catalog were cached');
        foreach ($files as $file) {
            $this->assertStringNotContainsString('write_enabled', (string) file_get_contents($file), basename($file));
        }
    }

    /**
     * CAT-1 and CAT-2: a phrase the server holds without a translation reads back
     * present with null. It is known - not queued - and renders as source.
     */
    public function testARegisteredButUntranslatedPhraseIsKnownAndRendersAsSource(): void
    {
        $world = $this->world('registered');

        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('es-es');

        $this->assertSame('Registered', $client->translate('Registered'));
        $this->assertSame('<p>Registered</p>', $this->body($client->translatePage('<html><body><p>Registered</p></body></html>')));
        $this->assertFalse($client->hasPendingRegistrations(), 'present with null is known, not a miss');

        $client->translate('Unknown');
        $this->assertTrue($client->hasPendingRegistrations(), 'control: a real miss is queued');
    }

    /**
     * CAT-3: a block the server holds without translations reads back as an
     * object whose phrases are null. It is a registered block: not queued again,
     * rendered as source.
     */
    public function testARegisteredButUntranslatedBlockIsKnownAndRendersAsSource(): void
    {
        $world = $this->world('untranslated-block');

        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('es-es');

        $this->assertStringContainsString('<p>Café</p><p>Thé</p>', $client->translateContentBlock('<div><p>Café</p><p>Thé</p></div>', 'UI'));
        $this->assertFalse($client->hasPendingRegistrations());
    }

    /**
     * REG-12: presence decides "known", so page text that happens to equal a
     * block's id is not registered as a phrase.
     */
    public function testTextCollidingWithABlockIdIsNotRegisteredAsAPhrase(): void
    {
        $id = (new HtmlParser())->generateCustomId('UI', ['One', 'Two']);
        $world = $this->world('block-id-text');

        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('es-es');
        $client->translatePage("<html><body><p data-langsys-category=\"UI\">$id</p></body></html>");

        $this->assertFalse($client->hasPendingRegistrations());
    }

    /**
     * WIRE-1: the key travels in X-Authorization. The API answers a missing
     * header 401 and an unknown key 403, so a translation arriving proves the
     * header was read.
     */
    public function testTheKeyAuthenticatesInXAuthorization(): void
    {
        $world = $this->world('hello');

        $client = $this->client($world->key('read'), $world->project());
        $client->setLocale('es-es');
        $this->assertSame('Hola', $client->translate('Hello'));

        $stranger = $this->client($world->key('stranger'), $world->project());
        $stranger->setLocale('es-es');
        $this->assertSame('Hello', $stranger->translate('Hello'), 'control: an unknown key is refused');
    }

    /**
     * WIRE-3: no category travels as absent, never as the sentinel. The API
     * skips a phrase sent with `__uncategorized__` and would store a block under
     * it as a category of that name, so either mistake shows in what it holds.
     */
    public function testNoCategoryTravelsAsAbsentOnEveryRegistrationPath(): void
    {
        $world = $this->world('writer');

        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('es-es');
        $client->translate('Loose');
        $client->translatePage('<html><body><p>Page</p></body></html>');
        $client->translateContentBlock('<div><p>One</p><p>Two</p></div>');
        $client->flushPendingRegistrations();

        $held = $this->held($world->key('write'), $world->project(), 'en-us');
        $this->assertSame(['__uncategorized__' => ['Loose', 'Page']], $held['phrases']);
        $this->assertSame([null], array_column($this->storedBlocks($world, $world->key('write'), 'en-us'), 'category'), 'the block is stored with no category');
        $this->assertSame([['One', 'Two']], array_values($held['blocks']['__uncategorized__']));
    }

    /**
     * WIRE-3's read half: the catalog serves an uncategorised block under
     * `__uncategorized__`, the key uncategorised phrases use, so a block rendered
     * with no category is found there. Non-ASCII, so no historical id shape finds
     * it a second way.
     */
    public function testAnUncategorisedBlockIsReadFromTheUncategorisedKey(): void
    {
        $world = $this->world('uncategorised-block');

        foreach ([null, ''] as $category) {
            $client = $this->client($world->key('read'), $world->project());
            $client->setLocale('es-es');

            $this->assertStringContainsString('<p>Cafe ES</p><p>Te ES</p>', $client->translateContentBlock('<div><p>Café</p><p>Thé</p></div>', $category), var_export($category, true));
            $this->assertFalse($client->hasPendingRegistrations(), var_export($category, true) . ': found, not queued');
        }
    }

    /**
     * WIRE-3: the locale travels lowercase. The catalog holds es-es; a caller
     * spelling it es-ES still reads it.
     */
    public function testALocaleInAnySpellingReadsTheLowercaseCatalog(): void
    {
        $world = $this->world('hello');

        foreach (['es-ES', 'es_ES', 'ES-es'] as $spelling) {
            $client = $this->client($world->key('read'), $world->project());
            $this->assertSame('Hola', $client->translate('Hello', $spelling), $spelling);
        }
    }

    /**
     * CID-3: a block is registered under its current id and never a historical
     * one, on every path that creates blocks. Non-ASCII content, where the
     * historical code-unit and pipe-form ids differ from the current id.
     */
    public function testEveryBlockPathRegistersUnderTheCurrentId(): void
    {
        $parser = new HtmlParser();
        $current = $parser->generateCustomId('UI', ['Café', 'Thé']);
        $legacy = array_values(array_diff($parser->legacyCustomIds('UI', ['Café', 'Thé']), [$current]));
        $this->assertNotEmpty($legacy, 'control: this content has historical ids that differ from the current one');

        $world = $this->world('writer');
        $client = $this->client($world->key('write'), $world->project());
        $client->setLocale('es-es');
        $client->translateContentBlock('<div><p>Café</p><p>Thé</p></div>', 'UI');
        $client->flushPendingRegistrations();
        $this->assertSame([$current], $this->blockIds($world), 'the render path, through the queue');

        $world = $this->world('writer');
        $client = $this->client($world->key('write'), $world->project());
        $client->registerContentBlock('<div><p>Café</p><p>Thé</p></div>', 'UI');
        $this->assertSame([$current], $this->blockIds($world), 'the public single-block creator');

        $world = $this->world('writer');
        $client = $this->client($world->key('write'), $world->project());
        $client->translatableItems()->createContentBlocks([['html' => '<div><p>Café</p><p>Thé</p></div>', 'category' => 'UI']]);
        $this->assertSame([$current], $this->blockIds($world), 'the public batch creator');
    }

    private function blockIds(World $world)
    {
        return self::anyBlock($this->held($world->key('write'), $world->project(), 'en-us'));
    }

    private function body($html)
    {
        return preg_match('#<body>(.*)</body>#s', $html, $m) ? trim($m[1]) : trim($html);
    }
}
