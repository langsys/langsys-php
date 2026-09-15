<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Messages\MessageTemplate;
use PHPUnit\Framework\TestCase;

/**
 * MSG-3 / MSG-4: the marker grammar and fill, ported from langsys4 907's
 * ErrorTemplate so a template the server builds and a template this SDK fills
 * agree character for character.
 */
class MessageTemplateTest extends TestCase
{
    public function testTheMarkerPatternIsTheReferencePattern(): void
    {
        $this->assertSame('/\{([a-z][a-z0-9_]*)\}/', MessageTemplate::MARKER_PATTERN);
    }

    public function testMarkersAreReturnedOnceEachInOrder(): void
    {
        $this->assertSame(['min'], MessageTemplate::markers('The password must be at least {min} characters.'));
        $this->assertSame(['used', 'limit'], MessageTemplate::markers('You have used {used} of your {limit} probes, {used} so far.'));
        $this->assertSame(['first_name2'], MessageTemplate::markers('Hello {first_name2}.'));
    }

    public function testBracesThatAreNotMarkersAreNotMarkers(): void
    {
        foreach (['{Name}', '{ min }', '{1x}', '{}', '{min-x}', '{MIN}', 'no braces'] as $text) {
            $this->assertSame([], MessageTemplate::markers($text), $text);
        }
    }

    public function testFillReproducesTheReferenceMessage(): void
    {
        $this->assertSame(
            'The password must be at least 12 characters.',
            MessageTemplate::fill('The password must be at least {min} characters.', ['min' => 12])
        );
        $this->assertSame(
            'You have used 7 of your 10 probes.',
            MessageTemplate::fill('You have used {used} of your {limit} probes.', ['used' => 7, 'limit' => 10])
        );
    }

    public function testAMissingParamIsLeftAsItsLiteralMarker(): void
    {
        $this->assertSame('7 of {limit}', MessageTemplate::fill('{used} of {limit}', ['used' => 7]));
    }

    public function testBracesThatAreNotMarkersAreNeverFilled(): void
    {
        $this->assertSame('{Name} is 3', MessageTemplate::fill('{Name} is {n}', ['Name' => 'x', 'n' => 3]));
    }

    public function testATemplateWithNoMarkerFillsToItself(): void
    {
        $this->assertSame('The password confirmation does not match.', MessageTemplate::fill('The password confirmation does not match.', ['min' => 1]));
    }
}
