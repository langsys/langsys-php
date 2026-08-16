<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Interpolator;
use PHPUnit\Framework\TestCase;

/**
 * Parity tests against the JS SDK's src/interpolate.ts.
 *
 * The whole point of this class is that one catalog string renders identically
 * in every SDK, so each test names the JS behaviour it mirrors.
 */
class InterpolatorTest extends TestCase
{
    private function requireIntl()
    {
        if (!class_exists('NumberFormatter') || !class_exists('IntlDateFormatter')) {
            $this->markTestSkipped('ext-intl is not available.');
        }
    }

    // =========================================================================
    // ICU detection — JS ICU_PATTERN (interpolate.ts:22)
    // =========================================================================

    /**
     * A style-less slot is valid ICU. Requiring a trailing comma sent it down
     * the simple path, where it rendered as the literal "{n, number}".
     */
    public function testDetectsStyleLessIcuSlot()
    {
        $this->assertTrue(Interpolator::isICU('You have {n, number} points'));
    }

    public function testDetectsIcuSlotWithStyle()
    {
        $this->assertTrue(Interpolator::isICU('You have {n, number, integer} points'));
    }

    public function testDetectsStyleLessPlural()
    {
        $this->assertTrue(Interpolator::isICU('{n, plural, one {# item} other {# items}}'));
    }

    public function testPlainSlotIsNotIcu()
    {
        $this->assertFalse(Interpolator::isICU('Hello {name}'));
    }

    public function testFormatsStyleLessIcuRatherThanEmittingItLiterally()
    {
        $this->requireIntl();

        $this->assertSame(
            'You have 5,000 points',
            Interpolator::interpolate('You have {n, number} points', ['n' => 5000], 'en-us')
        );
    }

    // =========================================================================
    // Simple interpolation — CLDR output, matching JS (interpolate.ts:143-166)
    // =========================================================================

    /**
     * JS uses Intl.NumberFormat here, so a bare {n} is grouped.
     */
    public function testFormatsNumbersWithGroupingLikeIntlNumberFormat()
    {
        $this->requireIntl();

        $this->assertSame(
            'You have 5,000 points',
            Interpolator::interpolate('You have {n} points', ['n' => 5000], 'en-us')
        );
    }

    public function testNumberFormattingFollowsTheLocale()
    {
        $this->requireIntl();

        $this->assertSame(
            'Tienes 5.000 puntos',
            Interpolator::interpolate('Tienes {n} puntos', ['n' => 5000], 'es-es')
        );
    }

    /**
     * JS uses { dateStyle: 'medium' } — a date, with no time component.
     */
    public function testFormatsDatesAsCldrMediumDateOnly()
    {
        $this->requireIntl();

        $out = Interpolator::interpolate(
            'on {d}',
            ['d' => new \DateTimeImmutable('2026-08-15T12:34:56+00:00')],
            'en-us'
        );

        $this->assertSame('on Aug 15, 2026', $out);
        $this->assertStringNotContainsString('12:34', $out, 'dateStyle medium carries no time');
    }

    public function testStringsAreUnchanged()
    {
        $this->assertSame(
            'Hello Sarah',
            Interpolator::interpolate('Hello {name}', ['name' => 'Sarah'], 'en-us')
        );
    }

    public function testBooleansRenderAsJsonLiterals()
    {
        $this->assertSame(
            'active: true',
            Interpolator::interpolate('active: {a}', ['a' => true], 'en-us')
        );
    }

    public function testMissingKeyIsLeftIntact()
    {
        $this->assertSame(
            'Hello {name}',
            Interpolator::interpolate('Hello {name}', [], 'en-us')
        );
    }

    public function testNullValueIsLeftIntact()
    {
        $this->assertSame(
            'Hello {name}',
            Interpolator::interpolate('Hello {name}', ['name' => null], 'en-us')
        );
    }

    // =========================================================================
    // A malformed catalog string must never reach the caller's render path
    // =========================================================================

    /**
     * formatMessage() signals a bad pattern with false by default and with an
     * IntlException when intl.use_exceptions=1 — which '@' does not suppress.
     * Both must degrade to simple interpolation.
     */
    public function testMalformedIcuFallsBackInsteadOfThrowing()
    {
        $this->requireIntl();

        $previous = ini_get('intl.use_exceptions');
        ini_set('intl.use_exceptions', '1');

        try {
            $out = Interpolator::interpolate(
                '{n, plural, one {# item} other {# items}',
                ['n' => 2],
                'en-us'
            );
            $this->assertIsString($out);
        } finally {
            ini_set('intl.use_exceptions', $previous === false ? '0' : $previous);
        }
    }

    public function testMalformedIcuFallsBackWhenExceptionsAreOff()
    {
        $this->requireIntl();

        $previous = ini_get('intl.use_exceptions');
        ini_set('intl.use_exceptions', '0');

        try {
            $out = Interpolator::interpolate(
                '{n, plural, one {# item} other {# items}',
                ['n' => 2],
                'en-us'
            );
            $this->assertIsString($out);
        } finally {
            ini_set('intl.use_exceptions', $previous === false ? '0' : $previous);
        }
    }
}
