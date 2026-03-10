<?php

namespace Langsys\SDK\Resources;

use Langsys\SDK\Html\HtmlParser;
use Langsys\SDK\Http\HttpClient;
use Langsys\SDK\Log\LoggerInterface;
use Langsys\SDK\Log\NullLogger;

/**
 * Resource for handling translatable items API operations.
 */
class TranslatableItems
{
    /**
     * Default batch limit when the API does not provide one.
     */
    const DEFAULT_BATCH_LIMIT = 200;

    /**
     * @var HttpClient
     */
    protected $http;

    /**
     * @var string
     */
    protected $projectId;

    /**
     * @var array|null Custom translatable attributes for HTML parsing
     */
    protected $translatableAttributes;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var int Batch limit for chunking API requests
     */
    protected $batchLimit;

    /**
     * Create a new TranslatableItems instance.
     *
     * @param HttpClient $http
     * @param string $projectId
     * @param LoggerInterface $logger
     */
    public function __construct(HttpClient $http, $projectId, $logger = null)
    {
        $this->http = $http;
        $this->projectId = $projectId;
        $this->translatableAttributes = null;
        $this->batchLimit = self::DEFAULT_BATCH_LIMIT;
        $this->logger = $logger !== null ? $logger : new NullLogger();
    }

    /**
     * Get the translatable attributes used for HTML parsing.
     *
     * @return array
     */
    public function getTranslatableAttributes()
    {
        return $this->translatableAttributes !== null
            ? $this->translatableAttributes
            : HtmlParser::DEFAULT_TRANSLATABLE_ATTRIBUTES;
    }

    /**
     * Set the translatable attributes for HTML parsing (replaces defaults).
     *
     * @param array $attributes
     * @return $this
     */
    public function setTranslatableAttributes(array $attributes)
    {
        $this->translatableAttributes = $attributes;
        return $this;
    }

    /**
     * Add additional translatable attributes to the default list.
     *
     * @param array $attributes
     * @return $this
     */
    public function addTranslatableAttributes(array $attributes)
    {
        $current = $this->translatableAttributes !== null
            ? $this->translatableAttributes
            : HtmlParser::DEFAULT_TRANSLATABLE_ATTRIBUTES;

        $this->translatableAttributes = array_unique(
            array_merge($current, $attributes)
        );
        return $this;
    }

    /**
     * Reset to default translatable attributes.
     *
     * @return $this
     */
    public function resetTranslatableAttributes()
    {
        $this->translatableAttributes = null;
        return $this;
    }

    /**
     * Set the batch limit for chunking API requests.
     *
     * @param int $limit
     * @return $this
     */
    public function setBatchLimit($limit)
    {
        $this->batchLimit = (int) $limit;
        return $this;
    }

    /**
     * Get the current batch limit.
     *
     * @return int
     */
    public function getBatchLimit()
    {
        return $this->batchLimit;
    }

    /**
     * Create or update phrases.
     *
     * @param array $phrases Array of phrases, each with: phrase (required), category (optional), translatable (optional)
     * @return array
     */
    public function createPhrases(array $phrases)
    {
        $items = [];

        foreach ($phrases as $phrase) {
            $items[] = [
                'type' => 'phrase',
                'phrase' => is_string($phrase) ? $phrase : $phrase['phrase'],
                'category' => is_string($phrase) ? null : (isset($phrase['category']) ? $phrase['category'] : null),
                'translatable' => is_string($phrase) ? true : (isset($phrase['translatable']) ? $phrase['translatable'] : true),
            ];
        }

        $this->logger->debug('Creating phrases', ['count' => count($items)]);

        $chunks = array_chunk($items, $this->batchLimit);
        $lastResponse = null;

        foreach ($chunks as $chunk) {
            $lastResponse = $this->http->post('translatable-items', [
                'project_id' => $this->projectId,
                'translatable_items' => $chunk,
            ]);
        }

        return $lastResponse;
    }

    /**
     * Normalize HTML content by trimming and collapsing whitespace.
     *
     * @param string $html
     * @return string
     */
    protected function normalizeHtmlContent($html)
    {
        // Trim leading/trailing whitespace
        $html = trim($html);

        // Collapse multiple whitespace (spaces, tabs, newlines) to single space
        $html = preg_replace('/\s+/', ' ', $html);

        // Clean up space around tags
        $html = preg_replace('/>\s+</', '><', $html);

        return $html;
    }

