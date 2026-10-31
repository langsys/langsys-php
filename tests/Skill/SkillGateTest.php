<?php

namespace Langsys\SDK\Tests\Skill;

use PHPUnit\Framework\TestCase;

/**
 * The agent skill in skill/ is held to the code it describes.
 *
 * skill/ ships in the Composer package, and an agent acts on what it says
 * without reading src/. So every method, class, option, environment variable,
 * command and link it names must exist, the options and variables it lists
 * must be all of them, its frontmatter must name this package at the version
 * the CHANGELOG is about to release, and it must open with the shared Langsys
 * skill preamble word for word (skill/preamble.md, this package's copy of the
 * template every Langsys SDK's skill opens with).
 *
 * problems() reads the docs and the facts as data, so each check is proven by
 * planting the defect it exists to catch.
 *
 * It holds names, not behaviour: what a method does, an argument's order and
 * an option's default are the review's to check. Three name checks are out of
 * reach on purpose. Whether a `Class::method()` is static: the skill's prose
 * writes instance methods that way too (`MessageCatalog::addRule()`). A method
 * past the first link of a chain. And an object in a variable RECEIVERS
 * doesn't name, since the examples' other variables are the reader's own.
 */
class SkillGateTest extends TestCase
{
    /** Names a skill quotes that belong to a framework or the reader's code, not to this SDK. */
    const FOREIGN_CALLS = ['__', 't', 'trans', 'getHtml', 'choiceHtml', 'label'];

    /** Variables the skill's examples hold an SDK object in. */
    const RECEIVERS = [
        'client' => 'Langsys\SDK\Client',
        'plan' => 'Langsys\SDK\Sync\SyncPlan',
        'entry' => 'Langsys\SDK\Messages\ServerMessage',
        'messages' => 'Langsys\SDK\Messages\MessageSet',
        'scanner' => 'Langsys\SDK\Sync\SourceScanner',
    ];

    /**
     * Option keys the source reads that are not constructor options:
     * `send_vary` belongs to `request_locale` and to resolveRequestLocale()'s
     * own options.
     */
    const NOT_CONSTRUCTOR_OPTIONS = ['send_vary'];

    const PREAMBLE = '/<!-- langsys-skill-preamble:start -->\n(.*?)\n<!-- langsys-skill-preamble:end -->/s';

    /**
     * Where the skill writes an array the SDK reads keys from, and which keys
     * those are. Each pattern ends at the array's opening bracket.
     */
    const KEYED_LITERALS = [
        "/'request_locale' => \\[/" => 'request_locale',
        '/resolveRequestLocale\\(\\[/' => 'request',
        "/'redis' => \\[/" => 'redis',
        '/new RedisCache\\(\\[/' => 'redis',
        "/'migration' => \\[/" => 'migration',
        "/'namespaces' => \\[/" => 'namespaces',
        '/importLegacyTranslations\\(\\[/' => 'targets',
        '#flushPendingRegistrations\\(\\);\\s*//\\s*\\[#' => 'flush',
    ];

    public function testTheSkillHasNoProblemAgainstTheCode(): void
    {
        $docs = self::docs();

        $this->assertArrayHasKey('SKILL.md', $docs);
        $this->assertArrayHasKey('preamble.md', $docs);
        $this->assertSame([], self::problems($docs, self::facts()));
    }

