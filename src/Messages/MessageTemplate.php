<?php

namespace Langsys\SDK\Messages;

/**
 * The marker grammar of a server message template (MSG-3, MSG-4).
 *
 * The same grammar and fill every Langsys SDK uses, pinned by the shared
 * server-message vectors: a server fills `message` from the template and a
 * client fills the translated template on the way back, so both must agree on
 * what a marker is and what a fill produces. A `{name}` marker stands for a
 * value that is not translatable - a
 * number, a date, what the user typed - and anything translatable is written
 * into the sentence instead.
 */
final class MessageTemplate
{
    /**
     * A marker is a lowercase snake_case name in braces. `{Name}`, `{ min }` and
     * `{1x}` are not markers and are never filled.
     */
    const MARKER_PATTERN = '/\{([a-z][a-z0-9_]*)\}/';

    /**
     * The marker names in a template, once each, in the order they first appear.
     *
     * @param string $template
     * @return string[]
     */
    public static function markers($template)
    {
        if (!is_string($template) || $template === '') {
            return [];
        }

        preg_match_all(self::MARKER_PATTERN, $template, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * Fill a template's markers from params.
     *
     * A marker with no param is left as its literal marker rather than blanked,
     * so a missing value is visible instead of silently producing a sentence
     * that reads as complete.
     *
     * @param string $template
     * @param array $params
     * @return string
     */
    public static function fill($template, array $params)
    {
        $template = (string) $template;

        $filled = preg_replace_callback(self::MARKER_PATTERN, function ($match) use ($params) {
            // A missing param stays its visible marker, and a present-but-null
            // one is missing (ICU-2): never an empty gap in the sentence.
            if (!array_key_exists($match[1], $params) || $params[$match[1]] === null) {
                return $match[0];
            }

            $value = $params[$match[1]];

            // Params never carry structures (MSG-4); leave the marker rather than
            // print "Array" into a user-facing sentence.
            if (is_array($value) || (is_object($value) && !method_exists($value, '__toString'))) {
                return $match[0];
            }

            return (string) $value;
        }, $template);

        return $filled === null ? $template : $filled;
    }
}