    /**
     * Create or update a content block.
     *
     * Phrases are automatically extracted from the HTML content.
     *
     * @param string $content HTML content of the content block
     * @param string|null $category Category for the content block
     * @param string|null $label Label to identify the content block
     * @param string|null $customId Custom ID to identify the content block (auto-generated if null)
     * @return array
     */
    public function createContentBlock($content, $category = null, $label = null, $customId = null)
    {
        $parser = new HtmlParser($this->translatableAttributes);

        // Normalize HTML content
        $content = $this->normalizeHtmlContent($content);

        // Extract phrases from HTML content
        $phrases = $parser->extractPhrases($content);

        // Auto-generate customId if not provided
        if ($customId === null) {
            $customId = $parser->generateCustomId($category, $phrases);
        }

        // Format phrases for API
        $formattedPhrases = [];
        foreach ($phrases as $phrase) {
            $formattedPhrases[] = ['phrase' => $phrase];
        }

        $this->logger->debug('Creating content block', [
            'custom_id' => $customId,
            'category' => $category,
            'phrase_count' => count($formattedPhrases),
        ]);

        $item = [
            'type' => 'content_block',
            'custom_id' => $customId,
            'content' => $content,
            'phrases' => $formattedPhrases,
        ];

        if ($category !== null) {
            $item['category'] = $category;
        }

        if ($label !== null) {
            $item['label'] = $label;
        }

        return $this->http->post('translatable-items', [
            'project_id' => $this->projectId,
            'translatable_items' => [$item],
        ]);
    }

    /**
     * Create or update multiple content blocks in a single API call.
     *
     * @param array $contentBlocks Array of content blocks, each with: html, category, customId
     * @return array
     */
    public function createContentBlocks(array $contentBlocks)
    {
        if (empty($contentBlocks)) {
            return ['status' => true, 'created' => 0];
        }

        $parser = new HtmlParser($this->translatableAttributes);
        $requestItems = [];

        foreach ($contentBlocks as $block) {
            $html = isset($block['html']) ? $block['html'] : '';
            $category = isset($block['category']) ? $block['category'] : null;
            $customId = isset($block['customId']) ? $block['customId'] : null;
            $label = isset($block['label']) ? $block['label'] : null;

            // Normalize HTML content
            $html = $this->normalizeHtmlContent($html);

            // Extract phrases from HTML content
            $phrases = $parser->extractPhrases($html);

            // Auto-generate customId if not provided
            if ($customId === null) {
                $customId = $parser->generateCustomId($category, $phrases);
            }

            // Format phrases for API
            $formattedPhrases = [];
            foreach ($phrases as $phrase) {
                $formattedPhrases[] = ['phrase' => $phrase];
            }

            $item = [
                'type' => 'content_block',
                'custom_id' => $customId,
                'content' => $html,
                'phrases' => $formattedPhrases,
            ];

            if ($category !== null && $category !== '__uncategorized__') {
                $item['category'] = $category;
            }

            if ($label !== null) {
                $item['label'] = $label;
            }

            $requestItems[] = $item;
        }

        $this->logger->debug('Creating content blocks batch', [
            'count' => count($requestItems),
        ]);

        $chunks = array_chunk($requestItems, $this->batchLimit);
        $lastResponse = null;

        foreach ($chunks as $chunk) {
            $lastResponse = $this->http->post('translatable-items', [
                'project_id' => $this->projectId,
                'translatable_items' => $chunk,
            ]);
        }

        return $lastResponse;
    }

    /**
     * Create multiple phrases with the same category.
     *
     * @param array $phraseStrings Array of phrase strings
     * @param string $category Category for all phrases
     * @param bool $translatable Whether phrases should be marked as translatable
     * @return array
     */
    public function createPhrasesWithCategory(array $phraseStrings, $category, $translatable = true)
    {
        $phrases = [];

        foreach ($phraseStrings as $phrase) {
            $phrases[] = [
                'phrase' => $phrase,
                'category' => $category,
                'translatable' => $translatable,
            ];
        }

        return $this->createPhrases($phrases);
    }

    /**
     * Bulk create phrases from a key-value map.
     *
     * @param array $phraseMap [category => [phrase1, phrase2, ...]]
     * @return array
     */
    public function createFromMap(array $phraseMap)
    {
        $phrases = [];

        foreach ($phraseMap as $category => $categoryPhrases) {
            foreach ($categoryPhrases as $phrase) {
                $phrases[] = [
                    'phrase' => $phrase,
                    'category' => $category,
                ];
            }
        }

        return $this->createPhrases($phrases);
    }
}
