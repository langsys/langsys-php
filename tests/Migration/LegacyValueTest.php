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
    private function converts($value, $expected)
    {
        $result = LegacyValue::convert($value);
        $this->assertTrue($result['recognised'], "recognised: $value");
        $this->assertSame($expected, $result['text'], $value);
    }

    private function keeps($value)
    {
        $result = LegacyValue::convert($value);
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
        $this->keeps('Welcome, :Name');
        $this->keeps('WELCOME, :NAME');
    }

    public function testLaravelRangesBecomeIcu(): void
    {
        $this->converts('{0} No apples|{1} One apple|[2,*] :count apples', '{count, plural, =0 {No apples} =1 {One apple} other {# apples}}');
        $this->converts('{0} None|[1,*] :count left', '{count, plural, =0 {None} other {# left}}');
        $this->converts(':count apple|:count apples', '{count, plural, one {# apple} other {# apples}}');
        $this->converts('{1} One apple|{0} No apples|[2,*] :count apples', '{count, plural, =0 {No apples} =1 {One apple} other {# apples}}');
    }

    public function testRangesThatDoNotMapToCldrAreFlagged(): void
    {
        $this->keeps('[2,19] Some|[20,*] Many');
        $this->keeps('{0} None|[2,*] Many');
        $this->keeps('{1} One|[2,*] Many');
    }

    public function testVuePipesBecomeIcu(): void
    {
        // vue selects by count, so exact values - never CLDR categories.
        $this->converts('car | cars', '{count, plural, =1 {car} other {cars}}');
        $this->converts('no apples | one apple | {count} apples', '{count, plural, =0 {no apples} =1 {one apple} other {# apples}}');
        $this->converts('{n} item | {n} items', '{count, plural, =1 {# item} other {# items}}');
    }

    public function testAPipeThatIsNoRecognisedPluralIsFlagged(): void
    {
        $this->keeps('Yes|No');
        $this->keeps('apple|apples');
        $this->keeps(':count apple|apples');
        $this->keeps('a | b | c | d');
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
}
