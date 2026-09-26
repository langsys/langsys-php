<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Messages\ServerMessage;
use PHPUnit\Framework\TestCase;

/**
 * MSG-1 / MSG-2 / MSG-4 / MSG-9: the entry - a template and its params, with
 * the framework's own code and field carried unchanged.
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

    /**
     * MSG-1: an entry needs a template or a message; `message` defaults to the
     * template filled from the params, and `code` and `field` are the
     * framework's.
     */
    public function testAnEntryNeedsATemplateOrAMessage(): void
    {
        $this->assertNull(ServerMessage::fromArray(['code' => 'required', 'field' => 'name']), 'neither a template nor a message');
        $this->assertNull(ServerMessage::fromArray(['template' => ['not', 'text']]));

        $messageOnly = ServerMessage::fromArray(['message' => 'Something went wrong.']);
        $this->assertNull($messageOnly->getTemplate(), 'a message alone is an entry with nothing to look up');
        $this->assertSame(['message' => 'Something went wrong.'], $messageOnly->toArray());
        $this->assertNull(ServerMessage::fromArray(['message' => 'Something went wrong.'], [], true), 'unless a template is required');

        $entry = ServerMessage::fromArray(['template' => 'The password field must be at least {min} characters.', 'params' => ['min' => 12]]);
        $this->assertSame('The password field must be at least 12 characters.', $entry->getMessage(), 'message is the filled template');
        $this->assertNull($entry->getCode());
        $this->assertSame(['message' => 'The password field must be at least 12 characters.', 'template' => 'The password field must be at least {min} characters.', 'params' => ['min' => 12]], $entry->toArray(), 'no code on the wire when the framework has none');
    }

    public function testTheFrameworksCodeIsPassedThroughUnchanged(): void
    {
        foreach (['min', 'App\\Rules\\Uppercase', 'string_too_short', 1042] as $code) {
            $entry = ServerMessage::fromArray(['code' => $code, 'template' => 'Bad.', 'message' => 'Bad.']);
            $this->assertSame($code, $entry->getCode());
            $this->assertSame($code, $entry->toArray()['code']);
        }
    }

    public function testPieceNamesAreConfigurable(): void
    {
        $entry = ServerMessage::fromArray(['msgid' => 'At least {min} characters.', 'vars' => ['min' => 3], 'type' => 'string_too_short'], ['template' => 'msgid', 'params' => 'vars', 'code' => 'type']);

        $this->assertSame('At least {min} characters.', $entry->getTemplate());
        $this->assertSame(['min' => 3], $entry->getParams());
        $this->assertSame('string_too_short', $entry->getCode());
    }

    /**
     * MSG-9: a failure that arrives as finished text registers as that text,
     * with no params, and carries no code unless the framework had one.
     */
    public function testATextOnlyFailureIsItsOwnTemplateWithNoCode(): void
    {
        $this->assertSame([
            'field' => 'card',
            'message' => 'The gateway declined the card.',
            'template' => 'The gateway declined the card.',
        ], ServerMessage::fromText('The gateway declined the card.', 'card')->toArray());

        $this->assertSame('card_declined', ServerMessage::fromText('The gateway declined the card.', 'card', 'card_declined')->getCode());
    }
}
