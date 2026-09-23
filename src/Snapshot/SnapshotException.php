<?php

namespace Langsys\SDK\Snapshot;

use Langsys\SDK\Exception\LangsysException;

/**
 * A snapshot that cannot be used: not a snapshot, an unknown version, or one
 * changed after Langsys produced it.
 */
class SnapshotException extends LangsysException
{
}
