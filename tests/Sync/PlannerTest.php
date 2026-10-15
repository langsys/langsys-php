<?php

namespace Langsys\SDK\Tests\Sync;

use Langsys\SDK\Sync\Planner;
use Langsys\SDK\Sync\SourceScanner;
use PHPUnit\Framework\TestCase;

/**
 * The sync rules without a client: a call registers the phrase the same call
 * looks up at runtime, and a plan with no catalog and no key still reports
 * what a strict run fails on.
 */
class PlannerTest extends TestCase
{
    /** @var string|null */
    private $dir;

    protected function tearDown(): void
    {
        if ($this->dir === null) {
            return;
        }
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $entry) {
            $entry->isDir() ? rmdir($entry->getPathname()) : unlink($entry->getPathname());
        }
        rmdir($this->dir);
    }

    private function hits($code)
    {
        return (new SourceScanner())->scan("<?php\n" . $code, 'app.php');
    }

    private function phrases($code)
    {
        return array_column(Planner::offline($this->hits($code))->items, 'phrase');
    }

    /**
     * Replacements the source does not spell out still pass their keys at
     * runtime, so sync registers the placeholder the lookup uses: a
     * compact() by its names, a variable by every placeholder in the text.
     * A literal empty array and no replacements pass none.
     */
    public function testAPhraseMatchesWhatTheRuntimeCallLooksUp(): void
    {
        $this->assertSame([
            'Limit {limit}, max {max}.',
            'Limit {limit}, max {max}, note:done.',
            'Limit :limit as written.',
            'Plain :limit too.',
            '{count, plural, one {# item} other {# items over {limit}}}',
            'Mixed {limit} and {max}.',
        ], $this->phrases(
            "__('Limit :limit, max :max.', compact('limit', 'max'));\n"
            . "__('Limit :limit, max :max, note:done.', \$params);\n"
            . "__('Limit :limit as written.', []);\n"
            . "__('Plain :limit too.');\n"
            . "trans_choice(':count item|:count items over :limit', \$n, \$replace);\n"
            . "__('Mixed :limit and :max.', compact('limit', \$other));"
        ), 'a compact() naming a variable is as dynamic as the variable');
    }

    /**
     * The spec's strict cases, with no client and no key: every phrase is
     * new, a non-literal call is reported, a label line goes through the
     * validation listing, and a runtime key in a held or named group is
     * covered.
     */
    public function testAnOfflinePlanReportsWhatAStrictRunFailsOn(): void
    {
        $this->dir = sys_get_temp_dir() . '/langsys-planner-' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        file_put_contents($this->dir . '/auth.php', '<?php return ["failed" => "Wrong credentials."];');

        $plan = Planner::offline(
            $this->hits("__('Welcome');\n__(\$dynamic);\n__('The :attribute is required.');\n__(\"auth.\$k\");\n__(\"validation.\$k\");"),
            ['files' => [$this->dir . '/auth.php']],
            [],
            ['covered_groups' => ['validation']]
        );

        $this->assertSame(['Welcome', 'Wrong credentials.'], array_column($plan->items, 'phrase'));
        $this->assertSame(['new'], array_values(array_unique(array_column($plan->items, 'status'))));
        $this->assertSame([3], array_column($plan->reported, 'line'));
        $this->assertSame([':attribute'], array_column($plan->viaValidation, 'placeholder'));
        $this->assertSame(['auth', 'validation'], array_column($plan->covered, 'group'));
        $this->assertTrue($plan->failsStrict());
    }

    /**
     * MSG-7: a translate call inside an app message's template method is
     * that message's sentence, registered by the messages listing - never as
     * an uncategorised literal. The same call anywhere else is a phrase.
     */
    public function testACallInAnAppMessagesTemplateIsTheListings(): void
    {
        $plan = Planner::offline($this->hits(
            "class Quota implements HasAppMessageTemplate {\n"
            . "  public function template() { return __('You hit the limit.'); }\n"
            . "  public function hint() { return __('Upgrade your plan.'); }\n"
            . "}\n"
            . "class Plain { public function template() { return __('Not a message.'); } }"
        ));

        $this->assertSame([['file' => 'app.php', 'line' => 3, 'entry_point' => '__', 'class' => 'Quota', 'phrase' => 'You hit the limit.']], $plan->viaMessageListing);
        $this->assertSame(['Upgrade your plan.', 'Not a message.'], array_column($plan->items, 'phrase'));
        $this->assertFalse($plan->failsStrict());
    }

    /**
     * Langsys's shapes: a call in an enum's template() match arm, and one
     * nested in a helper call with a $locale argument, in a class that
     * inherits the contract - found through the classes the binding names,
     * since no scan sees an inherited interface.
     */
    public function testEveryShapeOfATemplateMethodCallIsTheListings(): void
    {
        $code = "namespace App\\Errors;\n"
            . "enum ApiErrors: string implements HasAppMessageTemplate {\n"
            . "  case Key = 'key';\n"
            . "  public function template(string \$locale = 'en'): string { return match (\$this) { self::Key => __('Invalid API key', [], \$locale) }; }\n"
            . "}\n"
            . "class ProjectLimitReachedError extends ApiError {\n"
            . "  public function template(string \$locale = 'en') { return self::source(__('You reached the :limit project limit.', ['limit' => ':limit'], \$locale)); }\n"
            . "  public function other() { return __('Not a template.'); }\n"
            . "}";

        $plan = Planner::offline($this->hits($code), null, [], ['message_classes' => ['\\App\\Errors\\ProjectLimitReachedError']]);

        $this->assertSame(['Invalid API key', 'You reached the :limit project limit.'], array_column($plan->viaMessageListing, 'phrase'));
        $this->assertSame(['ApiErrors', 'ProjectLimitReachedError'], array_column($plan->viaMessageListing, 'class'));
        $this->assertSame(['Not a template.'], array_column($plan->items, 'phrase'));

        $unnamed = Planner::offline($this->hits($code));
        $this->assertSame(['ApiErrors'], array_column($unnamed->viaMessageListing, 'class'), 'without the list, an inherited contract is not seen');
    }

    /**
     * FRM-2: a call whose sentence the messages listing lists is covered by
     * it wherever it sits - a rule-side error class a rule's template reads -
     * and never registers uncategorised beside it. A literal with no listed
     * twin still registers, uncategorised.
     */
    public function testASentenceTheListingListsIsCoveredWhereverItSits(): void
    {
        $plan = Planner::offline($this->hits(
            "class FieldErrors { const REQ = 1; public static function required() { return __('Enter the :field.', ['field' => \$f]); } }\n"
            . "function helper() { return __('Too many requests.'); }\n"
            . "__('Welcome back');"
        ), null, [], ['listed_templates' => ['Enter the {field}.', 'Too many requests.', 'The email is required.']]);

        $this->assertSame(['Enter the {field}.', 'Too many requests.'], array_column($plan->viaMessageListing, 'phrase'));
        $this->assertSame([[null, 'Welcome back']], array_map(function ($item) {
            return [$item['category'], $item['phrase']];
        }, $plan->items));
        $this->assertFalse($plan->failsStrict());
    }

    public function testDeclaredValueSetsExpandOffline(): void
    {
        \Langsys\SDK\Tests\Frm\Fixtures\Category::$rows = ['Books', 'Music'];

        $plan = Planner::offline($this->hits("__('Filed under :category.', ['category' => \$c]);"), null, [\Langsys\SDK\Tests\Frm\Fixtures\Category::class]);

        $this->assertSame(['Filed under Books.', 'Filed under Music.'], array_column($plan->items, 'phrase'));
    }

    // FRM-8: a line a call prints raw

    private function files(array $base, array $spanish)
    {
        $this->dir = sys_get_temp_dir() . '/langsys-planner-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/en', 0777, true);
        mkdir($this->dir . '/es');
        file_put_contents($this->dir . '/en/legal.php', '<?php return ' . var_export($base, true) . ';');
        file_put_contents($this->dir . '/es/legal.php', '<?php return ' . var_export($spanish, true) . ';');

        return [new \Langsys\SDK\Migration\LegacyKeys(['files' => [['path' => $this->dir . '/en/legal.php', 'format' => 'laravel']]]), ['es-es' => new \Langsys\SDK\Migration\LegacyKeys(['files' => [['path' => $this->dir . '/es/legal.php', 'format' => 'laravel']]])]];
    }

    private static function items(\Langsys\SDK\Sync\SyncPlan $plan)
    {
        return array_map(function ($item) {
            return [$item['category'], $item['phrase'], $item['translations']];
        }, $plan->items);
    }

    /**
     * A rich line registers once, with its inline markup as tokens - the
     * phrase translateRich() looks up for it - and never as its raw HTML.
     * A rich line with no markup registers as itself, and one with no text
     * of its own registers nothing and reports nothing.
     */
    public function testARichLineRegistersOnceInTheFormTheRuntimeLooksUp(): void
    {
        $plan = Planner::offline($this->hits(
            "echo app('translator')->getHtml('Read our <a href=\"/terms\">terms</a> today');\n"
            . "echo app('translator')->getHtml('Place order');\n"
            . "echo app('translator')->getHtml('<b translate=\"no\">Langsys</b>');"
        ));

        $this->assertSame(['Read our {m0o}terms{m0c} today', 'Place order'], array_column($plan->items, 'phrase'));
        $this->assertSame([], $plan->reported);
    }

    /**
     * A key reached through a rich line registers its line tokenized under
     * its group, with no raw copy from the base-language lines, and carries
     * each translation whose elements line up with the source's, tokenized
     * the same way. One whose elements do not is not converted, and a rich
     * line with no text of its own reports nothing either. A key only plain
     * calls reach is still one item with its base-language line.
     */
    public function testARichKeyLineRegistersTokenizedWithTheTranslationsThatLineUp(): void
    {
        list($source, $readers) = $this->files(
            ['terms' => 'Accept the <a href="/terms">terms</a> to continue', 'privacy' => 'See our <a href="/privacy">policy</a>', 'plain' => 'Just <b>plain</b>'],
            ['terms' => 'Acepta los <a href="/es/terminos">términos</a> para continuar', 'privacy' => 'Vea nuestra <b>política</b> <a href="/es/privacidad">aquí</a>', 'plain' => 'Solo <b>simple</b>']
        );

        $plan = (new Planner($source))->plan($this->hits("echo app('translator')->getHtml('legal.terms');\necho app('translator')->getHtml('legal.privacy');\necho app('translator')->getHtml('<b translate=\"no\">Langsys</b>');\necho e(__('legal.plain'));"), $readers);

        $this->assertSame([
            ['legal', 'Accept the {m0o}terms{m0c} to continue', ['es-es' => 'Acepta los {m0o}términos{m0c} para continuar']],
            ['legal', 'See our {m0o}policy{m0c}', []],
            ['legal', 'Just <b>plain</b>', ['es-es' => 'Solo <b>simple</b>']],
        ], self::items($plan));
        $this->assertSame(['app.php:5', 'legal.plain'], $plan->items[2]['origins'], 'the call and its base-language line, one item');
        $this->assertSame([['key' => 'legal.privacy', 'locale' => 'es-es', 'reason' => 'not_converted']], $plan->skipped);
    }

    /**
     * A key built at runtime in a rich line's group may be any of the
     * group's lines, each looked up tokenized: every line registers
     * tokenized, with no raw copy, unless a plain call can reach it too.
     */
    public function testARichLineBuildingItsKeyAtRuntimeRegistersItsGroupTokenized(): void
    {
        list($source, $readers) = $this->files(['built' => 'Mind <b>that</b>', 'terms' => 'Read our <a href="/terms">terms</a>'], ['built' => 'Ojo <b>eso</b>', 'terms' => 'Lee nuestros <a href="/terms">términos</a>']);

        $plan = (new Planner($source))->plan($this->hits("echo app('translator')->getHtml(\"legal.\$k\");\necho app('translator')->getHtml('legal.terms');"), $readers);
        $this->assertSame([
            ['legal', 'Read our {m0o}terms{m0c}', ['es-es' => 'Lee nuestros {m0o}términos{m0c}']],
            ['legal', 'Mind {m0o}that{m0c}', ['es-es' => 'Ojo {m0o}eso{m0c}']],
        ], self::items($plan));
        $this->assertCount(1, $plan->covered, 'the runtime key is covered by its group');

        $plan = (new Planner($source))->plan($this->hits("echo app('translator')->getHtml(\"legal.\$k\");\necho e(__(\"legal.\$j\"));"), $readers);
        $this->assertSame([
            ['legal', 'Mind {m0o}that{m0c}', ['es-es' => 'Ojo {m0o}eso{m0c}']],
            ['legal', 'Mind <b>that</b>', ['es-es' => 'Ojo <b>eso</b>']],
            ['legal', 'Read our {m0o}terms{m0c}', ['es-es' => 'Lee nuestros {m0o}términos{m0c}']],
            ['legal', 'Read our <a href="/terms">terms</a>', ['es-es' => 'Lee nuestros <a href="/terms">términos</a>']],
        ], self::items($plan), 'a plain runtime key in the group reaches the raw lines too');
    }

    /**
     * A rich `@choice` line registers as its plural, with each inline
     * element a token pair in every branch, the key translateRich() looks up
     * for the same call.
     */
    public function testARichChoiceLineRegistersItsPluralTokenized(): void
    {
        $plan = Planner::offline($this->hits("echo app('translator')->choiceHtml('<b>One</b> apple|:count <b>apples</b>', \$n);"));

        $runtime = \Langsys\SDK\Html\MarkupTokenizer::readLine(\Langsys\SDK\Migration\LegacyValue::fromCall('<b>One</b> apple|:count <b>apples</b>', [], 'trans_choice', 1)['text']);
        $this->assertSame([$runtime['encoded']['text']], array_column($plan->items, 'phrase'));
        $this->assertStringContainsString('{m0o}One{m0c}', $plan->items[0]['phrase']);
        $this->assertStringNotContainsString('<b>', $plan->items[0]['phrase']);
    }

    /**
     * A translation that reorders elements of one tag carries each run on the
     * element its attributes name, so the page rebuilds every run on the
     * right link; one whose elements cannot be told apart that way is not
     * converted.
     */
    public function testACarriedTranslationKeepsEachRunOnItsOwnElement(): void
    {
        list($source, $readers) = $this->files(
            ['links' => 'Read the <a href="/terms">terms</a> and the <a href="/privacy">privacy policy</a>', 'local' => 'Read the <a href="/terms">terms</a> and the <a href="/privacy">privacy policy</a>.', 'twice' => 'Use <b>this</b> or <b>that</b>'],
            ['links' => 'Lea la <a href="/privacy">política de privacidad</a> y los <a href="/terms">términos</a>', 'local' => 'Lea la <a href="/es/privacidad">política</a> y los <a href="/es/terminos">términos</a>.', 'twice' => 'Usa <b>eso</b> o <b>esto</b>']
        );

        $plan = (new Planner($source))->plan($this->hits("echo app('translator')->getHtml('legal.links');\necho app('translator')->getHtml('legal.local');\necho app('translator')->getHtml('legal.twice');"), $readers);

        $this->assertSame([
            ['legal', 'Read the {m0o}terms{m0c} and the {m1o}privacy policy{m1c}', ['es-es' => 'Lea la {m1o}política de privacidad{m1c} y los {m0o}términos{m0c}']],
            ['legal', 'Read the {m0o}terms{m0c} and the {m1o}privacy policy{m1c}.', []],
            ['legal', 'Use {m0o}this{m0c} or {m1o}that{m1c}', ['es-es' => 'Usa {m0o}eso{m0c} o {m1o}esto{m1c}']],
        ], self::items($plan), 'two elements nothing tells apart keep their order');
        $this->assertSame([['key' => 'legal.local', 'locale' => 'es-es', 'reason' => 'not_converted']], $plan->skipped);
    }

    /**
     * Two elements kept whole that share a tag and attributes are told apart
     * by the source content they render, so a translation that swaps two
     * excluded names keeps each on its own run.
     */
    public function testAReorderOfElementsKeptWholeFollowsTheirContent(): void
    {
        list($source, $readers) = $this->files(
            ['names' => '<span translate="no">Alice</span> meets <span translate="no">Bob</span>', 'math' => 'If <math><mi>x</mi></math> then <math><mi>y</mi></math>'],
            ['names' => '<span translate="no">Bob</span> trifft <span translate="no">Alice</span>', 'math' => 'Entonces <math><mi>y</mi></math> si <math><mi>x</mi></math>']
        );

        $plan = (new Planner($source))->plan($this->hits("echo app('translator')->getHtml('legal.names');\necho app('translator')->getHtml('legal.math');"), $readers);

        $this->assertSame([
            ['legal', '{m0o}{m0c} meets {m1o}{m1c}', ['es-es' => '{m1o}{m1c} trifft {m0o}{m0c}']],
            ['legal', 'If {m0o}{m0c} then {m1o}{m1c}', ['es-es' => 'Entonces {m1o}{m1c} si {m0o}{m0c}']],
        ], self::items($plan));
    }

    /**
     * A line of a rich runtime key's group that registers nothing - no text
     * of its own - reports nothing either.
     */
    public function testAGroupLineWithNoTextReportsNothing(): void
    {
        list($source, $readers) = $this->files(['brand' => '<b translate="no">Langsys</b>', 'terms' => 'Read <b>this</b>'], ['terms' => 'Lee <b>esto</b>']);

        $plan = (new Planner($source))->plan($this->hits("echo app('translator')->getHtml(\"legal.\$k\");"), $readers);

        $this->assertSame(['Read {m0o}this{m0c}'], array_column($plan->items, 'phrase'));
        $this->assertSame([], $plan->skipped);
    }

    /**
     * A missing translation is reported once however many routes reach its
     * key: a rich literal and its rich runtime group, or a plain call and the
     * base-language lines.
     */
    public function testEachSkipIsReportedOnce(): void
    {
        list($source, $readers) = $this->files(['gone' => 'Only in <b>English</b>', 'plain' => 'Only in English'], []);

        $plan = (new Planner($source))->plan($this->hits("echo app('translator')->getHtml(\"legal.\$k\");\necho app('translator')->getHtml('legal.gone');\necho e(__('legal.plain'));"), $readers);

        $this->assertSame([
            ['key' => 'legal.gone', 'locale' => 'es-es', 'reason' => 'missing'],
            ['key' => 'legal.plain', 'locale' => 'es-es', 'reason' => 'missing'],
        ], $plan->skipped);
    }

    /**
     * A translation a rich key's other languages lack is reported once, by
     * the call that reached it, not again by the base-language lines.
     */
    public function testAMissingTranslationOfARichKeyIsReportedOnce(): void
    {
        list($source, $readers) = $this->files(['terms' => 'Read our <a href="/terms">terms</a>', 'gone' => 'Only in <b>English</b>'], ['terms' => 'Lee nuestros <a href="/terms">términos</a>']);

        $plan = (new Planner($source))->plan($this->hits("echo app('translator')->getHtml('legal.terms');\necho app('translator')->getHtml('legal.gone');"), $readers);

        $this->assertSame([['key' => 'legal.gone', 'locale' => 'es-es', 'reason' => 'missing']], $plan->skipped);
    }

    /**
     * A key a plain call reaches too, or a key built at runtime in its group
     * may reach, keeps its raw line beside the tokenized one: each is what
     * some lookup asks for.
     */
    public function testAKeyAlsoReachedPlainlyKeepsItsRawLine(): void
    {
        list($source, $readers) = $this->files(['both' => 'Read <b>this</b>', 'built' => 'Mind <b>that</b>'], ['both' => 'Lee <b>esto</b>', 'built' => 'Ojo <b>eso</b>']);

        $plan = (new Planner($source))->plan($this->hits(
            "echo app('translator')->getHtml('legal.both');\necho e(__('legal.both'));\n"
            . "echo app('translator')->getHtml('legal.built');\necho e(__(\"legal.\$k\"));"
        ), $readers);

        $this->assertSame([
            ['legal', 'Read {m0o}this{m0c}', ['es-es' => 'Lee {m0o}esto{m0c}']],
            ['legal', 'Read <b>this</b>', ['es-es' => 'Lee <b>esto</b>']],
            ['legal', 'Mind {m0o}that{m0c}', ['es-es' => 'Ojo {m0o}eso{m0c}']],
            ['legal', 'Mind <b>that</b>', ['es-es' => 'Ojo <b>eso</b>']],
        ], self::items($plan));
    }
}
