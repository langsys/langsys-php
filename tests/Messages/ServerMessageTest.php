<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Messages\ServerMessage;
use PHPUnit\Framework\TestCase;

/**
 * MSG-1 / MSG-4 / MSG-9: the entry and its four fixed pieces. Expected arrays are
 * langsys4 907's wire examples (ApiErrorTest, the SDK plan's section 3.1).
 */
class ServerMessageTest extends TestCase
{
    public function testMakeFillsTheMessageAndKeepsOnlyTheMarkersParams(): void
    {
        $entry = ServerMessage::make('too_short', 'The password must be at least {min} characters.', ['min' => 12, 'unused' => 'x'], 'password');

        $this->assertSame([
            'field' => 'password',
            'code' => 'too_short',
            'message' => 'The password must be at least 12 characters.',
            'template' => 'The password must be at least {min} characters.',
            'params' => ['min' => 12],
        ], $entry->toArray());
    }

    public function testAnEntryWhoseTemplateHasNoMarkerOmitsParams(): void
    {
        $entry = ServerMessage::make('mismatch', 'The password confirmation does not match.', ['min' => 3], 'password');

        $this->assertSame([
            'field' => 'password',
            'code' => 'mismatch',
            'message' => 'The password confirmation does not match.',
            'template' => 'The password confirmation does not match.',
        ], $entry->toArray());
        $this->assertSame([], $entry->getParams());
        $this->assertSame($entry->getTemplate(), $entry->getMessage());
    }

    public function testAGeneralEntryCarriesNoField(): void
    {
        $entry = ServerMessage::make('project_limit_reached', "Your plan's project limit is {limit}.", ['limit' => 3]);

        $this->assertNull($entry->getField());
        $this->assertArrayNotHasKey('field', $entry->toArray());
        $this->assertSame("Your plan's project limit is 3.", $entry->getMessage());
    }

    public function testNumericParamsStayNumbersOnTheWire(): void
    {
        $json = json_encode(ServerMessage::make('too_short', 'The password must be at least {min} characters.', ['min' => 12], 'password'));

        $this->assertStringContainsString('"params":{"min":12}', $json);
    }

    public function testTheReferenceWireEntryRoundTrips(): void
    {
        $wire = [
            'field' => 'password',
            'code' => 'too_short',
            'message' => 'The password must be at least 12 characters.',
            'template' => 'The password must be at least {min} characters.',
            'params' => ['min' => 12],
        ];

        $this->assertSame($wire, ServerMessage::fromArray($wire)->toArray());
    }

    public function testAnEntryMissingAPieceIsNotAnEntry(): void
    {
        $whole = ['code' => 'required', 'message' => 'The name is required.', 'template' => 'The name is required.'];

        foreach (['code', 'message', 'template'] as $piece) {
            $partial = $whole;
            unset($partial[$piece]);
            $this->assertNull(ServerMessage::fromArray($partial), "missing $piece");
        }

        $this->assertNull(ServerMessage::fromArray(['code' => 'required', 'message' => 'x', 'template' => ['not', 'text']]));
        $this->assertNotNull(ServerMessage::fromArray($whole));
    }

    public function testATextOnlyFailureBecomesInvalidWithItsTextAsTheTemplate(): void
    {
        $entry = ServerMessage::fromText('The gateway declined the card.', 'card');

        $this->assertSame([
            'field' => 'card',
            'code' => 'invalid',
            'message' => 'The gateway declined the card.',
            'template' => 'The gateway declined the card.',
        ], $entry->toArray());
    }
}
