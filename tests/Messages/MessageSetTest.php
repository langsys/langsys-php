<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Messages\MessageSet;
use Langsys\SDK\Messages\ServerMessage;
use PHPUnit\Framework\TestCase;

/**
 * MSG-1: entries resolve wherever they sit. The envelope is the app's; only the
 * entry's pieces are fixed.
 */
class MessageSetTest extends TestCase
{
    private function validationFailure(): array
    {
        return [
            'status' => false,
            'error' => [
                'message' => 'The request failed validation.',
                'code' => 'validation_failed',
                'template' => 'The request failed validation.',
                'errors' => [
                    ['field' => 'password', 'code' => 'too_short', 'message' => 'The password must be at least 12 characters.', 'template' => 'The password must be at least {min} characters.', 'params' => ['min' => 12]],
                    ['field' => 'password', 'code' => 'mismatch', 'message' => 'The password confirmation does not match.', 'template' => 'The password confirmation does not match.'],
                ],
            ],
        ];
    }

    public function testTheDefaultLangsysEnvelopeResolves(): void
    {
        $set = MessageSet::fromResponse($this->validationFailure());

        $this->assertCount(3, $set);
        $this->assertSame(['too_short', 'mismatch'], array_map(function (ServerMessage $m) { return $m->getCode(); }, $set->forField('password')));
        $this->assertSame(['validation_failed'], array_map(function (ServerMessage $m) { return $m->getCode(); }, $set->general()));
        $this->assertSame(['password'], $set->fields());
    }

    public function testAPlainErrorResolvesToOneGeneralEntryAndIgnoresDetails(): void
    {
        $set = MessageSet::fromResponse([
            'status' => false,
            'error' => ['message' => "Your plan's project limit is 3.", 'code' => 'project_limit_reached', 'template' => "Your plan's project limit is {limit}.", 'params' => ['limit' => 3], 'details' => ['limit' => 3]],
        ]);

        $this->assertCount(1, $set);
        $this->assertSame(['limit' => 3], $set->all()[0]->getParams());
    }

    public function testEntriesUnderForeignContainerKeysResolveWithoutConfiguration(): void
    {
        $entries = $this->validationFailure()['error']['errors'];

        $laravel = ['message' => 'The given data was invalid.', 'errors' => ['password' => ['The password must be at least 12 characters.']], 'langsys_errors' => $entries];
        $jsonApi = ['errors' => [['status' => '422', 'meta' => ['entry' => $entries[0]]], ['status' => '422', 'meta' => ['entry' => $entries[1]]]]];

        $this->assertSame($entries, MessageSet::fromResponse($laravel)->toArray());
        $this->assertSame($entries, MessageSet::fromResponse($jsonApi)->toArray());
    }

    public function testAConfiguredKeyNarrowsWhereEntriesAreRead(): void
    {
        $entries = $this->validationFailure()['error']['errors'];
        $body = ['data' => ['form_errors' => [$entries[0]]], 'other' => [$entries[1]]];

        $this->assertSame([$entries[0]], MessageSet::fromResponse($body, ['key' => 'data.form_errors'])->toArray());
        $this->assertCount(0, MessageSet::fromResponse($body, ['key' => 'data.missing']));
    }

    public function testAResolverMapsAnAppsNativeFailuresToTheSameEntries(): void
    {
        $native = ['violations' => [['path' => 'password', 'rule' => 'min', 'limit' => 12]]];
        $resolver = function (array $body) {
            $out = [];
            foreach ($body['violations'] as $violation) {
                $out[] = ServerMessage::make('too_short', 'The password must be at least {min} characters.', ['min' => $violation['limit']], $violation['path']);
            }
            return $out;
        };

        $mapped = MessageSet::fromResponse($native, ['resolver' => $resolver])->toArray();

        $this->assertSame([$this->validationFailure()['error']['errors'][0]], $mapped);
    }

    public function testAnEntryMissingItsTemplateDoesNotResolve(): void
    {
        $body = ['error' => ['code' => 'too_short', 'message' => 'The password must be at least 12 characters.', 'field' => 'password']];

        $this->assertCount(0, MessageSet::fromResponse($body));
    }

    public function testAJsonBodyResolvesLikeTheDecodedOne(): void
    {
        $this->assertSame(
            MessageSet::fromResponse($this->validationFailure())->toArray(),
            MessageSet::fromResponse(json_encode($this->validationFailure()))->toArray()
        );
        $this->assertCount(0, MessageSet::fromResponse('not json'));
        $this->assertCount(0, MessageSet::fromResponse(null));
    }

    public function testASetSurvivesASessionRoundTrip(): void
    {
        $set = MessageSet::fromResponse($this->validationFailure());

        $this->assertSame($set->toArray(), MessageSet::fromArray(json_decode(json_encode($set), true))->toArray());
    }
}
