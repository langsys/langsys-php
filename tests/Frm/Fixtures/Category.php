<?php

namespace Langsys\SDK\Tests\Frm\Fixtures;

use Langsys\SDK\Messages\TranslatableValues;

/**
 * A model-like declaration whose values change between reads, as rows do.
 */
class Category implements TranslatableValues
{
    /** @var string[] */
    public static $rows = ['Books', 'Music'];

    public static function translatableValues()
    {
        return ['category' => static::$rows];
    }
}
