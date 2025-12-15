<?php

namespace Langsys\SDK\Html;

use DOMDocument;
use DOMElement;
use Langsys\SDK\Locale\LocaleDetector;

/**
 * Handles <head> section translation and meta tag management.
 *
 * Responsibilities:
 * - Set lang attribute on <html> element
 * - Ensure <meta charset="utf-8"> exists
 * - Translate <title> tag
 * - Translate meta description, keywords, author
 * - Translate OpenGraph tags (og:title, og:description, og:site_name)
 * - Translate Twitter card tags (twitter:title, twitter:description)
 * - Update og:locale to match target locale
 */
class HeadHandler
{
    /**
     * Meta tags to translate (by name attribute).
     */
    const META_NAMES = [
        'description',
        'keywords',
        'author',
    ];

    /**
     * OpenGraph properties to translate (by property attribute).
     */
    const OG_PROPERTIES = [
        'og:title',
        'og:description',
        'og:site_name',
    ];

    /**
     * Twitter card properties to translate (by name attribute).
     */
    const TWITTER_PROPERTIES = [
        'twitter:title',
        'twitter:description',
    ];

    /**
     * Extract translatable phrases from <head> section.
     *
     * @param DOMDocument $doc The document to extract from
     * @return array Array of phrases found in head section
     */
    public function extractPhrases(DOMDocument $doc)
    {
        $phrases = [];
        $head = $this->getHeadElement($doc);

        if ($head === null) {
            return $phrases;
        }

        // Extract title text
        $titles = $head->getElementsByTagName('title');
        if ($titles->length > 0) {
            $titleText = trim($titles->item(0)->textContent);
            if ($titleText !== '') {
                $phrases[] = $titleText;
            }
        }

        // Extract meta tags
        $metas = $head->getElementsByTagName('meta');
        foreach ($metas as $meta) {
            $content = $meta->getAttribute('content');
            if ($content === '') {
                continue;
            }

            $name = $meta->getAttribute('name');
            $property = $meta->getAttribute('property');

            // Check name attribute (description, keywords, author, twitter:*)
            if ($name !== '') {
                if (in_array($name, self::META_NAMES, true) ||
                    in_array($name, self::TWITTER_PROPERTIES, true)) {
                    $phrases[] = $content;
                }
            }

            // Check property attribute (og:*)
            if ($property !== '' && in_array($property, self::OG_PROPERTIES, true)) {
                $phrases[] = $content;
            }
        }

        return $phrases;
    }

    /**
     * Process <head> section: set lang, ensure charset, apply translations.
     *
     * @param DOMDocument $doc The document to process
     * @param string $locale Target locale
     * @param array $translations Translation map [category => [phrase => translation]]
     * @param string|null $category Category for phrase lookup
     * @return void
     */
    public function process(DOMDocument $doc, $locale, array $translations, $category = null)
    {
        // Set lang attribute on <html>
        $this->setLangAttribute($doc, $locale);

        $head = $this->getHeadElement($doc);
        if ($head === null) {
            return;
        }

        // Ensure charset meta exists
        $this->ensureCharsetMeta($head, $doc);

        // Translate title
        $this->processTitle($head, $translations, $category);

        // Translate meta tags
        $this->processMetaTags($head, $translations, $category, $locale);
    }

    /**
     * Set the lang attribute on <html> element.
     *
     * @param DOMDocument $doc The document
     * @param string $locale The locale to set
     * @return void
     */
    protected function setLangAttribute(DOMDocument $doc, $locale)
    {
        $htmlElements = $doc->getElementsByTagName('html');
        if ($htmlElements->length > 0) {
            $htmlElements->item(0)->setAttribute('lang', $locale);
        }
    }

