<?php

namespace Langsys\SDK\Tests\Html;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Snapshot\Snapshot;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * TOK-1: an author's own exclusion, `translate="no"` or `data-notrans`, is
 * opaque in a rich phrase on every route that tokenizes one - translateRich(),
 * a page's marked phrase host, a block's marked phrase host. It is one empty
 * markup pair whatever it holds, restored as given on render; a phrase made
 * only of excluded content registers nothing; and a phrase an earlier reader
 * keyed by walking into the element renders that key's translation while
 * only the current key registers.
 */
class ExcludedElementTest extends TestCase
{
    private function client(array $catalog = [])
    {
        $http = new MockHttpClient();
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'write', 'write_enabled' => true]]);
        $http->setResponse('GET', 'translations', ['data' => ['UI' => $catalog]]);

        $client = new Client('test-api-key', 'project-id', ['cache' => new NullCache(), 'error_log' => false]);
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

    /**
     * The phrase's rendered content on $route, and the phrases it queued.
     *
     * @return array{0: string, 1: string[]}
     */
    private function render($route, $source, array $catalog = [], array $seed = null, array $params = [])
    {
        $client = $this->client($catalog);
        if ($seed !== null) {
            $payload = ['project_id' => 'project-id', 'generated_at' => '2026-10-14T00:00:00Z', 'base_locale' => 'en-us', 'locales' => ['es-es'], 'categories' => ['UI'], 'catalog' => ['es-es' => ['UI' => $seed]]];
            $client->useSnapshot(Snapshot::load(json_encode(['format' => Snapshot::FORMAT, 'version' => Snapshot::VERSION] + $payload + ['checksum' => 'sha256:' . hash('sha256', Snapshot::canonical($payload))])));
        }

        if ($route === 'rich') {
            $html = $client->translateRich($source, null, 'UI', $params)['html'];
        } elseif ($route === 'page') {
            $page = $client->translatePage('<html><body><p data-ls-phrase>' . $source . '</p></body></html>', 'UI', [], $params);
            $html = preg_replace('#^.*<p data-ls-phrase>|</p>.*$#s', '', $page);
        } else {
            $block = $client->translateContentBlock('<p data-ls-phrase>' . $source . '</p>', 'UI', $params);
            $html = preg_replace('#^<p data-ls-phrase>|</p>$#', '', $block);
        }

        return [$html, array_values(array_column($client->getPendingPhrases(), 'phrase'))];
    }

    /**
     * A param shaped like the placeholders a phrase's elements are rebuilt
     * from is text: it cannot wrap itself in one of the source's elements or
     * repeat one, on any route.
     *
     * @dataProvider routeProvider
     */
    public function testAParamShapedLikeAnElementPlaceholderIsText($route): void
    {
        foreach (['no nonce' => '', 'made-up nonce' => str_repeat('0', 32)] as $kind => $nonce) {
            $wrap = "\u{E000}{$nonce}0\u{E001}CLICK\u{E002}{$nonce}0\u{E003}";
            list($html) = $this->render($route, 'Hi {name}, read the <a href="/terms">terms</a>', [], null, ['name' => $wrap]);
            $this->assertSame(1, substr_count($html, '<a '), $kind . ': ' . $html);
            $this->assertStringContainsString('CLICK', $html, $kind);
            $this->assertStringContainsString('<a href="/terms">terms</a>', $html, $kind);

            $repeat = "\u{E000}{$nonce}0\u{E001}\u{E002}{$nonce}0\u{E003}";
            list($html) = $this->render($route, 'Hi {name}<script>track()</script>', [], null, ['name' => $repeat]);
            $this->assertSame(1, substr_count($html, '<script>'), $kind . ': ' . $html);
        }
    }

    public function routeProvider()
    {
        return ['translateRich' => ['rich'], 'page phrase host' => ['page'], 'block phrase host' => ['block']];
    }

    public function shapeProvider()
    {
        $shapes = [
            'literal text' => '<span translate="no">Langsys</span>',
            'a marked value' => '<span translate="no"><!--ls:email-->a@b.c<!--/ls--></span>',
            'an attribute-form marked value' => '<span translate="no"><span data-ls-param="email">a@b.c</span></span>',
            'an unmarked value' => '<span translate="no">a@b.c</span>',
            'text beside a value' => '<span translate="no">user <!--ls:email-->a@b.c<!--/ls--></span>',
            'data-notrans' => '<span data-notrans>Langsys</span>',
        ];

        $cases = [];
        foreach ($this->routeProvider() as $route => list($path)) {
            foreach ($shapes as $label => $shape) {
                $cases["$route, $label"] = [$path, $shape];
            }
        }

        return $cases;
    }

    /**
     * The spec's test: every form keys the same `{m0o}{m0c}` phrase, and the
     * element renders its source content, the value as it was marked and
     * never as `{email}`.
     *
     * @dataProvider shapeProvider
     */
    public function testEveryExcludedShapeKeysOneEmptyPairAndRendersAsGiven($route, $shape): void
    {
        list($html, $queued) = $this->render($route, 'Signed in as ' . $shape . ' today');

        $this->assertSame(['Signed in as {m0o}{m0c} today'], $queued);
        $this->assertSame('Signed in as ' . $shape . ' today', $html);
    }

    /**
     * A translation's text inside an excluded element's token pair is
     * dropped: the element is restored from source, never rewritten.
     *
     * @dataProvider routeProvider
     */
    public function testATranslationCannotWriteIntoAnExcludedElement($route): void
    {
        list($html) = $this->render($route, 'Signed in as <span translate="no"><!--ls:email-->a@b.c<!--/ls--></span> today', ['Signed in as {m0o}{m0c} today' => 'Conectado como {m0o}EXTRA{m0c} hoy']);

        $this->assertSame('Conectado como <span translate="no"><!--ls:email-->a@b.c<!--/ls--></span> hoy', $html);
    }

    /**
     * A phrase of only excluded content has no text: nothing registers and
     * it renders as given.
     *
     * @dataProvider routeProvider
     */
    public function testAPhraseOfOnlyExcludedContentRegistersNothing($route): void
    {
        foreach (['<span translate="no">Langsys</span>', ' <span data-notrans>A</span> <b translate="no"><!--ls:email-->a@b.c<!--/ls--></b> '] as $source) {
            list($html, $queued) = $this->render($route, $source);

            $this->assertSame([], $queued, $source);
            $this->assertSame($source, $html, $source);
        }
    }

    /**
     * A host with no text of its own still has its attributes, and its
     * descendants', registered and translated, on the page as in a block.
     *
     * @dataProvider routeProvider
     */
    public function testAHostOfOnlyExcludedContentStillTranslatesItsAttributes($route): void
    {
        if ($route === 'rich') {
            $this->addToAssertionCount(1);

            return;
        }

        list($html, $queued) = $this->render($route, '<span translate="no">Langsys</span> <img alt="Logo">');
        $this->assertSame(['Logo'], $queued, 'the attribute registers, the excluded text does not');

        $client = $this->client(['Logo' => 'Logotipo', 'Hello' => 'Hola']);
        $out = $route === 'page'
            ? $client->translatePage('<html><body><p data-ls-phrase title="Hello"><span translate="no">Langsys</span> <img alt="Logo"></p></body></html>', 'UI')
            : $client->translateContentBlock('<p data-ls-phrase title="Hello"><span translate="no">Langsys</span> <img alt="Logo"></p>', 'UI');
        $this->assertStringContainsString('title="Hola"', $out);
        $this->assertStringContainsString('alt="Logotipo"', $out);
        $this->assertStringContainsString('<span translate="no">Langsys</span>', $out);

        // Applied once: a translation is never looked up again as source.
        $client = $this->client(['Logo' => 'Logotipo', 'Hello' => 'Hola', 'Hola' => 'not this']);
        $out = $route === 'page'
            ? $client->translatePage('<html><body><p data-ls-phrase title="Hello"><span translate="no">Langsys</span> <img alt="Logo"></p></body></html>', 'UI')
            : $client->translateContentBlock('<p data-ls-phrase title="Hello"><span translate="no">Langsys</span> <img alt="Logo"></p>', 'UI');
        $this->assertStringContainsString('title="Hola"', $out);
    }

    /**
     * `data-notrans="false"` is no exclusion: its text is the phrase's.
     *
     * @dataProvider routeProvider
     */
    public function testAnOptedOutExclusionKeepsItsText($route): void
    {
        list(, $queued) = $this->render($route, 'Welcome to <span data-notrans="false">Langsys</span>');

        $this->assertSame(['Welcome to {m0o}Langsys{m0c}'], $queued);
    }

    /**
     * The spec's test: on a miss for its key, a host holding an excluded
     * element renders the translation under the key an earlier reader gave
     * it, the element restored from source, and registers only the current
     * key.
     *
     * @dataProvider routeProvider
     */
    public function testAMissRendersTheEarlierKeysTranslationAndRegistersOnlyTheCurrentKey($route): void
    {
        list($html, $queued) = $this->render($route, 'Welcome to <span translate="no">Langsys</span>', ['Welcome to {m0o}Langsys{m0c}' => 'Bienvenido a {m0o}Langsys{m0c}']);

        $this->assertSame('Bienvenido a <span translate="no">Langsys</span>', $html);
        $this->assertSame(['Welcome to {m0o}{m0c}'], $queued, 'never the earlier key');

        list($html) = $this->render($route, 'Signed in as <span translate="no"><!--ls:email-->a@b.c<!--/ls--></span> today', ['Signed in as {m0o}{email}{m0c} today' => 'Conectado como {m0o}{email}{m0c} hoy']);
        $this->assertSame('Conectado como <span translate="no"><!--ls:email-->a@b.c<!--/ls--></span> hoy', $html, 'a marked value inside, as the earlier reader keyed it');
    }

    /**
     * The earlier key's slots are renumbered onto the current ones: an
     * element inside the excluded one had a slot of its own then.
     *
     * @dataProvider routeProvider
     */
    public function testTheEarlierTranslationTakesTheCurrentSlots($route): void
    {
        list($html, $queued) = $this->render($route, '<span translate="no">a <b>x</b></span> and <i>y</i>', ['{m0o}a {m1o}x{m1c}{m0c} and {m2o}y{m2c}' => '{m2o}Y{m2c} y {m0o}A {m1o}X{m1c}{m0c}']);

        $this->assertSame('<i>Y</i> y <span translate="no">a <b>x</b></span>', $html);
        $this->assertSame(['{m0o}{m0c} and {m1o}y{m1c}'], $queued);

        list($html) = $this->render($route, '<span translate="no">a <b>x</b></span> and <i>y</i>', ['{m0o}a {m1o}x{m1c}{m0c} and {m2o}y{m2c}' => '{m0o}A{m0c} y {m1o}X{m1c} {m2o}Y{m2c}']);
        $this->assertSame('<span translate="no">a <b>x</b></span> y X <i>Y</i>', $html, 'an inner element moved out of the excluded one has no slot now');
    }

    /**
     * An excluded element inside an ordinary one is kept whole too, and its
     * phrase is looked up under the earlier key as well.
     *
     * @dataProvider routeProvider
     */
    public function testAnExcludedElementNestedInAnOrdinaryOne($route): void
    {
        $source = 'Signed in as <b>user <span translate="no"><!--ls:email-->a@b.c<!--/ls--></span></b> today';

        list($html, $queued) = $this->render($route, $source);
        $this->assertSame(['Signed in as {m0o}user {m1o}{m1c}{m0c} today'], $queued);
        $this->assertSame($source, $html);

        list($html) = $this->render($route, $source, ['Signed in as {m0o}user {m1o}{email}{m1c}{m0c} today' => 'Conectado como {m0o}usuario {m1o}{email}{m1c}{m0c} hoy']);
        $this->assertSame('Conectado como <b>usuario <span translate="no"><!--ls:email-->a@b.c<!--/ls--></span></b> hoy', $html);
    }

    /**
     * The current key outranks the earlier one, translated or registered
     * untranslated alike, and a phrase with no excluded element makes no
     * earlier lookup.
     *
     * @dataProvider routeProvider
     */
    public function testTheCurrentKeyWinsAndAControlPhraseKeepsItsKey($route): void
    {
        list($html) = $this->render($route, 'Welcome to <span translate="no">Langsys</span>', ['Welcome to {m0o}{m0c}' => 'Hola {m0o}{m0c}', 'Welcome to {m0o}Langsys{m0c}' => 'Bienvenido a {m0o}Langsys{m0c}']);
        $this->assertSame('Hola <span translate="no">Langsys</span>', $html);

        list($html, $queued) = $this->render($route, 'Welcome to <span translate="no">Langsys</span>', ['Welcome to {m0o}{m0c}' => null, 'Welcome to {m0o}Langsys{m0c}' => 'Bienvenido a {m0o}Langsys{m0c}']);
        $this->assertSame('Bienvenido a <span translate="no">Langsys</span>', $html, 'registered but untranslated is a miss');
        $this->assertSame([], $queued, 'already registered');

        list($html) = $this->render($route, 'Welcome to <span translate="no">Langsys</span>', ['Welcome to {m0o}Langsys{m0c}' => '']);
        $this->assertSame('Welcome to <span translate="no">Langsys</span>', $html, 'an untranslated earlier key is a miss too');

        list($html, $queued) = $this->render($route, 'Welcome to <b>Langsys</b>', ['Welcome to {m0o}Langsys{m0c}' => 'Bienvenido a {m0o}Langsys{m0c}']);
        $this->assertSame('Bienvenido a <b>Langsys</b>', $html);
        $this->assertSame([], $queued);
    }

    /**
     * A seeded snapshot holding the current key untranslated renders the
     * earlier key's translation it holds, as the live catalog does.
     *
     * @dataProvider routeProvider
     */
    public function testASeededSnapshotAnswersUnderTheEarlierKeyToo($route): void
    {
        list($html) = $this->render($route, 'Welcome to <span translate="no">Langsys</span>', [], ['Welcome to {m0o}{m0c}' => '', 'Welcome to {m0o}Langsys{m0c}' => 'Bienvenido a {m0o}Langsys{m0c}']);

        $this->assertSame('Bienvenido a <span translate="no">Langsys</span>', $html);
    }

    /**
     * In a framework call's line the source is a template: a placeholder the
     * caller passes is filled inside an excluded element too, as plain text,
     * on a miss and on a hit, in the element's descendants too, and the key
     * is unchanged. One it does not pass, or passes as null, stays as
     * written, and so does a script, inside the element or not.
     */
    public function testARichLineFillsTheCallersParamsInsideAnExcludedElement(): void
    {
        $source = 'Write to <b translate="no">{email} <i>{email}</i><script>go("{email}")</script> {other}</b> for help';
        $filled = '<b translate="no">ana@example.com <i>ana@example.com</i><script>go("{email}")</script> {other}</b>';
        $params = ['email' => 'ana@example.com', 'other' => null];

        $client = $this->client();
        $this->assertSame('Write to ' . $filled . ' for help', $client->translateRich($source, null, 'UI', $params)['html']);
        $this->assertSame(['Write to {m0o}{m0c} for help'], array_values(array_column($client->getPendingPhrases(), 'phrase')));

        $client = $this->client(['Write to {m0o}{m0c} for help' => 'Escribe a {m0o}{m0c} para ayuda']);
        $this->assertSame('Escribe a ' . $filled . ' para ayuda', $client->translateRich($source, null, 'UI', $params)['html']);

        $this->assertSame('Run <script>go("{email}")</script> now', $this->client()->translateRich('Run <script>go("{email}")</script> now', null, 'UI', $params)['html']);
    }

    /**
     * Inside an excluded element a value renders as the Interpolator writes
     * it elsewhere in the line: `%name%` is `{name}` (TOK-5), a boolean is
     * `true` or `false`, a number its digits; a date, which is no plain
     * text, stays as written.
     */
    public function testAFillRendersEachValueAsTheInterpolatorDoes(): void
    {
        $params = ['email' => 'ana@example.com', 'on' => true, 'off' => false, 'count' => 3, 'ratio' => 2.5, 'when' => new \DateTime('2026-10-14T12:00:00Z')];

        $html = $this->client()->translateRich('Write to <b translate="no">%email% {on} {off} {count} {ratio} {when} %when%</b> for help', null, 'UI', $params)['html'];

        $this->assertSame('Write to <b translate="no">ana@example.com true false 3 2.5 {when} %when%</b> for help', $html);
    }

    /**
     * A rich line made only of an excluded element registers nothing and
     * renders with the caller's params filled.
     */
    public function testAnExcludedOnlyRichLineFillsTheCallersParams(): void
    {
        $client = $this->client();

        $this->assertSame(['html' => '<b translate="no">ana@example.com</b>', 'from' => 'source'], $client->translateRich('<b translate="no">{email}</b>', null, 'UI', ['email' => 'ana@example.com']));
        $this->assertSame([], $client->getPendingPhrases());
    }

    /**
     * A page or a block keeps excluded text exactly as written, params or
     * not: `<code translate="no">{name}</code>` is how a page shows the
     * syntax itself.
     */
    public function testAPageOrBlockKeepsExcludedTextAsWritten(): void
    {
        foreach (['<p>Use <code translate="no">{name}</code> for the name</p>', '<p data-ls-phrase>Use <code translate="no">{name}</code> for the name</p>'] as $html) {
            $page = $this->client()->translatePage('<html><body>' . $html . '</body></html>', 'UI', [], ['name' => 'Ana']);
            $this->assertStringContainsString('<code translate="no">{name}</code>', $page, $html);

            $block = $this->client()->translateContentBlock($html, 'UI', ['name' => 'Ana']);
            $this->assertStringContainsString('<code translate="no">{name}</code>', $block, $html);
        }
    }

    /**
     * The shared fixture's rich-phrase rows, authored by the JS core
     * (`canonicalization-reference.json`, `phrase_cases`), executed through
     * the reader translateRich() keys with: the phrase by codepoint, the
     * values it renders with, whether it registers, and - where a row names
     * one - the earlier key this SDK's v1.0.1-v1.0.2 reader gave the host,
     * which for a host with no excluded element is its current key.
     */
    public function testTheSharedRichPhraseRowsHold(): void
    {
        $fixture = json_decode(file_get_contents(dirname(__DIR__) . '/fixtures/canonicalization-reference.json'), true);
        $this->assertCount(13, $fixture['phrase_cases']);

        foreach ($fixture['phrase_cases'] as $row) {
            $line = \Langsys\SDK\Html\MarkupTokenizer::readLine($row['html']);
            $phrase = $line['encoded']['text'];

            $codepoints = array_map(function ($char) {
                return sprintf('U+%04X', mb_ord($char, 'UTF-8'));
            }, preg_split('//u', $phrase, -1, PREG_SPLIT_NO_EMPTY));
            $this->assertSame($row['expected_phrase_codepoints'], $codepoints, $row['id'] . ': got ' . $phrase);

            preg_match_all('/\{([a-z][a-z0-9_]*)\}/', $phrase, $named);
            $values = array_intersect_key(array_map(function ($unused) {
                return true;
            }, $line['markers']->sentinels()), array_flip($named[1]));
            $this->assertSame(array_keys($row['expected_values']), array_keys($values), $row['id'] . ': values');

            $client = $this->client();
            $client->translateRich($row['html'], null, $row['category']);
            $this->assertSame($row['registers'] ? [$row['expected_phrase']] : [], array_values(array_column($client->getPendingPhrases(), 'phrase')), $row['id'] . ': registers');

            if (array_key_exists('earlier_phrase', $row)) {
                $earlier = $line['encoded']['earlier'] === null ? $phrase : $line['encoded']['earlier']['text'];
                $this->assertSame($row['earlier_phrase'], $earlier, $row['id'] . ': earlier key');
            }
        }
    }

    /**
     * A script is kept whole by TOK-1's own list, author-excluded or not,
     * and no earlier reader walked into it: no earlier key is looked up.
     *
     * @dataProvider routeProvider
     */
    public function testAScriptHasNoEarlierKey($route): void
    {
        foreach (['<script>go()</script>', '<script translate="no">go()</script>'] as $script) {
            list($html, $queued) = $this->render($route, 'Run ' . $script . ' now', ['Run {m0o}go(){m0c} now' => 'Corre {m0o}go(){m0c} ya']);

            $this->assertSame(['Run {m0o}{m0c} now'], $queued, $script);
            $this->assertSame('Run ' . $script . ' now', $html, $script);
        }
    }
}
