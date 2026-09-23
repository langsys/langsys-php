<?php

namespace Langsys\SDK\Resources;

use Langsys\SDK\Locale\LocaleDetector;

use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Http\HttpClient;
use Langsys\SDK\Log\LoggerInterface;
use Langsys\SDK\Log\NullLogger;

/**
 * Resource for handling translation API operations.
 */
class Translations
{
    /**
     * @var HttpClient
     */
    protected $http;

    /**
     * @var string
     */
    protected $projectId;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Create a new Translations instance.
     *
     * @param HttpClient $http
     * @param string $projectId
     * @param LoggerInterface $logger
     */
    public function __construct(HttpClient $http, $projectId, $logger = null)
    {
        $this->http = $http;
        $this->projectId = $projectId;
        $this->logger = $logger !== null ? $logger : new NullLogger();
    }

    /**
     * Get translations in flat format.
     *
     * Response structure:
     * [
     *   'status' => true,
     *   'words' => 752,
     *   'untranslated' => 25,
     *   'data' => [
     *     'UI' => [
     *       'Home' => 'Inicio',
     *       'About' => 'Acerca de'
     *     ],
     *     '__uncategorized__' => [
     *       'Welcome' => 'Bienvenido'
     *     ]
     *   ]
     * ]
     *
     * @param string $locale Locale code (e.g., 'es-es', 'fr-ca')
     * @return array
     */
    public function getFlat($locale)
    {
        return $this->http->get('translations', [
            'project_id' => $this->projectId,
            'locale' => LocaleDetector::normalize($locale),
            'format' => 'flat',
        ]);
    }

    /**
     * Get translations in data format (full object structure).
     *
     * @param string $locale Locale code
     * @return array
     */
    public function getData($locale)
    {
        return $this->http->get('translations/data', [
            'project_id' => $this->projectId,
            'locale' => LocaleDetector::normalize($locale),
        ]);
    }

    /**
     * Get just the translation data without metadata.
     *
     * @param string $locale Locale code
     * @return array [category => [phrase => translation]]
     */
    public function getTranslationMap($locale)
    {
        $this->logger->debug('Fetching translations', ['locale' => $locale]);

        $response = $this->getFlat($locale);

        // A 2xx with no `data` key is FOREIGN, not an empty catalog.
        //
        // This was carved out as "a project with no translations legitimately
        // has an empty catalog" and that is false: every translations response
        // goes through ApiResponse::resourceResponse(), which assigns
        // simpleResponse['data'] unconditionally, so an empty catalog arrives
        // WITH the key. Nothing in the backend omits it.
        //
        // And the carve-out was reachable without anything exotic:
        // HttpClient::handleResponse() turns any empty-bodied 2xx into [] - it
        // has to, for 204 - so an empty 200 from a proxy or load balancer read
        // as "no data", cached [], and blanked translations for the whole TTL.
        // That is the very mode the validation below exists to prevent, let
        // back in through the one door left open for it.
        //
        // array_key_exists, not isset: `{"data":null}` is also not a catalog,
        // and isset() cannot tell it from an absent key.
        if (!array_key_exists('data', $response)) {
            throw new LangsysException(sprintf(
                'Translations response for %s carried no data key',
                $locale
            ));
        }

        $data = $response['data'];

        // Validate BEFORE this can reach a cache. Returning the payload
        // unchecked meant a malformed server response was written into the
        // shared cache and then re-read for the rest of its TTL - the SDK
        // manufacturing the poison it guards against on the next request.
        // Depth 2, because a top-level array of scalar slices breaks every
        // lookup just as thoroughly as a scalar does.
        //
        // THROWS rather than returning []. Returning an empty catalog was the
        // first attempt and it was worse than the defect: [] is a perfectly
        // valid shape, so the caller cached it and every later request read a
        // blank catalog for the rest of the TTL - an hour by default, fleet-wide
        // on a shared Redis, and unable to self-heal at all under a read key,
        // which never refetches. One bad response silently un-translated every
        // page. Failing loudly puts this on the same footing as an unreachable
        // API: the entry points degrade to source text, nothing is cached, and
        // the next request tries again.
        //
        // A MISSING data key is not this case - a project with no translations
        // legitimately has an empty catalog, and caching that is correct.
        if (!is_array($data)) {
            throw new LangsysException(sprintf(
                'Translations response for %s is not a catalog map (got %s)',
                $locale,
                gettype($data)
            ));
        }

        foreach ($data as $category => $slice) {
            if (!is_array($slice)) {
                throw new LangsysException(sprintf(
                    'Translations response for %s has a malformed category slice %s (got %s)',
                    $locale,
                    var_export($category, true),
                    gettype($slice)
                ));
            }
        }

        return $data;
    }

    /**
     * Get all phrases from translations as a flat list.
     *
     * @param string $locale Locale code
     * @return array List of phrases
     */
    public function getAllPhrases($locale)
    {
        $translations = $this->getTranslationMap($locale);
        $phrases = [];

        foreach ($translations as $category => $items) {
            foreach ($items as $phrase => $translation) {
                // Handle content blocks (nested arrays)
                if (is_array($translation)) {
                    foreach ($translation as $blockPhrase => $blockTranslation) {
                        $phrases[] = $blockPhrase;
                    }
                } else {
                    $phrases[] = $phrase;
                }
            }
        }

        return $phrases;
    }

    /**
     * Get translation statistics.
     *
     * @param string $locale Locale code
     * @return array ['words' => int, 'untranslated' => int]
     */
    public function getStats($locale)
    {
        $response = $this->getFlat($locale);

        return [
            'words' => isset($response['words']) ? (int) $response['words'] : 0,
            'untranslated' => isset($response['untranslated']) ? (int) $response['untranslated'] : 0,
        ];
    }
}
