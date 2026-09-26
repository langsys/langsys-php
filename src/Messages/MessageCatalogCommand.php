<?php

namespace Langsys\SDK\Messages;

use Langsys\SDK\Client;
use Langsys\SDK\Exception\LangsysException;

/**
 * The build-time command (MSG-7): list every template an app can send, fail the
 * build naming each message it cannot list, and register the templates the
 * catalog lacks under the messages category.
 *
 * collect() and register() carry the behaviour so a framework binding can
 * render them through its own console; run() is the stream wrapper
 * bin/langsys-messages uses.
 */
final class MessageCatalogCommand
{
    /**
     * List every template the sources declare.
     *
     * A source that throws is reported as a problem rather than aborting the
     * listing: the build must name what it could not list, not stop at the first.
     *
     * @param MessageSource[] $sources
     * @return MessageCatalog
     */
    public static function collect(array $sources)
    {
        $catalog = new MessageCatalog();

        foreach ($sources as $index => $source) {
            if (!$source instanceof MessageSource) {
                $catalog->problem('source #' . $index, 'is ' . (is_object($source) ? get_class($source) : gettype($source)) . ', not a message source', 'pass an object implementing ' . MessageSource::class);
                continue;
            }

            try {
                $source->collect($catalog);
            } catch (\Throwable $e) {
                $catalog->problem(get_class($source), 'failed while listing its templates: ' . $e->getMessage(), 'make the source list its templates without a request');
            }
        }

        return $catalog;
    }

    /**
     * Register the listed templates the catalog does not already hold, under the
     * messages category (MSG-6). Idempotent: a second run registers nothing.
     *
     * @param MessageCatalog $catalog
     * @param Client $client
     * @return array{registered: int, skipped: int}
     * @throws LangsysException When the key cannot write or the catalog cannot be read
     */
    public static function register(MessageCatalog $catalog, Client $client)
    {
        $templates = array_column($catalog->templates(), 'template');

        if ($templates === []) {
            return ['registered' => 0, 'skipped' => 0];
        }

        if (!$client->canWrite()) {
            throw new LangsysException('the API key cannot write to this project, so it cannot register templates');
        }

        $category = $client->getConfig()->getMessagesCategory();
        $locale = $client->getLocale();

        if ($locale === null) {
            throw new LangsysException('there is no locale to read the catalog in, and the project reported no base locale');
        }

        try {
            $translations = $client->getTranslations($locale);
        } catch (\Throwable $e) {
            throw new LangsysException('the catalog could not be read to see what is already registered: ' . $e->getMessage(), 0, $e);
        }

        $listed = (isset($translations[$category]) && is_array($translations[$category])) ? $translations[$category] : [];
        $missing = array_values(array_filter($templates, function ($template) use ($listed) {
            return !array_key_exists($template, $listed);
        }));

        if ($missing !== []) {
            $client->registerPhrases(array_map(function ($template) use ($category) {
                return ['phrase' => $template, 'category' => $category];
            }, $missing));
        }

        return ['registered' => count($missing), 'skipped' => count($templates) - count($missing)];
    }

    /**
     * @param MessageSource[] $sources
     * @param Client|null $client Needed only to register
     * @param array $options `register`, `verbose` and `strict`
     * @param resource|null $out
     * @param resource|null $err
     * @return int Exit code: 1 when registering fails, or under `strict` when a
     *             message cannot be listed; 0 otherwise
     */
    public static function run(array $sources, $client = null, array $options = [], $out = null, $err = null)
    {
        $out = $out ?: fopen('php://stdout', 'w');
        $err = $err ?: fopen('php://stderr', 'w');

        $catalog = self::collect($sources);
        $templates = $catalog->templates();

        fwrite($out, count($templates) . " message templates\n");

        if (!empty($options['verbose'])) {
            foreach ($templates as $entry) {
                fwrite($out, '  ' . $entry['template'] . '  ' . $entry['source'] . "\n");
            }
        }

        foreach ($catalog->problems() as $problem) {
            fwrite($err, '✗ ' . $problem . "\n");
        }

        // A message that cannot be listed still registers the first time it is
        // emitted (MSG-8), so it is reported, not failed - unless the app asks
        // for no untranslated error ever, with --strict (MSG-7).
        if ($catalog->hasProblems()) {
            $count = count($catalog->problems());
            fwrite($err, $count . ($count === 1 ? ' message cannot' : ' messages cannot') . " be registered ahead of time; each registers the first time it is sent\n");

            if (!empty($options['strict'])) {
                return 1;
            }
        }

        if (empty($options['register'])) {
            return 0;
        }

        if (!$client instanceof Client) {
            fwrite($err, "Cannot register: no Langsys client is configured. Give the command a client with the project's API key and id.\n");

            return 1;
        }

        try {
            $result = self::register($catalog, $client);
        } catch (\Throwable $e) {
            fwrite($err, 'Cannot register: ' . $e->getMessage() . "\n");

            return 1;
        }

        $category = $client->getConfig()->getMessagesCategory();

        if ($result['registered'] === 0) {
            fwrite($out, "All {$result['skipped']} templates are already registered under \"$category\": nothing new to register\n");
        } else {
            fwrite($out, "Registered {$result['registered']} templates under \"$category\"" . ($result['skipped'] > 0 ? " ({$result['skipped']} already registered)" : '') . "\n");
        }

        return 0;
    }
}
