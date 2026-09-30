<?php

namespace Langsys\SDK\Messages;

/**
 * The app's declared value sets (FRM-7): the classes implementing
 * TranslatableValues and the backed enums marked TranslatesAs, read at the
 * moment they are asked, so a row added since the last sync counts.
 *
 * A value belongs to the set declared for its placeholder when it is one of
 * that set's display words, a case of an enum declared for it, or such a
 * case's backing value. A placeholder is never linked to a set by name
 * alone: only a declaration does that.
 */
final class ValueSets
{
    /** @var array<int, string|object> */
    private $declarations;

    /**
     * @param array<int, string|object> $declarations Class names or instances
     */
    public function __construct(array $declarations)
    {
        $this->declarations = array_values($declarations);
    }

    /**
     * Every declared set, placeholder => display words.
     *
     * @return array<string, string[]>
     */
    public function sets()
    {
        $sets = [];

        foreach ($this->declarations as $declaration) {
            foreach ($this->read($declaration) as $placeholder => $words) {
                foreach ($words as $word) {
                    $sets[$placeholder][(string) $word] = true;
                }
            }
        }

        return array_map('array_keys', $sets);
    }

    /**
     * The display word a value is written in as, or null when the value is in
     * no set declared for its placeholder.
     *
     * @param string $placeholder
     * @param mixed $value
     * @return string|null
     */
    public function displayWord($placeholder, $value)
    {
        foreach ($this->enumsFor($placeholder) as $enum) {
            if ($value instanceof $enum) {
                return self::wordOf($value);
            }

            if (is_string($value) || is_int($value)) {
                $case = $enum::tryFrom($value);
                if ($case !== null) {
                    return self::wordOf($case);
                }
            }
        }

        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $sets = $this->sets();

        return isset($sets[$placeholder]) && in_array((string) $value, $sets[$placeholder], true) ? (string) $value : null;
    }

    /**
     * A phrase with each declared value written in (MSG-3): the phrase to look
     * up and register, and the params left to fill. Null when no param holds a
     * declared value for its placeholder.
     *
     * @param string $phrase Langsys syntax
     * @param array $params
     * @return array{0: string, 1: array}|null
     */
    public function writeIn($phrase, array $params)
    {
        $written = false;

        foreach ($params as $name => $value) {
            if (!is_string($name) || strpos($phrase, '{' . $name . '}') === false) {
                continue;
            }

            $word = $this->displayWord($name, $value);
            if ($word === null) {
                continue;
            }

            $phrase = str_replace('{' . $name . '}', $word, $phrase);
            unset($params[$name]);
            $written = true;
        }

        return $written ? [$phrase, $params] : null;
    }

    /**
     * Every sentence a template makes with the declared values written in,
     * one per combination; the template itself when it names no declared set.
     *
     * @param string $template Langsys syntax
     * @return string[]
     */
    public function sentences($template)
    {
        $sentences = [$template];

        foreach ($this->sets() as $placeholder => $words) {
            if (strpos($template, '{' . $placeholder . '}') === false || $words === []) {
                continue;
            }

            $next = [];
            foreach ($sentences as $sentence) {
                foreach ($words as $word) {
                    $next[] = str_replace('{' . $placeholder . '}', $word, $sentence);
                }
            }
            $sentences = $next;
        }

        return $sentences;
    }

    /**
     * @param string|object $declaration
     * @return array<string, string[]>
     */
    private function read($declaration)
    {
        $class = is_object($declaration) ? get_class($declaration) : (string) $declaration;

        if (is_subclass_of($class, TranslatableValues::class)) {
            $values = $class::translatableValues();

            return is_array($values) ? array_filter($values, 'is_array') : [];
        }

        $placeholder = self::declaredPlaceholder($class);
        if ($placeholder === null) {
            return [];
        }

        return [$placeholder => array_map([self::class, 'wordOf'], $class::cases())];
    }

    /**
     * @param string $placeholder
     * @return string[] Enum classes declared for the placeholder
     */
    private function enumsFor($placeholder)
    {
        $enums = [];

        foreach ($this->declarations as $declaration) {
            $class = is_object($declaration) ? get_class($declaration) : (string) $declaration;
            if (self::declaredPlaceholder($class) === $placeholder) {
                $enums[] = $class;
            }
        }

        return $enums;
    }

    /**
     * The placeholder a backed enum is declared for, or null.
     *
     * @param string $class
     * @return string|null
     */
    public static function declaredPlaceholder($class)
    {
        if (!function_exists('enum_exists') || !enum_exists($class) || !is_subclass_of($class, 'BackedEnum')) {
            return null;
        }

        foreach ((new \ReflectionClass($class))->getAttributes(TranslatesAs::class) as $attribute) {
            return $attribute->newInstance()->placeholder;
        }

        return null;
    }

    /**
     * A backed enum case's display word: label() where defined, else value.
     *
     * @param object $case
     * @return string
     */
    private static function wordOf($case)
    {
        return method_exists($case, 'label') ? (string) $case->label() : (string) $case->value;
    }
}
