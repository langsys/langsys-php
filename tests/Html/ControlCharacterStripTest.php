<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Html\Canonical;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * TOK-2's C0 strip: U+0001-U+0008, U+000B, U+000C and U+000E-U+001F are
 * deleted from every id input and catalog key, before whitespace collapses.
 *
 * Proven on the canonicalization function, a string in and a string out,
 * because libxml2 2.9.13 already deletes these characters from DOM text - a
 * DOM-level test of a text node passes whether or not this SDK strips. The
 * attribute path, where the parser keeps them, is the shared fixture's
 * fs-in-attr row. Every character is written as an escape.
 */
class ControlCharacterStripTest extends TestCase
{
    /**
     * @dataProvider strippedProvider
     */
    public function testEachStrippedControlIsDeletedNotCollapsed($codepoint): void
    {
        $this->assertSame('ab', Canonical::phrase('a' . chr($codepoint) . 'b'), sprintf('U+%04X', $codepoint));
    }

    public function strippedProvider(): array
    {
        $rows = [];
        foreach (array_merge(range(0x01, 0x08), [0x0B, 0x0C], range(0x0E, 0x1F)) as $codepoint) {
            $rows[sprintf('U+%04X', $codepoint)] = [$codepoint];
        }

        return $rows;
    }

    public function testTheStripSetIsExactlyTwentyEight(): void
    {
        $this->assertCount(28, $this->strippedProvider());
    }

    /**
     * TAB, LF and CR are not stripped; they collapse to one space.
     *
     * @dataProvider collapsedProvider
     */
    public function testTabLineFeedAndCarriageReturnStillCollapse($codepoint): void
    {
        $this->assertSame('a b', Canonical::phrase('a' . chr($codepoint) . 'b'));
    }

    public function collapsedProvider(): array
    {
        return ['TAB' => [0x09], 'LF' => [0x0A], 'CR' => [0x0D]];
    }

    /**
     * NUL, DEL and the C1 range stay.
     *
     * @dataProvider keptProvider
     */
    public function testNulDelAndC1AreKept($char): void
    {
        $this->assertSame('a' . $char . 'b', Canonical::phrase('a' . $char . 'b'));
    }

    public function keptProvider(): array
    {
        return ['NUL' => ["\x00"], 'DEL' => ["\x7F"], 'NEL U+0085' => ["\u{0085}"], 'U+009F' => ["\u{009F}"]];
    }

    /**
     * Strip, then collapse, then trim: a control between two spaces leaves one
     * space, and a control at the edge leaves nothing to trim around.
     */
    public function testTheStripRunsBeforeTheCollapse(): void
    {
        $this->assertSame('a b', Canonical::phrase("a \x1C b"));
        $this->assertSame('Along description', Canonical::phrase("A\x1Clong   description"));
        $this->assertSame('ab', Canonical::phrase("\x0Bab\x0C"));
        $this->assertSame('', Canonical::phrase("\x1C\x1D"));
    }

    public function testMalformedUtf8StillStrips(): void
    {
        $this->assertSame("a\xFFb", Canonical::stripControls("a\x1C\xFFb"));
    }

    // The code-registered path: translate() keys, register and lookup alike.

    private function client(array $catalog)
    {
        $http = new MockHttpClient();
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'write', 'write_enabled' => true]]);
        $http->setResponse('GET', 'translations', ['data' => $catalog]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache()]);
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
        $client->setLocale('es-es');

        return $client;
    }

    public function testATranslateKeyIsLookedUpWithoutItsControls(): void
    {
        $client = $this->client(['UI' => ['Save changes' => 'Guardar cambios']]);

        $this->assertSame('Guardar cambios', $client->translate("Save\x1C changes", null, 'UI'));
        $this->assertFalse($client->hasPendingRegistrations());
    }

    public function testATranslateKeyRegistersWithoutItsControls(): void
    {
        $client = $this->client(['UI' => []]);
        $client->translate("Sa\x0Bve", null, 'UI');

        $this->assertSame(['Save'], array_values(array_map(function ($pending) {
            return $pending['phrase'];
        }, $client->getPendingPhrases())));
    }

    public function testQueuedAndDirectlyRegisteredPhrasesAreStripped(): void
    {
        $client = $this->client(['UI' => []]);
        $client->queuePhraseForRegistration("Buy\x1F now", 'UI');

        $this->assertSame(['Buy now'], array_values(array_map(function ($pending) {
            return $pending['phrase'];
        }, $client->getPendingPhrases())));

        $http = new MockHttpClient();
        $items = new \Langsys\SDK\Resources\TranslatableItems($http, 'project-id');
        $items->createPhrases(["Pay\x01 now", ['phrase' => "Log\x0C in", 'category' => 'UI']]);

        $this->assertSame(['Pay now', 'Log in'], array_column($http->getLastRequest()['data']['translatable_items'], 'phrase'));
    }

    public function testAServerMessageTemplateIsLookedUpWithoutItsControls(): void
    {
        $client = $this->client(['Errors' => ['The {field} field is required.' => 'El campo {field} es obligatorio.']]);

        $message = new \Langsys\SDK\Messages\ServerMessage('required', 'The email field is required.', "The {field}\x1C field is required.", ['field' => 'email']);

        $this->assertSame('El campo email es obligatorio.', $client->translateMessage($message));
    }

    public function testSyncComparesLocalPhrasesWithoutTheirControls(): void
    {
        $client = $this->client(['UI' => ['Save' => 'Guardar']]);

        $this->assertSame([], $client->sync([['phrase' => "Sa\x1Cve", 'category' => 'UI']], 'es-es')['new_phrases']);
    }
}
