<?php

namespace Langsys\SDK\Snapshot;

use Langsys\SDK\Client;
use Langsys\SDK\Locale\LocaleDetector;

/**
 * A catalog snapshot: the catalog for chosen locales and categories, exported
 * from the API into a file an app can load without calling the API on its
 * render path - a mobile bundle, a first paint, an air-gapped deploy.
 *
 * Export is a client-side filter of `GET /translations/data` by category
 * (SNAP-1); there is no export endpoint. A snapshot carries exactly the entries
 * the API returns for its categories, and nothing from the response envelope.
 *
 * A snapshot is a cache, never a source (SNAP-3). It carries a checksum of its
 * contents, and loading refuses one that no longer matches, so an edited
 * snapshot is caught rather than served. The refresh path is a new export.
 */
final class Snapshot
{
    const FORMAT = 'langsys-catalog-snapshot';
    const VERSION = 1;
    const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_LINE_TERMINATORS;

    /** @var array */
    private $payload;

    /**
     * @param array $payload project_id, generated_at, locales, categories, catalog
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
     * @throws SnapshotException When a catalog cannot be read
     */
    public static function export(Client $client, array $locales, array $categories)
    {
        if ($locales === [] || $categories === []) {
            throw new SnapshotException('A snapshot names at least one locale and at least one category.');
        }

        $locales = array_values(array_unique(array_map([LocaleDetector::class, 'normalize'], $locales)));
        $categories = array_values(array_unique(array_map('strval', $categories)));
        $catalog = [];

        foreach ($locales as $locale) {
            try {
                $response = $client->translations()->getData($locale);
            } catch (\Throwable $e) {
                throw new SnapshotException("The $locale catalog could not be read: " . $e->getMessage(), 0, $e);
            }

            if (!is_array($response) || !array_key_exists('data', $response) || !is_array($response['data'])) {
                throw new SnapshotException("The $locale catalog response carried no catalog.");
            }

            $catalog[$locale] = [];

            foreach ($categories as $category) {
                if (array_key_exists($category, $response['data'])) {
                    $catalog[$locale][$category] = $response['data'][$category];
                }
            }
        }

        return new self([
            'project_id' => (string) $client->getConfig()->getProjectId(),
            'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'locales' => $locales,
            'categories' => $categories,
            'catalog' => $catalog,
        ]);
    }

    /**
     * Load a snapshot from a file path or its JSON.
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
            throw new SnapshotException('This is not a Langsys catalog snapshot.');
        }

        if (!isset($document['version']) || $document['version'] !== self::VERSION) {
            throw new SnapshotException('This snapshot has an unsupported version; export it again with this SDK.');
        }

        foreach (['project_id', 'generated_at', 'locales', 'categories', 'catalog', 'checksum'] as $field) {
            if (!array_key_exists($field, $document)) {
                throw new SnapshotException("This snapshot has no $field; export it again.");
            }
        }

        $payload = [
            'project_id' => $document['project_id'],
            'generated_at' => $document['generated_at'],
            'locales' => $document['locales'],
            'categories' => $document['categories'],
            'catalog' => $document['catalog'],
        ];

        if (!is_string($document['checksum']) || !hash_equals(self::checksum($payload), $document['checksum'])) {
            throw new SnapshotException('This snapshot was changed after it was exported; a snapshot is a cache, never edited - export it again.');
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
    public function toJson()
    {
        return json_encode(
            ['format' => self::FORMAT, 'version' => self::VERSION] + $this->payload + ['checksum' => self::checksum($this->payload)],
            self::JSON_FLAGS | JSON_PRETTY_PRINT
        ) . "\n";
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
     * @param array $payload
     * @return string
     */
    private static function checksum(array $payload)
    {
        return 'sha256:' . hash('sha256', (string) json_encode($payload, self::JSON_FLAGS));
    }
}
