<?php

namespace Langsys\SDK\Html;

use Langsys\SDK\Locale\LocaleDetector;

use Langsys\SDK\Log\LoggerInterface;
use Langsys\SDK\Log\NullLogger;
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
     * Elements the page walk does not descend into (TOK-1).
     *
     * `svg` is deliberately NOT here: SVG `<text>` is visible copy. It is not a
     * block element either, so an icon inside a paragraph never makes the
     * walker recurse past the paragraph's own text; an `<svg>` is part of the
     * unit that holds it, or a unit of its own.
     */
    const SKIP_ELEMENTS = [
        'script', 'style', 'noscript', 'template', 'math',
        // The head handler's, and walked only on a page with no <body>.
        'head',
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
     * @var MarkupTokenizer
     */
    protected $markupTokenizer;

    /**
     * @var array Placeholder values for the current translate() call.
     */
    protected $params = [];

    /**
     * @var string|null Target locale for the current translate() call.
     */
    protected $currentLocale = null;

    /**
     * @var ValueMarkers|null The value markers read from the current document.
     */
    protected $markers = null;

    /**
     * @var bool Whether the current page's <html> is one parseDocument() added
     *           to a fragment, written back out as the fragment it was.
     */
    protected $fragmentRoot = false;

    /** Elements a browser puts in the head a page leaves implied. */
    const HEAD_CONTENT = ['base', 'link', 'meta', 'noscript', 'script', 'style', 'template', 'title'];

    /**
     * @var LoggerInterface|null Defaulted in the CONSTRUCTOR, not here.
     *
     * PHP cannot `new` in a property initializer, so this is null on any
     * instance built WITHOUT running this constructor. That is a real limit,
     * not a claim to round off, and reflection is not the only way in: a
     * subclass whose constructor skips parent::__construct(), or a plain
     * unserialize(), produces one too. Every instance that runs this
     * constructor is safe.
     */
    protected $logger;

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
        $this->markupTokenizer = new MarkupTokenizer();

        // Declared and defaulted, never assumed. A debug() call was added to
        // this class against a $this->logger that did not exist: on the path
        // that reaches it the warning became "Call to a member function
        // debug() on null" and took the render down - a line whose entire
        // purpose was to avoid failing silently, failing loudly instead, in
        // the one fallback that exists for markup we are about to lose.
        //
        // NullLogger rather than null, so the property is always callable even
        // when the client cannot supply one (a test double, a partially
        // constructed client). This is the CONSTRUCTOR's default - the property
        // declaration above cannot carry it, since PHP forbids `new` there.
        $this->logger = (is_object($client) && method_exists($client, 'getLogger') && $client->getLogger() !== null)
            ? $client->getLogger()
            : new NullLogger();
    }

    /**
     * Translate an entire HTML page.
     *
     * @param string $html Full HTML document
     * @param string $locale Target locale (e.g., 'es-es')
     * @param string|null $defaultCategory Default category/name (can be overridden by data-langsys-category attribute)
     * @param array $selectorCategories Map of CSS selector => category config
     * @param array $params Placeholder values applied page-wide
     * @return string Translated HTML
     */
    public function translate($html, $locale, $defaultCategory = null, array $selectorCategories = [], array $params = [])
    {
        // Lowercase xx-yy on the wire and in every key, and in the lang attribute
        // written from it (WIRE-3).
        $locale = LocaleDetector::normalize($locale);

        // Per-call state; reset every time since the instance is reused.
        $this->params = $params;
        $this->currentLocale = $locale;
        $this->markers = null;

        try {
            return $this->translateDocument($html, $locale, $defaultCategory, $selectorCategories, $params);
        } finally {
            // Do not retain caller data (or arm interp() for any future code
            // path outside this call tree) after the call returns, including
            // when it unwinds on an exception.
            $this->params = [];
            $this->currentLocale = null;
            $this->markers = null;
        }
    }

    /**
     * @param string $html
     * @param string $locale
     * @param string|null $defaultCategory
     * @param array $selectorCategories
     * @param array $params
     * @return string
     */
    protected function translateDocument($html, $locale, $defaultCategory, array $selectorCategories, array $params)
    {
        if (empty($html)) {
            return $html;
        }

        // Parse document. One with no element - whitespace, a comment, a
        // doctype - has nothing to translate and comes back as given.
        $doc = $this->parseDocument($html);
        if ($doc === null || $doc->documentElement === null) {
            return $html;
        }

        // Value markers read as placeholders before anything is tokenized
        // (VAR-3); finish() puts the values back before the page is saved.
        $this->markers = ValueMarkers::read($doc->documentElement);

        // Create selector matcher if selector categories provided
        $this->selectorMatcher = !empty($selectorCategories)
            ? $this->selectorMatcher($selectorCategories)
            : null;

        // Extract first: whether a seeded snapshot answers the page is decided
        // by what the page holds.
        $headPhrases = $this->headHandler->extractPhrases($doc);

        // Process body section (respects data-langsys-category attributes and selector categories)
        $bodyResult = $this->processBody($doc, $defaultCategory);
        $bodyPhrases = $bodyResult['phrases'];
        $contentBlocks = $bodyResult['contentBlocks'];

        // SNAP-2: a seeded snapshot that holds every unit of the page answers it
        // with no fetch. Otherwise the live catalog is read; if it cannot be,
        // the snapshot still renders what it holds. Either way the snapshot
        // never decides a registration (REG-13).
        $seed = $this->client->seedCatalog($locale);
        $fromSeed = $seed !== null && $this->seedHolds($seed, $this->addCategoryToPhrases($headPhrases, $defaultCategory), $bodyPhrases, $contentBlocks);

        if ($fromSeed) {
            $translations = $seed;
        } else {
            try {
                $translations = $this->client->getTranslations($locale);
            } catch (\Throwable $e) {
                // \Throwable, not \Exception: an \Error from a wrong-shaped cache
                // hit escaped this catch and took the whole page render down.
                if ($seed === null) {
                    return $html;
                }

                $translations = $seed;
                $fromSeed = true;
            }
        }

        // Head: apply translations and set lang/charset. Placeholders must
        // resolve here too, or <title> and meta description ship raw {name}
        // to the browser while the body renders correctly.
        $this->headHandler->useInterpolation($this->client->getInterpolator(), $params, $locale);
        $this->headHandler->process($doc, $locale, $translations, $defaultCategory);

        if ($fromSeed) {
            $this->applyBodyTranslations($doc, $bodyPhrases, $contentBlocks, $translations, $defaultCategory);
            $this->markers->finish($doc->documentElement);
            $this->markResolvedRoot($doc, $locale);

            return $this->saveHtml($doc);
        }

        // Collect all unique categories used
        $usedCategories = $this->collectUsedCategories($headPhrases, $bodyPhrases, $contentBlocks, $defaultCategory);

        // Get already registered items from cache for each category
        $registeredItems = [];
        foreach ($usedCategories as $cat) {
            $registeredItems[$cat] = $this->getRegisteredItems($cat);
        }

        // Identify new items (not in translations AND not already registered)
        // Head phrases use default category, body phrases use their own category.
        // A unit in a resolved subtree is translated like any other but is never a
        // registration candidate: its text is output, not source (GATE-10).
        $heads = $doc->getElementsByTagName('head');
        $newHeadPhrases = ($heads->length > 0 && HtmlParser::isResolvedScope($heads->item(0))) ? [] : $this->findNewPhrasesWithCategory(
            $this->addCategoryToPhrases($headPhrases, $defaultCategory),
            $translations,
            $registeredItems
        );
        $newBodyPhrases = $this->findNewPhrasesWithCategory(
            $this->withText($this->outsideResolvedScope($bodyPhrases)),
            $translations,
            $registeredItems
        );
        $newPhrases = array_merge($newHeadPhrases, $newBodyPhrases);

        $newContentBlocks = $this->findNewContentBlocksWithCategory(
            $this->withText($this->outsideResolvedScope($contentBlocks)),
            $translations,
            $registeredItems
        );

        // Register new items (silent skip if read-only)
        if (!empty($newPhrases) || !empty($newContentBlocks)) {
            $this->registerNewItemsWithCategory($newPhrases, $newContentBlocks, $locale);

            // NOT marked as registered here. These items have only been QUEUED;
            // the flush that would send them runs later and can skip (read-only
            // key), fail, or never run at all on a hard timeout. Writing the
            // marker now records an attempt as an acceptance, and the marker
            // suppresses the item on every later render until the cache expires -
            // so a single failed flush silently costs the content indefinitely.
            //
            // Without the marker a queued-but-unsent block is re-queued next
            // render, which is cheap: the flush batches, and the API is
            // idempotent per project. Re-doing cheap work beats recording work
            // that never happened.

            // No refetch: registration is queued now, so nothing has been
            // created yet and the catalog cannot have changed. The flush clears
            // the cache after it runs, so the next request sees new translations.
        }

        // Apply body translations
        $this->applyBodyTranslations($doc, $bodyPhrases, $contentBlocks, $translations, $defaultCategory);
        $this->markers->finish($doc->documentElement);

        $this->markResolvedRoot($doc, $locale);

        // Return translated HTML
        return $this->saveHtml($doc);
    }

    /**
     * Whether a snapshot's catalog holds every unit of a page: each phrase in
     * its category, and each block under its id.
     *
     * @param array $seed category => entries
     * @param array $headPhrases With categories
     * @param array $bodyPhrases
     * @param array $contentBlocks
     * @return bool
     */
    protected function seedHolds(array $seed, array $headPhrases, array $bodyPhrases, array $contentBlocks)
    {
        foreach (array_merge($headPhrases, $bodyPhrases) as $phrase) {
            $cat = isset($phrase['category']) ? $phrase['category'] : '__uncategorized__';
            if (!isset($seed[$cat]) || !is_array($seed[$cat]) || !array_key_exists($phrase['text'], $seed[$cat]) || is_array($seed[$cat][$phrase['text']])) {
                return false;
            }
        }

        foreach ($contentBlocks as $block) {
            $cat = isset($block['category']) ? $block['category'] : '__uncategorized__';
            if (!isset($seed[$cat][$block['customId']]) || !is_array($seed[$cat][$block['customId']])) {
                return false;
            }
        }

        return true;
    }

    /**
     * The units that may register: not one made only of value markers
     * (VAR-3), nor one holding a value the reader cannot name (VAR-7).
     *
     * @param array $units Collected phrases or content blocks
     * @return array
     */
    protected function withText(array $units)
    {
        $markers = $this->markers;
        if ($markers === null || (!$markers->any() && !$markers->unnamedWithin())) {
            return $units;
        }

        $logger = $this->logger;

        return array_values(array_filter($units, function ($unit) use ($markers, $logger) {
            // A value the reader cannot name is never registered (VAR-7).
            if (isset($unit['element']) && $unit['element'] instanceof DOMNode && $markers->unnamedWithin($unit['element'])) {
                if (ValueMarkers::firstNotice()) {
                    $logger->debug('A value marker names its value outside [a-z][a-z0-9_]*, so text holding it is not registered');
                }

                return false;
            }

            return !$markers->markerOnly(isset($unit['phrases']) ? $unit['phrases'] : [$unit['text']]);
        }));
    }

    /**
     * The units whose text is source: those outside any resolved subtree.
     *
     * @param array $units Collected phrases or content blocks
     * @return array
     */
    protected function outsideResolvedScope(array $units)
    {
        return array_values(array_filter($units, function ($unit) {
            $scope = isset($unit['scope']) ? $unit['scope'] : (isset($unit['element']) ? $unit['element'] : null);

            return !($scope instanceof \DOMNode && HtmlParser::isResolvedScope($scope));
        }));
    }

    /**
     * Mark the document root as resolved when this render is in a locale other
     * than the project's base locale (GATE-10, producing).
     *
     * The page this returns is then output, not source: a later walk - a JS SDK
     * hydrating it, a middleware translating the response again - registers
     * none of it. A base-locale render is source and is left unmarked so it stays
     * discoverable, and when the base locale is unknown nothing is marked, since
     * the render cannot be shown to be anything but source. A fragment has no
     * root of its own, so each of its top-level elements is marked. A marker
     * already on one, in either spelling, is the page author's and is kept.
     *
     * @param DOMDocument $doc
     * @param string $locale
     * @return void
     */
    protected function markResolvedRoot(DOMDocument $doc, $locale)
    {
        $htmls = $doc->getElementsByTagName('html');
        $root = $htmls->length > 0 ? $htmls->item(0) : $doc->documentElement;

        // A fragment's markers go on its top-level elements, the <html> it
        // was read in being the SDK's.
        $roots = [];
        foreach ($this->fragmentRoot && $root instanceof DOMElement ? $root->childNodes : [$root] as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            foreach (HtmlParser::RESOLVED_MARKERS as $attribute) {
                if ($node->hasAttribute($attribute)) {
                    continue 2;
                }
            }
            $roots[] = $node;
        }

        if ($roots === []) {
            return;
        }

        try {
            $project = $this->client->getProject();
        } catch (\Throwable $e) {
            return;
        }

        $base = (is_array($project) && isset($project['base_locale']) && is_string($project['base_locale']))
            ? LocaleDetector::normalize($project['base_locale'])
            : null;

        if ($base === null || $base === '' || $base === $locale) {
            return;
        }

        foreach ($roots as $element) {
            $element->setAttribute(HtmlParser::RESOLVED_MARKERS[0], $locale);
        }
    }

    /**
     * A matcher for the selector categories it can read. One it cannot - a
     * pseudo-class, a combinator, an empty selector - is skipped with a
     * warning, so the page still renders under the rest (WIRE-4).
     *
     * @param array $selectorCategories
     * @return SelectorMatcher
     */
    protected function selectorMatcher(array $selectorCategories)
    {
        $readable = [];

        foreach ($selectorCategories as $selector => $config) {
            try {
                new SelectorMatcher([$selector => $config]);
                $readable[$selector] = $config;
            } catch (\Exception $e) {
                $this->logger->warning('A selector category is skipped: its selector cannot be matched', ['selector' => (string) $selector, 'error' => $e->getMessage()]);
            }
        }

        return new SelectorMatcher($readable);
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

        // Decoded as a browser decodes it, then read as the UTF-8 it now is,
        // whatever its metas say (Document ignores them).
        $html = Charset::toUtf8($html);

        // A page without an <html> is read inside one, as a browser reads it,
        // opened after its doctype and never closed, so every top-level
        // element is part of the page. One that is a document - a doctype, a
        // head, a body or a title - keeps it; a fragment is written back as
        // the fragment it was. Both are read from the page's tags, never from
        // text that mentions one.
        $this->fragmentRoot = false;
        $tags = Document::startTags($html);
        if ($tags !== [] && !in_array('html', $tags, true)) {
            $prolog = substr($html, 0, Document::prologLength($html));
            $this->fragmentRoot = stripos($prolog, '<!doctype') === false && array_intersect(['head', 'body', 'title'], $tags) === [];
            $html = $prolog . '<html>' . substr($html, strlen($prolog));
        }

        $loaded = Document::load($doc, '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        libxml_clear_errors();
        libxml_use_internal_errors($internalErrors);

        if (!$loaded) {
            return null;
        }

        if (!$this->fragmentRoot) {
            $this->impliedHead($doc);
        }

        return $doc;
    }

    /**
     * Give a page whose <html> has no <head> the one a browser implies: the
     * head elements the page leads with - its title, metas, links, styles,
     * scripts - or none, ahead of its body, so its title translates and its
     * charset is declared there.
     *
     * @param DOMDocument $doc
     * @return void
     */
    protected function impliedHead(DOMDocument $doc)
    {
        $html = $doc->getElementsByTagName('html')->item(0);
        if (!$html instanceof DOMElement || $doc->getElementsByTagName('head')->length > 0) {
            return;
        }

        $leading = [];
        $elements = 0;
        $body = null;
        foreach ($html->childNodes as $child) {
            if ($child instanceof DOMElement && in_array(strtolower($child->nodeName), self::HEAD_CONTENT, true)) {
                $leading[] = $child;
                $elements++;
                continue;
            }
            if ($child instanceof \DOMComment || ($child instanceof \DOMText && trim($child->data) === '')) {
                $leading[] = $child;
                continue;
            }
            $body = $child;
            break;
        }

        $head = $doc->createElement('head');

        // Nothing to move: the comments it leads with stay in the <html>.
        if ($elements === 0) {
            $html->insertBefore($head, $body);
            return;
        }

        $html->insertBefore($head, $html->firstChild);
        foreach ($leading as $node) {
            $head->appendChild($node);
        }
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

            // Skip elements excluded from translation entirely.
            if (HtmlParser::isTranslationExcluded($child)) {
                continue;
            }

            // Determine effective category using priority rules
            $effectiveCategory = $this->determineEffectiveCategory($child, $inheritedCategory);

            // data-langsys-phrase wins over data-langsys-contentblock: it is the
            // narrower, more explicit instruction ("this run is ONE phrase").
            if (HtmlParser::isPhraseMarked($child)) {
                $this->extractAsTokenizedPhrase($child, $phrases, $effectiveCategory);
                $this->extractNestedHosts($child, $phrases, $contentBlocks, $effectiveCategory);
                continue;
            }

            // Check for data-langsys-contentblock - treat entire element as single content block
            if ($this->hasContentBlockAttribute($child)) {
                $this->extractAsContentBlock($child, $contentBlocks, $effectiveCategory);
                $this->extractNestedHosts($child, $phrases, $contentBlocks, $effectiveCategory);
                continue;
            }

            // A container of blocks is walked; anything else is a unit
            // (TOK-6), including a void or inline element directly under a
            // container - an `<img alt>` or `<a title>` under `<body>`.
            if ($this->containsNestedBlocks($child)) {
                $this->walkForExtraction($child, $phrases, $contentBlocks, $effectiveCategory);
                continue;
            }

            $this->extractUnit($child, $phrases, $contentBlocks, $effectiveCategory);
            $this->extractNestedHosts($child, $phrases, $contentBlocks, $effectiveCategory);
        }
    }

    /**
     * Register each outermost marked host inside $element as a unit of its own
     * (MARK-4): the walk that tokenized $element excised it, so its words are
     * registered here, on its own terms, and nowhere else.
     *
     * @param DOMElement $element
     * @param array &$phrases
     * @param array &$contentBlocks
     * @param string|null $category
     * @return void
     */
    protected function extractNestedHosts(DOMElement $element, array &$phrases, array &$contentBlocks, $category)
    {
        foreach ($element->childNodes as $child) {
            if (!$child instanceof DOMElement
                || HtmlParser::isTranslationExcluded($child)
                || in_array(strtolower($child->tagName), self::SKIP_ELEMENTS, true)) {
                continue;
            }

            if (!HtmlParser::isMarkedHost($child)) {
                $this->extractNestedHosts($child, $phrases, $contentBlocks, $category);
                continue;
            }

            $hostCategory = $this->determineEffectiveCategory($child, $category);

            if (HtmlParser::isPhraseMarked($child)) {
                $this->extractAsTokenizedPhrase($child, $phrases, $hostCategory);
            } else {
                $this->extractAsContentBlock($child, $contentBlocks, $hostCategory);
            }

            $this->extractNestedHosts($child, $phrases, $contentBlocks, $hostCategory);
        }
    }

    /**
     * An element and its descendants, stopping at a marked host below it, and
     * at an element its author excluded or one whose text is not prose, which
     * is read for nothing.
     *
     * @param DOMElement $element
     * @return DOMElement[]
     */
    protected function ownElements(DOMElement $element)
    {
        $elements = [$element];

        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && !HtmlParser::isMarkedHost($child) && !HtmlParser::isTranslationExcluded($child)
                && !in_array(strtolower($child->nodeName), HtmlParser::NON_PROSE_ELEMENTS, true)) {
                foreach ($this->ownElements($child) as $own) {
                    $elements[] = $own;
                }
            }
        }

        return $elements;
    }

    /**
     * Register one unit of the page walk (TOK-6).
     *
     * Its tokens are its own translatable attributes first, in TOK-3 order,
     * then its content. It registers as a phrase only when it has one token and
     * that token is its one text node, which is the only shape the phrase apply
     * can write back into: the text node is replaced in place, so an svg
     * drawing or inline markup around it survives. Anything else - several
     * tokens, or a single token held in an attribute - is a content block.
     *
     * @param DOMElement $element
     * @param array &$phrases
     * @param array &$contentBlocks
     * @param string|null $effectiveCategory
     * @return void
     */
    protected function extractUnit(DOMElement $element, array &$phrases, array &$contentBlocks, $effectiveCategory)
    {
        $unit = $this->htmlParser->unitTokens($element);

        if (empty($unit['tokens'])) {
            return;
        }

        $itemCategory = $effectiveCategory !== null ? $effectiveCategory : '__uncategorized__';

        if (HtmlParser::isPhraseUnit($unit)) {
            $phrases[] = [
                'text' => $unit['tokens'][0],
                'element' => $element,
                'category' => $itemCategory,
            ];

            return;
        }

        // The registered content must re-tokenize to the block's tokens, so a
        // unit whose own attributes carry tokens registers with its tag.
        $html = $this->htmlParser->ownTokens($element) === []
            ? $this->getInnerHtml($element)
            : Document::html($element);

        $contentBlocks[] = [
            'customId' => $this->htmlParser->generateCustomId($itemCategory, $unit['tokens']),
            'phrases' => $unit['tokens'],
            'element' => $element,
            'html' => $html,
            'category' => $itemCategory,
        ];
    }

    /**
     * Check if an element has the data-langsys-contentblock attribute with a truthy value.
     *
     * @param DOMElement $element The element to check
     * @return bool True if element should be treated as a content block
     */
    /**
     * Extract an element's whole subtree as ONE tokenized phrase.
     *
     * @param DOMElement $element
     * @param array &$phrases
     * @param string|null $effectiveCategory
     * @return void
     */
    protected function extractAsTokenizedPhrase(DOMElement $element, array &$phrases, $effectiveCategory)
    {
        $encoded = $this->markupTokenizer->encode($element);
        $category = $effectiveCategory !== null ? $effectiveCategory : '__uncategorized__';

        // A run with no text of its own - only elements kept whole - is no
        // phrase (TOK-1), but its attributes still are.
        $hasText = $this->markupTokenizer->hasText($encoded['text']);
        if ($hasText) {
            $phrases[] = [
                'text' => $encoded['text'],
                'element' => $element,
                'category' => $category,
                'slots' => $encoded['slots'],
                'tokenized' => true,
            ];
        }

        // Translatable attributes are not part of the tokenized text, so without
        // this they would be silently dropped - adding the marker to keep a
        // sentence together would un-translate the element's title/alt/placeholder.
        // A host with no phrase of its own has them applied on their own.
        foreach ($this->collectAttributePhrases($element) as $attributePhrase) {
            $phrases[] = [
                'text' => $attributePhrase,
                'element' => null,
                'scope' => $element,
                'category' => $category,
                'attributeOnly' => true,
            ] + ($hasText ? [] : ['host' => $element]);
        }
    }

    /**
     * Collect translatable attribute values from an element and its descendants.
     *
     * @param DOMElement $element
     * @return array
     */
    protected function collectAttributePhrases(DOMElement $element)
    {
        $found = [];
        $attributes = $this->htmlParser->getTranslatableAttributes();

        // TOK-4, and a register/lookup pair: collection trimmed while the apply
        // side looked up the RAW value, so an alt text wrapped across source
        // lines registered collapsed and was never found again - a miss on
        // plain spaces, not only on non-ASCII whitespace. A nested marked host's
        // attributes are its own.
        foreach ($this->ownElements($element) as $descendant) {
            foreach ($attributes as $attr) {
                if ($descendant->hasAttribute($attr)) {
                    $value = Canonical::phrase($descendant->getAttribute($attr));
                    if ($value !== '') {
                        $found[] = $value;
                    }
                }
            }
        }

        return array_values(array_unique($found));
    }

    protected function hasContentBlockAttribute(DOMElement $element)
    {
        return HtmlParser::isContentBlockMarked($element);
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

        // MARK-3: a stamped id is the host's custom_id, recognised rather than
        // re-derived; the host renders from it and is never registered.
        $marker = HtmlParser::contentBlockMarker($element);
        $stamped = $marker !== null && $marker['kind'] === 'identity';

        $contentBlocks[] = [
            'customId' => $stamped ? $marker['id'] : $this->htmlParser->generateCustomId($itemCategory, $extractedPhrases),
            'phrases' => $extractedPhrases,
            'element' => $element,
            'html' => $innerHtml,
            'category' => $itemCategory,
            'stamped' => $stamped,
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
        // TOK-2: the page path registers phrases too, so it must canonicalise
        // identically to the block path or the same text yields two phrases.
        return Canonical::phrase($element->textContent);
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
            $innerHTML .= Document::html($child);
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
        // Content blocks first: they translate in place, so a block nested in a
        // phrase host is translated before that phrase's markup is rebuilt.
        foreach ($contentBlocks as $block) {
            if (!isset($block['element']) || !($block['element'] instanceof DOMElement)) {
                continue;
            }

            // Use block's category or fall back to default
            $blockCategory = isset($block['category']) ? $block['category'] : $defaultCategory;
            $cat = $blockCategory !== null ? $blockCategory : '__uncategorized__';

            $customId = $block['customId'];
            $categoryTranslations = isset($translations[$cat]) ? $translations[$cat] : [];
            $blockTranslations = !empty($block['stamped'])
                ? (isset($categoryTranslations[$customId]) && is_array($categoryTranslations[$customId]) ? $categoryTranslations[$customId] : null)
                : $this->client->resolveContentBlockTranslations(
                    $categoryTranslations, $cat, $block['phrases'], $customId, $this->htmlParser
                );

            if (!is_array($blockTranslations) || empty($blockTranslations)) {
                // No translations for this content block, but placeholders in the
                // original markup should still resolve.
                if (!empty($this->params)) {
                    $this->applyContentBlockTranslations($block['element'], []);
                }
            } else {
                $this->applyContentBlockTranslations($block['element'], $blockTranslations);
            }

            // MARK-1: the rendered host carries the id it was rendered from.
            HtmlParser::stampIdentity($block['element'], $customId);
        }

        // Then phrases, innermost first: a phrase host nested in another
        // (MARK-4) is translated before the outer one rebuilds its markup from
        // copies of its children.
        $attributesApplied = new \SplObjectStorage();
        foreach (array_reverse($phrases) as $phraseData) {
            // A host with no phrase of its own still has its attributes
            // translated, once (TOK-1).
            if (isset($phraseData['host']) && $phraseData['host'] instanceof DOMElement) {
                if (!$attributesApplied->contains($phraseData['host'])) {
                    $attributesApplied->attach($phraseData['host']);
                    $this->applyAttributeTranslations($phraseData['host'], isset($phraseData['category']) ? $phraseData['category'] : $defaultCategory, $translations);
                }
                continue;
            }

            if (!isset($phraseData['element']) || !($phraseData['element'] instanceof DOMElement)) {
                continue;
            }

            $originalText = $phraseData['text'];
            // Use item's category or fall back to default
            $itemCategory = isset($phraseData['category']) ? $phraseData['category'] : $defaultCategory;

            // Tokenized phrases rebuild their markup rather than replacing text.
            if (!empty($phraseData['tokenized'])) {
                $this->applyTokenizedPhrase($phraseData, $itemCategory, $translations);
                continue;
            }

            $translated = $this->interp($this->lookupTranslation($originalText, $itemCategory, $translations), $phraseData['element']);

            if ($translated !== $originalText) {
                // Replace text content while preserving structure (br tags, etc.)
                $this->replaceTextContent($phraseData['element'], $originalText, $translated);
            }
        }
    }

    /**
     * Apply a translation to a tokenized (data-langsys-phrase) element.
     *
     * The markup tokens are supplied as ordinary interpolation params with
     * sentinel values, so ICU substitutes them inside plural and select
     * branches exactly as it does any other argument. The element's children
     * are then rebuilt from the result.
     *
     * @param array $phraseData
     * @param string|null $itemCategory
     * @param array $translations
     * @return void
     */
    protected function applyTokenizedPhrase(array $phraseData, $itemCategory, array $translations)
    {
        $element = $phraseData['element'];

        // Slots are taken now, not at extraction: a marked host nested in this
        // one (MARK-4) has been translated since, and the rebuild copies it.
        $encoded = $this->markupTokenizer->encode($element, $this->markers);
        $slots = $encoded['slots'];

        $translated = $this->lookupTranslation($phraseData['text'], $itemCategory, $translations);

        // TOK-1: with no translation under its key, a phrase holding an
        // excluded element renders one under the key an earlier reader gave
        // it. Only the current key is ever registered.
        $cat = $itemCategory !== null ? $itemCategory : '__uncategorized__';
        $current = isset($translations[$cat][$phraseData['text']]) ? $translations[$cat][$phraseData['text']] : null;
        if ($encoded['earlier'] !== null && !(is_string($current) && $current !== '')) {
            $value = isset($translations[$cat][$encoded['earlier']['text']]) ? $translations[$cat][$encoded['earlier']['text']] : null;
            if (is_string($value) && $value !== '') {
                $translated = $this->markupTokenizer->fromEarlier($value, $encoded['earlier']);
            }
        }

        $tokenParams = $this->markupTokenizer->tokenParams(count($slots));
        $merged = array_merge($this->paramsFor($element, $translated), $tokenParams);

        $rendered = $this->client->getInterpolator()->interpolate($translated, $merged, $this->currentLocale);

        // A token naming a slot that does not exist is an UNKNOWN KEY to the
        // interpolator, so it survives verbatim rather than becoming a sentinel
        // - which would ship a literal "{m7o}" to the browser. Drop the markup
        // instead and keep the text.
        if ($this->markupTokenizer->hasTokens($rendered)) {
            $rendered = preg_replace('/\{m\d+[oc]\}/', '', $rendered);
            $rendered = $rendered === null ? $translated : $rendered;
            $slots = [];
        }

        $nodes = $this->markupTokenizer->render($rendered, $slots, $element->ownerDocument, $encoded['opaque']);

        // Replace the element's children with the rebuilt nodes.
        while ($element->firstChild !== null) {
            $element->removeChild($element->firstChild);
        }

        foreach ($nodes as $node) {
            $element->appendChild($node);
        }

        // Attributes live outside the tokenized text, so translate them here.
        $this->applyAttributeTranslations($element, $itemCategory, $translations);
    }

    /**
     * Translate translatable attributes on an element and its descendants.
     *
     * @param DOMElement $element
     * @param string|null $itemCategory
     * @param array $translations
     * @return void
     */
    protected function applyAttributeTranslations(DOMElement $element, $itemCategory, array $translations)
    {
        $attributes = $this->htmlParser->getTranslatableAttributes();

        $targets = $this->ownElements($element);

        foreach ($targets as $target) {
            foreach ($attributes as $attr) {
                if (!$target->hasAttribute($attr)) {
                    continue;
                }

                // The lookup side of the pair above: must canonicalise the
                // same way, or the key we registered can never be found.
                $value = Canonical::phrase($target->getAttribute($attr));
                if ($value === '') {
                    continue;
                }

                $translated = $this->interp($this->lookupTranslation($value, $itemCategory, $translations));

                if ($translated !== $value) {
                    $target->setAttribute($attr, $translated);
                }
            }
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
        // Find the text node carrying the phrase, at any depth, and replace it
        // in place.
        //
        // This used to check DIRECT children only and then fall back to
        // `$element->textContent = $translated`, which replaces every child
        // with a string. A phrase wrapped in inline markup - <li><a>Label</a>
        // </li>, <p><strong>Bold</strong></p> - has its text one level down,
        // so the wrapper was DELETED on translation: links disappeared from
        // translated pages, which for a nav item means the navigation stops
        // working.
        //
        // The direct-child loop that stood here was kept at first and then
        // removed: replaceDescendantText() checks direct children before it
        // recurses, so the two were the same comparison written twice - and
        // duplicating it made the first copy unmutable, since breaking it just
        // let the second copy answer. Two copies of one rule drifting apart is
        // the defect this whole lane keeps finding.
        if ($this->replaceDescendantText($element, $original, $translated)) {
            return;
        }

        // Last resort, and it loses markup. Reached only when no single text
        // node carries the phrase - the text is split across several nodes, as
        // in a tokenized run, which the tokenizer path handles instead. Logged
        // rather than silent, because losing markup should be visible.
        $this->logger->debug('Replacing an element\'s whole content; markup inside it will be lost', [
            'element' => $element->nodeName,
            'phrase' => $original,
        ]);

        $element->textContent = $translated;
    }

    /**
     * Replace the first descendant text node carrying $original, in place.
     *
     * Depth-first so the innermost wrapper wins, and skipping subtrees that are
     * excluded from translation or are not prose - a phrase must never be
     * written into a <script> or a node the author opted out of.
     *
     * @param DOMElement $element
     * @param string $original
     * @param string $translated
     * @return bool Whether a node was replaced
     */
    protected function replaceDescendantText(DOMElement $element, $original, $translated)
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMText) {
                if (Canonical::phrase($child->textContent) !== $original) {
                    continue;
                }

                $leadingSpace = preg_match('/^' . Whitespace::JS_WHITESPACE . '/u', $child->textContent) ? ' ' : '';
                $trailingSpace = preg_match('/' . Whitespace::JS_WHITESPACE . '$/u', $child->textContent) ? ' ' : '';
                $child->textContent = $leadingSpace . $translated . $trailingSpace;

                return true;
            }

            if (!$child instanceof DOMElement) {
                continue;
            }

            if (HtmlParser::isTranslationExcluded($child)
                || HtmlParser::isMarkedHost($child)
                || in_array(strtolower($child->nodeName), HtmlParser::NON_PROSE_ELEMENTS, true)) {
                continue;
            }

            if ($this->replaceDescendantText($child, $original, $translated)) {
                return true;
            }
        }

        return false;
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
            // Lookup side - must match what getElementText() registered.
            $normalizedText = Canonical::phrase($node->textContent);
            if ($normalizedText !== '') {
                $translated = isset($translations[$normalizedText]) ? $translations[$normalizedText] : null;

                // Fall back to the original so placeholders resolve even when
                // this block has no translation yet.
                if ($translated === '' || $translated === null) {
                    $translated = $normalizedText;
                }

                $translated = $this->interp($translated, $node);

                if ($translated !== $normalizedText) {
                    // Preserve whitespace pattern
                    $leadingSpace = preg_match('/^' . Whitespace::JS_WHITESPACE . '/u', $node->textContent) ? ' ' : '';
                    $trailingSpace = preg_match('/' . Whitespace::JS_WHITESPACE . '$/u', $node->textContent) ? ' ' : '';
                    $node->textContent = $leadingSpace . $translated . $trailingSpace;
                }
            }
            return;
        }

        // Handle element nodes
        if ($node instanceof DOMElement) {
            // Skip elements excluded from translation entirely, and those
            // registration reads no prose in: script, style, noscript,
            // template and math.
            if (HtmlParser::isTranslationExcluded($node) || in_array(strtolower($node->nodeName), HtmlParser::NON_PROSE_ELEMENTS, true)) {
                return;
            }

            // Translate attributes
            $translatableAttrs = $this->htmlParser->getTranslatableAttributes();
            foreach ($translatableAttrs as $attr) {
                if ($node->hasAttribute($attr)) {
                    $this->translateAttribute($node, $attr, $translations);
                }
            }

            // Handle button/input values
            $tagName = strtolower($node->tagName);
            if ($tagName === 'button' && $node->hasAttribute('value')) {
                $this->translateAttribute($node, 'value', $translations);
            }
            if ($tagName === 'input' && $node->hasAttribute('value')) {
                $type = strtolower($node->getAttribute('type'));
                if ($type === 'submit' || $type === 'button') {
                    $this->translateAttribute($node, 'value', $translations);
                }
            }

            // Recurse into children; a marked host is applied as its own unit.
            if ($node->hasChildNodes()) {
                foreach ($node->childNodes as $child) {
                    if ($child instanceof DOMElement && HtmlParser::isMarkedHost($child)) {
                        continue;
                    }

                    $this->walkAndTranslate($child, $translations);
                }
            }
        }
    }

    /**
     * Translate a single attribute value, then interpolate placeholders.
     *
     * @param DOMElement $node
     * @param string $attr Attribute name
     * @param array $translations Map of [original => translated]
     * @return void
     */
    protected function translateAttribute(DOMElement $node, $attr, array $translations)
    {
        // Canonicalised, because this is a LOOKUP side. The collection side
        // (collectAttributePhrases) registers the collapsed form, so looking up
        // the raw value could never match - and it missed on plain spaces, not
        // just on non-ASCII whitespace, so "attributes work" was never true for
        // any alt written across two source lines.
        $value = Canonical::phrase($node->getAttribute($attr));

        if ($value === '') {
            return;
        }

        $translated = isset($translations[$value]) ? $translations[$value] : null;

        if ($translated === '' || $translated === null) {
            $translated = $value;
        }

        $translated = $this->interp($translated);

        if ($translated !== $value) {
            $node->setAttribute($attr, $translated);
        }
    }

    /**
     * Interpolate placeholder values for the current translate() call.
     *
     * @param string $text
     * @return string
     */
    protected function interp($text, ?DOMNode $scope = null)
    {
        // No early return on empty params: a translation can hold ICU the caller
        // knows nothing about - the backend promotes a plain {name} into a
        // gendered select for locales that need one - and "no params" is the
        // case that needs recovery. The interpolator's own fast path handles
        // text with no construct in it.
        if (!is_string($text) || $text === '') {
            return $text;
        }

        return $this->client->getInterpolator()->interpolate($text, $this->paramsFor($scope, $text), $this->currentLocale);
    }

    /**
     * The params a render of $text inside $scope uses: the caller's, over the
     * values of any value markers read there (VAR-3).
     *
     * @param DOMNode|null $scope
     * @param string $text
     * @return array
     */
    protected function paramsFor(?DOMNode $scope = null, $text = '')
    {
        if ($scope === null || $this->markers === null || !$this->markers->any()) {
            return $this->params;
        }

        return $this->markers->params($scope, $text, $this->params);
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
        // Node by node, in UTF-8, which the page declares (HeadHandler). The
        // whole-document serializer of libxml 2.9 takes its output encoding
        // only from an http-equiv meta, and otherwise writes every character
        // outside ASCII as an entity, inside a script, JSON-LD or style too,
        // where an entity is not decoded.
        $html = '';

        foreach ($doc->childNodes as $child) {
            if ($child instanceof \DOMDocumentType) {
                $html .= self::doctype($child) . "\n";
                continue;
            }

            // The encoding hint parseDocument() adds is not the page's.
            if ($child instanceof \DOMProcessingInstruction && strtolower($child->target) === 'xml') {
                continue;
            }

            // Nor is the <html> it read a fragment inside.
            if ($this->fragmentRoot && $child instanceof DOMElement && $child->nodeName === 'html') {
                foreach ($child->childNodes as $node) {
                    $html .= Document::html($node);
                }
                continue;
            }

            $html .= Document::html($child);
        }

        return $html === '' ? '' : $html . "\n";
    }

    /**
     * A doctype as libxml writes it.
     *
     * @param \DOMDocumentType $doctype
     * @return string
     */
    private static function doctype(\DOMDocumentType $doctype)
    {
        $out = '<!DOCTYPE ' . $doctype->name;

        if ($doctype->publicId !== '') {
            $out .= ' PUBLIC "' . $doctype->publicId . '"' . ($doctype->systemId !== '' ? ' "' . $doctype->systemId . '"' : '');
        } elseif ($doctype->systemId !== '') {
            $out .= ' SYSTEM "' . $doctype->systemId . '"';
        }

        return $out . '>';
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

        // Namespaced by project like every other key in the SDK: this cache is
        // shared by every request on the host, and by the fleet on Redis, so an
        // un-namespaced key lets one project's record suppress another's.
        return 'registered_items_' . $this->client->getConfig()->getProjectId() . '_' . $cat;
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

        // A cache that fails mid-render is a miss: the page still renders.
        try {
            $cached = $cache->get($cacheKey);
        } catch (\Throwable $e) {
            $cached = null;
        }
        if ($cached !== null && is_array($cached)) {
            return $cached;
        }

        return [
            'phrases' => [],
            'contentBlocks' => [],
        ];
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
            // Presence alone means known: a nested map is a content block, never
            // a missing phrase, so text colliding with a block id must not be
            // registered as a phrase. Client::translate() branches the same way -
            // the two checks have to agree, or the colliding text re-registers on
            // every single render.
            if (array_key_exists($text, $categoryTranslations)) {
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

            // A stamped host outside a resolved scope registers under its
            // stamped id when the catalog lacks that id; no other id is read
            // for it (MARK-3).
            if (!empty($block['stamped'])) {
                $categoryTranslations = isset($translations[$cat]) ? $translations[$cat] : [];
                if (!is_array($categoryTranslations) || !array_key_exists($customId, $categoryTranslations)) {
                    $newBlocks[] = $block;
                }
                continue;
            }

            // Resolve through the client's single resolution path, so the page
            // path sees the legacy (pre-JSON-form) ids too. Without this a block
            // registered by an older SDK reads as new here, gets queued under the
            // current id, and its translations are stranded on the old one - the
            // exact damage the fallback exists to prevent, reached through the
            // path a website most likely renders with.
            $categoryTranslations = isset($translations[$cat]) ? $translations[$cat] : [];
            if ($this->client->resolveContentBlockTranslations(
                    $categoryTranslations, $cat, $block['phrases'], $customId, $this->htmlParser
                ) !== null) {
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
        // QUEUE, do not register inline.
        //
        // This used to issue blocking HTTP calls mid-render: one POST for the
        // phrases, one POST PER new content block, and then a cache clear plus a
        // refetch - so a page with eight new blocks blocked on ten round trips
        // before a byte reached the user, while translate() made none.
        //
        // The refetch was the reason for the ordering: registration ran inline so
        // the refreshed catalog could apply within the same response. But the
        // items just registered have no translations yet, so it could only ever
        // surface translations an EARLIER request registered and a translator has
        // since filled - which the next page load picks up anyway.
        //
        // Routing through the shared queue also removes the N+1:
        // flushPendingRegistrations() already batches content blocks through
        // createContentBlocks(), where this loop sent one request each.
        //
        // The write-permission check is deliberately NOT repeated here; the flush
        // performs it once and clears the queue silently on a read-only key.
        foreach ($newPhrases as $phrase) {
            $this->client->queuePhraseForRegistration($phrase['text'], $phrase['category']);
        }

        foreach ($newContentBlocks as $block) {
            $this->client->queueContentBlockForRegistration(
                $block['html'],
                $block['category'],
                $block['customId'],
                isset($block['phrases']) ? $block['phrases'] : []
            );
        }
    }
}
