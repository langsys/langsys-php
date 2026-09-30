<?php

namespace Langsys\SDK\Messages;

/**
 * Declares a backed enum as the set of values for a placeholder (FRM-7):
 *
 *     #[TranslatesAs('status')]
 *     enum OrderStatus: string { case Shipped = 'shipped'; ... }
 *
 * Each case's display word is its label() when the enum defines one, and its
 * value otherwise - so define label() when the value is a slug, or the slug is
 * what readers see and translators get.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class TranslatesAs
{
    /** @var string */
    public $placeholder;

    /**
     * @param string $placeholder
     */
    public function __construct($placeholder)
    {
        $this->placeholder = (string) $placeholder;
    }
}
