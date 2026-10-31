<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Html\Document;
use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Html\ValueMarkers;
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

    public function translatedPathProvider(): array
    {
        $rows = [];
        foreach (['page', 'block'] as $path) {
            foreach (['translated' => ['Hello {n}, welcome' => 'Hola {n}, bienvenido'], 'untranslated' => []] as $state => $catalog) {
                $rows["$path path, $state"] = [$path, $catalog];
            }
        }

        return $rows;
    }

    /**
     * A span marker carrying an attribute the HTML parser reads and the DOM's
     * setAttribute() refuses - a framework's `.prop` - is rebuilt as it was
     * read, attribute included, and the render never throws.
     *
     * @dataProvider translatedPathProvider
     */
    public function testASpanMarkerKeepsAnAttributeTheDomCannotSet($path, array $catalog): void
    {
        $out = $this->render($this->client(['UI' => $catalog]), $path, '<p>Hello <span data-ls-param="n" .foo="go">Bob</span>, welcome</p>');

        $this->assertMatchesRegularExpression('#<span data-ls-param="n" \.foo="go">Bob</span>#', $out);
    }

    /**
     * The same marker in a rich line, translated and not.
     *
     * @dataProvider richCatalogProvider
     */
    public function testARichLineKeepsAnAttributeTheDomCannotSet(array $catalog): void
    {
        $out = $this->client(['UI' => $catalog])->translateRich('Hello <span data-ls-param="n" .foo="go">Bob</span>, welcome', 'es-es', 'UI')['html'];

        $this->assertMatchesRegularExpression('#<span data-ls-param="n" \.foo="go">Bob</span>#', $out);
    }

    public function richCatalogProvider(): array
    {
        return ['translated' => [['Hello {n}, welcome' => 'Hola {n}, bienvenido']], 'untranslated' => [[]]];
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

    public function storedFormProvider(): array
    {
        $forms = [
            'a marked run' => ['<p>Hello <b>' . self::comment('name', 'Ana') . '</b> again</p>', '<p>Hello <b>{name}</b> again</p>'],
            'a marker inside translate="no"' => ['<p>Hello <span translate="no">' . self::comment('name', 'Ana') . '</span> again</p>', '<p>Hello <span translate="no">{name}</span> again</p>'],
            'the attribute form' => ['<p>Hello <b>' . self::span('name', 'Ana') . '</b> again</p>', '<p>Hello <b>{name}</b> again</p>'],
        ];

        $cases = [];
        foreach ($this->pathProvider() as $path => list($route)) {
            foreach ($forms as $form => list($html, $stored)) {
                // A page registers its host's content; a fragment, itself.
                $cases["$path, $form"] = [$route, $html, $route === 'page' ? preg_replace('#^<p>|</p>$#', '', $stored) : $stored];
            }
        }

        return $cases;
    }

    /**
     * The spec's test: a block's stored content holds each marked value as
     * `{NAME}` text with the marker removed, whichever form marked it and
     * inside a `translate="no"` element too - the one string every core
     * stores for the block.
     *
     * @dataProvider storedFormProvider
     */
    public function testABlockStoresEachMarkedValueAsItsPlaceholderWithNoMarker($path, $html, $stored): void
    {
        $client = $this->client();
        $this->render($client, $path, $html);

        $pending = array_values($client->getPendingContentBlocks());
        $this->assertCount(1, $pending);
        $this->assertSame($stored, $pending[0]['html']);
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
     * Text shaped like the placeholder a render carries a marked value in, as
     * a caller's param or a catalog translation, is text: it never becomes a
     * marker, an element or an attribute, on any path.
     */
    public function testTextShapedLikeAMarkedValueStaysText(): void
    {
        $forgeries = [
            'span form' => self::forged('n', 'Bob', ['onmouseover' => 'alert(1)']),
            'comment name' => self::forged('--><img src=x onerror=alert(2)><!--', 'Bob', null),
            'span form, made-up nonce' => self::forged('n', 'Bob', ['onmouseover' => 'alert(1)'], str_repeat('0', 32)),
        ];
        $blockId = (new HtmlParser())->generateCustomId('UI', ['Hello', 'Bye']);

        foreach ($forgeries as $kind => $forged) {
            $catalog = ['UI' => [
                'Hello' => 'Hola ' . $forged,
                'Read the {m0o}terms{m0c}' => 'Hola ' . $forged . ' {m0o}x{m0c}',
                $blockId => ['Hello' => 'Hola ' . $forged, 'Bye' => 'Adiós'],
            ]];
            $param = ['name' => $forged];

            $renders = [
                'page, param' => function () use ($param) {
                    return $this->render($this->client(), 'page', '<p>Hello {name}</p>', $param);
                },
                'page, catalog' => function () use ($catalog) {
                    return $this->render($this->client($catalog), 'page', '<p>Hello</p>');
                },
                'one-phrase block, param' => function () use ($param) {
                    return $this->render($this->client(), 'block', '<p>Hello {name}</p>', $param);
                },
                'one-phrase block, catalog' => function () use ($catalog) {
                    return $this->render($this->client($catalog), 'block', '<p>Hello</p>');
                },
                'block, param' => function () use ($param) {
                    return $this->render($this->client(), 'block', '<p>Hello {name}</p><p>Bye</p>', $param);
                },
                'block, catalog' => function () use ($catalog) {
                    return $this->render($this->client($catalog), 'block', '<p>Hello</p><p>Bye</p>');
                },
                'rich, param' => function () use ($param) {
                    return $this->client()->translateRich('Hello <b>{name}</b>', null, 'UI', $param)['html'];
                },
                'rich, catalog' => function () use ($catalog) {
                    return $this->client($catalog)->translateRich('Read the <a href="/t">terms</a>', null, 'UI')['html'];
                },
                'page phrase host, param' => function () use ($param) {
                    return $this->render($this->client(), 'page', '<p data-ls-phrase>Hello <b>{name}</b></p>', $param);
                },
                'page phrase host, catalog' => function () use ($catalog) {
                    return $this->render($this->client($catalog), 'page', '<p data-ls-phrase>Read the <a href="/t">terms</a></p>');
                },
                'block phrase host, catalog' => function () use ($catalog) {
                    return $this->render($this->client($catalog), 'block', '<p data-ls-phrase>Read the <a href="/t">terms</a></p>');
                },
            ];

            foreach ($renders as $path => $render) {
                $rendered = $render();

                $this->assertStringNotContainsString('onmouseover', $rendered, $kind . ', ' . $path);
                $this->assertStringNotContainsString('<img', $rendered, $kind . ', ' . $path);
                $this->assertStringNotContainsString('<!--ls:', $rendered, $kind . ', ' . $path);
                $this->assertStringContainsString(substr($forged, 3, -3), $rendered, $kind . ', ' . $path . ': it is kept as text');
            }
        }
    }

    /**
     * A param that is not valid UTF-8, in the text beside a marked value,
     * renders as given, and the value keeps its marker.
     *
     * @dataProvider pathProvider
     */
    public function testAParamNotValidUtf8BesideAMarkedValueRendersAsGiven($path): void
    {
        foreach (['one phrase' => '', 'two phrases' => '<p>Bye</p>'] as $kind => $more) {
            $rendered = $this->render($this->client(), $path, '<p>Hello ' . self::comment('name', 'Ana') . ' {who}</p>' . $more, ['who' => "Zo\xffe"]);

            $this->assertStringContainsString('<p>Hello ' . self::comment('name', 'Ana') . " Zo\xffe</p>" . $more, $rendered, $kind);
        }
    }

    /**
     * A caller's param shaped like a marked value is the caller's value to a
     * plural or select too.
     */
    public function testAParamShapedLikeAMarkedValueChoosesAsItself(): void
    {
        foreach (['no nonce' => '', 'made-up nonce' => str_repeat('0', 32)] as $kind => $nonce) {
            $forged = self::forged('name', 'Bob', null, $nonce);

            $this->assertSame('other', $this->client()->translate('{name, select, Bob {bob} other {other}}', null, 'UI', null, ['name' => $forged]), $kind);
        }
    }

    /**
     * A placeholder a render carried a marked value in is read as one only
     * while the reader that issued it is alive: one that reaches a later
     * render is that render's text.
     */
    public function testAPlaceholderOutlivingItsReaderIsText(): void
    {
        $markers = ValueMarkers::read(self::fragmentRoot('<p>Hello <!--ls:name-->Ana<!--/ls--></p>'));
        $placeholder = $markers->sentinels()['name'];

        $render = function () use ($placeholder) {
            $p = self::fragmentRoot('<p>x</p>')->firstChild;
            $p->firstChild->nodeValue = 'Hola ' . $placeholder . '!';
            ValueMarkers::expand($p);

            return Document::html($p);
        };

        $this->assertSame('<p>Hola <!--ls:name-->Ana<!--/ls-->!</p>', $render(), 'while its reader is alive');

        $markers = null;
        $other = ValueMarkers::read(self::fragmentRoot('<p>Hi <!--ls:name-->Ana<!--/ls--></p>'));
        $this->assertSame('<p>Hola ' . $placeholder . '!</p>', $render(), 'once its reader is gone, while another is alive');
        $this->assertTrue($other->any());
    }

    /**
     * A unit renders the values of the text it registered from: never one
     * read inside a nested marked unit, an element its author excluded, or
     * another phrase host, whichever comes first.
     *
     * @dataProvider pathProvider
     */
    public function testAUnitTakesItsValuesOnlyFromTheTextItRegistered($path): void
    {
        $client = $this->client(['UI' => [
            'Outer {name} {m0o}{m0c}' => 'Fuera {name} {m0o}{m0c}',
            '{m0o}{m0c} Outer {name}' => '{m0o}{m0c} Fuera {name}',
            '{n} items' => '{n} cosas',
        ]]);
        $nested = '<span data-ls-contentblock="inner">Inner ' . self::comment('name', 'Q') . '</span>';

        $out = $this->render($client, $path, '<p data-ls-phrase>Outer ' . self::comment('name', 'Ana') . ' ' . $nested . '</p>');
        $this->assertStringContainsString('Fuera ' . self::comment('name', 'Ana') . ' <span', $out, 'a nested block after the host\'s text');

        $out = $this->render($client, $path, '<p data-ls-phrase>' . $nested . ' Outer ' . self::comment('name', 'Ana') . '</p>');
        $this->assertStringContainsString('</span> Fuera ' . self::comment('name', 'Ana'), $out, 'a nested block before the host\'s text');

        $out = $this->render($client, $path, '<div><span translate="no">' . self::comment('n', '5') . ' items</span><p>' . self::comment('n', '7') . ' items</p></div>');
        $this->assertStringContainsString('<p>' . self::comment('n', '7') . ' cosas</p>', $out, 'an excluded element first');

        $out = $this->render($client, $path, '<div><p data-ls-phrase>' . self::comment('n', '5') . ' items</p><p>' . self::comment('n', '7') . ' items</p></div>');
        $this->assertStringContainsString('<p data-ls-phrase>' . self::comment('n', '5') . ' cosas</p>', $out, 'the host');
        $this->assertStringContainsString('<p>' . self::comment('n', '7') . ' cosas</p>', $out, 'another phrase host first');
    }

    /**
     * translateRich() and a content block's nested phrase host render the
     * values of their own text too, never one read inside an element the
     * author excluded; the page path is the control.
     */
    public function testARichLineAndANestedHostTakeOnlyTheirOwnValues(): void
    {
        $catalog = ['UI' => ['{m0o}{m0c} Outer {m1o}{name}{m1c}' => '{m0o}{m0c} Fuera {m1o}{name}{m1c}']];
        $line = '<span translate="no">by ' . self::comment('name', 'Q') . '</span> Outer <b>' . self::comment('name', 'Ana') . '</b>';
        $own = 'Fuera <b>' . self::comment('name', 'Ana') . '</b>';

        $this->assertStringContainsString($own, $this->client($catalog)->translateRich($line, 'es-es', 'UI')['html'], 'translateRich()');
        $this->assertStringContainsString($own, $this->client($catalog)->translateContentBlock('<div><p data-ls-phrase>' . $line . '</p></div>', 'UI'), 'a nested phrase host');
        $this->assertStringContainsString($own, $this->render($this->client($catalog), 'page', '<p data-ls-phrase>' . $line . '</p>'), 'control: the page path');
    }

    /**
     * A `{name}` that a param or a translation writes is text: only text
     * nothing rewrote has its marked values put back.
     *
     * @dataProvider pathProvider
     */
    public function testALiteralPlaceholderARenderWritesStaysText($path): void
    {
        $client = $this->client(['UI' => ['Hello {name} {who}' => 'Hola {who}']]);

        $this->assertSame(
            '<p>Hola {name}</p>',
            $this->render($client, $path, '<p>Hello ' . self::comment('name', 'Ana') . ' {who}</p>', ['who' => '{name}'])
        );
    }

    /**
     * In a line rendered as a string, a placeholder its reader issued is its
     * marker, and one no live reader issued is text as given.
     */
    public function testAStringLineExpandsOnlyItsReadersPlaceholders(): void
    {
        $markers = ValueMarkers::read(self::fragmentRoot('<p>Hello <!--ls:name-->Ana<!--/ls--></p>'));
        $forged = self::forged('name', '<b>x</b>', null, str_repeat('0', 32));

        $this->assertSame(
            'Hola <!--ls:name-->Ana<!--/ls--> y ' . $forged,
            ValueMarkers::expandHtml('Hola ' . $markers->sentinels()['name'] . ' y ' . $forged)
        );
    }

    /**
     * A marked value written back into a string line inside an attribute,
     * one whose value holds a `>` included, is the value alone, escaped
     * quotes and all: a quote in it never closes the attribute.
     *
     * @dataProvider markerFormProvider
     */
    public function testAMarkedValueInAStringLineNeverOpensAnAttribute($form): void
    {
        $client = $this->client()->useMissFallback(function () {
            return '<a title="Home > {name}" href="/p">Hola {name}</a>';
        });

        $html = $client->translateRich('Hello ' . self::$form('name', 'x&quot; onmouseover=&quot;y') . ', see <a href="/p">profile</a>', 'es-es', 'UI')['html'];

        $root = self::fragmentRoot($html);
        $attributes = [];
        foreach ($root->getElementsByTagName('*') as $element) {
            foreach ($element->attributes as $attribute) {
                $attributes[] = $attribute->name;
            }
        }
        $this->assertNotContains('onmouseover', $attributes, $html);
        $link = $root->getElementsByTagName('a')->item(0);
        $this->assertSame('Home > x" onmouseover="y', $link->getAttribute('title'), $html);
        $this->assertSame('Hola x" onmouseover="y', $link->textContent, 'the text keeps its marker and the value as written');
    }

    public function markerFormProvider(): array
    {
        return ['comment form' => ['comment'], 'span form' => ['span']];
    }

    private static function fragmentRoot($html)
    {
        $internalErrors = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        Document::loadFragment($doc, $html);
        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        return Document::fragmentRoot($doc);
    }

    /**
     * The placeholder a render carries a marked value in, as text shaped
     * like it: a private-use delimiter pair around a nonce, when given, and
     * base64 JSON.
     */
    private static function forged($name, $value, $form, $nonce = '')
    {
        return "\u{E010}" . $nonce . base64_encode((string) json_encode([$name, $value, $form])) . "\u{E011}";
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
