<?php

namespace Langsys\SDK\Tests\Docs;

use PHPUnit\Framework\TestCase;

/**
 * Every surface that shows a developer how to use this package writes a phrase
 * flat, as the sentence reads, each value a `{placeholder}`: `You have {count}
 * new messages`. None writes a `plural`, `select` or `selectordinal` into a
 * phrase, because Langsys makes each language's forms, the base language's
 * included (spec ICU-7).
 *
 * ICU appears in the documentation in three roles only, each a passage named
 * in EXEMPT with its role:
 * - `catalog`: what the catalog returns, for code that renders a catalog string
 *   itself or for a reader wondering what a translation holds;
 * - `mig-4`: what the migration converts a legacy plural file into, which the
 *   SDK registers, not the developer;
 * - `counter-example`: ICU marked wrong, beside the flat phrase that replaces it.
 * A new ICU example fails here until someone decides it belongs in one of them
 * and names it, and a named passage that is reworded, repeated or loses its
 * marking fails too, so it is decided again.
 *
 * The surfaces are every Markdown document outside tests/ and vendor/ (the
 * README, the upgrade guide, the agent skill, docs/) and every file in
 * examples/, of the files git tracks or would track: one it ignores, such as
 * a developer's own CLAUDE.md, is in no download and on no page. CHANGELOG.md records what earlier releases did and is outside the
 * rule; CONFORMANCE.md grades this package against the spec, quotes the spec's
 * rules about ICU, and is not in the Composer download.
 *
 * scan() reads a file's text as data, so each check is proven by planting the
 * defect it exists to catch.
 */
class FlatPhrasesTest extends TestCase
{
    /**
     * An ICU argument that selects a branch: `{count, plural, …}`,
     * `{name_gender, select, …}`, `{0, plural, …}`. ICU names an argument
     * with any run of characters but space and syntax, or with a number.
     */
    const ICU_CONSTRUCT = '/\{\s*[^\s{},]+\s*,\s*(?:plural|select|selectordinal)\b/';

    const ROLES = ['catalog', 'mig-4', 'counter-example'];

    /** Documents outside the rule, each with its reason. Any other document is scanned. */
    const OUTSIDE = [
        'CHANGELOG.md' => 'a changelog records what earlier releases did',
        'CONFORMANCE.md' => 'grades this package against the spec for its maintainers, and is not in the Composer download',
    ];

    /**
     * The passages where ICU is allowed. `text` occurs exactly once in `file`
     * and holds every construct it exempts. `mark` is what shows the passage
     * for what it is, on its own line or in the three lines before it, where
     * the sentence introducing a code block sits. A counter-example's `flat`
     * is its flat replacement, on its own line or the line on either side.
     *
     * No passage serves the `mig-4` role: the README's and the skill's
     * migration sections say in prose that a plural file becomes one ICU
     * phrase, with no construct in them.
     */
    const EXEMPT = [
        [
            // A Russian translation of a markup phrase, showing that the
            // tokens move with the words; the phrase registered is flat.
            'file' => 'README.md',
            'role' => 'catalog',
            'text' => 'На основе {n, plural, one {# {m0o}отзыва{m0c}} other {# {m0o}отзывов{m0c}}}',
            'mark' => 'Translators may **move** them',
        ],
        [
            'file' => 'README.md',
            'role' => 'catalog',
            'text' => '`{count, plural, one {Tienes # mensaje nuevo.} other {Tienes # mensajes nuevos.}}`',
            'mark' => 'is stored for Spanish as',
        ],
        [
            'file' => 'README.md',
            'role' => 'catalog',
            'text' => '`{name_gender, select, …}`',
            'mark' => 'in the translation',
        ],
        [
            'file' => 'UPGRADING.md',
            'role' => 'catalog',
            'text' => '`{count, plural, one {…} other {…}}`',
            'mark' => 'return a plural',
        ],
        [
            'file' => 'skill/catalog.md',
            'role' => 'catalog',
            'text' => '`{count, plural, one {…} other {…}}`',
            'mark' => 'returns a plural or select Langsys generated',
        ],
        [
            'file' => 'skill/interpolation.md',
            'role' => 'catalog',
            'text' => '`{name_gender, select, …}`',
            'mark' => 'in the translation',
        ],
        [
            'file' => 'skill/invariants.md',
            'role' => 'counter-example',
            'text' => "\$client->translate('{count, plural, one {# new message} other {# new messages}}', null, 'Inbox', null, ['count' => \$count]);",
            'mark' => '❌',
            'flat' => "\$client->translate('You have {count} new messages', null, 'Inbox', null, ['count' => \$count]);   // ✅",
        ],
        [
            // The quick-reference table's Don't column.
            'file' => 'skill/invariants.md',
            'role' => 'counter-example',
            'text' => '`{count, plural, …}`',
            'mark' => 'written by hand',
            'flat' => "`translate('You have {count} new messages', null, 'Inbox', null, ['count' => \$n])`",
        ],
    ];

