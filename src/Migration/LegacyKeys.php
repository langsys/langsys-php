<?php

namespace Langsys\SDK\Migration;

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
     */
    public function __construct(array $config)
    {
        $this->config = $config;
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

        return $this->resolveIn([$this->paths('files'), $this->paths('fallback_files')], $key, $key);
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

            foreach ($files as $path) {
                foreach (array_keys($this->leaves($path)) as $leaf) {
                    $seen[$prefix . $leaf][] = $path;
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
            foreach ($files as $path) {
                foreach ($this->leaves($path) as $leaf => $value) {
                    $converted = is_array($value) ? LegacyValue::fromPluralForms($value) : LegacyValue::convert($value);

                    if (!$converted['recognised']) {
                        $problems[] = ['file' => $path, 'key' => $prefix . $leaf, 'issue' => $converted['issue'], 'fix' => 'rewrite the value as Langsys source text'];
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
            foreach ($files as $path) {
                $found = $this->lookup($path, $key);

                if ($found === null) {
                    continue;
                }

                list($value, $category) = $found;
                $converted = is_array($value) ? LegacyValue::fromPluralForms($value) : LegacyValue::convert($value);

                return [
                    'phrase' => $converted['text'],
                    'category' => $category,
                    'key' => $fullKey,
                    'file' => $path,
                    'recognised' => $converted['recognised'],
                    'issue' => $converted['issue'],
                ];
            }
        }

        return null;
    }

    /**
     * @param string $path
     * @param string $key
     * @return array|null [string|array $value, string|null $category]
     */
    private function lookup($path, $key)
    {
        $data = $this->load($path);

        if ($data === null) {
            return null;
        }

        if ($this->isPhp($path)) {
            $group = basename($path, '.php');

            if (strpos($key, $group . '.') !== 0) {
                return null;
            }

            $value = self::valueAt($data, explode('.', substr($key, strlen($group) + 1)));

            return $value === null ? null : [$value, $group];
        }

        if (array_key_exists($key, $data) && is_string($data[$key])) {
            return [$data[$key], self::looksLikeAPath($key) ? strstr($key, '.', true) : null];
        }

        if (strpos($key, '.') === false) {
            return null;
        }

        $value = self::valueAt($data, explode('.', $key));

        return $value === null ? null : [$value, strstr($key, '.', true)];
    }

    /**
     * A string, or plural forms keyed by CLDR category, at a path.
     *
     * @param array $data
     * @param string[] $segments
     * @return string|array|null
     */
    private static function valueAt(array $data, array $segments)
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
     * Every leaf of a file by its full key: strings, and plural forms grouped
     * under their base key.
     *
     * @param string $path
     * @return array<string, string|array>
     */
    private function leaves($path)
    {
        $data = $this->load($path);

        if ($data === null) {
            return [];
        }

        $prefix = $this->isPhp($path) ? basename($path, '.php') . '.' : '';
        $out = [];
        $this->collectLeaves($data, $prefix, $out);

        return $out;
    }

    /**
     * @param array $node
     * @param string $prefix
     * @param array $out
     * @return void
     */
    private function collectLeaves(array $node, $prefix, array &$out)
    {
        $suffixes = array_merge(array_map(function ($c) {
            return '_' . $c;
        }, LegacyValue::CATEGORIES), ['_plural']);

        foreach ($node as $key => $value) {
            $key = (string) $key;

            if (is_array($value)) {
                $this->collectLeaves($value, $prefix . $key . '.', $out);
                continue;
            }

            if (!is_string($value)) {
                continue;
            }

            foreach ($suffixes as $suffix) {
                if (substr($key, -strlen($suffix)) === $suffix && strlen($key) > strlen($suffix)) {
                    $base = substr($key, 0, -strlen($suffix));
                    $forms = self::valueAt($node, [$base]);

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
     * @return array<string, string[]>
     */
    private function ownTiers()
    {
        $tiers = ['' => $this->paths('files')];

        foreach (array_keys($this->namespaces()) as $namespace) {
            $tiers[$namespace . '::'] = $this->namespaceTiers($namespace)[0];
        }

        return $tiers;
    }

    /**
     * @param string $namespace
     * @return array<int, string[]>|null [files, fallback_files]
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
                isset($entry['files']) ? array_values((array) $entry['files']) : [],
                isset($entry['fallback_files']) ? array_values((array) $entry['fallback_files']) : [],
            ];
        }

        return [array_values((array) $entry), []];
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
     * @return string[]
     */
    private function paths($tier)
    {
        return isset($this->config[$tier]) ? array_values((array) $this->config[$tier]) : [];
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
