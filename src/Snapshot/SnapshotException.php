<?php

namespace Langsys\SDK\Snapshot;

use Langsys\SDK\Exception\LangsysException;

/**
 * A snapshot that cannot be used: not a snapshot, an unknown version, or one
 * changed after Langsys produced it.
 */
class SnapshotException extends LangsysException
{
    /** @var string|null format, version, missing-member or checksum, when a load was refused */
    private $reason;

    /**
     * @param string $message
     * @param string|null $reason Why a load was refused (SNAP-1)
     * @param int $code
     * @param \Throwable|null $previous
     */
    public function __construct($message = '', $reason = null, $code = 0, \Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->reason = $reason;
    }

    /**
     * Why a load was refused: `format`, `version`, `missing-member` or
     * `checksum`; null for any other failure.
     *
     * @return string|null
     */
    public function getReason()
    {
        return $this->reason;
    }
}