    public function testItScansTheReadmeTheUpgradeGuideTheSkillTheDocsAndTheExamples()
    {
        $surfaces = self::surfaces();

        foreach (['README.md', 'UPGRADING.md', 'skill/SKILL.md', 'skill/invariants.md', 'docs/langsys-llm-docs.md', 'examples/basic-usage.php'] as $expected) {
            $this->assertContains($expected, $surfaces);
        }
        foreach (array_keys(self::OUTSIDE) as $outside) {
            $this->assertNotContains($outside, $surfaces);
        }
        foreach ($surfaces as $surface) {
            $this->assertDoesNotMatchRegularExpression('#^(tests|vendor)/#', $surface);
        }
    }

    public function testNoSurfaceWritesIcuIntoAPhraseOutsideTheNamedPassages()
    {
        $findings = [];
        foreach (self::surfaces() as $surface) {
            $text = file_get_contents(self::root() . '/' . $surface);
            $findings = array_merge($findings, self::scan($surface, $text, self::EXEMPT));
        }

        $this->assertSame([], $findings);
    }

    public function testEveryNamedPassageHasARoleAndIsInAScannedSurface()
    {
        $surfaces = self::surfaces();
        foreach (self::EXEMPT as $passage) {
            $this->assertContains($passage['role'], self::ROLES);
            $this->assertContains($passage['file'], $surfaces);
            $this->assertSame(1, preg_match(self::ICU_CONSTRUCT, $passage['text']), $passage['text']);
            if ($passage['role'] === 'counter-example') {
                $this->assertSame(0, preg_match(self::ICU_CONSTRUCT, $passage['flat']), $passage['flat']);
            }
        }
    }

    public function testTheChangelogIsOutsideTheRule()
    {
        $this->assertArrayHasKey('CHANGELOG.md', self::OUTSIDE);
        $this->assertNotContains('CHANGELOG.md', self::surfaces());
    }

    public function testFlatPhrasesPass()
    {
        $flat = implode("\n", [
            '`You have {count} new messages`',
            "\$client->translate('You have {count} new messages', null, 'Inbox', null, ['count' => \$count]);",
            '<p data-langsys-phrase>Based on {n} <strong>reviews</strong></p>',
        ]);

        $this->assertSame([], self::scan('x.md', $flat, []));
    }

    public function plantedIcu()
    {
        return [
            'a translate() argument' => ["\$client->translate('{count, plural, one {# item} other {# items}}', null, 'Cart', null, ['count' => \$n]);"],
            'no spaces' => ["\$client->translate('{n,plural,one{# item}other{# items}}', null, 'Cart', null, ['n' => \$n]);"],
            'a double-quoted string' => ["\$client->translate(\"{count, plural, one {# item} other {# items}}\");"],
            'a marked phrase in markup' => ['<p data-langsys-phrase>{count, plural, one {# review} other {# reviews}}</p>'],
            'a select' => ["\$client->translate('{who_gender, select, male {He} other {They}} left', null, 'UI');"],
            'a selectordinal in prose' => ['Write `{place, selectordinal, one {#st} two {#nd} other {#th}}` and it works.'],
            'an =0 branch in an example' => ["echo \$client->translate('{count, plural, =0 {None} other {# left}}', null, 'Demo', null, ['count' => \$count]);"],
            'a message template' => ["const MESSAGE = '{count, plural, one {# attempt} other {# attempts}} left';"],
            'a numbered argument' => ["\$client->translate('{0, plural, one {# item} other {# items}}', null, 'Cart');"],
            'an argument named in another language' => ["\$client->translate('{número, plural, one {# artículo} other {# artículos}}', null, 'Cart');"],
        ];
    }

