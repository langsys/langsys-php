<?php

namespace Langsys\SDK\Tests\Sync;

use Langsys\SDK\Sync\SourceScanner;
use PHPUnit\Framework\TestCase;

/**
 * FRM-2: the literal translate calls in an app's source, and the calls that
 * cannot be collected, found with PHP's own tokenizer.
 */
class SourceScannerTest extends TestCase
{
    private function scan($code)
    {
        return (new SourceScanner())->scan("<?php\n" . $code, 'view.php');
    }

    private function texts($code)
    {
        return array_column($this->scan($code), 'text');
    }

    public function testEveryEntryPointIsFound(): void
    {
        $this->assertSame(
            ['Welcome', 'Hi', 'one|many', 'auth.failed', 'auth.throttle', 'Alias'],
            $this->texts("__('Welcome'); trans('Hi'); trans_choice('one|many', \$n); \\Illuminate\\Support\\Facades\\Lang::get('auth.failed'); Lang::choice('auth.throttle', 2); t('Alias');")
        );
    }

    /**
     * A compiled Blade view nests the call in an expression, and compiles
     * @lang and @choice to the translator's own methods.
     */
    public function testCompiledBladeIsRead(): void
    {
        $hits = $this->scan("echo e(__('Save')); echo app('translator')->get('checkout.submit'); echo app('translator')->choice('cart.apples', 3);");

        $this->assertSame(['Save', 'checkout.submit', 'cart.apples'], array_column($hits, 'text'));
        $this->assertSame(['__', 'Lang::get', 'Lang::choice'], array_column($hits, 'entry_point'));
        $this->assertSame(['__', '__', 'trans_choice'], array_column($hits, 'kind'));
    }

    public function testANonLiteralCallIsReportedWithItsLine(): void
    {
        $hits = $this->scan("\n\n__(\$dynamic);\n__('Prefix ' . \$x);\n__(\"Hi {\$name}\");");

        $this->assertSame([null, null, null], array_column($hits, 'text'));
        $this->assertSame(['non-literal', 'non-literal', 'non-literal'], array_column($hits, 'skipped'));
        $this->assertSame([4, 5, 6], array_column($hits, 'line'));
        $this->assertSame(['view.php'], array_values(array_unique(array_column($hits, 'file'))));
    }

    /**
     * A key built at runtime inside a literal group names its group, so a
     * plan can tell it is covered; a sentence built at runtime names none.
     */
    public function testARuntimeKeyInALiteralGroupNamesTheGroup(): void
    {
        $hits = $this->scan("__(\"validation.\$key\"); __('auth.' . \$k); __(\"validation.custom.{\$f}.required\"); __(\"Hello \$name\"); __(\$key);");

        $this->assertSame([null, null, null, null, null], array_column($hits, 'text'));
        $this->assertSame(['validation', 'auth', 'validation', null, null], array_column($hits, 'group'));
    }

    /**
     * What is not a call to the function is never collected: a method, a
     * declaration, a string, a comment.
     */
    public function testOnlyFunctionCallsCount(): void
    {
        $this->assertSame([], $this->scan("\$o->__('m'); Foo::__('s'); function __x() {} // __('c')\n\$s = \"__('in a string')\"; /* trans('d') */"));
    }

    public function testTheReplaceArrayGivesItsKeys(): void
    {
        $hits = $this->scan("__('Hello :name', ['name' => \$n]); trans_choice(':count apples', \$n, ['count' => \$n, 'fruit' => \$f]); __('A :x', \$params); __('Plain');");

        $this->assertSame([['name'], ['count', 'fruit'], null, null], array_column($hits, 'replace_keys'));
        $this->assertSame([2, 3, 2, 1], array_column($hits, 'arg_count'));
    }

    public function testLiteralsAreDecodedAsPhpReadsThem(): void
    {
        $this->assertSame(
            ["It's", "Tab\there", 'No $var here', "Line\nbreak"],
            $this->texts("__('It\\'s'); __(\"Tab\\there\"); __('No \$var here'); __(<<<'TXT'\nLine\nbreak\nTXT\n);")
        );
    }

    public function testTheFunctionListIsConfigurable(): void
    {
        $hits = (new SourceScanner(['_t' => '__']))->scan("<?php _t('Mine'); __('Not configured');");

        $this->assertSame(['Mine'], array_column($hits, 'text'));
    }
}
