<?php

namespace Langsys\SDK\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The SDK runs on PHP 7.4. The source is read for what newer PHP added and a
 * 7.4 process lacks: the DOM members 8.0 and 8.3 gave elements, the string
 * and type functions of 8.0 and 8.1, and the nullsafe operator. On 7.4 a
 * missing DOM property reads as null with a notice, which a framework's error
 * handler raises, and a missing function is fatal. Read rather than run, so
 * this holds when the suite runs on a newer PHP.
 */
class PhpFloorTest extends TestCase
{
    const DOM_MEMBERS = [
        'firstElementChild', 'lastElementChild', 'childElementCount', 'nextElementSibling',
        'previousElementSibling', 'parentElement', 'isConnected', 'replaceWith', 'replaceChildren',
    ];

    const FUNCTIONS = [
        'str_contains', 'str_starts_with', 'str_ends_with', 'get_debug_type', 'get_resource_id',
        'fdiv', 'preg_last_error_msg', 'array_is_list',
    ];

    public function testNothingInTheSourceNeedsAPhpNewerThan74(): void
    {
        $src = dirname(__DIR__) . '/src';
        $newer = [];
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $tokens = array_values(array_filter(token_get_all(file_get_contents($file->getPathname())), function ($token) {
                return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
            }));
            $where = substr($file->getPathname(), strlen($src) + 1) . ':';
            foreach ($tokens as $i => $token) {
                if (!is_array($token)) {
                    continue;
                }
                $next = isset($tokens[$i + 1]) ? $tokens[$i + 1] : null;
                $previous = $i > 0 ? $tokens[$i - 1] : null;
                if (defined('T_NULLSAFE_OBJECT_OPERATOR') && $token[0] === T_NULLSAFE_OBJECT_OPERATOR) {
                    $newer[] = $where . $token[2] . ' ?->';
                }
                if ($token[0] === T_OBJECT_OPERATOR && is_array($next) && in_array($next[1], self::DOM_MEMBERS, true)) {
                    $newer[] = $where . $token[2] . ' ->' . $next[1];
                }
                if ($token[0] === T_STRING && in_array(strtolower($token[1]), self::FUNCTIONS, true) && $next === '('
                    && !(is_array($previous) && in_array($previous[0], [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION], true))) {
                    $newer[] = $where . $token[2] . ' ' . $token[1] . '()';
                }
            }
        }

        $this->assertSame([], $newer);
    }

    /**
     * CI lints every PHP file in src and tests with a 7.4 `php -l`, which
     * refuses syntax 7.4 cannot parse anywhere in a file, a test fixture's
     * included. Code that needs a newer PHP is declared at run time, inside a
     * string, where the lint does not read it.
     */
    public function testEveryLintedFileParsesOnPhp74(): void
    {
        $keywords = [];
        foreach (['T_MATCH' => 'match', 'T_ENUM' => 'enum', 'T_READONLY' => 'readonly', 'T_NULLSAFE_OBJECT_OPERATOR' => '?->'] as $constant => $word) {
            if (defined($constant)) {
                $keywords[constant($constant)] = $word;
            }
        }
        if ($keywords === []) {
            $this->markTestSkipped('This PHP has none of the newer keywords to look for');
        }

        $root = dirname(__DIR__);
        $newer = [];
        foreach (['src', 'tests'] as $dir) {
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root . '/' . $dir, \FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }
                // TOKEN_PARSE reads a keyword used as a name, such as a method
                // called match(), as the name it is.
                foreach (token_get_all(file_get_contents($file->getPathname()), TOKEN_PARSE) as $token) {
                    if (is_array($token) && isset($keywords[$token[0]])) {
                        $newer[] = substr($file->getPathname(), strlen($root) + 1) . ':' . $token[2] . ' ' . $keywords[$token[0]];
                    }
                }
            }
        }

        $this->assertSame([], $newer);
    }
}
