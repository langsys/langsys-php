<?php

namespace Langsys\SDK\Migration;

use Langsys\SDK\Exception\LangsysException;

/**
 * Resolves legacy i18n keys against an app's kept source-language files
 * (MIG-2, MIG-5, MIG-7).
 *
 * Files come in two tiers. `files` are the app's own: the first file that holds
 * a key answers it, and a key held by more than one of them is reported.
 * `fallback_files` answer only what `files` do not - a framework's or a
 * package's bundled strings - and a key present in both tiers is an override,
 * never a duplicate. `namespaces` map a package prefix (`courier::`) to the same
 * two tiers.
 *
 * A JSON file is read by the key as written first, then by dotted path. A PHP
 * array file is a group: its basename is the key's first segment and its
 * namespace. The namespace becomes the category.
 *
 * Every file carries a format - laravel, vue-i18n, i18next or plain - which
 * decides what its values' plurals mean (MIG-4, MIG-7). An entry is a path, or
 * ['path' => ..., 'format' => ...]; with none declared a PHP array file is
 * laravel and a JSON file is plain.
 *
 * Nothing is read until a key is first looked up.
 */
final class LegacyKeys
{
    /** @var array */
    private $config;

    /** @var array<string, array|null> path => decoded data, null when unreadable */
    private $data = [];

    /** @var array[] */
    private $unreadable = [];

    /**
     * @param array $config ['files' => [...], 'fallback_files' => [...], 'namespaces' => [ns => [...] | ['files' => [...], 'fallback_files' => [...]]]]
     * @throws LangsysException When a configured file is in a format this SDK does not read (MIG-7)
     */
    public function __construct(array $config)
    {
        $this->config = $config;
        self::refuseUnreadFormats($config);
    }

