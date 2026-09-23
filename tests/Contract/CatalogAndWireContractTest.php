<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Html\HtmlParser;

/**
 * Rows whose property turns on what the server answers - the catalog's shapes,
 * the write decision across requests, the wire - proven against the contract
 * fixture. Where the server's idempotence would hide a duplicate registration,
 * the test reads the SDK's own queue under the server's real answer, never a
 * record kept by the double.
 */
class CatalogAndWireContractTest extends ContractTestCase
{
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
     * cache; between them the server stops allowing the key to write, and the
     * second request follows the server rather than the first request's answer.
     */
    public function testTheWriteDecisionIsNotCarriedIntoTheNextRequest(): void
    {
        list($cache) = $this->sharedCache();

        $this->seedProject(['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => ['127.0.0.1']]]);
        $this->assertTrue($this->client('k-ip', $cache)->canWrite());

        $this->seedProject(['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => ['10.1.2.3']]]);
        $second = $this->client('k-ip', $cache);
        $second->setLocale('es-es');
        $second->translate('Second request');
        $second->flushPendingRegistrations();

        $this->assertFalse($second->canWrite());
        $this->assertSame([], $this->registeredPhrases());
    }

    /**
     * GATE-4: the flag the server sends is never part of anything cached. The
     * control is that the decision really came from the flag: an ip_write key
     * can only be answered by it.
     */
    public function testNothingCachedCarriesTheWriteDecision(): void
    {
        list($cache, $dir) = $this->sharedCache();
        $this->seedProject(['k-ip' => ['type' => 'ip_write', 'ip_allowlist' => ['127.0.0.1']]], ['phrases' => [['phrase' => 'Hello', 'translations' => ['es-es' => 'Hola']]]]);

        $client = $this->client('k-ip', $cache);
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
        $this->seedProject(['k-write' => ['type' => 'write']], ['phrases' => [['phrase' => 'Registered']]]);

        $client = $this->client('k-write');
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
        // Non-ASCII on purpose: for ASCII content a legacy id shape equals the
        // current id, and the legacy fallback would find the block a second way.
        $id = (new HtmlParser())->generateCustomId('UI', ['Café', 'Thé']);
        $this->seedProject(['k-write' => ['type' => 'write']], ['blocks' => [['category' => 'UI', 'custom_id' => $id, 'phrases' => [['phrase' => 'Café'], ['phrase' => 'Thé']]]]]);

        $client = $this->client('k-write');
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
        $this->seedProject(['k-write' => ['type' => 'write']], ['phrases' => [['phrase' => 'placeholder']], 'blocks' => [['category' => 'UI', 'custom_id' => $id, 'phrases' => [['phrase' => 'One'], ['phrase' => 'Two']]]]]);

        $client = $this->client('k-write');
        $client->setLocale('es-es');
        $client->translatePage("<html><body><p data-langsys-category=\"UI\">$id</p></body></html>");

        $this->assertFalse($client->hasPendingRegistrations());
    }

    /**
     * WIRE-1: the key travels in X-Authorization. The double answers a missing
     * header 401 and an unknown key 403, so a translation arriving proves the
     * header was read.
     */
    public function testTheKeyAuthenticatesInXAuthorization(): void
    {
        $this->seedProject(['k-read' => ['type' => 'read']], ['phrases' => [['phrase' => 'Hello', 'translations' => ['es-es' => 'Hola']]]]);

        $client = $this->client('k-read');
        $client->setLocale('es-es');
        $this->assertSame('Hola', $client->translate('Hello'));

        $stranger = $this->client('not-a-key');
        $stranger->setLocale('es-es');
        $this->assertSame('Hello', $stranger->translate('Hello'), 'control: an unknown key is refused');
    }

    /**
     * WIRE-3: no category travels as absent, never as the sentinel. The double
     * skips a phrase sent with `__uncategorized__` and would store a block under
     * it, so either mistake shows in the state.
     */
    public function testNoCategoryTravelsAsAbsentOnEveryRegistrationPath(): void
    {
        $this->seedProject(['k-write' => ['type' => 'write']]);

        $client = $this->client('k-write');
        $client->setLocale('es-es');
        $client->translate('Loose');
        $client->translatePage('<html><body><p>Page</p></body></html>');
        $client->translateContentBlock('<div><p>One</p><p>Two</p></div>');
        $client->flushPendingRegistrations();

        $this->assertEqualsCanonicalizing([[null, 'Loose'], [null, 'Page']], $this->registeredPhrases());
        $this->assertSame([[null, ['One', 'Two']]], $this->registeredBlocks());
    }

    /**
     * WIRE-3's read half: the catalog serves an uncategorised block under
     * `__uncategorized__`, the key uncategorised phrases use, so a block rendered
     * with no category is found there. Non-ASCII, so no historical id shape finds
     * it a second way.
     */
    public function testAnUncategorisedBlockIsReadFromTheUncategorisedKey(): void
    {
        $id = (new HtmlParser())->generateCustomId(null, ['Café', 'Thé']);
        $this->seedProject(['k-read' => ['type' => 'read']], ['blocks' => [['category' => null, 'custom_id' => $id, 'phrases' => [['phrase' => 'Café', 'translations' => ['es-es' => 'Cafe ES']], ['phrase' => 'Thé', 'translations' => ['es-es' => 'Te ES']]]]]]);

        foreach ([null, ''] as $category) {
            $client = $this->client('k-read');
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
        $this->seedProject(['k-read' => ['type' => 'read']], ['phrases' => [['phrase' => 'Hello', 'translations' => ['es-es' => 'Hola']]]]);

        foreach (['es-ES', 'es_ES', 'ES-es'] as $spelling) {
            $client = $this->client('k-read');
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

        $this->seedProject(['k-write' => ['type' => 'write']]);
        $client = $this->client('k-write');
        $client->setLocale('es-es');
        $client->translateContentBlock('<div><p>Café</p><p>Thé</p></div>', 'UI');
        $client->flushPendingRegistrations();
        $this->assertSame([$current], $this->registeredBlockIds(), 'the render path, through the queue');

        $this->seedProject(['k-write' => ['type' => 'write']]);
        $client = $this->client('k-write');
        $client->registerContentBlock('<div><p>Café</p><p>Thé</p></div>', 'UI');
        $this->assertSame([$current], $this->registeredBlockIds(), 'the public single-block creator');

        $this->seedProject(['k-write' => ['type' => 'write']]);
        $client = $this->client('k-write');
        $client->translatableItems()->createContentBlocks([['html' => '<div><p>Café</p><p>Thé</p></div>', 'category' => 'UI']]);
        $this->assertSame([$current], $this->registeredBlockIds(), 'the public batch creator');
    }

    private function body($html)
    {
        return preg_match('#<body>(.*)</body>#s', $html, $m) ? trim($m[1]) : trim($html);
    }
}
