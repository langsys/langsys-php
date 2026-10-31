<?php

namespace Langsys\SDK\Snapshot;

use Langsys\SDK\Client;
use Langsys\SDK\Locale\LocaleDetector;

/**
 * A catalog snapshot: the catalog for chosen locales and categories, exported
 * from the API into a file an app can load without calling the API on its
 * render path - a mobile bundle, a first paint, an air-gapped deploy.
 *
 * Export is a client-side filter of each locale's flat catalog - what
 * `GET /translations` returns and an SDK seeds - by category (SNAP-1); there is
 * no export endpoint. A snapshot carries exactly the entries the API returns
 * for its categories, and nothing from the response envelope.
 *
 * Every Langsys SDK writes and reads the one format,
 * `langsys-catalog-snapshot` version 1, so a snapshot exported here loads in
 * any other core. Its checksum is the SHA-256 of a canonical serialisation
 * every core computes byte for byte.
 *
 * A snapshot is a cache, never a source (SNAP-3): loading refuses one whose
 * checksum no longer matches, so an edited snapshot is caught rather than
 * served. The refresh path is a new export.
 */
final class Snapshot
{
    const FORMAT = 'langsys-catalog-snapshot';
    const VERSION = 1;

    /** The members the checksum covers: every member but format, version and checksum. */
    const MEMBERS = ['project_id', 'generated_at', 'base_locale', 'locales', 'categories', 'catalog'];

    /** @var array */
    private $payload;

    /**
     * @param array $payload The MEMBERS
     */
    private function __construct(array $payload)
    {
        $this->payload = $payload;
    }

    /**
     * Export the chosen categories of each locale's catalog.
     *
     * @param Client $client
     * @param string[] $locales
     * @param string[] $categories
     * @return self
     * @throws SnapshotException When a catalog or the project cannot be read
     */
    public static function export(Client $client, array $locales, array $categories)
    {
        if ($locales === [] || $categories === []) {
            throw new SnapshotException('A snapshot names at least one locale and at least one category.');
        }

        $locales = array_values(array_unique(array_map([LocaleDetector::class, 'normalize'], $locales)));
        $categories = array_values(array_unique(array_map('strval', $categories)));
        sort($locales, SORT_STRING);
        sort($categories, SORT_STRING);

        try {
            $project = $client->getProject();
        } catch (\Throwable $e) {
            throw new SnapshotException('The project could not be read: ' . $e->getMessage(), null, 0, $e);
        }

        if (!is_array($project) || !isset($project['base_locale']) || !is_string($project['base_locale']) || $project['base_locale'] === '') {
            throw new SnapshotException('The project carried no base locale.');
        }

        $catalog = [];

        foreach ($locales as $locale) {
            try {
                $flat = $client->translations()->getTranslationMap($locale);
            } catch (\Throwable $e) {
                throw new SnapshotException("The $locale catalog could not be read: " . $e->getMessage(), null, 0, $e);
            }

            $catalog[$locale] = [];

            foreach ($categories as $category) {
                if (array_key_exists($category, $flat)) {
                    $catalog[$locale][$category] = is_array($flat[$category]) ? $flat[$category] : [];
                }
            }
        }

        return new self([
            'project_id' => (string) $client->getConfig()->getProjectId(),
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'base_locale' => LocaleDetector::normalize($project['base_locale']),
            'locales' => $locales,
            'categories' => $categories,
            'catalog' => $catalog,
        ]);
    }

    /**
     * Load a snapshot from a file path or its JSON - any JSON encoding of the
     * document. Refused, with the reason named, when its format or version is
     * not this one, a member is missing, or its checksum does not match.
     *
     * @param string $source
     * @return self
     * @throws SnapshotException
     */
    public static function load($source)
    {
        $json = (is_string($source) && is_file($source)) ? (string) file_get_contents($source) : (string) $source;
        $document = json_decode($json, true);

        if (!is_array($document) || !isset($document['format']) || $document['format'] !== self::FORMAT) {
            throw new SnapshotException('This is not a Langsys catalog snapshot: its format is not ' . self::FORMAT . '.', 'format');
        }

        if (!isset($document['version']) || $document['version'] !== self::VERSION) {
            throw new SnapshotException('This snapshot has an unsupported version; export it again with this SDK.', 'version');
        }

        foreach (array_merge(self::MEMBERS, ['checksum']) as $member) {
            if (!array_key_exists($member, $document)) {
                throw new SnapshotException("This snapshot is missing the member $member; export it again.", 'missing-member');
            }
        }

        $payload = [];
        foreach (self::MEMBERS as $member) {
            $payload[$member] = $document[$member];
        }

        $recomputed = self::wellFormed($payload) ? self::checksum($payload) : null;

        if ($recomputed === null || !is_string($document['checksum']) || !hash_equals($recomputed, $document['checksum'])) {
            throw new SnapshotException('This snapshot was changed after it was exported: its checksum does not match. A snapshot is a cache, never edited - export it again.', 'checksum');
        }

        return new self($payload);
    }

    /**
     * The snapshot's catalog for a locale, category => entries as the API
     * returns them, or null when the snapshot does not hold the locale.
     *
     * @param string $locale
     * @return array|null
     */
    public function catalog($locale)
    {
        $locale = LocaleDetector::normalize($locale);

        return isset($this->payload['catalog'][$locale]) ? $this->payload['catalog'][$locale] : null;
    }

    /**
     * @return string[]
     */
    public function locales()
    {
        return $this->payload['locales'];
    }

    /**
     * @return string[]
     */
    public function categories()
    {
        return $this->payload['categories'];
    }

