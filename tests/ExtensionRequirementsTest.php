<?php

namespace Langsys\SDK\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Every extension the source calls is one composer.json requires or suggests,
 * or one PHP always has, so a host that lacks one is refused at install rather
 * than failing inside a render. A call is guarded when its file asks
 * function_exists() for it first. Read rather than run: a function or class
 * counts when the extension providing it is loaded in the PHP running this.
 */
class ExtensionRequirementsTest extends TestCase
{
    const ALWAYS_PRESENT = ['core', 'standard', 'date', 'pcre', 'spl', 'reflection', 'hash', 'random', 'json'];

    public function testEveryExtensionTheSourceCallsIsDeclared(): void
    {
        $root = dirname(__DIR__);
        $composer = json_decode(file_get_contents($root . '/composer.json'), true);
        $declared = self::ALWAYS_PRESENT;
        foreach (array_merge(array_keys($composer['require']), array_keys(isset($composer['suggest']) ? $composer['suggest'] : [])) as $package) {
            if (strpos($package, 'ext-') === 0) {
                $declared[] = strtolower(substr($package, 4));
            }
        }

        $undeclared = [];
        $src = $root . '/src';
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($src, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }
            $code = file_get_contents($file->getPathname());
            preg_match_all("/function_exists\\(\\s*'([A-Za-z0-9_]+)'/", $code, $guards);
            $tokens = array_values(array_filter(token_get_all($code), function ($token) {
                return !is_array($token) || !in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true);
            }));
            $where = substr($file->getPathname(), strlen($src) + 1) . ':';
            foreach ($tokens as $i => $token) {
                if (!is_array($token) || !in_array($token[0], self::nameTokens(), true)) {
                    continue;
                }
                $name = ltrim($token[1], '\\');
                $next = isset($tokens[$i + 1]) ? $tokens[$i + 1] : null;
                $previous = $i > 0 ? $tokens[$i - 1] : null;
                $previousKind = is_array($previous) ? $previous[0] : $previous;
                if ($previousKind === T_NS_SEPARATOR) {
                    $previous = $i > 1 ? $tokens[$i - 2] : null;
                    $previousKind = is_array($previous) ? $previous[0] : $previous;
                }

                $extension = null;
                if ($next === '(' && !in_array($previousKind, [T_OBJECT_OPERATOR, T_DOUBLE_COLON, T_FUNCTION, T_NEW], true)) {
                    if (function_exists($name) && !in_array($name, $guards[1], true)) {
                        $function = new \ReflectionFunction($name);
                        $extension = $function->isInternal() ? $function->getExtensionName() : null;
                    }
                } elseif ($previousKind === T_NEW || $previousKind === T_INSTANCEOF || (is_array($next) && $next[0] === T_DOUBLE_COLON)) {
                    if (class_exists($name) || interface_exists($name)) {
                        $class = new \ReflectionClass($name);
                        $extension = $class->isInternal() ? $class->getExtensionName() : null;
                    }
                }

                if ($extension !== null && !in_array(strtolower($extension), $declared, true)) {
                    $undeclared[] = $where . $token[2] . ' ' . $name . ' (ext-' . strtolower($extension) . ')';
                }
            }
        }

        $this->assertSame([], $undeclared);
    }

    /**
     * The tokens a function or class name reads as: a bare name, and on PHP 8 a
     * whole qualified one.
     */
    private static function nameTokens()
    {
        $kinds = [T_STRING];
        foreach (['T_NAME_FULLY_QUALIFIED', 'T_NAME_QUALIFIED'] as $kind) {
            if (defined($kind)) {
                $kinds[] = constant($kind);
            }
        }

        return $kinds;
    }
}