    /**
     * Ensure <meta charset="utf-8"> exists in head.
     *
     * @param DOMElement $head The head element
     * @param DOMDocument $doc The document (for creating new elements)
     * @return void
     */
    protected function ensureCharsetMeta(DOMElement $head, DOMDocument $doc)
    {
        $metas = $head->getElementsByTagName('meta');
        $hasCharset = false;

        foreach ($metas as $meta) {
            // Check for charset attribute
            if ($meta->hasAttribute('charset')) {
                $meta->setAttribute('charset', 'utf-8');
                $hasCharset = true;
                break;
            }

            // Check for http-equiv="Content-Type"
            $httpEquiv = strtolower($meta->getAttribute('http-equiv'));
            if ($httpEquiv === 'content-type') {
                $hasCharset = true;
                // Update content to use utf-8
                $meta->setAttribute('content', 'text/html; charset=utf-8');
                break;
            }
        }

        // If no charset found, insert <meta charset="utf-8"> at the beginning
        if (!$hasCharset) {
            $charsetMeta = $doc->createElement('meta');
            $charsetMeta->setAttribute('charset', 'utf-8');

            if ($head->firstChild) {
                $head->insertBefore($charsetMeta, $head->firstChild);
            } else {
                $head->appendChild($charsetMeta);
            }
        }
    }

    /**
     * Translate <title> tag.
     *
     * @param DOMElement $head The head element
     * @param array $translations Translation map
     * @param string|null $category Category for lookup
     * @return void
     */
    protected function processTitle(DOMElement $head, array $translations, $category = null)
    {
        $titles = $head->getElementsByTagName('title');
        if ($titles->length === 0) {
            return;
        }

        $titleElement = $titles->item(0);
        $originalText = trim($titleElement->textContent);

        if ($originalText === '') {
            return;
        }

        $translated = $this->lookupTranslation($originalText, $category, $translations);
        if ($translated !== $originalText) {
            $titleElement->textContent = $translated;
        }
    }

    /**
     * Translate meta tags (description, keywords, og:*, twitter:*).
     *
     * @param DOMElement $head The head element
     * @param array $translations Translation map
     * @param string|null $category Category for lookup
     * @param string $locale Target locale
     * @return void
     */
    protected function processMetaTags(DOMElement $head, array $translations, $category = null, $locale = '')
    {
        $metas = $head->getElementsByTagName('meta');

        foreach ($metas as $meta) {
            $content = $meta->getAttribute('content');
            if ($content === '') {
                continue;
            }

            $name = $meta->getAttribute('name');
            $property = $meta->getAttribute('property');

            // Handle name attribute (description, keywords, author, twitter:*)
            if ($name !== '') {
                if (in_array($name, self::META_NAMES, true) ||
                    in_array($name, self::TWITTER_PROPERTIES, true)) {
                    $translated = $this->lookupTranslation($content, $category, $translations);
                    if ($translated !== $content) {
                        $meta->setAttribute('content', $translated);
                    }
                }
            }

            // Handle property attribute (og:*)
            if ($property !== '') {
                // Special case: og:locale - set to target locale
                if ($property === 'og:locale' && $locale !== '') {
                    $meta->setAttribute('content', LocaleDetector::toOpenGraphFormat($locale));
                    continue;
                }

                // Translate og:title, og:description, og:site_name
                if (in_array($property, self::OG_PROPERTIES, true)) {
                    $translated = $this->lookupTranslation($content, $category, $translations);
                    if ($translated !== $content) {
                        $meta->setAttribute('content', $translated);
                    }
                }
            }
        }
    }

    /**
     * Look up translation for a phrase.
     *
     * @param string $phrase The phrase to translate
     * @param string|null $category The category
     * @param array $translations Translation map
     * @return string Translated phrase or original if not found/empty
     */
    protected function lookupTranslation($phrase, $category, array $translations)
    {
        $cat = $category !== null ? $category : '__uncategorized__';

        if (isset($translations[$cat][$phrase])) {
            $value = $translations[$cat][$phrase];
            // Only return if it's a string and not empty
            if (!is_array($value) && $value !== '' && $value !== null) {
                return $value;
            }
        }

        // Fallback to original
        return $phrase;
    }

    /**
     * Get the <head> element from document.
     *
     * @param DOMDocument $doc The document
     * @return DOMElement|null The head element or null
     */
    protected function getHeadElement(DOMDocument $doc)
    {
        $heads = $doc->getElementsByTagName('head');
        if ($heads->length > 0) {
            return $heads->item(0);
        }
        return null;
    }
}