    /**
     * MIG-7: a file is read in the formats this SDK's ecosystem uses - laravel
     * and plain - and vue-i18n and i18next besides. Any other format is refused
     * when the configuration is loaded, naming the format and the file, never
     * read as something it is not. A `.mo` is refused for every format: it is
     * the compiled artifact of a `.po`, and the source file is the one to name.
     * Reads no file.
     *
     * @param array $config
     * @return void
     * @throws LangsysException
     */
    public static function refuseUnreadFormats(array $config)
    {
        $entries = [];
        foreach (['files', 'fallback_files'] as $tier) {
            $entries = array_merge($entries, isset($config[$tier]) && is_array($config[$tier]) ? array_values($config[$tier]) : []);
        }
        foreach (isset($config['namespaces']) && is_array($config['namespaces']) ? $config['namespaces'] : [] as $namespace) {
            $tiers = is_array($namespace) && (isset($namespace['files']) || isset($namespace['fallback_files'])) ? $namespace : ['files' => $namespace];
            foreach (['files', 'fallback_files'] as $tier) {
                $entries = array_merge($entries, isset($tiers[$tier]) && is_array($tiers[$tier]) ? array_values($tiers[$tier]) : []);
            }
        }

        foreach ($entries as $entry) {
            $path = is_array($entry) ? (isset($entry['path']) ? (string) $entry['path'] : '') : (string) $entry;
            $format = self::formatOf($entry);

            if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'mo') {
                throw new LangsysException(sprintf(
                    'The migration file %s is a compiled gettext catalog; configure its source, %s, instead.',
                    $path,
                    substr($path, 0, -3) . '.po'
                ));
            }

            if (!in_array($format, LegacyValue::FORMATS, true)) {
                throw new LangsysException(sprintf(
                    'The migration file %s is in the %s format, which this SDK does not read; it reads %s.',
                    $path,
                    $format,
                    implode(', ', LegacyValue::FORMATS)
                ));
            }
        }
    }

    /**
     * A file entry's format: the one it declares, else its type's - a PHP
     * array is laravel, YAML rails-i18n, a .po or .mo gettext, and anything
     * else, JSON included, plain.
     *
     * @param string|array $entry
     * @return string
     */
    private static function formatOf($entry)
    {
        if (is_array($entry) && isset($entry['format'])) {
            return (string) $entry['format'];
        }

        $path = is_array($entry) ? (isset($entry['path']) ? (string) $entry['path'] : '') : (string) $entry;
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $byType = ['php' => 'laravel', 'yml' => 'rails-i18n', 'yaml' => 'rails-i18n', 'po' => 'gettext', 'mo' => 'gettext'];

        return isset($byType[$extension]) ? $byType[$extension] : 'plain';
    }

    /**
     * The group a file's keys sit under: a PHP array file's basename, or the
     * namespace a JSON file declares. Null when its keys are its own.
     *
     * @param array $entry
     * @return string|null
     */
    private function group(array $entry)
    {
        if ($this->isPhp($entry['path'])) {
            return basename($entry['path'], '.php');
        }

        return $entry['namespace'];
    }

    /**
     * @param string $key
     * @return bool
     */
    public static function isPackageKey($key)
    {
        return is_string($key) && strpos($key, '::') !== false;
    }

    /**
     * The key's source value, converted, or null when no file holds it.
     *
     * @param string $key
     * @return array{phrase: string, category: string|null, key: string, file: string, recognised: bool, issue: string|null}|null
     */
    public function resolve($key)
    {
        if (!is_string($key) || $key === '') {
            return null;
        }

        if (self::isPackageKey($key)) {
            list($namespace, $rest) = explode('::', $key, 2);
            $tiers = $this->namespaceTiers($namespace);

            return $tiers === null ? null : $this->resolveIn($tiers, $rest, $key);
        }

        return $this->resolveIn([$this->entries('files'), $this->entries('fallback_files')], $key, $key);
    }

    /**
     * Keys held by more than one of an app's own files, with those files.
     *
     * @return array<string, string[]>
     */
    public function duplicates()
    {
        $duplicates = [];

        foreach ($this->ownTiers() as $prefix => $files) {
            $seen = [];

            foreach ($files as $entry) {
                foreach (array_keys($this->leaves($entry)) as $leaf) {
                    $seen[$prefix . $leaf][] = $entry['path'];
                }
            }

            foreach ($seen as $key => $paths) {
                if (count($paths) > 1) {
                    $duplicates[$key] = $paths;
                }
            }
        }

        return $duplicates;
    }

    /**
     * What cannot be migrated as it stands: an unreadable file, or a value in an
     * app's own files that converts to nothing recognised.
     *
     * @return array<int, array{file: string, key: string|null, issue: string, fix: string}>
     */
    public function problems()
    {
        $problems = [];

        foreach ($this->ownTiers() as $prefix => $files) {
            foreach ($files as $entry) {
                foreach ($this->leaves($entry) as $leaf => $value) {
                    $converted = is_array($value) ? LegacyValue::fromPluralForms($value) : LegacyValue::convert($value, $entry['format']);

                    if (!$converted['recognised']) {
                        $problems[] = ['file' => $entry['path'], 'key' => $prefix . $leaf, 'issue' => $converted['issue'], 'fix' => 'rewrite the value as Langsys source text, or declare the file\'s format'];
                    }
                }
            }
        }

        foreach ($this->unreadable as $path) {
            $problems[] = ['file' => $path, 'key' => null, 'issue' => 'cannot be read as a JSON object or a PHP array', 'fix' => 'check the path in the migration configuration'];
        }

        return $problems;
    }

    /**
     * @return string[]
     */
    public function loadedFiles()
    {
        return array_keys(array_filter($this->data, function ($data) {
            return $data !== null;
        }));
    }

    /**
     * @param array<int, string[]> $tiers
     * @param string $key The key within its namespace
     * @param string $fullKey The key as the caller wrote it
     * @return array|null
     */
    private function resolveIn(array $tiers, $key, $fullKey)
    {
        foreach ($tiers as $files) {
            foreach ($files as $entry) {
                $found = $this->lookup($entry, $key);

                if ($found === null) {
                    continue;
                }

                list($value, $category) = $found;
                $converted = is_array($value) ? LegacyValue::fromPluralForms($value) : LegacyValue::convert($value, $entry['format']);

                return [
                    'phrase' => $converted['text'],
                    'category' => $category,
                    'key' => $fullKey,
                    'file' => $entry['path'],
                    'recognised' => $converted['recognised'],
                    'issue' => $converted['issue'],
                ];
            }
        }

        return null;
    }

    /**
     * @param array $entry ['path', 'format', 'invalid']
     * @param string $key
     * @return array|null [string|array $value, string|null $category]
     */
    private function lookup(array $entry, $key)
    {
        $path = $entry['path'];
        $pairs = $entry['format'] === 'i18next';
        $data = $this->load($path);

        if ($data === null) {
            return null;
        }

        $group = $this->group($entry);

        if ($group !== null) {
            if (strpos($key, $group . '.') !== 0) {
                return null;
            }

            $value = self::valueAt($data, explode('.', substr($key, strlen($group) + 1)), $pairs);

            return $value === null ? null : [$value, $group];
        }

        // In an i18next file a root key pairs too (`items_one` at the top), and
        // its pair outranks its bare string: `item` beside `item_plural` is the
        // plural's one form, not the phrase.
        if ($pairs && strpos($key, '.') === false) {
            $forms = self::valueAt($data, [$key], true);
            if (is_array($forms)) {
                return [$forms, null];
            }
        }

        if (array_key_exists($key, $data) && is_string($data[$key])) {
            return [$data[$key], self::looksLikeAPath($key) ? strstr($key, '.', true) : null];
        }

        if (strpos($key, '.') === false) {
            return null;
        }

        $value = self::valueAt($data, explode('.', $key), $pairs);

        return $value === null ? null : [$value, strstr($key, '.', true)];
    }

    /**
     * A string at a path - or, in an i18next file, plural forms keyed by CLDR
     * category when the key is suffix-paired.
     *
     * @param array $data
     * @param string[] $segments
     * @param bool $pairs Whether suffix-paired keys pair (i18next only)
     * @return string|array|null
     */
    private static function valueAt(array $data, array $segments, $pairs)
    {
        $last = array_pop($segments);
        $container = $data;

        foreach ($segments as $segment) {
            if (!is_array($container) || !array_key_exists($segment, $container)) {
                return null;
            }

            $container = $container[$segment];
        }

        if (!is_array($container)) {
            return null;
        }

        if (!$pairs) {
            return (isset($container[$last]) && is_string($container[$last])) ? $container[$last] : null;
        }

        $forms = [];

        foreach (LegacyValue::CATEGORIES as $category) {
            if (isset($container[$last . '_' . $category]) && is_string($container[$last . '_' . $category])) {
                $forms[$category] = $container[$last . '_' . $category];
            }
        }

        if ($forms !== []) {
            return $forms;
        }

        if (isset($container[$last], $container[$last . '_plural']) && is_string($container[$last]) && is_string($container[$last . '_plural'])) {
            return ['one' => $container[$last], 'other' => $container[$last . '_plural']];
        }

        return (isset($container[$last]) && is_string($container[$last])) ? $container[$last] : null;
    }

    /**
     * Every leaf of a file by its full key: strings, and - in an i18next file -
     * plural forms grouped under their base key.
     *
     * @param array $entry
     * @return array<string, string|array>
     */
    private function leaves(array $entry)
    {
        $data = $this->load($entry['path']);

        if ($data === null) {
            return [];
        }

        $group = $this->group($entry);
        $prefix = $group === null ? '' : $group . '.';
        $out = [];
        $this->collectLeaves($data, $prefix, $out, $entry['format'] === 'i18next');

        return $out;
    }

    /**
     * @param array $node
     * @param string $prefix
     * @param array $out
     * @param bool $pairs
     * @return void
     */
    private function collectLeaves(array $node, $prefix, array &$out, $pairs)
    {
        $suffixes = array_merge(array_map(function ($c) {
            return '_' . $c;
        }, LegacyValue::CATEGORIES), ['_plural']);

        foreach ($node as $key => $value) {
            $key = (string) $key;

            if (is_array($value)) {
                $this->collectLeaves($value, $prefix . $key . '.', $out, $pairs);
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            foreach ($pairs ? $suffixes : [] as $suffix) {
                if (substr($key, -strlen($suffix)) === $suffix && strlen($key) > strlen($suffix)) {
                    $base = substr($key, 0, -strlen($suffix));
                    $forms = self::valueAt($node, [$base], true);

                    if (is_array($forms)) {
                        $out[$prefix . $base] = $forms;
                        continue 2;
                    }
                }
            }

            if (!isset($out[$prefix . $key])) {
                $out[$prefix . $key] = $value;
            }
        }
    }

    /**
     * The app's own tiers, by key prefix: top-level `files`, and each namespace's.
     *
     * @return array<string, array[]>
     */
    private function ownTiers()
    {
        $tiers = ['' => $this->entries('files')];

        foreach (array_keys($this->namespaces()) as $namespace) {
            $tiers[$namespace . '::'] = $this->namespaceTiers($namespace)[0];
        }

        return $tiers;
    }

    /**
     * @param string $namespace
     * @return array<int, array[]>|null [files, fallback_files]
     */
    private function namespaceTiers($namespace)
    {
        $namespaces = $this->namespaces();

        if (!isset($namespaces[$namespace])) {
            return null;
        }

        $entry = $namespaces[$namespace];

        if (isset($entry['files']) || isset($entry['fallback_files'])) {
            return [
                $this->normalize(isset($entry['files']) ? (array) $entry['files'] : []),
                $this->normalize(isset($entry['fallback_files']) ? (array) $entry['fallback_files'] : []),
            ];
        }

        return [$this->normalize((array) $entry), []];
    }

    /**
     * @return array
     */
    private function namespaces()
    {
        return (isset($this->config['namespaces']) && is_array($this->config['namespaces'])) ? $this->config['namespaces'] : [];
    }

    /**
     * @param string $tier
     * @return array[]
     */
    private function entries($tier)
    {
        return $this->normalize(isset($this->config[$tier]) ? (array) $this->config[$tier] : []);
    }

    /**
     * File entries as ['path', 'format', 'invalid']: a bare path takes its type's
     * default format - laravel for a PHP array, plain for JSON - and an unknown
     * declared format reads as plain and is reported.
     *
     * @param array $entries
     * @return array[]
     */
    private function normalize(array $entries)
    {
        $out = [];

        foreach (array_values($entries) as $entry) {
            $path = is_array($entry) ? (isset($entry['path']) ? (string) $entry['path'] : '') : (string) $entry;

            $out[] = [
                'path' => $path,
                'format' => self::formatOf($entry),
                'namespace' => (is_array($entry) && isset($entry['namespace']) && $entry['namespace'] !== '') ? (string) $entry['namespace'] : null,
            ];
        }

        return $out;
    }

    /**
     * @param string $path
     * @return array|null
     */
    private function load($path)
    {
        if (array_key_exists($path, $this->data)) {
            return $this->data[$path];
        }

        $data = null;

        if (is_file($path) && is_readable($path)) {
            if ($this->isPhp($path)) {
                $data = (static function ($file) {
                    return include $file;
                })($path);
            } else {
                $data = json_decode((string) file_get_contents($path), true);
            }
        }

        if (!is_array($data)) {
            $data = null;
            $this->unreadable[] = $path;
        }

        return $this->data[$path] = $data;
    }

    /**
     * @param string $path
     * @return bool
     */
    private function isPhp($path)
    {
        return strtolower(pathinfo($path, PATHINFO_EXTENSION)) === 'php';
    }

    /**
     * A flat JSON key names a namespace only when it is shaped like a key path;
     * a sentence used as its own key (`Welcome back.`) has none.
     *
     * @param string $key
     * @return bool
     */
    private static function looksLikeAPath($key)
    {
        return preg_match('/^[A-Za-z0-9_-]+(\.[A-Za-z0-9_-]+)+$/', $key) === 1;
    }
}
