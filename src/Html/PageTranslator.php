<?php

namespace Langsys\SDK\Html;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMText;

/**
 * Main orchestrator for full HTML page translation.
 *
 * Parses an entire HTML document, extracts translatable content,
 * registers new phrases/content blocks (if write permission),
 * and returns the translated HTML with fallback to source content.
 *
 * Supports CSS selector-based category mapping for fine-grained control
 * over how elements are categorized for translation.
 */
class PageTranslator
{
    /**
     * Block-level elements that may become content blocks.
     */
    const BLOCK_ELEMENTS = [
        // Structural
        'div', 'section', 'article', 'header', 'footer', 'nav', 'aside', 'main',
        // Text blocks
        'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'blockquote', 'pre', 'address',
        // Lists
        'ul', 'ol', 'li', 'dl', 'dt', 'dd',
        // Tables
        'table', 'tr', 'th', 'td', 'thead', 'tbody', 'tfoot', 'caption',
        // Forms
        'form', 'fieldset', 'legend',
        // Media
        'figure', 'figcaption',
        // Interactive
        'details', 'summary', 'dialog',
    ];

    /**
     * Elements to skip entirely (never translate contents).
     */
    const SKIP_ELEMENTS = [
        'script', 'style', 'noscript', 'template', 'svg', 'math',
    ];

    /**
     * @var \Langsys\SDK\Client
     */
    protected $client;

    /**
     * @var HtmlParser
     */
    protected $htmlParser;

    /**
     * @var HeadHandler
     */
    protected $headHandler;

    /**
     * @var SelectorMatcher|null
     */
    protected $selectorMatcher = null;

    /**
     * Create a new PageTranslator instance.
     *
     * @param \Langsys\SDK\Client $client The Langsys client
     * @param array|null $translatableAttributes Custom translatable attributes (or null for defaults)
     */
    public function __construct($client, $translatableAttributes = null)
    {
        $this->client = $client;
        $this->htmlParser = new HtmlParser($translatableAttributes);
        $this->headHandler = new HeadHandler();
    }

    /**
     * Translate an entire HTML page.
     *
     * @param string $html Full HTML document
     * @param string $locale Target locale (e.g., 'es-es')
     * @param string|null $defaultCategory Default category/name (can be overridden by data-langsys-category attribute)
     * @param array $selectorCategories Map of CSS selector => category config
     * @return string Translated HTML
     */
    public function translate($html, $locale, $defaultCategory = null, array $selectorCategories = [])
    {
        if (empty($html)) {
            return $html;
        }

        // Parse document
        $doc = $this->parseDocument($html);
        if ($doc === null) {
            return $html; // Return original on parse failure
        }

        // Create selector matcher if selector categories provided
        $this->selectorMatcher = !empty($selectorCategories)
            ? new SelectorMatcher($selectorCategories)
            : null;

        // Get translations (uses cache)
        try {
            $translations = $this->client->getTranslations($locale);
        } catch (\Exception $e) {
            // API unavailable - return original HTML
            return $html;
        }

        // Process head section (extract phrases + apply translations + set lang/charset)
        $headPhrases = $this->headHandler->extractPhrases($doc);
        $this->headHandler->process($doc, $locale, $translations, $defaultCategory);

        // Process body section (respects data-langsys-category attributes and selector categories)
        $bodyResult = $this->processBody($doc, $defaultCategory);
        $bodyPhrases = $bodyResult['phrases'];
        $contentBlocks = $bodyResult['contentBlocks'];

        // Collect all unique categories used
        $usedCategories = $this->collectUsedCategories($headPhrases, $bodyPhrases, $contentBlocks, $defaultCategory);

        // Get already registered items from cache for each category
        $registeredItems = [];
        foreach ($usedCategories as $cat) {
            $registeredItems[$cat] = $this->getRegisteredItems($cat);
        }

        // Identify new items (not in translations AND not already registered)
        // Head phrases use default category, body phrases use their own category
        $newHeadPhrases = $this->findNewPhrasesWithCategory(
            $this->addCategoryToPhrases($headPhrases, $defaultCategory),
            $translations,
            $registeredItems
        );
        $newBodyPhrases = $this->findNewPhrasesWithCategory(
            $bodyPhrases,
            $translations,
            $registeredItems
        );
        $newPhrases = array_merge($newHeadPhrases, $newBodyPhrases);

        $newContentBlocks = $this->findNewContentBlocksWithCategory(
            $contentBlocks,
            $translations,
            $registeredItems
        );

        // Register new items (silent skip if read-only)
        if (!empty($newPhrases) || !empty($newContentBlocks)) {
            $this->registerNewItemsWithCategory($newPhrases, $newContentBlocks, $locale);

            // Mark items as registered in cache (to avoid re-registration on next load)
            $this->markItemsAsRegisteredWithCategory($newPhrases, $newContentBlocks);

            // Refresh translations cache
            try {
                $this->client->clearCache($locale);
                $translations = $this->client->getTranslations($locale);
            } catch (\Exception $e) {
                // Ignore - use existing translations
            }
        }

        // Apply body translations
        $this->applyBodyTranslations($doc, $bodyPhrases, $contentBlocks, $translations, $defaultCategory);

        // Return translated HTML
        return $this->saveHtml($doc);
    }