    /** @dataProvider plantedIcu */
    public function testItFailsOnICUWrittenIntoAPhrase($text)
    {
        $findings = self::scan('examples/basic-usage.php', $text, []);

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('ICU construct in a phrase', $findings[0]);
    }

    public function testTheWordsPluralAndSelectWithNoArgumentPass()
    {
        $text = '`<select>` picks a locale; pluralization needs the count and its noun in one phrase; a {select} placeholder; {n, number}';

        $this->assertSame([], self::scan('x.md', $text, []));
    }

    public function testACatalogPassageIsAllowedWhereItsMarkingIs()
    {
        $text = "The catalog returns it for Spanish as\n\n```\n{count, plural, one {# mensaje} other {# mensajes}}\n```";

        $this->assertSame([], self::scan('x.md', $text, [self::catalogPassage()]));
    }

    public function testACatalogPassageFailsWhenItsMarkingIsMoreThanThreeLinesAbove()
    {
        $text = "The catalog returns it for Spanish as\n\n\n\n{count, plural, one {# mensaje} other {# mensajes}}";

        $this->assertStringContainsString('lost its marking', implode("\n", self::scan('x.md', $text, [self::catalogPassage()])));
    }

    public function testACounterExampleMarkedWrongBesideItsFlatPhrasePasses()
    {
        $this->assertSame([], self::scan('x.md', self::markedCounterExample(), [self::counterExample()]));
    }

    public function testACounterExampleFailsWhenItLosesItsMarking()
    {
        $findings = self::scan('x.md', str_replace('❌', '', self::markedCounterExample()), [self::counterExample()]);

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('lost its marking', $findings[0]);
    }

    public function testACounterExampleFailsWhenItsFlatReplacementIsGone()
    {
        $findings = self::scan('x.md', explode("\n", self::markedCounterExample())[1], [self::counterExample()]);

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('no flat replacement', $findings[0]);
    }

    public function testASecondCopyOfANamedPassageFailsAndSoDoesAConstructOutsideIt()
    {
        $wrong = explode("\n", self::markedCounterExample())[1];

        $twice = self::scan('x.md', self::markedCounterExample() . "\n" . $wrong, [self::counterExample()]);
        $this->assertCount(2, $twice);
        $this->assertStringContainsString('more than once', $twice[0]);
        $this->assertStringContainsString('ICU construct in a phrase', $twice[1]);

        $other = self::scan('x.md', self::markedCounterExample() . "\n" . "\$client->translate('{n, plural, one {#} other {#}}');", [self::counterExample()]);
        $this->assertCount(1, $other);
        $this->assertStringContainsString('ICU construct in a phrase', $other[0]);
    }

    public function testANamedPassageThatNoLongerExistsFails()
    {
        $findings = self::scan('x.md', 'You have {count} new messages', [self::counterExample()]);

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('not found', $findings[0]);
    }

    public function testAPassageNamedForAnotherFileExemptsNothingHere()
    {
        $findings = self::scan('y.md', self::markedCounterExample(), [self::counterExample()]);

        $this->assertCount(1, $findings);
        $this->assertStringContainsString('ICU construct in a phrase', $findings[0]);
    }

    /**
     * What is wrong in one surface: an ICU construct no named passage holds, or
     * a named passage that is missing, repeated, unmarked, or a counter-example
     * with no flat phrase beside it. Each finding is `file:line problem`.
     *
     * @return string[]
     */
    public static function scan(string $file, string $text, array $exempt): array
    {
        $findings = [];
        $lines = explode("\n", $text);
        $spans = [];

        foreach ($exempt as $passage) {
            if ($passage['file'] !== $file) {
                continue;
            }
            $role = $passage['role'];
            $first = strpos($text, $passage['text']);
            if ($first === false) {
                $findings[] = "$file:0 named passage ($role) not found: " . $passage['text'];
                continue;
            }
            $line = self::lineOf($text, $first);
            if (strpos($text, $passage['text'], $first + 1) !== false) {
                $findings[] = "$file:$line named passage ($role) appears more than once: " . $passage['text'];
            }
            $spans[] = [$first, $first + strlen($passage['text'])];

            $from = max(0, $line - 4);
            if (strpos(implode("\n", array_slice($lines, $from, $line - $from)), $passage['mark']) === false) {
                $findings[] = "$file:$line named passage ($role) lost its marking: " . $passage['mark'];
            }
            if ($role === 'counter-example') {
                $from = max(0, $line - 2);
                if (strpos(implode("\n", array_slice($lines, $from, $line + 1 - $from)), $passage['flat']) === false) {
                    $findings[] = "$file:$line counter-example has no flat replacement beside it: " . $passage['flat'];
                }
            }
        }

        preg_match_all(self::ICU_CONSTRUCT, $text, $matches, PREG_OFFSET_CAPTURE);
        foreach ($matches[0] as $match) {
            $at = $match[1];
            foreach ($spans as $span) {
                if ($at >= $span[0] && $at < $span[1]) {
                    continue 2;
                }
            }
            $line = self::lineOf($text, $at);
            $findings[] = "$file:$line ICU construct in a phrase, not a named passage: " . trim($lines[$line - 1]);
        }

        return $findings;
    }

