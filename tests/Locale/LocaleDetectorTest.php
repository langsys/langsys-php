<?php

namespace Langsys\SDK\Tests\Locale;

use PHPUnit\Framework\TestCase;
use Langsys\SDK\Locale\LocaleDetector;

class LocaleDetectorTest extends TestCase
{
    /**
     * @var string|null Original HTTP_ACCEPT_LANGUAGE value
     */
    private $originalAcceptLanguage;

    protected function setUp(): void
    {
        // Save original value
        $this->originalAcceptLanguage = isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])
            ? $_SERVER['HTTP_ACCEPT_LANGUAGE']
            : null;
    }

    protected function tearDown(): void
    {
        // Restore original value
        if ($this->originalAcceptLanguage !== null) {
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] = $this->originalAcceptLanguage;
        } else {
            unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        }
    }

    // =========================================================================
    // normalize() Tests
    // =========================================================================

    public function testNormalizeUnderscoreToHyphen(): void
    {
        $this->assertEquals('en-us', LocaleDetector::normalize('en_US'));
        $this->assertEquals('es-es', LocaleDetector::normalize('es_ES'));
        $this->assertEquals('fr-ca', LocaleDetector::normalize('fr_CA'));
    }

    public function testNormalizeUppercaseToLowercase(): void
    {
        $this->assertEquals('en-us', LocaleDetector::normalize('EN-US'));
        $this->assertEquals('en-us', LocaleDetector::normalize('En-Us'));
        $this->assertEquals('de-de', LocaleDetector::normalize('DE-DE'));
    }

    public function testNormalizeMixedFormat(): void
    {
        $this->assertEquals('en-us', LocaleDetector::normalize('EN_us'));
        $this->assertEquals('pt-br', LocaleDetector::normalize('PT_BR'));
    }

    public function testNormalizeAlreadyNormalized(): void
    {
        $this->assertEquals('en-us', LocaleDetector::normalize('en-us'));
        $this->assertEquals('es-mx', LocaleDetector::normalize('es-mx'));
    }

    public function testNormalizeLanguageOnly(): void
    {
        $this->assertEquals('en', LocaleDetector::normalize('en'));
        $this->assertEquals('es', LocaleDetector::normalize('ES'));
    }

    public function testNormalizeEmpty(): void
    {
        $this->assertEquals('', LocaleDetector::normalize(''));
        $this->assertNull(LocaleDetector::normalize(null));
    }

    // =========================================================================
    // toOpenGraphFormat() Tests
    // =========================================================================

    public function testToOpenGraphFormat(): void
    {
        $this->assertEquals('en_US', LocaleDetector::toOpenGraphFormat('en-us'));
        $this->assertEquals('es_ES', LocaleDetector::toOpenGraphFormat('es-es'));
        $this->assertEquals('fr_CA', LocaleDetector::toOpenGraphFormat('fr-ca'));
        $this->assertEquals('pt_BR', LocaleDetector::toOpenGraphFormat('pt-br'));
    }

    public function testToOpenGraphFormatFromNonNormalized(): void
    {
        $this->assertEquals('en_US', LocaleDetector::toOpenGraphFormat('EN-US'));
        $this->assertEquals('de_DE', LocaleDetector::toOpenGraphFormat('de_DE'));
    }

    public function testToOpenGraphFormatLanguageOnly(): void
    {
        // Language-only should assume country matches language
        $this->assertEquals('en_EN', LocaleDetector::toOpenGraphFormat('en'));
        $this->assertEquals('es_ES', LocaleDetector::toOpenGraphFormat('es'));
    }

    public function testToOpenGraphFormatEmpty(): void
    {
        $this->assertEquals('', LocaleDetector::toOpenGraphFormat(''));
    }

    // =========================================================================
    // getLanguageCode() Tests
    // =========================================================================

    public function testGetLanguageCode(): void
    {
        $this->assertEquals('en', LocaleDetector::getLanguageCode('en-us'));
        $this->assertEquals('es', LocaleDetector::getLanguageCode('es-es'));
        $this->assertEquals('fr', LocaleDetector::getLanguageCode('fr-ca'));
    }

    public function testGetLanguageCodeFromNonNormalized(): void
    {
        $this->assertEquals('en', LocaleDetector::getLanguageCode('EN-US'));
        $this->assertEquals('de', LocaleDetector::getLanguageCode('de_DE'));
    }

    public function testGetLanguageCodeLanguageOnly(): void
    {
        $this->assertEquals('en', LocaleDetector::getLanguageCode('en'));
        $this->assertEquals('es', LocaleDetector::getLanguageCode('ES'));
    }

    public function testGetLanguageCodeEmpty(): void
    {
        $this->assertEquals('', LocaleDetector::getLanguageCode(''));
    }

    // =========================================================================
    // getCountryCode() Tests
    // =========================================================================

    public function testGetCountryCode(): void
    {
        $this->assertEquals('us', LocaleDetector::getCountryCode('en-us'));
        $this->assertEquals('es', LocaleDetector::getCountryCode('es-es'));
        $this->assertEquals('ca', LocaleDetector::getCountryCode('fr-ca'));
    }

    public function testGetCountryCodeFromNonNormalized(): void
    {
        $this->assertEquals('us', LocaleDetector::getCountryCode('EN-US'));
        $this->assertEquals('de', LocaleDetector::getCountryCode('de_DE'));
    }

    public function testGetCountryCodeLanguageOnly(): void
    {
        $this->assertNull(LocaleDetector::getCountryCode('en'));
        $this->assertNull(LocaleDetector::getCountryCode('es'));
    }

    public function testGetCountryCodeEmpty(): void
    {
        $this->assertNull(LocaleDetector::getCountryCode(''));
    }

    // =========================================================================
    // fromBrowser() Tests
    // =========================================================================

    public function testFromBrowserWithFullLocale(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en-US,en;q=0.9';
        $this->assertEquals('en-us', LocaleDetector::fromBrowser());
    }

    public function testFromBrowserWithUnderscoreLocale(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'es_ES,es;q=0.9';
        $this->assertEquals('es-es', LocaleDetector::fromBrowser());
    }

    public function testFromBrowserWithMultipleLocales(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'fr-FR,fr;q=0.9,en-US;q=0.8';
        // Should pick first full locale
        $this->assertEquals('fr-fr', LocaleDetector::fromBrowser());
    }

    public function testFromBrowserWithLanguageOnly(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en';
        // Should assume country matches language
        $this->assertEquals('en-en', LocaleDetector::fromBrowser());
    }

    public function testFromBrowserWithComplexHeader(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'de-DE,de;q=0.9,en-US;q=0.8,en;q=0.7';
        $this->assertEquals('de-de', LocaleDetector::fromBrowser());
    }

    public function testFromBrowserMissingHeader(): void
    {
        unset($_SERVER['HTTP_ACCEPT_LANGUAGE']);
        $this->assertNull(LocaleDetector::fromBrowser());
    }

    public function testFromBrowserEmptyHeader(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = '';
        $this->assertNull(LocaleDetector::fromBrowser());
    }

    public function testFromBrowserWithQualityValues(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'pt-BR;q=0.8,pt;q=0.6';
        $this->assertEquals('pt-br', LocaleDetector::fromBrowser());
    }

    public function testFromBrowserNormalizesResult(): void
    {
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'EN-US';
        // Should be lowercase
        $this->assertEquals('en-us', LocaleDetector::fromBrowser());
    }

    public function testFromBrowserWithLocaleInMiddle(): void
    {
        // Locale appears in middle of header after some text
        $_SERVER['HTTP_ACCEPT_LANGUAGE'] = 'en,es-MX;q=0.9';
        $this->assertEquals('es-mx', LocaleDetector::fromBrowser());
    }
}
