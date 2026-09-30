<?php

namespace Langsys\SDK\Messages;

/**
 * A validation rule object that states its sentence ahead of time (FRM-2).
 *
 * A framework hands its validator a finished message, values already filled
 * in, so a listing cannot tell where a value goes: `The name may not be more
 * than 255 characters.` would register the number as part of the phrase. A
 * rule implementing this says it instead:
 *
 *     public $max = 255;
 *
 *     public function template()
 *     {
 *         return 'The :attribute may not be more than {max} characters.';
 *     }
 *
 * The label placeholder is written in once per field that uses the rule
 * (MSG-3), and each `{name}` marker is filled from the public property of the
 * same name.
 */
interface HasMessageTemplate
{
    /**
     * The rule's sentence in the source language: the label placeholder where
     * the field's label goes, and a `{name}` marker for each value, named
     * after the public property that holds it.
     *
     * @return string
     */
    public function template();
}