    /**
     * Parse HTML string into DOMDocument.
     *
     * @param string $html The HTML to parse
     * @return DOMDocument|null The document or null on failure
     */
    protected function parseDocument($html)
    {
        $internalErrors = libxml_use_internal_errors(true);

        $doc = new DOMDocument();
        $doc->encoding = 'UTF-8';

        // Use HTML5 doctype hint and preserve encoding
        $htmlWithEncoding = $html;
        if (stripos($html, '<meta charset') === false &&
            stripos($html, 'http-equiv="Content-Type"') === false) {
            // Add encoding hint for DOMDocument
            $htmlWithEncoding = '<?xml encoding="UTF-8">' . $html;
        }

        $loaded = $doc->loadHTML($htmlWithEncoding, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if (!$loaded) {
            return null;
        }

        return $doc;
    }

    /**
     * Process body section, identifying phrases and content blocks.
     *
     * Respects data-langsys-category attributes on elements to override
     * the default category for all translatable content within that element.
     *
     * @param DOMDocument $doc The document
     * @param string|null $defaultCategory Default category for content blocks
     * @return array ['phrases' => [...], 'contentBlocks' => [...]]
     */
    protected function processBody(DOMDocument $doc, $defaultCategory = null)
    {
        $phrases = [];
        $contentBlocks = [];

        $bodies = $doc->getElementsByTagName('body');
        if ($bodies->length === 0) {
            // No body tag - treat entire document as content
            $this->walkForExtraction($doc->documentElement, $phrases, $contentBlocks, $defaultCategory);
        } else {
            $this->walkForExtraction($bodies->item(0), $phrases, $contentBlocks, $defaultCategory);
        }

        return [
            'phrases' => $phrases,
            'contentBlocks' => $contentBlocks,
        ];
    }

    /**
     * Walk DOM tree to extract phrases and identify content blocks.
     *
     * Respects data-langsys-category attributes on elements to override
     * the inherited category for all translatable content within that element.
     *
     * If data-langsys-contentblock is present (truthy), the element and all its
     * children are treated as a single content block without further decomposition.
     *
     * Category priority (highest to lowest):
     * 1. Selector with overrideParentElementCategory=true
     * 2. Element's data-langsys-category attribute
     * 3. Inherited category from parent (data-langsys-category OR selector category)
     * 4. Selector with overrideParentElementCategory=false
     * 5. Default category parameter
     * 6. '__uncategorized__'
     *
     * @param DOMNode $node The node to process
     * @param array &$phrases Collected phrases
     * @param array &$contentBlocks Collected content blocks
     * @param string|null $inheritedCategory Category inherited from parent or default
     * @return void
     */
    protected function walkForExtraction(DOMNode $node, array &$phrases, array &$contentBlocks, $inheritedCategory = null)
    {
        if (!$node->hasChildNodes()) {
            return;
        }

        foreach ($node->childNodes as $child) {
            // Skip non-element nodes at this level (text directly in body is rare)
            if (!($child instanceof DOMElement)) {
                continue;
            }

            $tagName = strtolower($child->tagName);

            // Skip elements to ignore
            if (in_array($tagName, self::SKIP_ELEMENTS, true)) {
                continue;
            }

            // Skip elements with translate="no" or data-notrans
            if ($child->getAttribute('translate') === 'no' || $child->getAttribute('data-notrans')) {
                continue;
            }

            // Determine effective category using priority rules
            $effectiveCategory = $this->determineEffectiveCategory($child, $inheritedCategory);

            // Check for data-langsys-contentblock - treat entire element as single content block
            if ($this->hasContentBlockAttribute($child)) {
                $this->extractAsContentBlock($child, $contentBlocks, $effectiveCategory);
                continue;
            }

            // Is this a block element?
            if (in_array($tagName, self::BLOCK_ELEMENTS, true)) {
                if ($this->containsNestedBlocks($child)) {
                    // Container with nested blocks - recurse with effective category
                    $this->walkForExtraction($child, $phrases, $contentBlocks, $effectiveCategory);
                } else {
                    // Smallest block element - determine if it's a phrase or content block
                    // by counting how many phrases HtmlParser would extract
                    $innerHtml = $this->getInnerHtml($child);
                    $extractedPhrases = $this->htmlParser->extractPhrases($innerHtml);
                    $textContent = $this->getTextContent($child);

                    if (empty($extractedPhrases)) {
                        // No translatable content
                        continue;
                    }

                    // Determine the category for this item
                    $itemCategory = $effectiveCategory !== null ? $effectiveCategory : '__uncategorized__';

                    // If exactly 1 phrase and it matches the text content,
                    // treat as simple phrase (even with inline formatting like <strong>)
                    if (count($extractedPhrases) === 1 && $extractedPhrases[0] === $textContent) {
                        $phrases[] = [
                            'text' => $textContent,
                            'element' => $child,
                            'category' => $itemCategory,
                        ];
                    } else {
                        // Multiple phrases or phrases from attributes -> content block
                        $customId = $this->htmlParser->generateCustomId($itemCategory, $extractedPhrases);
                        $contentBlocks[] = [
                            'customId' => $customId,
                            'phrases' => $extractedPhrases,
                            'element' => $child,
                            'html' => $innerHtml,
                            'category' => $itemCategory,
                        ];
                    }
                }
            } else {
                // Non-block element - recurse into it with effective category
                $this->walkForExtraction($child, $phrases, $contentBlocks, $effectiveCategory);
            }
        }
    }

    /**
     * Check if an element has the data-langsys-contentblock attribute with a truthy value.
     *
     * @param DOMElement $element The element to check
     * @return bool True if element should be treated as a content block
     */
    protected function hasContentBlockAttribute(DOMElement $element)
    {
        if (!$element->hasAttribute('data-langsys-contentblock')) {
            return false;
        }

        $value = $element->getAttribute('data-langsys-contentblock');

        // Truthy check: attribute exists and is not empty, "0", or "false"
        return $value !== '' && $value !== '0' && strtolower($value) !== 'false';
    }

    /**
     * Extract an element and all its children as a single content block.
     *
     * @param DOMElement $element The element to extract
     * @param array &$contentBlocks Collected content blocks
     * @param string|null $effectiveCategory The category for this content block
     * @return void
     */
    protected function extractAsContentBlock(DOMElement $element, array &$contentBlocks, $effectiveCategory)
    {
        $innerHtml = $this->getInnerHtml($element);
        $extractedPhrases = $this->htmlParser->extractPhrases($innerHtml);

        if (empty($extractedPhrases)) {
            // No translatable content
            return;
        }

        $itemCategory = $effectiveCategory !== null ? $effectiveCategory : '__uncategorized__';
        $customId = $this->htmlParser->generateCustomId($itemCategory, $extractedPhrases);

        $contentBlocks[] = [
            'customId' => $customId,
            'phrases' => $extractedPhrases,
            'element' => $element,
            'html' => $innerHtml,
            'category' => $itemCategory,
        ];
    }

    /**
     * Determine the effective category for an element using priority rules.
     *
     * Priority (highest to lowest):
     * 1. Selector with overrideParentElementCategory=true
     * 2. Element's data-langsys-category attribute
     * 3. Inherited category from parent (data-langsys-category OR selector category)
     * 4. Selector with overrideParentElementCategory=false
     * 5. Default category (handled by caller as inheritedCategory)
     *
     * @param DOMElement $element The element to check
     * @param string|null $inheritedCategory Category inherited from parent
     * @return string|null The effective category for this element
     */
    protected function determineEffectiveCategory(DOMElement $element, $inheritedCategory)
    {
        // Check for selector with override=true (highest priority)
        if ($this->selectorMatcher !== null) {
            $selectorMatch = $this->selectorMatcher->matchElement($element);
            if ($selectorMatch !== null && $selectorMatch['override']) {
                return $selectorMatch['category'];
            }
        }

        // Check for data-langsys-category attribute
        if ($element->hasAttribute('data-langsys-category')) {
            return $element->getAttribute('data-langsys-category');
        }

        // Use inherited category if set
        if ($inheritedCategory !== null) {
            return $inheritedCategory;
        }

        // Check for selector with override=false (lowest priority before default)
        if ($this->selectorMatcher !== null) {
            $selectorMatch = $this->selectorMatcher->matchElement($element);
            if ($selectorMatch !== null && !$selectorMatch['override']) {
                return $selectorMatch['category'];
            }
        }

        // Return null to let caller use default
        return null;
    }

    /**
     * Check if an element contains nested block-level elements.
     *
     * @param DOMElement $element The element to check
     * @return bool True if contains nested blocks
     */
    protected function containsNestedBlocks(DOMElement $element)
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $tagName = strtolower($child->tagName);
                if (in_array($tagName, self::BLOCK_ELEMENTS, true)) {
                    return true;
                }
                // Recursively check children
                if ($this->containsNestedBlocks($child)) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Get normalized text content of an element.
     *
     * @param DOMElement $element The element
     * @return string Normalized text
     */
    protected function getTextContent(DOMElement $element)
    {
        $text = $element->textContent;
        // Normalize whitespace
        return trim(preg_replace('/\s+/', ' ', $text));
    }

    /**
     * Get inner HTML of an element.
     *
     * @param DOMElement $element The element
     * @return string Inner HTML
     */
    protected function getInnerHtml(DOMElement $element)
    {
        $innerHTML = '';
        foreach ($element->childNodes as $child) {
            $innerHTML .= $element->ownerDocument->saveHTML($child);
        }
        return $innerHTML;
    }

    /**
     * Apply translations to body content.
     *
     * Uses per-item categories from the extracted phrases/content blocks,
     * falling back to defaultCategory if not specified.
     *
     * @param DOMDocument $doc The document
     * @param array $phrases Extracted phrases with their elements and categories
     * @param array $contentBlocks Extracted content blocks with their elements and categories
     * @param array $translations Translation map
     * @param string|null $defaultCategory Default category (fallback)
     * @return void
     */
    protected function applyBodyTranslations(DOMDocument $doc, array $phrases, array $contentBlocks, array $translations, $defaultCategory = null)
    {
        // Apply phrase translations (text-only blocks)
        foreach ($phrases as $phraseData) {
            if (!isset($phraseData['element']) || !($phraseData['element'] instanceof DOMElement)) {
                continue;
            }

            $originalText = $phraseData['text'];
            // Use item's category or fall back to default
            $itemCategory = isset($phraseData['category']) ? $phraseData['category'] : $defaultCategory;
            $translated = $this->lookupTranslation($originalText, $itemCategory, $translations);

            if ($translated !== $originalText) {
                // Replace text content while preserving structure (br tags, etc.)
                $this->replaceTextContent($phraseData['element'], $originalText, $translated);
            }
        }

        // Apply content block translations
        foreach ($contentBlocks as $block) {
            if (!isset($block['element']) || !($block['element'] instanceof DOMElement)) {
                continue;
            }

            // Use block's category or fall back to default
            $blockCategory = isset($block['category']) ? $block['category'] : $defaultCategory;
            $cat = $blockCategory !== null ? $blockCategory : '__uncategorized__';

            $customId = $block['customId'];
            $blockTranslations = isset($translations[$cat][$customId]) ? $translations[$cat][$customId] : null;

            if (!is_array($blockTranslations) || empty($blockTranslations)) {
                continue; // No translations for this content block
            }

            // Apply translations within the content block
            $this->applyContentBlockTranslations($block['element'], $blockTranslations);
        }
    }

    /**
     * Replace text content of an element.
     *
     * @param DOMElement $element The element
     * @param string $original Original text
     * @param string $translated Translated text
     * @return void
     */
    protected function replaceTextContent(DOMElement $element, $original, $translated)
    {
        // For simple text-only elements, just replace textContent
        // This handles cases like <p>Hello</p> -> <p>Hola</p>
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMText) {
                $normalizedText = trim(preg_replace('/\s+/', ' ', $child->textContent));
                if ($normalizedText === $original) {
                    // Preserve leading/trailing whitespace pattern
                    $leadingSpace = preg_match('/^\s/', $child->textContent) ? ' ' : '';
                    $trailingSpace = preg_match('/\s$/', $child->textContent) ? ' ' : '';
                    $child->textContent = $leadingSpace . $translated . $trailingSpace;
                    return;
                }
            }
        }

