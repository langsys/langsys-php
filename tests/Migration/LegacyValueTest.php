<?php

namespace Langsys\SDK\Tests\Migration;

use Langsys\SDK\Migration\LegacyValue;
use PHPUnit\Framework\TestCase;

/**
 * MIG-4: a legacy value becomes Langsys/ICU syntax before it is a phrase, and
 * anything the table does not recognise is kept as written and flagged, never
 * silently mangled.
 */
class LegacyValueTest extends TestCase
{
    private function converts($value, $expected, $format = 'plain')
    {
        $result = LegacyValue::convert($value, $format);
        $this->assertTrue($result['recognised'], "recognised: $value");
        $this->assertSame($expected, $result['text'], $value);
    }

    private function keeps($value, $format = 'plain')
    {
        $result = LegacyValue::convert($value, $format);
        $this->assertFalse($result['recognised'], "flagged: $value");
        $this->assertSame($value, $result['text'], "kept as written: $value");
        $this->assertNotEmpty($result['issue'], "says why: $value");
    }

    public function testPlaceholdersBecomeBraceMarkers(): void
    {
        $this->converts('Hello {{name}}', 'Hello {name}');
        $this->converts('Hello {{ name }}', 'Hello {name}');
        $this->converts('Hello :name, welcome', 'Hello {name}, welcome');
        $this->converts('Hello {name}', 'Hello {name}');
        $this->converts('Plain text', 'Plain text');
    }

    public function testColonsThatAreNotPlaceholdersAreLeftAlone(): void
    {
        $this->converts('Opens at 10:30', 'Opens at 10:30');
        $this->converts('See https://langsys.dev', 'See https://langsys.dev');
    }

    public function testACaseTransformingPlaceholderIsFlaggedNotConverted(): void
    {
        foreach (LegacyValue::FORMATS as $format) {
            $this->keeps('Welcome, :Name', $format);
            $this->keeps('WELCOME, :NAME', $format);
        }
    }

    public function testPlaceholdersConvertTheSameWayInEveryFormat(): void
    {
        foreach (LegacyValue::FORMATS as $format) {
            $this->converts('Hello :name and {{other}}', 'Hello {name} and {other}', $format);
        }
    }

    public function testLaravelRangesBecomeIcu(): void
    {
        $this->converts('{0} No apples|{1} One apple|[2,*] :count apples', '{count, plural, =0 {No apples} =1 {One apple} other {# apples}}', 'laravel');
        $this->converts('{0} None|[1,*] :count left', '{count, plural, =0 {None} other {# left}}', 'laravel');
        $this->converts(':count apple|:count apples', '{count, plural, one {# apple} other {# apples}}', 'laravel');
        $this->converts('{1} One apple|{0} No apples|[2,*] :count apples', '{count, plural, =0 {No apples} =1 {One apple} other {# apples}}', 'laravel');
    }

    public function testRangesThatDoNotMapToCldrAreFlagged(): void
    {
        $this->keeps('[2,19] Some|[20,*] Many', 'laravel');
        $this->keeps('{0} None|[2,*] Many', 'laravel');
        $this->keeps('{1} One|[2,*] Many', 'laravel');
    }

    public function testVuePipesBecomeIcu(): void
    {
        // vue selects by count, so exact values - never CLDR categories.
        $this->converts('car | cars', '{count, plural, =1 {car} other {cars}}', 'vue-i18n');
        $this->converts('no apples | one apple | {count} apples', '{count, plural, =0 {no apples} =1 {one apple} other {# apples}}', 'vue-i18n');
        $this->converts('{n} item | {n} items', '{count, plural, =1 {# item} other {# items}}', 'vue-i18n');
        $this->converts('car|cars', '{count, plural, =1 {car} other {cars}}', 'vue-i18n');
    }

    public function testAPipeThatIsNoRecognisedPluralIsFlagged(): void
    {
        $this->keeps('Yes|No', 'laravel');
        $this->keeps('apple|apples', 'laravel');
        $this->keeps(':count apple|apples', 'laravel');
        $this->keeps('a | b | c | d', 'vue-i18n');
        $this->keeps('Save | Cancel', 'i18next');
    }

    /**
     * The same string means different things to different frameworks, so the
     * file's declared format decides, never the string's shape.
     */
    public function testThePipeMeansWhatTheFilesFormatSays(): void
    {
        $this->converts('car | cars', '{count, plural, =1 {car} other {cars}}', 'vue-i18n');
        $this->keeps('car | cars', 'laravel');
        $this->keeps('car | cars', 'plain');
        $this->converts('{0} none | {1} one', '{count, plural, =1 {{0} none} other {{1} one}}', 'vue-i18n');
        $this->converts('{0} None|{1} One|[2,*] Many', '{count, plural, =0 {None} =1 {One} other {Many}}', 'laravel');
        $this->keeps('{0} None|{1} One|[2,*] Many', 'plain');
    }