    /**
     * @return string
     */
    public function projectId()
    {
        return $this->payload['project_id'];
    }

    /**
     * @return string
     */
    public function generatedAt()
    {
        return $this->payload['generated_at'];
    }

    /**
     * @return string
     */
    public function baseLocale()
    {
        return $this->payload['base_locale'];
    }

    /**
     * The document, written by the canonical writer so every map - an empty
     * category included - is an object any core reads back as one.
     *
     * @return string
     */
    public function toJson()
    {
        $members = [];
        foreach (self::MEMBERS as $member) {
            $members[$member] = self::encodeMember($member, $this->payload[$member]);
        }
        $members['format'] = self::encodeString(self::FORMAT);
        $members['version'] = (string) self::VERSION;
        $members['checksum'] = self::encodeString(self::checksum($this->payload));

        return self::encodeObject($members) . "\n";
    }

    /**
     * @param string $path
     * @return void
     * @throws SnapshotException
     */
    public function writeTo($path)
    {
        if (@file_put_contents($path, $this->toJson()) === false) {
            throw new SnapshotException("The snapshot could not be written to $path.");
        }
    }

    /**
     * The canonical serialisation of the checksummed members (SNAP-1): object
     * members in code point order, no whitespace, every map an object and every
     * list an array, and strings escaped as CID-1 escapes them.
     *
     * @param array $payload
     * @return string
     */
    public static function canonical(array $payload)
    {
        $members = [];
        foreach (self::MEMBERS as $member) {
            $members[$member] = self::encodeMember($member, $payload[$member]);
        }

        return self::encodeObject($members);
    }

    /**
     * @param array $payload
     * @return string
     */
    private static function checksum(array $payload)
    {
        return 'sha256:' . hash('sha256', self::canonical($payload));
    }

    /**
     * Whether the members have the document's shape: strings, lists of
     * strings, and a catalog of locale => category => entries, an entry a
     * string, null, or a block's phrase map.
     *
     * @param array $payload
     * @return bool
     */
    private static function wellFormed(array $payload)
    {
        foreach (['project_id', 'generated_at', 'base_locale'] as $member) {
            if (!is_string($payload[$member])) {
                return false;
            }
        }

        foreach (['locales', 'categories'] as $member) {
            if (!is_array($payload[$member]) || array_values($payload[$member]) !== $payload[$member]
                || array_filter($payload[$member], 'is_string') !== $payload[$member]) {
                return false;
            }
        }

        if (!is_array($payload['catalog'])) {
            return false;
        }

        foreach ($payload['catalog'] as $categories) {
            if (!is_array($categories)) {
                return false;
            }
            foreach ($categories as $entries) {
                if (!is_array($entries)) {
                    return false;
                }
                foreach ($entries as $value) {
                    if (!self::isEntryValue($value, true)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * @param mixed $value
     * @param bool $blockAllowed
     * @return bool
     */
    private static function isEntryValue($value, $blockAllowed)
    {
        if ($value === null || is_string($value)) {
            return true;
        }

        if (!$blockAllowed || !is_array($value)) {
            return false;
        }

        foreach ($value as $translation) {
            if (!self::isEntryValue($translation, false)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $member
     * @param mixed $value
     * @return string
     */
    private static function encodeMember($member, $value)
    {
        if ($member === 'locales' || $member === 'categories') {
            return '[' . implode(',', array_map([self::class, 'encodeString'], $value)) . ']';
        }

        if ($member === 'catalog') {
            return self::encodeMap($value, function ($categories) {
                return self::encodeMap($categories, function ($entries) {
                    return self::encodeMap($entries, [self::class, 'encodeEntry']);
                });
            });
        }

        return self::encodeString($value);
    }

    /**
     * @param string|array|null $value
     * @return string
     */
    private static function encodeEntry($value)
    {
        if ($value === null) {
            return 'null';
        }

        if (is_array($value)) {
            return self::encodeMap($value, [self::class, 'encodeEntry']);
        }

        return self::encodeString($value);
    }

    /**
     * A map as a JSON object, keys as strings in code point order - PHP turns
     * a key like "404" into an integer, and an empty map is still `{}`.
     *
     * @param array $map
     * @param callable $encodeValue
     * @return string
     */
    private static function encodeMap(array $map, callable $encodeValue)
    {
        $members = [];
        foreach ($map as $key => $value) {
            $members[(string) $key] = call_user_func($encodeValue, $value);
        }

        return self::encodeObject($members);
    }

    /**
     * @param array<string, string> $members Already-encoded values
     * @return string
     */
    private static function encodeObject(array $members)
    {
        $keys = array_map('strval', array_keys($members));
        usort($keys, 'strcmp');

        $parts = [];
        foreach ($keys as $key) {
            $parts[] = self::encodeString($key) . ':' . $members[$key];
        }

        return '{' . implode(',', $parts) . '}';
    }

    /**
     * A string as CID-1 escapes it: `"`, `\` and U+0000-U+001F only, the last
     * as \b \t \n \f \r or lowercase \u00xx; everything else raw UTF-8.
     *
     * @param string $value
     * @return string
     */
    private static function encodeString($value)
    {
        $named = ["\x08" => '\b', "\t" => '\t', "\n" => '\n', "\x0C" => '\f', "\r" => '\r', '"' => '\"', '\\' => '\\\\'];

        return '"' . preg_replace_callback('/[\x00-\x1F"\\\\]/', function ($m) use ($named) {
            return isset($named[$m[0]]) ? $named[$m[0]] : sprintf('\u%04x', ord($m[0]));
        }, (string) $value) . '"';
    }
}
