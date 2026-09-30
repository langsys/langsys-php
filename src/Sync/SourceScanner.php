<?php

namespace Langsys\SDK\Sync;

/**
 * Finds the calls to a translate function in PHP source (FRM-2).
 *
 * Only a literal is a phrase: a call whose first argument is one string
 * literal is collected with its text; any other call is reported, never
 * guessed. The source is read with PHP's own tokenizer, so strings, heredocs
 * and comments are never mistaken for calls, and a call nested in an
 * expression (`e(__('Save'))`, as a compiled Blade view writes it) is found
 * like any other.
 *
 * Recognised: the configured function names, static calls written
 * `Class::method`, and `app('translator')->get(...)` / `->choice(...)`, which
 * Blade's `@lang` and `@choice` compile to.
 */
final class SourceScanner
{
    /** A call's function name => how it reads its arguments. */
    const DEFAULT_FUNCTIONS = [
        '__' => '__',
        'trans' => '__',
        't' => '__',
        'trans_choice' => 'trans_choice',
        'Lang::get' => '__',
        'Lang::choice' => 'trans_choice',
    ];

    /** @var array<string, string> */
    private $functions;

    /**
     * @param array<string, string>|null $functions name => '__' or 'trans_choice'
     */
    public function __construct(array $functions = null)
    {
        $this->functions = $functions === null ? self::DEFAULT_FUNCTIONS : $functions;
    }

    /**
     * Every translate call in $code.
     *
     * Each hit: `text` (the literal, or null), `skipped` (null, or
     * 'non-literal'), `entry_point` (the name as written), `kind` ('__' or
     * 'trans_choice'), `line` (in $code), `arg_count`, `replace_keys` (the
     * literal keys of a replace array, or null when there is none or it is not
     * a literal) and `file` ($label).
     *
     * @param string $code PHP source, or a compiled view
     * @param string $label The file it came from, carried into each hit
     * @return array[]
     */
    public function scan($code, $label = '')
    {
        $tokens = array_values(array_filter(token_get_all((string) $code), function ($token) {
            return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
        }));

        $hits = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $call = $this->callAt($tokens, $i);
            if ($call === null) {
                continue;
            }

            list($name, $open, $line) = $call;
            $args = self::arguments($tokens, $open);
            $kind = $this->functions[$name];
            $text = $args === [] ? null : self::literal($args[0]);

            $replaceIndex = $kind === 'trans_choice' ? 2 : 1;
            $replaceKeys = isset($args[$replaceIndex]) ? self::literalKeys($args[$replaceIndex]) : null;

            $hits[] = [
                'text' => $text,
                'group' => $text === null && $args !== [] ? self::literalGroup($args[0]) : null,
                'skipped' => $text === null ? 'non-literal' : null,
                'entry_point' => $name,
                'kind' => $kind,
                'line' => $line,
                'arg_count' => count($args),
                'replace_keys' => $replaceKeys,
                'file' => (string) $label,
            ];
        }

