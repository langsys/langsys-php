<?php

namespace Langsys\SDK\Messages;

/**
 * The code an app branches on (MSG-2): a stable snake_case slug, never used to
 * choose text, never renamed - only retired.
 */
final class MessageCodes
{
    const REQUIRED = 'required';
    const INVALID_TYPE = 'invalid_type';
    const INVALID_FORMAT = 'invalid_format';
    const INVALID_OPTION = 'invalid_option';
    const INVALID_DATE = 'invalid_date';
    const NOT_FOUND = 'not_found';
    const ALREADY_TAKEN = 'already_taken';
    const MISMATCH = 'mismatch';
    const TOO_SHORT = 'too_short';
    const TOO_LONG = 'too_long';
    const TOO_SMALL = 'too_small';
    const TOO_LARGE = 'too_large';
    const TOO_FEW = 'too_few';
    const TOO_MANY = 'too_many';
    const NOT_ALLOWED = 'not_allowed';
    const ALREADY_MEMBER = 'already_member';
    const NOT_MEMBER = 'not_member';
    const ALREADY_OWNER = 'already_owner';
    const EXPIRED = 'expired';
    const NOT_AVAILABLE = 'not_available';
    const INVALID = 'invalid';

    /**
     * The shared validation vocabulary, in the spec's order. A server SDK adds a
     * code only for a failure these cannot express.
     */
    const VOCABULARY = [
        self::REQUIRED, self::INVALID_TYPE, self::INVALID_FORMAT, self::INVALID_OPTION, self::INVALID_DATE,
        self::NOT_FOUND, self::ALREADY_TAKEN, self::MISMATCH, self::TOO_SHORT, self::TOO_LONG,
        self::TOO_SMALL, self::TOO_LARGE, self::TOO_FEW, self::TOO_MANY, self::NOT_ALLOWED,
        self::ALREADY_MEMBER, self::NOT_MEMBER, self::ALREADY_OWNER, self::EXPIRED, self::NOT_AVAILABLE,
        self::INVALID,
    ];

    const SLUG_PATTERN = '/^[a-z][a-z0-9]*(?:_[a-z0-9]+)*$/';

    /**
     * A size failure's code depends on which bound failed and on the field's
     * type: text is too_short/too_long, numbers too_small/too_large, lists
     * too_few/too_many. Framework-neutral on purpose - a binding maps its own
     * rules (min, between, gte, digits_between...) onto a side.
     */
    const BOUNDS = [
        'lower' => ['string' => self::TOO_SHORT, 'numeric' => self::TOO_SMALL, 'array' => self::TOO_FEW, 'file' => self::TOO_SMALL],
        'upper' => ['string' => self::TOO_LONG, 'numeric' => self::TOO_LARGE, 'array' => self::TOO_MANY, 'file' => self::TOO_LARGE],
    ];

    /**
     * The reference's size rules, each onto its side (MSG-2): an exclusive
     * bound (gt, lt) and an inclusive one (ge, le) are the same condition as
     * min and max, so they carry the same code.
     */
    const SIZE_RULE_SIDES = ['min' => 'lower', 'gt' => 'lower', 'ge' => 'lower', 'max' => 'upper', 'lt' => 'upper', 'le' => 'upper'];

    /**
     * @param mixed $code
     * @return bool
     */
    public static function isSlug($code)
    {
        return is_string($code) && preg_match(self::SLUG_PATTERN, $code) === 1;
    }

    /**
     * The code for a failed bound, or null for a side that is not a bound. An
     * unknown type reads as text, as the reference does.
     *
     * @param string $side lower or upper
     * @param string $type string, numeric, array or file
     * @return string|null
     */
    public static function forBound($side, $type)
    {
        if (!is_string($side) || !isset(self::BOUNDS[$side])) {
            return null;
        }

        return isset(self::BOUNDS[$side][$type]) ? self::BOUNDS[$side][$type] : self::BOUNDS[$side]['string'];
    }

    /**
     * The code for one of the reference's size rules (min, gt, ge, max, lt, le), or null.
     *
     * @param string $rule
     * @param string $type
     * @return string|null
     */
    public static function forSize($rule, $type)
    {
        return (is_string($rule) && isset(self::SIZE_RULE_SIDES[$rule])) ? self::forBound(self::SIZE_RULE_SIDES[$rule], $type) : null;
    }
}
