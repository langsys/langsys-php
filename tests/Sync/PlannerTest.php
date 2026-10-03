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
        foreach (glob($this->dir . '/*') ?: [] as $file) {
            unlink($file);
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
}