        return $hits;
    }

    /**
     * The call starting at token $i: [name, index of its "(", line], or null.
     *
     * @param array $tokens
     * @param int $i
     * @return array|null
     */
    private function callAt(array $tokens, $i)
    {
        $token = $tokens[$i];

        // app('translator')->get( / ->choice(
        if (is_array($token) && $token[0] === T_STRING && strtolower($token[1]) === 'app'
            && self::is($tokens, $i + 1, '(')
            && isset($tokens[$i + 2]) && is_array($tokens[$i + 2]) && $tokens[$i + 2][0] === T_CONSTANT_ENCAPSED_STRING
            && self::literal([$tokens[$i + 2]]) === 'translator'
            && self::is($tokens, $i + 3, ')')
            && isset($tokens[$i + 4]) && is_array($tokens[$i + 4]) && in_array($tokens[$i + 4][0], [T_OBJECT_OPERATOR, defined('T_NULLSAFE_OBJECT_OPERATOR') ? T_NULLSAFE_OBJECT_OPERATOR : -1], true)
            && isset($tokens[$i + 5]) && is_array($tokens[$i + 5]) && in_array(strtolower($tokens[$i + 5][1]), ['get', 'choice'], true)
            && self::is($tokens, $i + 6, '(')) {
            $name = strtolower($tokens[$i + 5][1]) === 'choice' ? 'Lang::choice' : 'Lang::get';

            return isset($this->functions[$name]) ? [$name, $i + 6, $token[2]] : null;
        }

        if (!is_array($token)) {
            return null;
        }

        $previous = $i > 0 ? $tokens[$i - 1] : null;

        // Class::method( - including a qualified class name.
        if ($token[0] === T_DOUBLE_COLON && $previous !== null && is_array($previous)
            && isset($tokens[$i + 1]) && is_array($tokens[$i + 1]) && $tokens[$i + 1][0] === T_STRING
            && self::is($tokens, $i + 2, '(')) {
            $class = ltrim($previous[1], '\\');
            $class = substr($class, (int) strrpos('\\' . $class, '\\'));
            $name = $class . '::' . $tokens[$i + 1][1];

            return isset($this->functions[$name]) ? [$name, $i + 2, $tokens[$i + 1][2]] : null;
        }

        $isName = $token[0] === T_STRING
            || (defined('T_NAME_FULLY_QUALIFIED') && $token[0] === T_NAME_FULLY_QUALIFIED);
        if (!$isName || !self::is($tokens, $i + 1, '(')) {
            return null;
        }

        // A method, a declaration or a static call's method name is not a
        // function call.
        if ($previous !== null && is_array($previous)
            && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW, defined('T_NULLSAFE_OBJECT_OPERATOR') ? T_NULLSAFE_OBJECT_OPERATOR : -1], true)) {
            return null;
        }

        $name = ltrim($token[1], '\\');

        return isset($this->functions[$name]) ? [$name, $i + 1, $token[2]] : null;
    }

    /**
     * The top-level arguments of the call whose "(" is at $open, each a list
     * of tokens.
     *
     * @param array $tokens
     * @param int $open
     * @return array[]
     */
    private static function arguments(array $tokens, $open)
    {
        $args = [];
        $current = [];
        $depth = 0;

        for ($i = $open + 1; $i < count($tokens); $i++) {
            $token = $tokens[$i];
            $text = is_array($token) ? $token[1] : $token;

            if (in_array($text, ['(', '[', '{'], true) || (is_array($token) && in_array($token[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif (in_array($text, [')', ']', '}'], true)) {
                if ($depth === 0) {
                    if ($current !== []) {
                        $args[] = $current;
                    }

                    return $args;
                }
                $depth--;
            } elseif ($text === ',' && $depth === 0) {
                $args[] = $current;
                $current = [];
                continue;
            }

            $current[] = $token;
        }

        return $args;
    }

    /**
     * The string an argument is, when it is exactly one string literal with
     * nothing interpolated into it; else null.
     *
     * @param array $arg
     * @return string|null
     */
    private static function literal(array $arg)
    {
        if (count($arg) === 1 && is_array($arg[0]) && $arg[0][0] === T_CONSTANT_ENCAPSED_STRING) {
            $raw = $arg[0][1];
            $body = substr($raw, 1, -1);

            if ($raw[0] === "'") {
                return strtr($body, ["\\\\" => "\\", "\\'" => "'"]);
            }

            return stripcslashes(str_replace('\\$', '$', $body));
        }

        // A heredoc or nowdoc with no interpolation.
        if (count($arg) === 3 && is_array($arg[0]) && $arg[0][0] === T_START_HEREDOC
            && is_array($arg[1]) && $arg[1][0] === T_ENCAPSED_AND_WHITESPACE
            && is_array($arg[2]) && $arg[2][0] === T_END_HEREDOC) {
            $body = rtrim($arg[1][1], "\r\n");

            return strpos($arg[0][1], "'") !== false ? $body : stripcslashes($body);
        }

        return null;
    }

    /**
     * The group a key built at runtime is fixed to: the literal prefix of
     * `"group.$key"` or `'group.' . $key`, when that prefix is a dotted key
     * path. Null for anything else - a sentence built at runtime has none.
     *
     * @param array $arg
     * @return string|null
     */
    private static function literalGroup(array $arg)
    {
        $prefix = null;

        if (isset($arg[0], $arg[1], $arg[2]) && $arg[0] === '"' && is_array($arg[1]) && $arg[1][0] === T_ENCAPSED_AND_WHITESPACE
            && is_array($arg[2]) && in_array($arg[2][0], [T_VARIABLE, T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true)) {
            $prefix = $arg[1][1];
        } elseif (isset($arg[0], $arg[1]) && is_array($arg[0]) && $arg[0][0] === T_CONSTANT_ENCAPSED_STRING && $arg[1] === '.') {
            $prefix = self::literal([$arg[0]]);
        }

        return $prefix !== null && preg_match('/^([A-Za-z0-9_-]+)\.(?:[A-Za-z0-9_-]+\.)*$/', $prefix, $m) ? $m[1] : null;
    }

    /**
     * The literal string keys of an array literal, or null when the argument
     * is not an array literal whose keys are all literal strings.
     *
     * @param array $arg
     * @return string[]|null
     */
    private static function literalKeys(array $arg)
    {
        $first = $arg[0];
        $short = !is_array($first) && $first === '[';
        $long = is_array($first) && $first[0] === T_ARRAY && isset($arg[1]) && $arg[1] === '(';
        if (!$short && !$long) {
            return null;
        }

        $inner = $short ? array_slice($arg, 1, -1) : array_slice($arg, 2, -1);
        $keys = [];
        $depth = 0;
        $element = [];

        $inner[] = ',';
        foreach ($inner as $token) {
            $text = is_array($token) ? $token[1] : $token;

            if (in_array($text, ['(', '[', '{'], true)) {
                $depth++;
            } elseif (in_array($text, [')', ']', '}'], true)) {
                $depth--;
            } elseif ($text === ',' && $depth === 0) {
                if ($element !== []) {
                    $arrow = null;
                    foreach ($element as $k => $part) {
                        if (is_array($part) && $part[0] === T_DOUBLE_ARROW) {
                            $arrow = $k;
                            break;
                        }
                    }
                    $key = $arrow === null ? null : self::literal(array_slice($element, 0, $arrow));
                    if ($key === null) {
                        return null;
                    }
                    $keys[] = $key;
                }
                $element = [];
                continue;
            }

            $element[] = $token;
        }

        return $keys;
    }

    /**
     * @param array $tokens
     * @param int $i
     * @param string $char
     * @return bool
     */
    private static function is(array $tokens, $i, $char)
    {
        return isset($tokens[$i]) && !is_array($tokens[$i]) && $tokens[$i] === $char;
    }
}