        // Fallback: just set textContent (may lose br tags)
        $element->textContent = $translated;
    }

    /**
     * Apply translations within a content block element.
     *
     * @param DOMElement $element The content block element
     * @param array $blockTranslations Map of [original => translated]
     * @return void
     */
    protected function applyContentBlockTranslations(DOMElement $element, array $blockTranslations)
    {
        $this->walkAndTranslate($element, $blockTranslations);
    }

    /**
     * Walk element and apply translations to text nodes and attributes.
     *
     * @param DOMNode $node The node to process
     * @param array $translations Map of [original => translated]
     * @return void
     */
    protected function walkAndTranslate(DOMNode $node, array $translations)
    {
        // Handle text nodes
        if ($node instanceof DOMText) {
            $normalizedText = trim(preg_replace('/\s+/', ' ', $node->textContent));
            if ($normalizedText !== '' && isset($translations[$normalizedText])) {
                $translated = $translations[$normalizedText];
                if ($translated !== '' && $translated !== null && $translated !== $normalizedText) {
                    // Preserve whitespace pattern
                    $leadingSpace = preg_match('/^\s/', $node->textContent) ? ' ' : '';
                    $trailingSpace = preg_match('/\s$/', $node->textContent) ? ' ' : '';
                    $node->textContent = $leadingSpace . $translated . $trailingSpace;
                }
            }
            return;
        }

        // Handle element nodes
        if ($node instanceof DOMElement) {
            // Skip translate="no" or data-notrans
            if ($node->getAttribute('translate') === 'no' || $node->getAttribute('data-notrans')) {
                return;
            }

            // Translate attributes
            $translatableAttrs = $this->htmlParser->getTranslatableAttributes();
            foreach ($translatableAttrs as $attr) {
                if ($node->hasAttribute($attr)) {
                    $value = $node->getAttribute($attr);
                    if ($value !== '' && isset($translations[$value])) {
                        $translated = $translations[$value];
                        if ($translated !== '' && $translated !== null) {
                            $node->setAttribute($attr, $translated);
                        }
                    }
                }
            }

            // Handle button/input values
            $tagName = strtolower($node->tagName);
            if ($tagName === 'button' && $node->hasAttribute('value')) {
                $value = $node->getAttribute('value');
                if ($value !== '' && isset($translations[$value])) {
                    $translated = $translations[$value];
                    if ($translated !== '' && $translated !== null) {
                        $node->setAttribute('value', $translated);
                    }
                }
            }
            if ($tagName === 'input' && $node->hasAttribute('value')) {
                $type = strtolower($node->getAttribute('type'));
                if ($type === 'submit' || $type === 'button') {
                    $value = $node->getAttribute('value');
                    if ($value !== '' && isset($translations[$value])) {
                        $translated = $translations[$value];
                        if ($translated !== '' && $translated !== null) {
                            $node->setAttribute('value', $translated);
                        }
                    }
                }
            }

            // Recurse into children
            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $child) {
                    $this->walkAndTranslate($child, $translations);
                }
            }
        }
    }

    /**
     * Look up translation for a phrase.
     *
     * @param string $phrase The phrase
     * @param string|null $category The category
     * @param array $translations Translation map
     * @param string|null $contentBlockId Content block ID for nested lookup
     * @return string Translated phrase or original if not found/empty
     */
    protected function lookupTranslation($phrase, $category, array $translations, $contentBlockId = null)
    {
        $cat = $category !== null ? $category : '__uncategorized__';

        if ($contentBlockId !== null) {
            // Content block phrase lookup
            if (isset($translations[$cat][$contentBlockId][$phrase])) {
                $value = $translations[$cat][$contentBlockId][$phrase];
                if ($value !== '' && $value !== null) {
                    return $value;
                }
            }
        } else {
            // Standalone phrase lookup
            if (isset($translations[$cat][$phrase])) {
                $value = $translations[$cat][$phrase];
                // Only return if it's a string (not content block) and not empty
                if (!is_array($value) && $value !== '' && $value !== null) {
                    return $value;
                }
            }
        }

        // Fallback to original
        return $phrase;
    }

    /**
     * Save document back to HTML string.
     *
     * @param DOMDocument $doc The document
     * @return string HTML string
     */
    protected function saveHtml(DOMDocument $doc)
    {
        // saveHTML can add extra wrapper elements, clean up
        $html = $doc->saveHTML();

        // Remove the XML declaration we may have added
        $html = preg_replace('/^<\?xml[^>]*>\s*/i', '', $html);

        return $html;
    }

    /**
     * Get the cache key for registered items.
     *
     * @param string|null $category Category
     * @return string Cache key
     */
    protected function getRegisteredItemsCacheKey($category = null)
    {
        $cat = $category !== null ? $category : '__uncategorized__';
        return 'registered_items_' . $cat;
    }

    /**
     * Get already registered items from cache.
     *
     * This prevents re-registration of items that were already sent to the API
     * but haven't been translated yet (and thus don't appear in translations).
     *
     * @param string|null $category Category
     * @return array ['phrases' => [...], 'contentBlocks' => [...]]
     */
    protected function getRegisteredItems($category = null)
    {
        $cacheKey = $this->getRegisteredItemsCacheKey($category);
        $cache = $this->client->getCache();

        $cached = $cache->get($cacheKey);
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        return [
            'phrases' => [],
            'contentBlocks' => [],
        ];
    }

    /**
     * Mark items as registered in cache.
     *
     * @param array $phrases Phrases that were registered
     * @param array $contentBlocks Content blocks that were registered
     * @param string|null $category Category
     * @return void
     */
    protected function markItemsAsRegistered(array $phrases, array $contentBlocks, $category = null)
    {
        $cacheKey = $this->getRegisteredItemsCacheKey($category);
        $cache = $this->client->getCache();

        // Get existing registered items
        $existing = $this->getRegisteredItems($category);

        // Merge new items
        $existing['phrases'] = array_unique(array_merge(
            $existing['phrases'],
            $phrases
        ));

        $contentBlockIds = [];
        foreach ($contentBlocks as $block) {
            $contentBlockIds[] = $block['customId'];
        }
        $existing['contentBlocks'] = array_unique(array_merge(
            $existing['contentBlocks'],
            $contentBlockIds
        ));

        // Store in cache (use a long TTL since these are permanent registrations)
        // The cache will be invalidated when translations are updated
        $cache->set($cacheKey, $existing);
    }

    /**
     * Collect all unique categories used by phrases and content blocks.
     *
     * @param array $headPhrases Head section phrases (use default category)
     * @param array $bodyPhrases Body phrases with their categories
     * @param array $contentBlocks Content blocks with their categories
     * @param string|null $defaultCategory Default category
     * @return array Unique category names
     */
    protected function collectUsedCategories(array $headPhrases, array $bodyPhrases, array $contentBlocks, $defaultCategory = null)
    {
        $categories = [];

        // Default category (used by head phrases)
        $categories[] = $defaultCategory !== null ? $defaultCategory : '__uncategorized__';

        // Body phrases
        foreach ($bodyPhrases as $phrase) {
            if (isset($phrase['category']) && $phrase['category'] !== null) {
                $categories[] = $phrase['category'];
            }
        }

        // Content blocks
        foreach ($contentBlocks as $block) {
            if (isset($block['category']) && $block['category'] !== null) {
                $categories[] = $block['category'];
            }
        }

        return array_unique($categories);
    }

    /**
     * Add category to phrases that don't have one.
     *
     * @param array $phrases Array of phrase strings or arrays
     * @param string|null $category Category to add
     * @return array Phrases with category added
     */
    protected function addCategoryToPhrases(array $phrases, $category = null)
    {
        $cat = $category !== null ? $category : '__uncategorized__';
        $result = [];

        foreach ($phrases as $phrase) {
            if (is_string($phrase)) {
                $result[] = [
                    'text' => $phrase,
                    'category' => $cat,
                ];
            } else {
                // Already has structure, add category if missing
                if (!isset($phrase['category'])) {
                    $phrase['category'] = $cat;
                }
                $result[] = $phrase;
            }
        }

        return $result;
    }

    /**
     * Find new phrases (per-item category aware).
     *
     * @param array $phrases Phrases with their categories
     * @param array $translations Translation map
     * @param array $registeredItems Registered items per category
     * @return array New phrases with their categories
     */
    protected function findNewPhrasesWithCategory(array $phrases, array $translations, array $registeredItems)
    {
        $newPhrases = [];
        $seen = []; // Track unique phrase+category combinations

        foreach ($phrases as $phraseData) {
            $text = is_string($phraseData) ? $phraseData : (isset($phraseData['text']) ? $phraseData['text'] : null);
            if ($text === null) {
                continue;
            }

            $cat = isset($phraseData['category']) ? $phraseData['category'] : '__uncategorized__';
            $key = $cat . '::' . $text;

            // Skip duplicates within this batch
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;

            // Check if already registered locally
            $catRegistered = isset($registeredItems[$cat]) ? $registeredItems[$cat] : ['phrases' => [], 'contentBlocks' => []];
            if (in_array($text, $catRegistered['phrases'], true)) {
                continue;
            }

            // Check if exists in translations
            $categoryTranslations = isset($translations[$cat]) ? $translations[$cat] : [];
            if (array_key_exists($text, $categoryTranslations) && !is_array($categoryTranslations[$text])) {
                continue;
            }

            $newPhrases[] = [
                'text' => $text,
                'category' => $cat,
            ];
        }

        return $newPhrases;
    }

    /**
     * Find new content blocks (per-item category aware).
     *
     * @param array $contentBlocks Content blocks with their categories
     * @param array $translations Translation map
     * @param array $registeredItems Registered items per category
     * @return array New content blocks
     */
    protected function findNewContentBlocksWithCategory(array $contentBlocks, array $translations, array $registeredItems)
    {
        $newBlocks = [];

        foreach ($contentBlocks as $block) {
            $customId = $block['customId'];
            $cat = isset($block['category']) ? $block['category'] : '__uncategorized__';

            // Check if already registered locally
            $catRegistered = isset($registeredItems[$cat]) ? $registeredItems[$cat] : ['phrases' => [], 'contentBlocks' => []];
            if (in_array($customId, $catRegistered['contentBlocks'], true)) {
                continue;
            }

            // Check if content block exists in translations
            $categoryTranslations = isset($translations[$cat]) ? $translations[$cat] : [];
            if (array_key_exists($customId, $categoryTranslations) && is_array($categoryTranslations[$customId])) {
                continue;
            }

            $newBlocks[] = $block;
        }

        return $newBlocks;
    }

    /**
     * Register new items with per-item categories.
     *
     * @param array $newPhrases Phrases with their categories
     * @param array $newContentBlocks Content blocks with their categories
     * @param string $locale Target locale
     * @return void
     */
    protected function registerNewItemsWithCategory(array $newPhrases, array $newContentBlocks, $locale)
    {
        // Silent skip if no write permission
        try {
            if (!$this->client->canWrite()) {
                return;
            }
        } catch (\Exception $e) {
            return;
        }

        // Register phrases (grouped by category for efficiency)
        if (!empty($newPhrases)) {
            try {
                $phraseData = [];
                foreach ($newPhrases as $phrase) {
                    $phraseData[] = [
                        'phrase' => $phrase['text'],
                        'category' => $phrase['category'],
                    ];
                }
                $this->client->registerPhrases($phraseData);
            } catch (\Exception $e) {
                // Silent failure
            }
        }

        // Register content blocks
        foreach ($newContentBlocks as $block) {
            try {
                $this->client->registerContentBlock(
                    $block['html'],
                    $block['category'],
                    null, // label
                    $block['customId']
                );
            } catch (\Exception $e) {
                // Silent failure
            }
        }
    }

    /**
     * Mark items as registered with per-item categories.
     *
     * @param array $phrases Phrases with their categories
     * @param array $contentBlocks Content blocks with their categories
     * @return void
     */
    protected function markItemsAsRegisteredWithCategory(array $phrases, array $contentBlocks)
    {
        // Group items by category
        $phrasesByCategory = [];
        foreach ($phrases as $phrase) {
            $cat = isset($phrase['category']) ? $phrase['category'] : '__uncategorized__';
            if (!isset($phrasesByCategory[$cat])) {
                $phrasesByCategory[$cat] = [];
            }
            $phrasesByCategory[$cat][] = $phrase['text'];
        }

        $blocksByCategory = [];
        foreach ($contentBlocks as $block) {
            $cat = isset($block['category']) ? $block['category'] : '__uncategorized__';
            if (!isset($blocksByCategory[$cat])) {
                $blocksByCategory[$cat] = [];
            }
            $blocksByCategory[$cat][] = $block;
        }

        // Mark items per category
        $allCategories = array_unique(array_merge(
            array_keys($phrasesByCategory),
            array_keys($blocksByCategory)
        ));

        foreach ($allCategories as $cat) {
            $catPhrases = isset($phrasesByCategory[$cat]) ? $phrasesByCategory[$cat] : [];
            $catBlocks = isset($blocksByCategory[$cat]) ? $blocksByCategory[$cat] : [];

            if (!empty($catPhrases) || !empty($catBlocks)) {
                $this->markItemsAsRegistered($catPhrases, $catBlocks, $cat);
            }
        }
    }
}
