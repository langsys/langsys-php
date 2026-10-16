<?php

namespace Langsys\SDK\Html;

/**
 * An ICU converter that writes U+FFFD for a byte sequence it cannot decode, as
 * a browser's decoder does, where ICU's own default writes U+001A for some
 * encodings and drops the bytes for others. Loaded only when intl is.
 *
 * @internal
 */
final class IcuDecoder extends \UConverter
{
    /**
     * @param int $reason
     * @param string $source
     * @param string $codeUnits
     * @param int $error
     * @return int|null
     */
    #[\ReturnTypeWillChange]
    public function toUCallback($reason, $source, $codeUnits, &$error)
    {
        if ($reason === self::REASON_ILLEGAL || $reason === self::REASON_IRREGULAR || $reason === self::REASON_UNASSIGNED) {
            $error = U_ZERO_ERROR;

            return 0xFFFD;
        }

        return null;
    }
}
