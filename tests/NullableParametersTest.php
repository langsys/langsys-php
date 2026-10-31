<?php

namespace Langsys\SDK\Tests;

use PHPUnit\Framework\TestCase;

/**
 * A typed parameter that defaults to null says it is nullable: `?DOMNode
 * $scope = null`. PHP 8.4 raises a deprecation notice as it compiles the
 * implicit form, `DOMNode $scope = null`, which a page shows when errors are
 * displayed. The source is read rather than compiled, so this holds on every
 * PHP the SDK supports, not only on 8.4.
 */
class NullableParametersTest extends TestCase
{
    /**
     * Each parameter in $tokens, as [its line, its tokens before the
     * variable, its variable, its default].
     */
    private static function parameters(array $tokens)
    {
        $parameters = [];
        $count = count($tokens);
        for ($i = 0; $i < $count; $i++) {
            if (!is_array($tokens[$i]) || !in_array($tokens[$i][0], [T_FUNCTION, T_FN], true)) {
                continue;
            }
            while ($i < $count && $tokens[$i] !== '(') {
                $i++;
            }
            $depth = 0;
            $current = [];
            for ($i++; $i < $count; $i++) {
                $token = $tokens[$i];
                if (in_array($token, ['(', '[', '{'], true)) {
                    $depth++;
                } elseif (in_array($token, [')', ']', '}'], true)) {
                    if ($depth === 0) {
                        break;
                    }
                    $depth--;
                } elseif ($token === ',' && $depth === 0) {
                    $parameters[] = $current;
                    $current = [];
                    continue;
                }
                if (!is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                    $current[] = $token;
                }
            }
            if ($current !== []) {
                $parameters[] = $current;
            }
        }

        $read = [];
        foreach ($parameters as $parameter) {
            foreach ($parameter as $at => $token) {
                if (is_array($token) && $token[0] === T_VARIABLE) {
                    $type = array_values(array_filter(array_slice($parameter, 0, $at), function ($t) {
                        return !in_array(is_array($t) ? $t[1] : $t, ['&', '...', 'public', 'protected', 'private', 'readonly'], true);
                    }));
                    $default = array_slice($parameter, $at + 1);
                    $read[] = [$token[2], $type, $token[1], $default];
                    break;
                }
            }
        }

        return $read;
    }

    public function testEveryTypedParameterDefaultingToNullSaysItIsNullable(): void
    {
        $src = dirname(__DIR__) . '/src';
        $implicit = [];
        $seen = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            foreach (self::parameters(token_get_all(file_get_contents($file->getPathname()))) as $parameter) {
                list($line, $type, $variable, $default) = $parameter;
                $isNull = count($default) === 2 && $default[0] === '=' && is_array($default[1]) && strtolower($default[1][1]) === 'null';
                if (!$isNull || $type === []) {
                    continue;
                }
                $seen++;
                if ($type[0] !== '?') {
                    $implicit[] = substr($file->getPathname(), strlen($src) + 1) . ':' . $line . ' ' . $variable;
                }
            }
        }

        $this->assertGreaterThan(0, $seen, 'the scan reads typed parameters defaulting to null');
        sort($implicit);
        $this->assertSame([], $implicit);
    }
}
