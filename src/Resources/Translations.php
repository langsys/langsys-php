<?php

namespace Langsys\SDK\Resources;

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
            'locale' => $locale,
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
            'locale' => $locale,
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

        if (isset($response['data'])) {
            return $response['data'];
        }

        return [];
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