    public function plantedDefects(): array
    {
        return [
            'a Client method that does not exist' => [
                function (&$d, &$f) { $d['writing.md'] .= "\n`\$client->flushRegistrations()`\n"; },
                'writing.md: $client->flushRegistrations() is not a public method of Langsys\SDK\Client',
            ],
            'a receiver\'s property that does not exist' => [
                function (&$d, &$f) { $d['migration.md'] .= "\n`\$plan->unreported`\n"; },
                'migration.md: $plan->unreported is not a public property of Langsys\SDK\Sync\SyncPlan',
            ],
            'a static call on an SDK class that does not exist' => [
                function (&$d, &$f) { $d['migration.md'] .= "\n`Snapshot::open('x')`\n"; },
                'migration.md: Snapshot::open() is not a public method of Langsys\SDK\Snapshot\Snapshot',
            ],
            'an inline name no SDK class has' => [
                function (&$d, &$f) { $d['primitives.md'] .= "\nCall `translateHtml()`.\n"; },
                'primitives.md: translateHtml() is not a public method of any SDK class',
            ],
            'an SDK class name that does not exist' => [
                function (&$d, &$f) { $d['catalog.md'] .= "\n`Langsys\\SDK\\Cache\\ApcuCache`\n"; },
                'catalog.md: Langsys\SDK\Cache\ApcuCache is not a class or interface of this SDK',
            ],
            'a class built with new that does not exist' => [
                function (&$d, &$f) { $d['catalog.md'] .= "\n`new MemcachedCache()`\n"; },
                'catalog.md: new MemcachedCache() names no class of this SDK or of the skill\'s own examples',
            ],
            'an option the source does not read' => [
                function (&$d, &$f) { $d['init-config.md'] = str_replace("| `cache_clear` |", "| `cache_flush` |", $d['init-config.md']); },
                'init-config.md: the option cache_flush is not read by the source',
            ],
            'an option the table leaves out' => [
                function (&$d, &$f) { $d['init-config.md'] = preg_replace('/^\| `snapshot` \|.*\n/m', '', $d['init-config.md']); },
                'init-config.md: the options table leaves out snapshot',
            ],
            'an environment variable the source does not read' => [
                function (&$d, &$f) { $d['catalog.md'] .= "\nSet `LANGSYS_CACHE_DIR`.\n"; },
                'catalog.md: LANGSYS_CACHE_DIR is not an environment variable the source reads',
            ],
            'an environment variable the table leaves out' => [
                function (&$d, &$f) { $d['init-config.md'] = preg_replace('/^\| `LANGSYS_LOG_LEVEL` \|.*\n/m', '', $d['init-config.md']); },
                'init-config.md: the environment variables table leaves out LANGSYS_LOG_LEVEL',
            ],
            'a command the package does not ship' => [
                function (&$d, &$f) { $d['migration.md'] .= "\n`vendor/bin/langsys-export`\n"; },
                'migration.md: vendor/bin/langsys-export is not a command composer.json ships',
            ],
            'a link to a file the skill does not hold' => [
                function (&$d, &$f) { $d['writing.md'] .= "\n[x](./caching.md)\n"; },
                'writing.md: links ./caching.md, which skill/ does not hold',
            ],
            'a link to a heading that does not exist' => [
                function (&$d, &$f) { $d['primitives.md'] = str_replace('](#opting-out)', '](#opting-in)', $d['primitives.md']); },
                'primitives.md: links primitives.md#opting-in, a heading primitives.md does not have',
            ],
            'a topic file SKILL.md does not link' => [
                function (&$d, &$f) { $d['orphan.md'] = "# Orphan\n"; },
                'orphan.md: SKILL.md does not link it',
            ],
            'a frontmatter name that is not the skill\'s' => [
                function (&$d, &$f) { $d['SKILL.md'] = preg_replace('/^name: .*$/m', 'name: langsys-sdk', $d['SKILL.md']); },
                'SKILL.md: frontmatter name is not langsys-php',
            ],
            'a frontmatter package that is not composer.json\'s' => [
                function (&$d, &$f) { $d['SKILL.md'] = preg_replace('/^  package: .*$/m', '  package: langsys/php-sdk', $d['SKILL.md']); },
                'SKILL.md: frontmatter package is not langsys/langsys-php',
            ],
            'a version the CHANGELOG is not about' => [
                function (&$d, &$f) { $d['SKILL.md'] = preg_replace('/^  version: .*$/m', '  version: 9.9.9', $d['SKILL.md']); },
                'SKILL.md: frontmatter version is 9.9.9, the CHANGELOG\'s newest is ',
            ],
            'a preamble that drifted from the template' => [
                function (&$d, &$f) { $d['SKILL.md'] = str_replace('never skip a step', 'skip what you like', $d['SKILL.md']); },
                'SKILL.md: the preamble span differs from preamble.md',
            ],
            'a preamble that is not the first thing after the title' => [
                function (&$d, &$f) { $d['SKILL.md'] = str_replace('<!-- langsys-skill-preamble:start -->', "An opening line.\n\n<!-- langsys-skill-preamble:start -->", $d['SKILL.md']); },
                'SKILL.md: the preamble is not the first thing after the title',
            ],
            'an extension composer.json requires that SKILL.md leaves out' => [
                function (&$d, &$f) { $f['extensions'][] = 'mbstring'; },
                'SKILL.md: does not name the mbstring extension composer.json requires',
            ],
            'a PHP floor that is not composer.json\'s' => [
                function (&$d, &$f) { $f['php'] = '8.1'; },
                'SKILL.md: does not state the PHP 8.1 floor composer.json requires',
            ],
            'skill/ kept out of the Composer download' => [
                function (&$d, &$f) { $f['export_ignored'] = self::exportIgnored("/skill export-ignore\n"); },
                '.gitattributes: skill is export-ignored, so the package would not ship it',
            ],
            'skill/ kept out by a pattern that names no directory' => [
                function (&$d, &$f) { $f['export_ignored'] = self::exportIgnored("*.md export-ignore\n"); },
                '.gitattributes: skill/SKILL.md is export-ignored, so the package would not ship it',
            ],
            'a bare class name that does not exist' => [
                function (&$d, &$f) { $d['init-config.md'] = str_replace('| `ApiException` |', '| `ApiErrorException` |', $d['init-config.md']); },
                'init-config.md: `ApiErrorException` names no class of this SDK, of PHP or of the skill\'s own examples',
            ],
            'a cache class the SDK does not have' => [
                function (&$d, &$f) { $d['SKILL.md'] = str_replace('`RedisCache`, `NullCache`', '`RedisCache`, `ApcuCache`', $d['SKILL.md']); },
                'SKILL.md: `ApcuCache` names no class of this SDK, of PHP or of the skill\'s own examples',
            ],
            'a command flag the command does not take' => [
                function (&$d, &$f) { $d['migration.md'] = str_replace('--out=catalog', '--output=catalog', $d['migration.md']); },
                'migration.md: --output is not an option of langsys-snapshot',
            ],
            'a request_locale key RequestLocale does not read' => [
                function (&$d, &$f) { $d['locales.md'] = str_replace("'query_param' =>", "'query' =>", $d['locales.md']); },
                'locales.md: \'query\' is not a request_locale key',
            ],
            'a request key resolveRequestLocale() does not read' => [
                function (&$d, &$f) { $d['server-rendering.md'] = str_replace("'accept_language' =>", "'accept-language' =>", $d['server-rendering.md']); },
                'server-rendering.md: \'accept-language\' is not a request key resolveRequestLocale() reads',
            ],
            'a redis key RedisCache does not read' => [
                function (&$d, &$f) { $d['catalog.md'] = str_replace("'prefix' => 'langsys::'", "'key_prefix' => 'langsys::'", $d['catalog.md']); },
                'catalog.md: \'key_prefix\' is not a redis option',
            ],
            'a migration key the migration reader does not read' => [
                function (&$d, &$f) { $d['migration.md'] = str_replace("'format' => 'laravel'", "'type' => 'laravel'", $d['migration.md']); },
                'migration.md: \'type\' is not a migration key',
            ],
            'a flush result key the flush does not return' => [
                function (&$d, &$f) { $d['writing.md'] = str_replace("'retained' => 0,", "'kept' => 0,", $d['writing.md']); },
                'writing.md: \'kept\' is not a key of flushPendingRegistrations()\'s result',
            ],
            'a heading that is only a comment in a code block' => [
                function (&$d, &$f) {
                    $d['primitives.md'] = str_replace('](#opting-out)', '](#opting-in)', $d['primitives.md']) . "\n```bash\n# Opting in\n```\n";
                },
                'primitives.md: links primitives.md#opting-in, a heading primitives.md does not have',
            ],
        ];
    }