    /** Paths from the repository root, forward slashes, sorted. */
    public function testAFileGitIgnoresIsNoSurface()
    {
        $root = sys_get_temp_dir() . '/flat-phrases-' . bin2hex(random_bytes(6));
        mkdir($root . '/examples', 0700, true);
        try {
            exec('git -C ' . escapeshellarg($root) . ' init -q 2>&1', $output, $status);
            if ($status !== 0) {
                $this->markTestSkipped('git is not available: ' . implode(' ', $output));
            }
            file_put_contents($root . '/.gitignore', "CLAUDE.md\n");
            file_put_contents($root . '/README.md', 'You have {count} new messages');
            file_put_contents($root . '/CLAUDE.md', '{count, plural, one {# item} other {# items}}');
            file_put_contents($root . '/examples/demo.php', '<?php');

            $this->assertSame(['README.md', 'examples/demo.php'], self::surfaces($root));
        } finally {
            exec('rm -rf ' . escapeshellarg($root));
        }
    }

    private static function surfaces(?string $root = null): array
    {
        $root = $root === null ? self::root() : $root;
        $listed = self::gitFiles($root);
        $surfaces = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveCallbackFilterIterator(
            new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS),
            function (\SplFileInfo $file) use ($root) {
                $path = substr($file->getPathname(), strlen($root) + 1);
                return !in_array($path, ['.git', 'vendor', 'tests', 'node_modules'], true);
            }
        ));
        foreach ($files as $file) {
            $path = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));
            $isDoc = substr($path, -3) === '.md' && !array_key_exists($path, self::OUTSIDE);
            if (($isDoc || strpos($path, 'examples/') === 0) && ($listed === null || isset($listed[$path]))) {
                $surfaces[] = $path;
            }
        }
        sort($surfaces);

        return $surfaces;
    }

    /**
     * The files under $root that git tracks or would track, as keys, or null
     * where git cannot say, when every file is a surface.
     */
    private static function gitFiles(string $root): ?array
    {
        $listing = shell_exec('git -C ' . escapeshellarg($root) . ' ls-files --cached --others --exclude-standard -z 2>/dev/null');
        if (!is_string($listing) || $listing === '') {
            return null;
        }

        return array_fill_keys(array_filter(explode("\0", $listing), 'strlen'), true);
    }

    private static function root(): string
    {
        return dirname(__DIR__, 2);
    }

    private static function lineOf(string $text, int $offset): int
    {
        return substr_count($text, "\n", 0, $offset) + 1;
    }

    private static function catalogPassage(): array
    {
        return [
            'file' => 'x.md',
            'role' => 'catalog',
            'text' => '{count, plural, one {# mensaje} other {# mensajes}}',
            'mark' => 'The catalog returns it',
        ];
    }

    private static function counterExample(): array
    {
        return [
            'file' => 'x.md',
            'role' => 'counter-example',
            'text' => "\$client->translate('{count, plural, one {#} other {#}}');",
            'mark' => '❌',
            'flat' => "\$client->translate('You have {count} new messages');   // ✅",
        ];
    }

    private static function markedCounterExample(): string
    {
        return "\$client->translate('You have {count} new messages');   // ✅\n"
            . "\$client->translate('{count, plural, one {#} other {#}}');   // ❌ ICU written by hand";
    }
}
