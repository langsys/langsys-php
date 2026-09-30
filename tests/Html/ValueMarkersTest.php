<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * VAR-1 and VAR-3: a value an emitter marked is read back as a placeholder,
 * so a sentence is one phrase for every user, and the render puts the value
 * back, marker and all.
 */
class ValueMarkersTest extends TestCase
{
    private function client(array $catalog = ['UI' => []], $logger = null)
    {
        $http = new MockHttpClient();
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'write', 'write_enabled' => true]]);
        $http->setResponse('GET', 'translations', ['data' => $catalog]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false] + ($logger !== null ? ['logger' => $logger] : []));
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

    private function render(Client $client, $path, $html, array $params = [])
    {
        if ($path === 'page') {
            $out = $client->translatePage('<html><body>' . $html . '</body></html>', 'UI', [], $params);

            return preg_replace('#^.*<body>|</body>.*$#s', '', $out);
        }

        return $client->translateContentBlock($html, 'UI', $params);
    }

    /**
     * @return array{phrases: string[], blocks: array<string, string[]>}
     */
    private function registered(Client $client)
    {
        $blocks = [];
        foreach ($client->getPendingContentBlocks() as $block) {
            $blocks[$block['customId']] = $block['phrases'];
        }

        return ['phrases' => array_values(array_column($client->getPendingPhrases(), 'phrase')), 'blocks' => $blocks];
    }

    private static function comment($name, $value)
    {
        return '<!--ls:' . $name . '-->' . $value . '<!--/ls-->';
    }

    private static function span($name, $value)
    {
        return '<span data-ls-param="' . $name . '">' . $value . '</span>';
    }

    public function pathProvider(): array
    {
        return ['page path' => ['page'], 'content-block path' => ['block']];
    }

    public function formProvider(): array
    {
        $rows = [];
        foreach (['page', 'block'] as $path) {
            foreach (['comment', 'span'] as $form) {
                $rows[$path . ', ' . $form . ' form'] = [$path, $form];
            }
        }

        return $rows;
    }

    /**
     * The spec's example: the text on both sides of a marker merges into one
     * token with the placeholder in place of the value. The same sentence for
     * two users is one phrase, and each render keeps its own value in its
     * marker.
     *
     * @dataProvider formProvider
     */
    public function testTheSameSentenceForTwoUsersIsOnePhrase($path, $form): void
    {
        foreach (['Ana', 'Luis'] as $name) {
            $markup = '<p>Hello ' . self::$form('name', $name) . ', welcome back</p>';
            $client = $this->client();
            $rendered = $this->render($client, $path, $markup);

            $this->assertSame(['phrases' => ['Hello {name}, welcome back'], 'blocks' => []], $this->registered($client), $name);
            $this->assertSame($markup, $rendered, $name . ': the value and its marker are put back as they were');
        }
    }

    /**
     * A translated sentence renders with the value in its marker, where the
     * translation places the placeholder.
     *
     * @dataProvider formProvider
     */
    public function testATranslationRendersTheValueInItsMarker($path, $form): void
    {
        $client = $this->client(['UI' => ['Hello {name}, welcome back' => 'Bienvenido de nuevo, {name}']]);

        $rendered = $this->render($client, $path, '<p>Hello ' . self::$form('name', 'Ana') . ', welcome back</p>');

        $this->assertSame('<p>Bienvenido de nuevo, ' . self::$form('name', 'Ana') . '</p>', $rendered);
        $this->assertSame([], $this->registered($client)['phrases']);
    }

    /**
     * A value an ICU construct in the translation uses is the value itself,
     * so plural and select choose on it.
     *
     * @dataProvider pathProvider
     */
    public function testAPluralInTheTranslationChoosesOnTheMarkedValue($path): void
    {
        foreach (['1' => 'Tienes 1 mensaje', '3' => 'Tienes 3 mensajes'] as $count => $expected) {
            $client = $this->client(['UI' => ['You have {count} messages' => '{count, plural, one {Tienes # mensaje} other {Tienes # mensajes}}']]);

            $rendered = $this->render($client, $path, '<p>You have ' . self::comment('count', $count) . ' messages</p>');

            $this->assertSame('<p>' . $expected . '</p>', $rendered);
        }
    }

    /**
     * Inside a block, an element slot holding only a marker is its own
     * placeholder token, and the block's id is the same for every user.
     *
     * @dataProvider pathProvider
     */
    public function testAMarkerAloneInASlotIsItsOwnToken($path): void
    {
        $ids = [];
        foreach ([['Ana', '3'], ['Luis', '12']] as list($name, $count)) {
            $markup = '<p>Hello <b>' . self::comment('name', $name) . '</b>, you have ' . self::comment('count', $count) . ' messages</p>';
            $client = $this->client();
            $rendered = $this->render($client, $path, $markup);

            $blocks = $this->registered($client)['blocks'];
            $this->assertSame([['Hello', '{name}', ', you have {count} messages']], array_values($blocks));
            $ids[] = key($blocks);
            $this->assertStringContainsString('<b>' . self::comment('name', $name) . '</b>, you have ' . self::comment('count', $count) . ' messages', $rendered);
        }

        $this->assertSame($ids[0], $ids[1], 'one id for both users');
        $this->assertSame((new HtmlParser())->generateCustomId('UI', ['Hello', '{name}', ', you have {count} messages']), $ids[0]);
    }

    /**
     * A translated block renders each value in its own marker, even where two
     * of its text nodes use the same name with different values.
     *
     * @dataProvider pathProvider
     */
    public function testATranslatedBlockRendersEachValueInPlace($path): void
    {
        $tokens = ['Hello', '{name}', ', you have {count} messages'];
        $id = (new HtmlParser())->generateCustomId('UI', $tokens);
        $client = $this->client(['UI' => [$id => ['Hello' => 'Hola', '{name}' => '{name}', ', you have {count} messages' => ', tienes {count} mensajes']]]);

        $rendered = $this->render($client, $path, '<p>Hello <b>' . self::comment('name', 'Ana') . '</b>, you have ' . self::comment('count', '3') . ' messages</p>');

        $this->assertStringContainsString('Hola <b>' . self::comment('name', 'Ana') . '</b>, tienes ' . self::comment('count', '3') . ' mensajes', $rendered);
        $this->assertSame([], $this->registered($client)['blocks']);
    }

    /**
     * The comment form and the attribute form read to the same token, so to
     * the same id.
     *
     * @dataProvider pathProvider
     */
    public function testBothFormsReadToTheSameId($path): void
    {
        $registered = [];
        foreach (['comment', 'span'] as $form) {
            $client = $this->client();
            $this->render($client, $path, '<p>Hi <i>there</i>, ' . self::$form('name', 'Ana') . '</p>');
            $registered[$form] = $this->registered($client);
        }

        $this->assertSame($registered['comment'], $registered['span']);
        $this->assertSame([['Hi', 'there', ', {name}']], array_values($registered['comment']['blocks']));
    }

    /**
     * A unit made only of markers has no text of its own and registers
     * nothing; it renders as it was.
     *
     * @dataProvider pathProvider
     */
    public function testAUnitOfOnlyMarkersRegistersNothing($path): void
    {
        $markup = '<p>' . self::comment('total', '1,200.50') . ' ' . self::comment('currency', 'EUR') . '</p>';
        $client = $this->client();

        $rendered = $this->render($client, $path, $markup);

        $this->assertFalse($client->hasPendingRegistrations());
        $this->assertSame($markup, $rendered);
    }

    /**
     * Everything that is not a well-formed marker splits exactly as before:
     * an ordinary comment, a pair with an element inside, an unclosed pair.
     *
     * @dataProvider voidedProvider
     */
    public function testAnythingElseSplitsAsBefore($path, $markup, array $tokens): void
    {
        $client = $this->client();
        $rendered = $this->render($client, $path, $markup);

        $this->assertSame([$tokens], array_values($this->registered($client)['blocks']));
        $this->assertSame([], $this->registered($client)['phrases']);
        $this->assertStringContainsString(preg_replace('#^<p>|</p>$#', '', $markup), $rendered, 'the markup is left as it was');
    }

    public function voidedProvider(): array
    {
        $cases = [
            'an ordinary comment' => ['<p>Hello <!--note-->Ana</p>', ['Hello', 'Ana']],
            'an element inside the pair' => ['<p>Hello <!--ls:name--><b>Ana</b><!--/ls--> there</p>', ['Hello', 'Ana', 'there']],
            'an unclosed pair' => ['<p>Hello <!--ls:name-->Ana there</p>', ['Hello', 'Ana there']],
            'a span with an element inside' => ['<p>Hello <span data-ls-param="name"><b>Ana</b></span> there</p>', ['Hello', 'Ana', 'there']],
        ];

        $rows = [];
        foreach (['page', 'block'] as $path) {
            foreach ($cases as $name => $case) {
                $rows[$path . ': ' . $name] = [$path, $case[0], $case[1]];
            }
        }

        return $rows;
    }

    /**
     * VAR-7: a well-formed marker whose name is outside VAR-2's grammar, or a
     * markup token's, marks a value the reader cannot name. The unit holding
     * it registers nothing and renders as it was.
     *
     * @dataProvider unnamedProvider
     */
    public function testAValueThatCannotBeNamedRegistersNothing($path, $markup): void
    {
        $client = $this->client();

        $rendered = $this->render($client, $path, $markup);

        $this->assertFalse($client->hasPendingRegistrations());
        $this->assertStringContainsString(preg_replace('#^<p>|</p>$#', '', $markup), $rendered);
    }

    public function unnamedProvider(): array
    {
        $cases = [
            'an uppercase name' => '<p>Hello <!--ls:Name-->Ana<!--/ls--> there</p>',
            'a markup token name' => '<p>Hello <!--ls:m0o-->Ana<!--/ls--> there</p>',
            'an empty name' => '<p>Hello <!--ls:-->Ana<!--/ls--> there</p>',
            'a dotted span name' => '<p>Hello <span data-ls-param="user.name">Ana</span> there</p>',
        ];

        $rows = [];
        foreach (['page', 'block'] as $path) {
            foreach ($cases as $name => $markup) {
                $rows[$path . ': ' . $name] = [$path, $markup];
            }
        }

        return $rows;
    }

    /**
     * The unnamed value is reported as a debug notice once per process, not
     * once per render.
     *
     * @dataProvider pathProvider
     */
    public function testAnUnnamedValueIsReportedOnce($path): void
    {
        $noticed = new \ReflectionProperty(\Langsys\SDK\Html\ValueMarkers::class, 'noticed');
        $noticed->setAccessible(true);
        $noticed->setValue(null, false);

        $logger = new \Langsys\SDK\Tests\Support\SpyLogger();
        $client = $this->client(['UI' => []], $logger);
        foreach ([1, 2, 3] as $render) {
            $this->render($client, $path, '<p>Hello <!--ls:Name-->Ana<!--/ls--> there</p>');
        }

        $notices = array_filter($logger->messagesAt('debug'), function ($message) {
            return strpos($message, 'value marker') !== false;
        });
        $this->assertCount(1, $notices);
    }

    /**
     * A block registers its content with each value read as its placeholder:
     * no user's value is sent as source.
     *
     * @dataProvider pathProvider
     */
    public function testABlockRegistersPlaceholdersNotValues($path): void
    {
        $client = $this->client();
        $this->render($client, $path, '<p>Hello <b>' . self::comment('name', 'Ana') . '</b> again</p>');

        $pending = array_values($client->getPendingContentBlocks());
        $this->assertCount(1, $pending);
        $this->assertStringNotContainsString('Ana', $pending[0]['html']);
        $this->assertStringContainsString('<b>{name}</b>', $pending[0]['html']);
    }

    /**
     * A param the caller names itself wins over the marked value.
     *
     * @dataProvider pathProvider
     */
    public function testACallerParamWinsOverTheMarkedValue($path): void
    {
        $client = $this->client();

        $rendered = $this->render($client, $path, '<p>Hello ' . self::comment('name', 'Ana') . '</p>', ['name' => 'Zoe']);

        $this->assertStringContainsString('Hello Zoe', $rendered);
        $this->assertSame(['Hello {name}'], $this->registered($client)['phrases']);
    }

    /**
     * A phrase host holding a marker registers one phrase with the markup
     * tokens and the placeholder, and renders the value back in its marker.
     *
     * @dataProvider pathProvider
     */
    public function testAPhraseHostReadsItsMarkers($path): void
    {
        foreach (['Ana', 'Luis'] as $name) {
            $client = $this->client(['UI' => ['Buy {m0o}now{m0c}, {name}' => 'Compra {m0o}ya{m0c}, {name}']]);
            $rendered = $this->render($client, $path, '<div><span data-ls-phrase>Buy <b>now</b>, ' . self::comment('name', $name) . '</span></div>');

            $this->assertStringContainsString('Compra <b>ya</b>, ' . self::comment('name', $name), $rendered);
        }

        $client = $this->client();
        $this->render($client, $path, '<div><span data-ls-phrase>Buy <b>now</b>, ' . self::comment('name', 'Ana') . '</span></div>');
        $this->assertSame(['Buy {m0o}now{m0c}, {name}'], $this->registered($client)['phrases']);
    }

    /**
     * A block's own text nodes each interpolate with their values: a plural
     * in a block translation chooses on the marked value.
     *
     * @dataProvider pathProvider
     */
    public function testAPluralInABlockChoosesOnTheMarkedValue($path): void
    {
        $tokens = ['Hi', 'there', ', you have {count} messages'];
        $id = (new HtmlParser())->generateCustomId('UI', $tokens);

        foreach (['1' => ', tienes 1 mensaje', '3' => ', tienes 3 mensajes'] as $count => $expected) {
            $client = $this->client(['UI' => [$id => [
                'Hi' => 'Hola',
                'there' => 'amigo',
                ', you have {count} messages' => '{count, plural, one {, tienes # mensaje} other {, tienes # mensajes}}',
            ]]]);

            $rendered = $this->render($client, $path, '<p>Hi <b>there</b>, you have ' . self::comment('count', $count) . ' messages</p>');

            $this->assertStringContainsString('Hola <b>amigo</b>' . $expected, $rendered);
        }
    }

    /**
     * A stamped block made only of markers registers nothing either.
     *
     * @dataProvider pathProvider
     */
    public function testAStampedBlockOfOnlyMarkersRegistersNothing($path): void
    {
        $client = $this->client();

        $this->render($client, $path, '<div data-ls-contentblock="abc123"><p>' . self::comment('total', '5') . '</p></div>');

        $this->assertFalse($client->hasPendingRegistrations());
    }

    /**
     * The parser's public phrase extraction reads markers as the walks do.
     */
    public function testExtractPhrasesReadsMarkers(): void
    {
        $this->assertSame(
            ['Hello {name}, welcome back', 'Order #{number}'],
            (new HtmlParser())->extractPhrases('<p>Hello ' . self::comment('name', 'Ana') . ', welcome back</p><p>Order #' . self::span('number', '1041') . '</p>')
        );
    }

    /**
     * A phrase host whose translation breaks its markup tokens loses the
     * markup and keeps the text, and still keeps the value in its marker.
     *
     * @dataProvider pathProvider
     */
    public function testAPhraseHostThatLosesItsMarkupKeepsTheValue($path): void
    {
        $client = $this->client(['UI' => ['Buy {m0o}now{m0c}, {name}' => 'Compra {m0o}ya, {name}']]);

        $rendered = $this->render($client, $path, '<div><span data-ls-phrase>Buy <b>now</b>, ' . self::comment('name', 'Ana') . '</span></div>');

        $this->assertStringContainsString('Compra ya, ' . self::comment('name', 'Ana'), $rendered);
    }

    /**
     * Unmarked text reads exactly as before: no marker, no change.
     *
     * @dataProvider pathProvider
     */
    public function testControlUnmarkedTextIsUnchanged($path): void
    {
        $client = $this->client();
        $rendered = $this->render($client, $path, '<p>Hello Ana, welcome back</p>');

        $this->assertSame(['Hello Ana, welcome back'], $this->registered($client)['phrases']);
        $this->assertSame('<p>Hello Ana, welcome back</p>', $rendered);
    }
}
