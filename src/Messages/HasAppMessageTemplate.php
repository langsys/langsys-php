<?php

namespace Langsys\SDK\Messages;

/**
 * A message the app defines itself - an API error, a system notice - stating
 * its sentence ahead of time (MSG-7):
 *
 *     final class QuotaExceeded implements HasAppMessageTemplate
 *     {
 *         public $limit;
 *
 *         public function template()
 *         {
 *             return 'You have used all {limit} of this month\'s requests.';
 *         }
 *
 *         public function code()
 *         {
 *             return 'quota_exceeded';
 *         }
 *     }
 *
 * Each `{name}` marker is filled from the public property of the same name.
 * It belongs to no field, so it carries no label placeholder; it is listed and
 * registered under the messages category with its code.
 */
interface HasAppMessageTemplate
{
    /**
     * The sentence in the source language, a `{name}` marker for each value,
     * named after the public property that holds it.
     *
     * @return string
     */
    public function template();

    /**
     * The message's own identifier, passed through as its entry's code
     * (MSG-1), or null when it has none.
     *
     * @return string|null
     */
    public function code();
}