    /**
     * @dataProvider plantedDefects
     */
    public function testEachCheckReportsTheDefectItExistsToCatch(callable $plant, string $expected): void
    {
        $docs = self::docs();
        $facts = self::facts();
        $plant($docs, $facts);

        $found = self::problems($docs, $facts);

        $this->assertNotEmpty(array_filter($found, function ($problem) use ($expected) {
            return strpos($problem, $expected) === 0;
        }), "Expected a problem starting \"$expected\"; found:\n" . implode("\n", $found));
    }

    /** @return array<string, string> file name => contents */
    private static function docs(): array
    {
        $docs = [];
        foreach (glob(dirname(__DIR__, 2) . '/skill/*.md') as $path) {
            $docs[basename($path)] = file_get_contents($path);
        }
        ksort($docs);

        return $docs;
    }

    private static function facts(): array
    {
        $root = dirname(__DIR__, 2);
        $composer = json_decode(file_get_contents("$root/composer.json"), true);

        preg_match('/^## \[(\d+\.\d+\.\d+)\]/m', file_get_contents("$root/CHANGELOG.md"), $version);

        $classes = [];
        $methods = [];
        $properties = [];
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator("$root/src", \FilesystemIterator::SKIP_DOTS));
        foreach ($files as $file) {
            if (substr($file->getFilename(), -4) !== '.php') {
                continue;
            }
            $class = 'Langsys\\SDK\\' . str_replace('/', '\\', substr($file->getPathname(), strlen("$root/src/"), -4));
            if (!class_exists($class) && !interface_exists($class) && !trait_exists($class)) {
                continue;
            }
            $reflection = new \ReflectionClass($class);
            $classes[$reflection->getShortName()] = $class;
            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
                $methods[$class][strtolower($method->getName())] = true;
            }
            foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
                $properties[$class][$property->getName()] = true;
            }
        }

        $options = [];
        foreach (['src/Config.php', 'src/Client.php'] as $source) {
            preg_match_all('/\$options\[\'([a-z_]+)\'\]/', file_get_contents("$root/$source"), $m);
            $options = array_merge($options, $m[1]);
        }
        $options = array_values(array_diff(array_unique($options), self::NOT_CONSTRUCTOR_OPTIONS));

        preg_match_all('/getEnv\(\'(LANGSYS_[A-Z_]+)\'/', file_get_contents("$root/src/Config.php"), $env);

        $extensions = [];
        foreach (array_keys($composer['require']) as $requirement) {
            if (strpos($requirement, 'ext-') === 0) {
                $extensions[] = substr($requirement, 4);
            }
        }
        preg_match('/(\d+\.\d+)/', $composer['require']['php'], $php);

        $commands = [];
        foreach ($composer['bin'] ?? [] as $bin) {
            preg_match('/getopt\(\'([a-z:]*)\', \[([^\]]*)\]\)/', file_get_contents("$root/$bin"), $m);
            preg_match_all("/'([a-z-]+):*'/", $m[2], $long);
            $commands[basename($bin)] = array_merge(str_split(str_replace(':', '', $m[1]) ?: ''), $long[1]);
        }

        $reads = function ($file, $variables) use ($root) {
            preg_match_all('/\$(?:' . $variables . ')\[\'([a-z_]+)\'\]/', file_get_contents("$root/$file"), $m);
            return array_values(array_unique($m[1]));
        };
        preg_match_all("/'(\\w+)' =>/", preg_match('/@return array \[(.*)\]/', (string) (new \ReflectionMethod('Langsys\SDK\Client', 'flushPendingRegistrations'))->getDocComment(), $doc) ? $doc[1] : '', $flush);

        return [
            'package' => $composer['name'],
            'version' => $version[1] ?? null,
            'classes' => $classes,
            'methods' => $methods,
            'properties' => $properties,
            'options' => $options,
            'env' => array_values(array_unique($env[1])),
            'bins' => array_map('basename', $composer['bin'] ?? []),
            'extensions' => $extensions,
            'php' => $php[1],
            'gitattributes' => file_get_contents("$root/.gitattributes"),
            'export_ignored' => self::exportIgnored(),
            'command_options' => $commands,
            'request_locale_keys' => array_merge(array_keys(\Langsys\SDK\Locale\RequestLocale::DEFAULTS), self::NOT_CONSTRUCTOR_OPTIONS),
            'request_keys' => $reads('src/Locale/RequestLocale.php', 'request'),
            'redis_keys' => $reads('src/Cache/RedisCache.php', 'options|redis'),
            'migration_keys' => $reads('src/Migration/LegacyKeys.php', 'config|entry|namespace|tiers'),
            'flush_keys' => $flush[1],
        ];
    }

    /**
     * The paths of skill/ that `git archive` leaves out, as git reads the
     * repository's attributes plus $extra, given as a global attributes file.
     */
    public static function exportIgnored(string $extra = ''): array
    {
        $root = dirname(__DIR__, 2);
        $paths = array_merge(['skill'], array_map(function ($path) use ($root) {
            return substr($path, strlen($root) + 1);
        }, glob("$root/skill/*.md")));

        $config = '';
        if ($extra !== '') {
            $file = tempnam(sys_get_temp_dir(), 'ls-attr');
            file_put_contents($file, $extra);
            $config = '-c core.attributesFile=' . escapeshellarg($file) . ' ';
        }
        $output = shell_exec('git -C ' . escapeshellarg($root) . ' ' . $config . 'check-attr export-ignore -- ' . implode(' ', array_map('escapeshellarg', $paths)));
        if (isset($file)) {
            unlink($file);
        }

        $ignored = [];
        foreach (explode("\n", trim((string) $output)) as $line) {
            if (preg_match('/^(.*): export-ignore: set$/', $line, $m)) {
                $ignored[] = $m[1];
            }
        }

        return $ignored;
    }

    /**
     * @param array<string, string> $docs
     * @return string[] one line per problem, each starting with the file it is in
     */
    private static function problems(array $docs, array $facts): array
    {
        $found = [];
        $allMethods = [];
        foreach ($facts['methods'] as $names) {
            $allMethods += $names;
        }
        $declared = [];
        foreach ($docs as $text) {
            preg_match_all('/\b(?:class|enum|interface)\s+([A-Z]\w*)/', $text, $m);
            $declared = array_merge($declared, $m[1]);
        }

        foreach ($docs as $file => $text) {
            // Calls and properties on the objects the examples hold.
            preg_match_all('/\$(\w+)->(\w+)(\()?/', $text, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                if (!isset(self::RECEIVERS[$hit[1]])) {
                    continue;
                }
                $class = self::RECEIVERS[$hit[1]];
                if (isset($hit[3])) {
                    if (!isset($facts['methods'][$class][strtolower($hit[2])])) {
                        $found[] = "$file: \${$hit[1]}->{$hit[2]}() is not a public method of $class";
                    }
                } elseif (!isset($facts['properties'][$class][$hit[2]])) {
                    $found[] = "$file: \${$hit[1]}->{$hit[2]} is not a public property of $class";
                }
            }

            // Class::method() on an SDK class.
            preg_match_all('/\b([A-Z]\w*)::(\w+)\(/', $text, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                if (!isset($facts['classes'][$hit[1]])) {
                    continue;
                }
                $class = $facts['classes'][$hit[1]];
                if (!isset($facts['methods'][$class][strtolower($hit[2])])) {
                    $found[] = "$file: {$hit[1]}::{$hit[2]}() is not a public method of $class";
                }
            }

            // A bare name() quoted inline: an SDK method, or PHP's own function.
            preg_match_all('/`([a-z_]\w*)\(/', $text, $m);
            foreach (array_unique($m[1]) as $name) {
                if (!isset($allMethods[strtolower($name)]) && !function_exists($name) && !in_array($name, self::FOREIGN_CALLS, true)) {
                    $found[] = "$file: $name() is not a public method of any SDK class";
                }
            }

            // A class named on its own, inline: one with a hump, as SDK classes are.
            preg_match_all('/`\\\\?([A-Z][a-z0-9]+(?:[A-Z][A-Za-z0-9]*)+)`/', $text, $m);
            foreach (array_unique($m[1]) as $name) {
                if (!isset($facts['classes'][$name]) && !in_array($name, $declared, true) && !class_exists($name) && !interface_exists($name)) {
                    $found[] = "$file: `$name` names no class of this SDK, of PHP or of the skill's own examples";
                }
            }

            // Command flags: a command's own on its line, any command's elsewhere.
            $anyOption = array_merge(...array_values($facts['command_options']));
            foreach (explode("\n", $text) as $line) {
                $command = preg_match('#vendor/bin/([a-z][a-z-]*)#', $line, $c) ? $c[1] : null;
                if ($command !== null && !isset($facts['command_options'][$command])) {
                    continue;
                }
                $pattern = $command !== null ? '/(?<![\w-])--?([a-z][a-z-]*)/' : '/`--([a-z][a-z-]*)/';
                preg_match_all($pattern, $command !== null ? preg_replace('#vendor/bin/[a-z-]+#', '', $line) : $line, $m);
                foreach (array_unique($m[1]) as $flag) {
                    $accepted = $command !== null ? $facts['command_options'][$command] : $anyOption;
                    if (!in_array($flag, $accepted, true)) {
                        $found[] = "$file: --$flag is not an option of " . ($command !== null ? $command : 'either command');
                    }
                }
            }

            // Keys inside the arrays the SDK reads.
            foreach (self::KEYED_LITERALS as $anchor => $kind) {
                $offset = 0;
                while (preg_match($anchor, $text, $a, PREG_OFFSET_CAPTURE, $offset)) {
                    $offset = $a[0][1] + strlen($a[0][0]);
                    list($keys, $end) = self::literalKeys($text, $offset - 1);
                    $found = array_merge($found, self::badKeys($file, $kind, $keys, $facts));
                    if ($kind === 'request' && preg_match('/\G\s*,\s*\[/', $text, $b, 0, $end)) {
                        list($keys) = self::literalKeys($text, $end + strlen($b[0]) - 1);
                        $found = array_merge($found, self::badKeys($file, 'request_locale', $keys, $facts));
                    }
                }
            }

            // Fully qualified names.
            // A namespace, as in `Langsys\SDK\Exception\*`, counts when a class is in it.
            preg_match_all('/Langsys\\\\SDK\\\\[A-Za-z\\\\]*[A-Za-z]/', $text, $m);
            foreach (array_unique($m[0]) as $name) {
                $isNamespace = array_filter($facts['classes'], function ($class) use ($name) {
                    return strpos($class, $name . '\\') === 0;
                });
                if (!class_exists($name) && !interface_exists($name) && !$isNamespace) {
                    $found[] = "$file: $name is not a class or interface of this SDK";
                }
            }

            // Objects built with new.
            preg_match_all('/\bnew\s+\\\\?(?:Langsys\\\\SDK\\\\[A-Za-z\\\\]*\\\\)?([A-Z]\w*)\(/', $text, $m);
            foreach (array_unique($m[1]) as $name) {
                if (!isset($facts['classes'][$name]) && !in_array($name, $declared, true)) {
                    $found[] = "$file: new $name() names no class of this SDK or of the skill's own examples";
                }
            }

            // Environment variables.
            preg_match_all('/\bLANGSYS_[A-Z_]+\b/', $text, $m);
            foreach (array_unique($m[0]) as $name) {
                if (!in_array($name, $facts['env'], true)) {
                    $found[] = "$file: $name is not an environment variable the source reads";
                }
            }

            // Commands.
            preg_match_all('#vendor/bin/([a-z][a-z-]*)#', $text, $m);
            foreach (array_unique($m[1]) as $name) {
                if (!in_array($name, $facts['bins'], true)) {
                    $found[] = "$file: vendor/bin/$name is not a command composer.json ships";
                }
            }

            // Links within the skill, and the headings they name.
            preg_match_all('/\]\((?:\.\/)?([\w-]+\.md)?(?:#([\w-]+))?\)/', $text, $m, PREG_SET_ORDER);
            foreach ($m as $hit) {
                $target = ($hit[1] ?? '') !== '' ? $hit[1] : $file;
                if (!isset($docs[$target])) {
                    $found[] = "$file: links ./$target, which skill/ does not hold";
                    continue;
                }
                if (isset($hit[2]) && !in_array($hit[2], self::anchors($docs[$target]), true)) {
                    $found[] = "$file: links $target#{$hit[2]}, a heading $target does not have";
                }
            }

            if ($file !== 'SKILL.md' && $file !== 'preamble.md' && strpos($docs['SKILL.md'] ?? '', "](./$file)") === false) {
                $found[] = "$file: SKILL.md does not link it";
            }
        }

        // The options and environment variables tables are complete and true.
        $config = $docs['init-config.md'] ?? '';
        $options = preg_match('/^## Options\n(.*?)(?=^## )/ms', $config, $section) ? $section[1] : '';
        preg_match_all('/^\| `([a-z_]+)` \|/m', $options, $m);
        foreach (array_diff($m[1], $facts['options']) as $name) {
            $found[] = "init-config.md: the option $name is not read by the source";
        }
        foreach (array_diff($facts['options'], $m[1]) as $name) {
            $found[] = "init-config.md: the options table leaves out $name";
        }
        $variables = preg_match('/^## Environment variables\n(.*?)(?=^## )/ms', $config, $section) ? $section[1] : '';
        preg_match_all('/^\| `(LANGSYS_[A-Z_]+)` \|/m', $variables, $m);
        foreach (array_diff($facts['env'], $m[1]) as $name) {
            $found[] = "init-config.md: the environment variables table leaves out $name";
        }

        // The frontmatter, as composer.json and the CHANGELOG hold them.
        $skill = $docs['SKILL.md'] ?? '';
        $front = preg_match('/^---\n(.*?)\n---\n/s', $skill, $m) ? $m[1] : '';
        if (!preg_match('/^name: langsys-php$/m', $front)) {
            $found[] = 'SKILL.md: frontmatter name is not langsys-php';
        }
        if (!preg_match('/^  package: ' . preg_quote($facts['package'], '/') . '$/m', $front)) {
            $found[] = "SKILL.md: frontmatter package is not {$facts['package']}";
        }
        $version = preg_match('/^  version: (\S+)$/m', $front, $m) ? $m[1] : null;
        if ($version !== $facts['version']) {
            $found[] = "SKILL.md: frontmatter version is $version, the CHANGELOG's newest is {$facts['version']}";
        }

        // The preamble, word for word, first after the title.
        if (!preg_match(self::PREAMBLE, $skill, $span)) {
            $found[] = 'SKILL.md: no langsys-skill-preamble span';
        } elseif (trim($span[1]) !== trim($docs['preamble.md'] ?? '')) {
            $found[] = 'SKILL.md: the preamble span differs from preamble.md';
        }
        $end = strpos($skill, "\n---\n", 4);
        $body = ltrim($end === false ? $skill : substr($skill, $end + 5));
        if (strpos(ltrim(preg_replace('/^# [^\n]*\n+/', '', $body)), '<!-- langsys-skill-preamble:start -->') !== 0) {
            $found[] = 'SKILL.md: the preamble is not the first thing after the title';
        }

        // What it takes to install.
        foreach ($facts['extensions'] as $extension) {
            if (strpos($skill, "`$extension`") === false) {
                $found[] = "SKILL.md: does not name the $extension extension composer.json requires";
            }
        }
        if (strpos($skill, "PHP {$facts['php']} or later") === false) {
            $found[] = "SKILL.md: does not state the PHP {$facts['php']} floor composer.json requires";
        }

        foreach ($facts['export_ignored'] as $path) {
            $found[] = ".gitattributes: $path is export-ignored, so the package would not ship it";
        }

        return $found;
    }

    /**
     * The keys of the PHP array literal whose `[` is at $open, each with its
     * depth and the key that holds its array, and the offset past its `]`.
     *
     * @return array{0: array<int, array{key: string, depth: int, parent: ?string}>, 1: int}
     */
    private static function literalKeys(string $text, int $open): array
    {
        $keys = [];
        $parents = [];
        $last = null;
        $length = strlen($text);
        for ($i = $open; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === '[') {
                $parents[] = $last;
                $last = null;
            } elseif ($char === ']') {
                $last = array_pop($parents);
                if ($parents === []) {
                    return [$keys, $i + 1];
                }
            } elseif ($char === "'" || $char === '"') {
                $close = $i + 1;
                while ($close < $length && $text[$close] !== $char) {
                    $close += $text[$close] === '\\' ? 2 : 1;
                }
                if (preg_match('/\G\s*=>/', $text, $m, 0, $close + 1)) {
                    $last = substr($text, $i + 1, $close - $i - 1);
                    $keys[] = ['key' => $last, 'depth' => count($parents), 'parent' => end($parents) ?: null];
                }
                $i = $close;
            }
        }

        return [$keys, $length];
    }

    /** The keys of one literal that the SDK doesn't read where the skill writes them. */
    private static function badKeys(string $file, string $kind, array $keys, array $facts): array
    {
        $found = [];
        foreach ($keys as $key) {
            $name = $key['key'];
            switch ($kind) {
                case 'request_locale':
                    $bad = $key['depth'] === 1 && !in_array($name, $facts['request_locale_keys'], true);
                    $why = 'is not a request_locale key';
                    break;
                case 'request':
                    $bad = $key['depth'] === 1 && !in_array($name, $facts['request_keys'], true);
                    $why = 'is not a request key resolveRequestLocale() reads';
                    break;
                case 'redis':
                    $bad = $key['depth'] === 1 && !in_array($name, $facts['redis_keys'], true);
                    $why = 'is not a redis option';
                    break;
                case 'flush':
                    $bad = $key['depth'] === 1 && !in_array($name, $facts['flush_keys'], true);
                    $why = "is not a key of flushPendingRegistrations()'s result";
                    break;
                default:
                    // A namespace's or a locale's own name is the reader's to choose.
                    $named = $key['parent'] === 'namespaces' || ($key['depth'] === 1 && $kind !== 'migration');
                    $bad = !$named && !in_array($name, $facts['migration_keys'], true);
                    $why = 'is not a migration key';
            }
            if ($bad) {
                $found[] = "$file: '$name' $why";
            }
        }

        return $found;
    }

    /** The anchors GitHub gives a file's headings. */
    private static function anchors(string $text): array
    {
        // A `#` line in a fenced block is a comment in its code, not a heading.
        preg_match_all('/^#{1,6} (.+)$/m', preg_replace('/^```.*?^```/ms', '', $text), $m);

        return array_map(function ($heading) {
            return str_replace(' ', '-', preg_replace('/[^a-z0-9 _-]/', '', strtolower($heading)));
        }, $m[1]);
    }
}