    public function testPluralKeyPairsBecomeIcu(): void
    {
        $this->assertSame(
            ['text' => '{count, plural, one {# item} other {# items}}', 'recognised' => true, 'issue' => null],
            LegacyValue::fromPluralForms(['one' => '{{count}} item', 'other' => '{{count}} items'])
        );
        $this->assertSame(
            '{count, plural, zero {none} one {one} few {a few} other {many}}',
            LegacyValue::fromPluralForms(['other' => 'many', 'zero' => 'none', 'few' => 'a few', 'one' => 'one'])['text'],
            'CLDR order regardless of file order'
        );
    }

    // A literal miss converts by its entry point (MIG-2)

    /**
     * The spec's vectors: each Laravel entry point converts as Laravel itself
     * reads the text, so `__('Hello :name', …)` and `t('Hello {name}')` are one
     * phrase.
     *
     * @dataProvider entryPointProvider
     */
    public function testALiteralMissConvertsByItsEntryPoint($text, array $params, $entryPoint, $count, $expected, $recognised): void
    {
        $converted = LegacyValue::fromCall($text, $params, $entryPoint, $count);

        $this->assertSame($expected, $converted['text']);
        $this->assertSame($recognised, $converted['recognised']);
    }

    public function entryPointProvider(): array
    {
        return [
            'a passed key converts' => ['Hello :name', ['name' => 'Ana'], '__', null, 'Hello {name}', true],
            'a pipe through __ is text' => ['a | b', [], '__', null, 'a | b', true],
            'trans_choice reads the plural table' => ['{0} none|[1,*] :count items', [], 'trans_choice', 3, '{count, plural, =0 {none} other {# items}}', true],
            'an unpassed :word stays' => ['Note:done', [], '__', null, 'Note:done', true],
            'a key not passed stays' => ['Hello :name', [], '__', null, 'Hello :name', true],
            'a cased placeholder stays and warns' => ['Hello :Name', ['name' => 'Ana'], '__', null, 'Hello :Name', false],
            'an upper-cased placeholder stays and warns' => ['HELLO :NAME', ['name' => 'Ana'], '__', null, 'HELLO :NAME', false],
            'a cased placeholder of an unpassed key is text' => ['Hello :Name', [], '__', null, 'Hello :Name', true],
            'longest key first, as Laravel substitutes' => ['Hi :name and :nam', ['nam' => 'b', 'name' => 'a'], '__', null, 'Hi {name} and {nam}', true],
            'a key inside a longer word, as Laravel substitutes' => ['Hello :names', ['name' => 'x'], '__', null, 'Hello {name}s', true],
            'trans_choice branches convert passed keys only' => ['{0} :name has none|[1,*] :name has :count, Note:done', ['name' => 'A'], 'trans_choice', 2, '{count, plural, =0 {{name} has none} other {{name} has #, Note:done}}', true],
            'an unpassed placeholder in a branch stays' => ['{0} none, see :link|[1,*] :count items, see :link', [], 'trans_choice', 2, '{count, plural, =0 {none, see :link} other {# items, see :link}}', true],
            'trans_choice two :count forms' => [':count apple|:count apples', [], 'trans_choice', 1, '{count, plural, one {# apple} other {# apples}}', true],
            'trans_choice outside the table stays and warns' => ['apple|apples', [], 'trans_choice', 1, 'apple|apples', false],
            'trans_choice without a pipe converts :count' => ['You have :count', [], 'trans_choice', 4, 'You have {count}', true],
            'a pipe in __ is never a plural' => ['{0} none|[1,*] :count items', ['count' => 3], '__', null, '{0} none|[1,*] {count} items', true],
        ];
    }

    public function testTransChoicePassesTheNumberAsCount(): void
    {
        $this->assertSame(['name' => 'A', 'count' => 5], LegacyValue::fromCall(':name: :count', ['name' => 'A', 'count' => 1], 'trans_choice', 5)['params']);
        $this->assertSame(['name' => 'A'], LegacyValue::fromCall('Hi :name', ['name' => 'A'])['params']);
    }

    public function testAReplacementNameIcuCannotUseStaysAndWarns(): void
    {
        $converted = LegacyValue::fromCall('Hi :first-name', ['first-name' => 'A']);

        $this->assertSame('Hi :first-name', $converted['text']);
        $this->assertFalse($converted['recognised']);
    }

    public function testAnUnknownEntryPointIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        LegacyValue::fromCall('Hi', [], 't');
    }
}
