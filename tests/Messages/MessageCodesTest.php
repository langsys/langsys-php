<?php

namespace Langsys\SDK\Tests\Messages;

use Langsys\SDK\Messages\MessageCodes;
use PHPUnit\Framework\TestCase;

/**
 * MSG-2: codes are the stable slug an app branches on, from one shared vocabulary.
 */
class MessageCodesTest extends TestCase
{
    public function testTheVocabularyIsTheSpecsList(): void
    {
        $this->assertSame([
            'required', 'invalid_type', 'invalid_format', 'invalid_option', 'invalid_date', 'not_found',
            'already_taken', 'mismatch', 'too_short', 'too_long', 'too_small', 'too_large', 'too_few',
            'too_many', 'not_allowed', 'already_member', 'not_member', 'already_owner', 'expired',
            'not_available', 'invalid',
        ], MessageCodes::VOCABULARY);
    }

    public function testOnlySnakeCaseSlugsAreCodes(): void
    {
        foreach (MessageCodes::VOCABULARY as $code) {
            $this->assertTrue(MessageCodes::isSlug($code), $code);
        }
        $this->assertTrue(MessageCodes::isSlug('project_limit_reached'));

        foreach (['TooShort', 'too-short', 'too short', '', '1abc', 'too__short', '_x', 'x_'] as $code) {
            $this->assertFalse(MessageCodes::isSlug($code), var_export($code, true));
        }
    }

    public function testASizeFailurePicksItsCodeByTheFieldsType(): void
    {
        $expected = [
            ['min', 'string', 'too_short'], ['min', 'numeric', 'too_small'], ['min', 'array', 'too_few'], ['min', 'file', 'too_small'],
            ['max', 'string', 'too_long'], ['max', 'numeric', 'too_large'], ['max', 'array', 'too_many'], ['max', 'file', 'too_large'],
            ['gt', 'string', 'too_short'], ['gt', 'numeric', 'too_small'], ['gt', 'array', 'too_few'], ['gt', 'file', 'too_small'],
            ['ge', 'string', 'too_short'], ['ge', 'numeric', 'too_small'], ['ge', 'array', 'too_few'], ['ge', 'file', 'too_small'],
            ['lt', 'string', 'too_long'], ['lt', 'numeric', 'too_large'], ['lt', 'array', 'too_many'], ['lt', 'file', 'too_large'],
            ['le', 'string', 'too_long'], ['le', 'numeric', 'too_large'], ['le', 'array', 'too_many'], ['le', 'file', 'too_large'],
        ];

        foreach ($expected as list($rule, $type, $code)) {
            $this->assertSame($code, MessageCodes::forSize($rule, $type), "$rule on $type");
        }

        $this->assertSame('too_short', MessageCodes::forSize('min', 'something-else'), 'an unknown type reads as text, as the reference does');
        $this->assertNull(MessageCodes::forSize('between', 'string'));
    }

    public function testABoundPicksItsCodeByTheFieldsTypeWithoutNamingAFrameworksRule(): void
    {
        $expected = [
            'lower' => ['string' => 'too_short', 'numeric' => 'too_small', 'array' => 'too_few', 'file' => 'too_small'],
            'upper' => ['string' => 'too_long', 'numeric' => 'too_large', 'array' => 'too_many', 'file' => 'too_large'],
        ];

        foreach ($expected as $side => $codes) {
            foreach ($codes as $type => $code) {
                $this->assertSame($code, MessageCodes::forBound($side, $type), "$side on $type");
            }
            $this->assertSame($codes['string'], MessageCodes::forBound($side, 'something-else'));
        }

        $this->assertNull(MessageCodes::forBound('middle', 'string'));

        foreach (['string', 'numeric', 'array', 'file'] as $type) {
            $this->assertSame(MessageCodes::forBound('lower', $type), MessageCodes::forSize('min', $type));
            $this->assertSame(MessageCodes::forBound('lower', $type), MessageCodes::forSize('gt', $type));
            $this->assertSame(MessageCodes::forBound('upper', $type), MessageCodes::forSize('max', $type));
        }
    }
}
