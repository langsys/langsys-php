<?php

namespace Langsys\SDK\Tests\Html;

use PHPUnit\Framework\TestCase;
use Langsys\SDK\Html\PageTranslator;
use Langsys\SDK\Client;
use Langsys\SDK\Config;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use Langsys\SDK\Html\HtmlParser;

class PageTranslatorTest extends TestCase
{
    /**
     * @var MockHttpClient
     */
    private $mockHttp;

    /**
     * @var Client
     */
    private $client;

    protected function setUp(): void
    {
        $this->mockHttp = new MockHttpClient();

        // Set up default authorize response
        $this->mockHttp->setResponse('GET', 'authorize-project/test-project-id', [
            'status' => true,
            'data' => [
                'id' => 'test-project-id',
                'title' => 'Test Project',
                'key_type' => 'write',
                'base_locale' => 'en-us',
            ],
        ]);

        // Create client with mock HTTP
        $this->client = $this->createMockClient();
    }

    /**
     * Create a client with mock HTTP for testing.
     */
    private function createMockClient()
    {
        // Create client with mock HTTP
        $client = new Client('test-api-key', 'test-project-id', [
            'cache' => new NullCache(),
        ]);

        // Use reflection to inject mock HTTP client into all components
        $reflection = new \ReflectionClass($client);

        // Inject into client
        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $httpProperty->setValue($client, $this->mockHttp);

        // Inject into translations resource
        $translationsProperty = $reflection->getProperty('translations');
        $translationsProperty->setAccessible(true);
        $translations = $translationsProperty->getValue($client);

        $translationsReflection = new \ReflectionClass($translations);
        $translationsHttpProperty = $translationsReflection->getProperty('http');
        $translationsHttpProperty->setAccessible(true);
        $translationsHttpProperty->setValue($translations, $this->mockHttp);

        // Inject into translatableItems resource
        $itemsProperty = $reflection->getProperty('translatableItems');
        $itemsProperty->setAccessible(true);
        $items = $itemsProperty->getValue($client);

        $itemsReflection = new \ReflectionClass($items);
        $itemsHttpProperty = $itemsReflection->getProperty('http');
        $itemsHttpProperty->setAccessible(true);
        $itemsHttpProperty->setValue($items, $this->mockHttp);

        return $client;
    }

    /**
     * Set up translations response.
     */
    private function setTranslations(array $translations)
    {
        $this->mockHttp->setResponse('GET', 'translations', [
            'status' => true,
            'data' => $translations,
        ]);
    }

    // =========================================================================
    // Basic Translation Tests
    // =========================================================================

    public function testTranslateSimplePage(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'Hello World' => 'Hola Mundo',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Hello World</p></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Hola Mundo', $result);
        $this->assertStringContainsString('lang="es-es"', $result);
    }

    public function testTranslateWithCategory(): void
    {
        $this->setTranslations([
            'homepage' => [
                'Welcome' => 'Bienvenido',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body><p>Welcome</p></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', 'homepage');

        $this->assertStringContainsString('Bienvenido', $result);
    }

    public function testTranslateReturnsOriginalForEmptyHtml(): void
    {
        $translator = new PageTranslator($this->client);
        $result = $translator->translate('', 'es-es');

        $this->assertEquals('', $result);
    }

    // =========================================================================
    // Head Section Tests
    // =========================================================================

    public function testTranslateSetsLangAttribute(): void
    {
        $this->setTranslations([]);

        $html = '<!DOCTYPE html><html><head></head><body></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'fr-ca');

        $this->assertStringContainsString('lang="fr-ca"', $result);
    }

    public function testTranslateEnsuresCharsetMeta(): void
    {
        $this->setTranslations([]);

        $html = '<!DOCTYPE html><html><head><title>Test</title></head><body></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'en-us');

        $this->assertStringContainsString('charset="utf-8"', strtolower($result));
    }

    public function testTranslateTitle(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'My Page Title' => 'Mi Titulo de Pagina',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head><title>My Page Title</title></head><body></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Mi Titulo de Pagina', $result);
    }

    public function testTranslateMetaDescription(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'A great website' => 'Un gran sitio web',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head><meta name="description" content="A great website"></head><body></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Un gran sitio web', $result);
    }

    public function testTranslateOpenGraphTags(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'OG Title' => 'Titulo OG',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head><meta property="og:title" content="OG Title"></head><body></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Titulo OG', $result);
    }

    public function testUpdatesOpenGraphLocale(): void
    {
        $this->setTranslations([]);

        $html = '<!DOCTYPE html><html><head><meta property="og:locale" content="en_US"></head><body></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-mx');

        $this->assertStringContainsString('es_MX', $result);
    }

    // =========================================================================
    // Text-Only Block Tests (become phrases)
    // =========================================================================

    public function testTextOnlyParagraphBecomesPhrase(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'Simple text' => 'Texto simple',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><p>Simple text</p></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Texto simple', $result);
    }

    public function testTextOnlyHeadingBecomesPhrase(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'Heading Text' => 'Texto del Encabezado',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><h1>Heading Text</h1></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Texto del Encabezado', $result);
    }

    public function testTextOnlyListItemBecomesPhrase(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'List item' => 'Elemento de lista',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><ul><li>List item</li></ul></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Elemento de lista', $result);
    }

    // =========================================================================
    // Content Block Tests (complex HTML)
    // =========================================================================

    public function testSinglePhraseWithInlineFormattingBecomesPhrase(): void
    {
        // Single phrase wrapped in inline element (like <a>, <strong>, etc.)
        // should be treated as a simple phrase, NOT a content block
        $this->setTranslations([
            '__uncategorized__' => [
                'Click here' => 'Haz clic aqui',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><p><a href="#">Click here</a></p></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Haz clic aqui', $result);
    }

    public function testMultiplePhrasesBecomesContentBlock(): void
    {
        // Multiple text nodes = content block
        // <p><strong>Hello</strong> World</p> has 2 phrases: "Hello" and "World"
        $customId = (new HtmlParser())->generateCustomId('__uncategorized__', ['Hello', 'World']);

        $this->setTranslations([
            '__uncategorized__' => [
                $customId => [
                    'Hello' => 'Hola',
                    'World' => 'Mundo',
                ],
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><p><strong>Hello</strong> World</p></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Hola', $result);
        $this->assertStringContainsString('Mundo', $result);
    }

    public function testContentBlockWithMultiplePhrases(): void
    {
        // Nav with multiple links
        $customId = (new HtmlParser())->generateCustomId('nav', ['Home', 'About', 'Contact']);

        $this->setTranslations([
            'nav' => [
                $customId => [
                    'Home' => 'Inicio',
                    'About' => 'Acerca de',
                    'Contact' => 'Contacto',
                ],
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><nav><a>Home</a><a>About</a><a>Contact</a></nav></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', 'nav');

        $this->assertStringContainsString('Inicio', $result);
        $this->assertStringContainsString('Acerca de', $result);
        $this->assertStringContainsString('Contacto', $result);
    }

    // =========================================================================
    // Fallback Tests
    // =========================================================================

    public function testFallbackToSourceForMissingTranslation(): void
    {
        $this->setTranslations([]);

        $html = '<!DOCTYPE html><html><head></head><body><p>Untranslated text</p></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Untranslated text', $result);
    }

    public function testFallbackForEmptyTranslation(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'Original text' => '', // Empty translation
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><p>Original text</p></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        // Should show original since translation is empty
        $this->assertStringContainsString('Original text', $result);
    }

    // =========================================================================
    // translate="no" Tests
    // =========================================================================

    public function testRespectTranslateNoAttribute(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'Do not translate' => 'No traducir',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><p translate="no">Do not translate</p></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        // Should NOT contain the translation
        $this->assertStringContainsString('Do not translate', $result);
        $this->assertStringNotContainsString('No traducir', $result);
    }

    // =========================================================================
    // Skip Elements Tests
    // =========================================================================

    public function testSkipScriptTags(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'var text' => 'var texto',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><script>var text = "hello";</script></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        // Script content should not be translated
        $this->assertStringContainsString('var text', $result);
    }

    public function testSkipStyleTags(): void
    {
        $this->setTranslations([]);

        $html = '<!DOCTYPE html><html><head><style>.class { color: red; }</style></head><body></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        // Style should remain unchanged
        $this->assertStringContainsString('.class { color: red; }', $result);
    }

    // =========================================================================
    // Nested Structure Tests
    // =========================================================================

    public function testNestedBlocksProcessedCorrectly(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'Header' => 'Encabezado',
                'Paragraph' => 'Parrafo',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <section>
                <div>
                    <h1>Header</h1>
                    <p>Paragraph</p>
                </div>
            </section>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Encabezado', $result);
        $this->assertStringContainsString('Parrafo', $result);
    }

    // =========================================================================
    // Attribute Translation Tests
    // =========================================================================

    public function testTranslatePlaceholderAttribute(): void
    {
        $customId = (new HtmlParser())->generateCustomId('__uncategorized__', ['Enter your name']);

        $this->setTranslations([
            '__uncategorized__' => [
                $customId => [
                    'Enter your name' => 'Ingresa tu nombre',
                ],
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><form><input placeholder="Enter your name"></form></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Ingresa tu nombre', $result);
    }

    public function testTranslateAltAttribute(): void
    {
        $customId = (new HtmlParser())->generateCustomId('__uncategorized__', ['Logo image']);

        $this->setTranslations([
            '__uncategorized__' => [
                $customId => [
                    'Logo image' => 'Imagen del logo',
                ],
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><figure><img alt="Logo image"></figure></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Imagen del logo', $result);
    }

    // =========================================================================
    // Button/Input Value Translation Tests
    // =========================================================================

    public function testTranslateSubmitButtonValue(): void
    {
        $customId = (new HtmlParser())->generateCustomId('__uncategorized__', ['Submit']);

        $this->setTranslations([
            '__uncategorized__' => [
                $customId => [
                    'Submit' => 'Enviar',
                ],
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><form><input type="submit" value="Submit"></form></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Enviar', $result);
    }

    // =========================================================================
    // Full Page Tests
    // =========================================================================

    public function testTranslateFullPage(): void
    {
        $navCustomId = (new HtmlParser())->generateCustomId('homepage', ['Home', 'About']);
        $formCustomId = (new HtmlParser())->generateCustomId('homepage', ['Your email', 'Subscribe']);

        $this->setTranslations([
            'homepage' => [
                'Welcome to our site' => 'Bienvenido a nuestro sitio',
                'This is a great website' => 'Este es un gran sitio web',
                $navCustomId => [
                    'Home' => 'Inicio',
                    'About' => 'Acerca de',
                ],
                $formCustomId => [
                    'Your email' => 'Tu correo',
                    'Subscribe' => 'Suscribirse',
                ],
            ],
        ]);

        $html = '<!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="iso-8859-1">
            <title>Welcome to our site</title>
            <meta name="description" content="This is a great website">
        </head>
        <body>
            <nav><a>Home</a><a>About</a></nav>
            <main>
                <h1>Welcome to our site</h1>
            </main>
            <form>
                <input placeholder="Your email">
                <button>Subscribe</button>
            </form>
        </body>
        </html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', 'homepage');

        // Check lang attribute updated
        $this->assertStringContainsString('lang="es-es"', $result);

        // Check charset updated
        $this->assertStringContainsString('charset="utf-8"', strtolower($result));

        // Check title translated
        $this->assertStringContainsString('Bienvenido a nuestro sitio', $result);

        // Check meta description translated
        $this->assertStringContainsString('Este es un gran sitio web', $result);
    }

    // =========================================================================
    // Edge Cases
    // =========================================================================

    public function testHandleMalformedHtml(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'Text' => 'Texto',
            ],
        ]);

        // Missing closing tags
        $html = '<html><head><body><p>Text';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        // Should not crash, should return something
        $this->assertNotEmpty($result);
    }

    public function testPreservesHtmlStructure(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'Hello' => 'Hola',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><div class="container" id="main"><p data-test="value">Hello</p></div></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        // Check attributes are preserved
        $this->assertStringContainsString('class="container"', $result);
        $this->assertStringContainsString('id="main"', $result);
        $this->assertStringContainsString('data-test="value"', $result);
    }

    public function testHandlesWhitespaceCorrectly(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'Text with spaces' => 'Texto con espacios',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><p>   Text with spaces   </p></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Texto con espacios', $result);
    }

    // =========================================================================
    // Read-Only Key Tests
    // =========================================================================

    public function testSilentFallbackWithReadOnlyKey(): void
    {
        // Override to return read-only key
        $this->mockHttp->setResponse('GET', 'authorize-project/test-project-id', [
            'status' => true,
            'data' => [
                'id' => 'test-project-id',
                'key_type' => 'read', // Read-only
            ],
        ]);

        $this->setTranslations([]);

        $html = '<!DOCTYPE html><html><head></head><body><p>New phrase</p></body></html>';

        $translator = new PageTranslator($this->client);

        // Should not throw, should return HTML with original content
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('New phrase', $result);
    }

    // =========================================================================
    // Selector-Based Category Tests
    // =========================================================================

    public function testSelectorCategoryByTag(): void
    {
        // Selector matches a block element (div) containing text
        $this->setTranslations([
            'UI Elements' => [
                'Click me' => 'Haz clic',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><div class="button">Click me</div></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', null, [
            'div.button' => ['category' => 'UI Elements'],
        ]);

        $this->assertStringContainsString('Haz clic', $result);
    }

    public function testSelectorCategoryByClass(): void
    {
        // Selector matches a block element by class
        $this->setTranslations([
            'CTA Buttons' => [
                'Sign Up' => 'Registrarse',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><p class="btn cta">Sign Up</p></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', null, [
            '.btn' => ['category' => 'CTA Buttons'],
        ]);

        $this->assertStringContainsString('Registrarse', $result);
    }

    public function testSelectorCategoryCommaSeparated(): void
    {
        // Multiple selectors targeting block elements
        $this->setTranslations([
            'Clickables' => [
                'Submit' => 'Enviar',
                'Login' => 'Iniciar sesion',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <p class="action">Submit</p>
            <div class="btn">Login</div>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', null, [
            '.action, .btn' => ['category' => 'Clickables'],
        ]);

        $this->assertStringContainsString('Enviar', $result);
        $this->assertStringContainsString('Iniciar sesion', $result);
    }

    public function testSelectorCategoryWithDescendant(): void
    {
        // Selector matches block element (li) inside nav
        $this->setTranslations([
            'Navigation' => [
                'Home' => 'Inicio',
            ],
            '__uncategorized__' => [
                'Other Link' => 'Otro enlace',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <nav><ul><li>Home</li></ul></nav>
            <div><p>Other Link</p></div>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', null, [
            'nav li' => ['category' => 'Navigation'],
        ]);

        $this->assertStringContainsString('Inicio', $result);
        // The div p should use default category
        $this->assertStringContainsString('Otro enlace', $result);
    }

    public function testSelectorOverridesDataLangsysCategory(): void
    {
        // Selector with override=true should win over parent's data-langsys-category
        $this->setTranslations([
            'Override Category' => [
                'Override me' => 'Texto sobrescrito',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <div data-langsys-category="Original Category">
                <p class="special">Override me</p>
            </div>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', null, [
            'p.special' => [
                'category' => 'Override Category',
                'overrideParentElementCategory' => true,
            ],
        ]);

        $this->assertStringContainsString('Texto sobrescrito', $result);
    }

    public function testSelectorWithoutOverrideRespectsDataLangsysCategory(): void
    {
        $this->setTranslations([
            'Original Category' => [
                'Keep me' => 'Mantener original',
            ],
        ]);

        // Selector without override should NOT override data-langsys-category
        $html = '<!DOCTYPE html><html><head></head><body>
            <div data-langsys-category="Original Category"><p>Keep me</p></div>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', null, [
            'p' => [
                'category' => 'Selector Category', // No override
            ],
        ]);

        // Should use Original Category (from data-langsys-category), not Selector Category
        $this->assertStringContainsString('Mantener original', $result);
    }

    public function testSelectorCategoryInheritsToChildren(): void
    {
        // Selector matches the section, nested paragraphs should inherit the category
        $this->setTranslations([
            'Form Section' => [
                'Name' => 'Nombre',
                'Email' => 'Correo',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <section class="contact-form">
                <p>Name</p>
                <p>Email</p>
            </section>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', null, [
            'section.contact-form' => [
                'category' => 'Form Section',
                'overrideParentElementCategory' => true,
            ],
        ]);

        $this->assertStringContainsString('Nombre', $result);
        $this->assertStringContainsString('Correo', $result);
    }

    public function testSelectorStringShorthand(): void
    {
        // String value instead of array should work
        $this->setTranslations([
            'Buttons' => [
                'Click' => 'Clic',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><div class="btn">Click</div></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', null, [
            '.btn' => 'Buttons', // String shorthand
        ]);

        $this->assertStringContainsString('Clic', $result);
    }

    public function testSelectorCategoryWithAttributeSelector(): void
    {
        // Attribute selector matching block element
        $this->setTranslations([
            'Special' => [
                'Special content' => 'Contenido especial',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <div data-type="special">Special content</div>
            <div>Normal content</div>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', null, [
            '[data-type="special"]' => ['category' => 'Special'],
        ]);

        $this->assertStringContainsString('Contenido especial', $result);
    }

    public function testMultipleSelectorRules(): void
    {
        // Multiple different selectors with different categories
        $this->setTranslations([
            'Navigation' => [
                'Home' => 'Inicio',
            ],
            'Actions' => [
                'Submit' => 'Enviar',
            ],
            '__uncategorized__' => [
                'Content' => 'Contenido',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <nav><ul><li>Home</li></ul></nav>
            <main><p>Content</p></main>
            <footer><p class="action">Submit</p></footer>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', null, [
            'nav li' => ['category' => 'Navigation'],
            '.action' => ['category' => 'Actions'],
        ]);

        $this->assertStringContainsString('Inicio', $result);
        $this->assertStringContainsString('Enviar', $result);
        $this->assertStringContainsString('Contenido', $result);
    }

    public function testEmptySelectorCategoriesArray(): void
    {
        // Empty array should work like normal (no selectors)
        $this->setTranslations([
            'page' => [
                'Hello' => 'Hola',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><p>Hello</p></body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', 'page', []);

        $this->assertStringContainsString('Hola', $result);
    }

    public function testSelectorCategoryPriorityOrder(): void
    {
        // Test the full priority order:
        // 1. Override selector
        // 2. data-langsys-category
        // 3. Inherited category
        // 4. Non-override selector
        // 5. Default category

        $this->setTranslations([
            'Level1-Override' => [
                'Override wins' => 'Sobrescritura gana',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <div data-langsys-category="Level2-DataAttr">
                <section data-langsys-category="Level3-Nested">
                    <p class="special">Override wins</p>
                </section>
            </div>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', 'Level5-Default', [
            '.special' => [
                'category' => 'Level1-Override',
                'overrideParentElementCategory' => true,
            ],
            'p' => [
                'category' => 'Level4-NonOverride',
                'overrideParentElementCategory' => false,
            ],
        ]);

        // Override selector should win over everything
        $this->assertStringContainsString('Sobrescritura gana', $result);
    }

    // =========================================================================
    // data-langsys-contentblock Tests
    // =========================================================================

    public function testContentBlockAttributeTreatsElementAsWholeBlock(): void
    {
        // Without data-langsys-contentblock, nested paragraphs would be separate phrases
        // With it, they become a single content block
        $customId = (new HtmlParser())->generateCustomId('__uncategorized__', ['Header', 'First paragraph', 'Second paragraph']);

        $this->setTranslations([
            '__uncategorized__' => [
                $customId => [
                    'Header' => 'Encabezado',
                    'First paragraph' => 'Primer parrafo',
                    'Second paragraph' => 'Segundo parrafo',
                ],
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <section data-langsys-contentblock="true">
                <h2>Header</h2>
                <p>First paragraph</p>
                <p>Second paragraph</p>
            </section>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Encabezado', $result);
        $this->assertStringContainsString('Primer parrafo', $result);
        $this->assertStringContainsString('Segundo parrafo', $result);
    }

    public function testContentBlockAttributeWithCategory(): void
    {
        $customId = (new HtmlParser())->generateCustomId('widgets', ['Widget Title', 'Widget Content']);

        $this->setTranslations([
            'widgets' => [
                $customId => [
                    'Widget Title' => 'Titulo del Widget',
                    'Widget Content' => 'Contenido del Widget',
                ],
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <div data-langsys-contentblock="1" data-langsys-category="widgets">
                <h3>Widget Title</h3>
                <p>Widget Content</p>
            </div>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Titulo del Widget', $result);
        $this->assertStringContainsString('Contenido del Widget', $result);
    }

    public function testContentBlockAttributeFalsyValuesIgnored(): void
    {
        // data-langsys-contentblock="false" should be ignored (truthy check)
        // So the nested elements should be processed separately as phrases
        $this->setTranslations([
            '__uncategorized__' => [
                'Separate' => 'Separado',
                'Phrases' => 'Frases',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <section data-langsys-contentblock="false">
                <p>Separate</p>
                <p>Phrases</p>
            </section>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Separado', $result);
        $this->assertStringContainsString('Frases', $result);
    }

    public function testContentBlockAttributeZeroIgnored(): void
    {
        // data-langsys-contentblock="0" should be ignored
        $this->setTranslations([
            '__uncategorized__' => [
                'Item One' => 'Elemento Uno',
                'Item Two' => 'Elemento Dos',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <div data-langsys-contentblock="0">
                <p>Item One</p>
                <p>Item Two</p>
            </div>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Elemento Uno', $result);
        $this->assertStringContainsString('Elemento Dos', $result);
    }

    public function testContentBlockAttributeEmptyEnablesIt(): void
    {
        // An empty value now ENABLES the marker, matching data-langsys-phrase
        // and data-notrans: presence is intent, only "false"/"0" opts out.
        //
        // Previously a bare or empty attribute was ignored, which meant
        // `<div data-langsys-contentblock>` silently did nothing - the same
        // failure shape as the data-notrans bug, where a marker looks applied
        // and isn't.
        $this->setTranslations([
            '__uncategorized__' => [
                'Text A' => 'Texto A',
                'Text B' => 'Texto B',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <div data-langsys-contentblock="">
                <p>Text A</p>
                <p>Text B</p>
            </div>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        // Treated as ONE content block, so the individual phrase translations
        // above no longer apply - the block has no translation of its own.
        $this->assertStringContainsString('Text A', $result);
        $this->assertStringContainsString('Text B', $result);
    }

    public function testContentBlockAttributeExplicitFalseOptsOut(): void
    {
        $this->setTranslations([
            '__uncategorized__' => [
                'Text A' => 'Texto A',
                'Text B' => 'Texto B',
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <div data-langsys-contentblock=" FALSE ">
                <p>Text A</p>
                <p>Text B</p>
            </div>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        // Opted out, so the paragraphs translate as individual phrases.
        $this->assertStringContainsString('Texto A', $result);
        $this->assertStringContainsString('Texto B', $result);
    }

    public function testContentBlockAttributeWithSelectorCategory(): void
    {
        // Selector category should work with data-langsys-contentblock
        $customId = (new HtmlParser())->generateCustomId('Cards', ['Card Title', 'Card Description']);

        $this->setTranslations([
            'Cards' => [
                $customId => [
                    'Card Title' => 'Titulo de Tarjeta',
                    'Card Description' => 'Descripcion de Tarjeta',
                ],
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <article class="card" data-langsys-contentblock="yes">
                <h4>Card Title</h4>
                <p>Card Description</p>
            </article>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es', null, [
            '.card' => ['category' => 'Cards'],
        ]);

        $this->assertStringContainsString('Titulo de Tarjeta', $result);
        $this->assertStringContainsString('Descripcion de Tarjeta', $result);
    }

    public function testContentBlockPreservesNestedStructure(): void
    {
        // Complex nested structure should be preserved as content block
        $customId = (new HtmlParser())->generateCustomId('__uncategorized__', ['Name', 'Email', 'Submit']);

        $this->setTranslations([
            '__uncategorized__' => [
                $customId => [
                    'Name' => 'Nombre',
                    'Email' => 'Correo',
                    'Submit' => 'Enviar',
                ],
            ],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body>
            <form data-langsys-contentblock="true">
                <div class="field">
                    <label>Name</label>
                </div>
                <div class="field">
                    <label>Email</label>
                </div>
                <div class="actions">
                    <button>Submit</button>
                </div>
            </form>
        </body></html>';

        $translator = new PageTranslator($this->client);
        $result = $translator->translate($html, 'es-es');

        $this->assertStringContainsString('Nombre', $result);
        $this->assertStringContainsString('Correo', $result);
        $this->assertStringContainsString('Enviar', $result);
        // Structure should be preserved
        $this->assertStringContainsString('<div class="field">', $result);
        $this->assertStringContainsString('<div class="actions">', $result);
    }

    // =========================================================================
    // R-1 — the legacy fallback must cover the PAGE path, not only
    //        translateContentBlock. A website renders through translatePage.
    // =========================================================================

    private function legacyBlockFixture()
    {
        $inner = '<h2>Welcome to our shop</h2><p>Free delivery</p>';
        $category = 'Marketing';
        $phrases = ['Welcome to our shop', 'Free delivery'];

        return [
            'inner' => $inner,
            'category' => $category,
            'html' => '<!DOCTYPE html><html><head></head><body><div data-langsys-contentblock>'
                . $inner . '</div></body></html>',
            'legacyId' => md5(implode('|', array_merge([$category], $phrases))),
            'translations' => [
                'Welcome to our shop' => 'Bienvenido a nuestra tienda',
                'Free delivery' => 'Envio gratis',
            ],
        ];
    }

    public function testTranslatePageServesAContentBlockFoundUnderItsLegacyId(): void
    {
        $f = $this->legacyBlockFixture();
        $this->setTranslations([$f['category'] => [$f['legacyId'] => $f['translations']]]);

        $result = (new PageTranslator($this->client))->translate($f['html'], 'es-es', $f['category']);

        $this->assertStringContainsString('Bienvenido a nuestra tienda', $result);
        $this->assertStringContainsString('Envio gratis', $result);
    }

    /**
     * The anti-stranding half on the page path. Queuing a legacy-resolved block
     * registers it under the CURRENT id and leaves its translations on the old
     * one - which is the damage, not a side effect of it.
     */
    public function testTranslatePageDoesNotQueueALegacyResolvedContentBlock(): void
    {
        $f = $this->legacyBlockFixture();
        $this->setTranslations([$f['category'] => [$f['legacyId'] => $f['translations']]]);

        (new PageTranslator($this->client))->translate($f['html'], 'es-es', $f['category']);

        $this->assertFalse(
            $this->client->hasPendingRegistrations(),
            'Queuing a legacy-resolved block is what strands its translations'
        );
    }

    /**
     * Both paths must agree - the divergence is the defect.
     */
    public function testPageAndContentBlockPathsAgreeOnALegacyBlock(): void
    {
        $f = $this->legacyBlockFixture();
        $this->setTranslations([$f['category'] => [$f['legacyId'] => $f['translations']]]);

        $viaBlock = $this->client->translateContentBlock(
            '<div data-langsys-contentblock>' . $f['inner'] . '</div>',
            $f['category']
        );
        $viaPage = (new PageTranslator($this->client))->translate($f['html'], 'es-es', $f['category']);

        $this->assertStringContainsString('Bienvenido a nuestra tienda', $viaBlock);
        $this->assertStringContainsString('Bienvenido a nuestra tienda', $viaPage);
    }

    // =========================================================================
    // R-2 — a queued item is an attempt, not an acceptance
    // =========================================================================

    /**
     * Items are only QUEUED here; the flush that would send them runs later and
     * can skip, fail, or never run. Recording them as registered suppresses them
     * on every later render until the cache expires, so one failed flush costs
     * the content indefinitely.
     */
    public function testDiscoveredItemsAreNotRecordedAsRegisteredWhenOnlyQueued(): void
    {
        $cache = new \Langsys\SDK\Cache\FileCache(sys_get_temp_dir() . '/langsys-test-' . uniqid());
        $this->setTranslations([]);
        $html = '<!DOCTYPE html><html><head></head><body><p>Undiscovered phrase</p></body></html>';

        // First render: the item is queued. The flush that would send it has not
        // run, so nothing has been accepted.
        $first = $this->createMockClientWithCache($cache);
        (new PageTranslator($first))->translate($html, 'es-es', 'Marketing');
        $this->assertTrue($first->hasPendingRegistrations(), 'sanity: the first render queues it');

        // Second render, fresh client, same shared cache - as under PHP-FPM,
        // with the first request having died before its flush completed. The
        // item must still be discoverable. Asserted behaviourally rather than by
        // reading the cache key, so this pin cannot be satisfied by the key
        // simply changing shape.
        $second = $this->createMockClientWithCache($cache);
        (new PageTranslator($second))->translate($html, 'es-es', 'Marketing');

        $this->assertTrue(
            $second->hasPendingRegistrations(),
            'A queued-but-unsent item was recorded as registered, so it can never be discovered again'
        );

        $cache->clear();
    }

    // =========================================================================
    // R-3 — presence and structure checks must agree with Client::translate()
    // =========================================================================

    public function testTextCollidingWithAContentBlockIdIsNotRegisteredAsAPhrase(): void
    {
        $blockId = md5('some-content-block');

        $this->setTranslations([
            'Marketing' => [$blockId => ['Inner phrase' => 'Frase interna']],
        ]);

        $html = '<!DOCTYPE html><html><head></head><body><p>' . $blockId . '</p></body></html>';
        (new PageTranslator($this->client))->translate($html, 'es-es', 'Marketing');

        $queued = array_map(
            function ($pending) { return $pending['phrase']; },
            array_values($this->client->getPendingPhrases())
        );

        // Asserted unconditionally: a foreach over the queue would pass by
        // performing no assertions at all when the queue is empty, which is the
        // same test whether the behaviour is right or absent.
        $this->assertNotContains(
            $blockId,
            $queued,
            'A nested map is a content block, never a missing phrase'
        );
    }

    // =========================================================================
    // R-4 — the registered-items cache is shared across projects
    // =========================================================================

    public function testRegisteredItemsCacheKeyIsProjectScoped(): void
    {
        $translator = new PageTranslator($this->client);

        $method = new \ReflectionMethod($translator, 'getRegisteredItemsCacheKey');
        $method->setAccessible(true);
        $key = $method->invoke($translator, 'Navigation');

        $this->assertStringContainsString('test-project-id', $key);
        $this->assertStringContainsString('Navigation', $key);
    }

    /**
     * Same wiring as createMockClient(), with a real shared cache.
     */
    private function createMockClientWithCache($cache)
    {
        $client = new Client('test-api-key', 'test-project-id', ['cache' => $cache]);
        $reflection = new \ReflectionClass($client);

        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $httpProperty->setValue($client, $this->mockHttp);

        foreach (['translations', 'translatableItems'] as $resourceName) {
            $property = $reflection->getProperty($resourceName);
            $property->setAccessible(true);
            $resource = $property->getValue($client);

            $resourceHttp = (new \ReflectionClass($resource))->getProperty('http');
            $resourceHttp->setAccessible(true);
            $resourceHttp->setValue($resource, $this->mockHttp);
        }

        return $client;
    }

    /**
     * The page path's attribute register/lookup pair.
     *
     * Collection trimmed; the apply side looked up the RAW attribute value. So
     * an alt text wrapped across source lines - the ordinary way anyone writes
     * long alt copy - registered as the collapsed phrase and was then never
     * found again.
     *
     * Driven as a true round trip: register the page, take whatever catalog
     * entry that produced, feed exactly that back, and require the same page to
     * render it. Building the catalog by hand is what hid this - an attribute
     * on the page path registers inside a CONTENT BLOCK, keyed by custom_id,
     * not as a flat phrase, so a hand-built flat catalog fails for every input
     * and proves nothing about the pair.
     *
     * The plain-space control is the load-bearing row: it passed throughout the
     * defect, which is why page attributes looked like they worked.
     *
     * @dataProvider pageAttributeWhitespaceProvider
     */
    public function testPageAttributesAreRegisteredAndTranslatedOnTheSameRender($attrValue, $expectedPhrase): void
    {
        $html = '<html><body><p>Text <img alt="' . $attrValue . '"></p></body></html>';

        $registered = $this->registeredPayloadFor($html);
        $this->assertNotEmpty($registered, 'nothing registered at all');

        $phrases = [];
        $catalog = [];
        foreach ($registered as $item) {
            if ($item['type'] === 'content_block') {
                $entry = [];
                foreach ($item['phrases'] as $nested) {
                    $phrases[] = $nested['phrase'];
                    $entry[$nested['phrase']] = 'X:' . $nested['phrase'];
                }
                $catalog[$item['custom_id']] = $entry;
            } elseif (isset($item['phrase'])) {
                $phrases[] = $item['phrase'];
                $catalog[$item['phrase']] = 'X:' . $item['phrase'];
            }
        }

        $this->assertContains($expectedPhrase, $phrases, 'registration side canonicalised the attribute');

        $rendered = $this->translatePageWithCatalog($html, $catalog);

        $this->assertStringContainsString(
            'alt="X:' . $expectedPhrase . '"',
            $rendered,
            'apply side never found the key the registration side wrote'
        );
    }

    public function pageAttributeWhitespaceProvider(): array
    {
        return [
            // The control. This passed before the fix, which is how the pair
            // stayed broken - attributes appeared to work.
            'plain single spaces'  => ['A short alt', 'A short alt'],
            'wrapped across lines' => ["A long\n     alt", 'A long alt'],
            'internal run'         => ['A  long   alt', 'A long alt'],
            'non-breaking space'   => ["A\u{00A0}long alt", 'A long alt'],
        ];
    }

    /**
     * Every phrase text a page registers, in order, from both wire shapes.
     */
    private function registeredTexts($html): array
    {
        $texts = [];
        foreach ($this->registeredPayloadFor($html) as $item) {
            if (isset($item['phrase'])) {
                $texts[] = $item['phrase'];
            }
            foreach (isset($item['phrases']) ? $item['phrases'] : [] as $nested) {
                $texts[] = $nested['phrase'];
            }
        }

        return $texts;
    }

    /**
     * Phrases this SDK actually POSTs for a page, as opposed to what it claims
     * to have found. Registration is what creates the catalog key, so this is
     * the only honest left-hand side for a register/lookup test.
     */
    private function registeredPayloadFor($html): array
    {
        $this->setTranslations(['__uncategorized__' => []]);
        $this->mockHttp->setResponse('POST', 'translatable-items', ['status' => true]);

        $client = $this->createMockClient();
        $client->setLocale('es-es');
        $client->translatePage($html);

        $this->mockHttp->clearRequests();
        $client->flushPendingRegistrations();

        $items = [];
        foreach ($this->mockHttp->getRequests() as $request) {
            if ($request['method'] !== 'POST' || !isset($request['data']['translatable_items'])) {
                continue;
            }
            foreach ($request['data']['translatable_items'] as $item) {
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Render a page against a catalog keyed exactly as given.
     */
    private function translatePageWithCatalog($html, array $catalog): string
    {
        $this->setTranslations(['__uncategorized__' => $catalog]);

        $client = $this->createMockClient();
        $client->setLocale('es-es');

        return $client->translatePage($html);
    }

    /**
     * SRV-5's measurable half: each miss is produced once per subtree.
     *
     * Asserted on the RAW WALKER, not on what gets POSTed, and the distinction
     * is the whole test. Three dedupe layers sit between the walk and the wire
     * (findNewPhrasesWithCategory's `$seen`, the pendingPhrases key, the
     * pendingContentBlocks id). A walker re-entering every nested block twice
     * produces eight copies of one miss at depth 3, and all three layers
     * collapse them to a single POST - so counting requests reports "once" for
     * a walker doing 2^n work and stays green through exactly the defect the
     * rule exists for. The earlier version of this test counted requests.
     *
     * The duplicates would be identical, which is why a set-based assertion
     * cannot see them either - the count is right, the SUBJECT was wrong.
     */
    public function testADeeplyNestedMissIsWalkedExactlyOnce(): void
    {
        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML(
            '<?xml encoding="UTF-8"><html><body><div><div><div><p>Deeply nested</p></div></div></div></body></html>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();

        $reflection = new \ReflectionClass(PageTranslator::class);
        $translator = $reflection->newInstanceWithoutConstructor();

        $parser = $reflection->getProperty('htmlParser');
        $parser->setAccessible(true);
        $parser->setValue($translator, new HtmlParser());

        $walk = $reflection->getMethod('walkForExtraction');
        $walk->setAccessible(true);

        $phrases = [];
        $blocks = [];
        $walk->invokeArgs($translator, [$doc->documentElement, &$phrases, &$blocks, null]);

        $occurrences = 0;
        foreach ($phrases as $phrase) {
            if (isset($phrase['text']) && $phrase['text'] === 'Deeply nested') {
                $occurrences++;
            }
        }

        $this->assertSame(
            1,
            $occurrences,
            'a depth-3 subtree must be WALKED once; dedupe downstream would hide 2^n here'
        );
    }

    /**
     * TOK-1: the page path translates SVG text, and skips MathML.
     *
     * This was a characterisation test asserting the opposite - the gap was
     * real and recorded - and it failed the moment the gap closed, which is
     * what a characterisation test is for.
     *
     * `<svg>` is handled as a LEAF where it is found, not promoted to a block
     * element. The promotion is what broke icon-bearing paragraphs the first
     * time: it made containsNestedBlocks() true for the icon's parent, so the
     * walker recursed past it and skipped the parent's own text.
     */
    public function testThePagePathTranslatesSvgTextButNotMath(): void
    {
        $registered = $this->registeredTexts(
            '<html><body><svg><text>SvgLabel</text></svg>'
            . '<math><mi>MathLabel</mi></math><p>Ordinary</p></body></html>'
        );

        // Sorted, not in document order: a standalone <svg> registers as a
        // content block and the paragraph as a phrase, and the two queues flush
        // as separate batches - so cross-queue order is an artefact of the
        // flush, not a property worth pinning. Order WITHIN a block is a real
        // property and is asserted in testAnInlineIconKeepsDocumentOrder.
        sort($registered);

        $this->assertSame(['Ordinary', 'SvgLabel'], $registered);
    }

    /**
     * D1's ordering clause: a block containing an inline icon registers its own
     * text AND the svg's text, in document order.
     *
     * This is the clause the first attempt broke - promoting `<svg>` to a block
     * element made the walker skip the parent's own text, so `Hello` vanished
     * and only `Label` survived. assertSame on the whole list, because the
     * defect was text going missing and containment cannot see that.
     *
     * @dataProvider inlineIconOrderProvider
     */
    public function testAnInlineIconKeepsDocumentOrder($html, array $expected): void
    {
        $this->assertSame($expected, $this->registeredTexts('<html><body>' . $html . '</body></html>'));
    }

    public function inlineIconOrderProvider(): array
    {
        return [
            'text then icon text' => [
                '<p>Hello <svg><text>Label</text></svg></p>', ['Hello', 'Label'],
            ],
            'icon text then text' => [
                '<p><svg><text>Label</text></svg> Hello</p>', ['Label', 'Hello'],
            ],
            'text either side' => [
                '<p>Before <svg><text>Mid</text></svg> After</p>', ['Before', 'Mid', 'After'],
            ],
            'decorative icon, no svg text' => [
                '<p>Click <svg><path/></svg> to continue</p>', ['Click', 'to continue'],
            ],
        ];
    }

    /**
     * D1's in-place clause: translating SVG text replaces the text node and
     * leaves the drawing alone.
     *
     * Both shapes that used to destroy it: a standalone `<svg>`, and an `<svg>`
     * that is an element's only content - the latter took the simple-phrase
     * branch, which applies by assigning to textContent, and rendered
     * `<p>Etiqueta</p>` with every `<path>` gone.
     *
     * @dataProvider svgApplyProvider
     */
    public function testTranslatingSvgTextLeavesTheDrawingIntact($html, $why): void
    {
        $rendered = $this->translatePageWithCatalog($html, $this->catalogFor($html));

        $this->assertStringContainsString('X:Label', $rendered, 'the label must actually translate');
        $this->assertStringContainsString('<path', $rendered, $why);
        $this->assertStringContainsString('<text', $rendered, 'the text element must survive too');
    }

    public function svgApplyProvider(): array
    {
        $svg = '<svg viewBox="0 0 10 10"><path d="M0 0L1 1"/><text x="1">Label</text></svg>';

        return [
            'standalone'        => ['<html><body>' . $svg . '</body></html>', 'a standalone drawing must survive'],
            'only child of a p' => ['<html><body><p>' . $svg . '</p></body></html>', 'this shape used to render as <p>Etiqueta</p>'],
            'beside text'       => ['<html><body><p>Hello ' . $svg . '</p></body></html>', 'and beside text'],
        ];
    }

    /**
     * A catalog that resolves whatever the page registers, flat and by block id,
     * so an apply test does not have to know which shape it will take.
     */
    private function catalogFor($html): array
    {
        $catalog = [];
        foreach ($this->registeredPayloadFor($html) as $item) {
            if (isset($item['phrase'])) {
                $catalog[$item['phrase']] = 'X:' . $item['phrase'];
            }
            if (isset($item['phrases'])) {
                $entry = [];
                foreach ($item['phrases'] as $nested) {
                    $entry[$nested['phrase']] = 'X:' . $nested['phrase'];
                }
                $catalog[$item['custom_id']] = $entry;
            }
        }

        return $catalog;
    }

    /**
     * But the CONTENT-BLOCK path does tokenize SVG text, and excludes MathML -
     * so the gap above is the page path alone, not the SDK.
     */
    public function testTheBlockPathTokenizesSvgTextAndNotMath(): void
    {
        $parser = new HtmlParser();

        $this->assertSame(
            ['SvgLabel', 'Ordinary'],
            array_values($parser->extractPhrases(
                '<svg><text>SvgLabel</text></svg><math><mi>MathLabel</mi></math><p>Ordinary</p>'
            ))
        );
    }

    /**
     * An inline `<svg>` icon must not cost the text around it.
     *
     * Icon-bearing paragraphs, headings, list items and links are among the
     * commonest markup on a modern page. Treating `<svg>` as a block element
     * made `containsNestedBlocks()` true for the parent, so the walker recursed
     * into it and skipped the parent's own direct text nodes - and
     * `<p>Click <svg/> to continue</p>` registered NOTHING at all.
     *
     * assertSame on the whole registered set, not assertContains: the defect
     * was text going MISSING, which a containment assertion cannot see.
     *
     * @dataProvider inlineIconProvider
     */
    public function testTextAroundAnInlineSvgIconIsStillRegistered($html, array $expected, $why): void
    {
        $registered = [];
        foreach ($this->registeredPayloadFor('<html><body>' . $html . '</body></html>') as $item) {
            if (isset($item['phrase'])) {
                $registered[] = $item['phrase'];
            }
            foreach (isset($item['phrases']) ? $item['phrases'] : [] as $nested) {
                $registered[] = $nested['phrase'];
            }
        }

        $this->assertSame($expected, $registered, $why);
    }

    public function inlineIconProvider(): array
    {
        return [
            'icon after text in a paragraph' => [
                '<p>Hello <svg><text>Label</text></svg></p>', ['Hello', 'Label'],
                'the paragraph text must survive an icon beside it',
            ],
            'text either side of an icon' => [
                '<p>Click <svg><path/></svg> to continue</p>', ['Click', 'to continue'],
                'a decorative icon mid-sentence registered nothing at all',
            ],
            'icon leading a list item' => [
                '<li><svg/> Item one</li>', ['Item one'],
                'icon-led list items are ordinary markup',
            ],
            'icon leading a heading' => [
                '<h1><svg/>Heading</h1>', ['Heading'],
                'and icon-led headings',
            ],
        ];
    }

    /**
     * And a standalone `<svg>` must survive being rendered.
     *
     * Extracting it as a simple phrase routed it through the text-content
     * fallback, which replaces the element's entire contents with a string - so
     * translating the label deleted every `<path>` in the graphic. Whatever we
     * do about translating SVG text, destroying the artwork is not it.
     */
    public function testAStandaloneSvgSurvivesRendering(): void
    {
        $svg = '<svg viewBox="0 0 10 10"><path d="M0 0L1 1"/><text x="1">Label</text></svg>';

        $rendered = $this->translatePageWithCatalog(
            '<html><body>' . $svg . '</body></html>',
            ['Label' => 'X:Label']
        );

        $this->assertStringContainsString('<path', $rendered, 'the graphic must not be destroyed');
        $this->assertStringContainsString('<text', $rendered, 'nor its text element flattened away');
    }

    /**
     * The page path's padding restoration, which had only its Client-side twin
     * pinned: non-breaking padding around a translated run must survive, or
     * words run together in the rendered page.
     */
    public function testPagePathPreservesNonBreakingPadding(): void
    {
        $rendered = $this->translatePageWithCatalog(
            "<html><body><p>\u{00A0}Buy now\u{00A0}</p></body></html>",
            ['Buy now' => 'Compra ya']
        );

        $this->assertMatchesRegularExpression(
            '/>[\s\x{00A0}]Compra ya[\s\x{00A0}]</u',
            $rendered,
            'padding either side of the translated run must survive'
        );
    }

    /**
     * A tokenized phrase's descendant ATTRIBUTES are registered canonicalised,
     * and looked up the same way. Both halves were live and neither was pinned.
     */
    public function testTokenizedDescendantAttributesAreCanonicalised(): void
    {
        $registered = [];
        foreach ($this->registeredPayloadFor(
            "<html><body><p data-ls-phrase>Hi <img alt=\"A long\n  alt\"> there</p></body></html>"
        ) as $item) {
            if (isset($item['phrase'])) {
                $registered[] = $item['phrase'];
            }
        }

        $this->assertContains(
            'A long alt',
            $registered,
            'a descendant attribute inside a tokenized run must register collapsed'
        );
    }

    public function testTokenizedDescendantAttributesAreTranslated(): void
    {
        $rendered = $this->translatePageWithCatalog(
            "<html><body><p data-ls-phrase>Hi <img alt=\"A long\n  alt\"> there</p></body></html>",
            ['A long alt' => 'Alt traducido']
        );

        $this->assertStringContainsString(
            'alt="Alt traducido"',
            $rendered,
            'the apply side must look up the key the registration side wrote'
        );
    }

    /**
     * The other page-path padding site: a content block's text node, reached
     * through walkAndTranslate rather than the single-phrase branch. Both had
     * to be pinned separately - the first test covered only one of them, so the
     * second could be reverted to ASCII `\s` with the suite green.
     */
    public function testPagePathPreservesNonBreakingPaddingInsideABlock(): void
    {
        $parser = new HtmlParser();
        $id = $parser->generateCustomId('__uncategorized__', ['Buy now', 'Later']);

        $rendered = $this->translatePageWithCatalog(
            "<html><body><p>\u{00A0}Buy now\u{00A0}<span>Later</span></p></body></html>",
            [$id => ['Buy now' => 'Compra ya', 'Later' => 'Luego']]
        );

        $this->assertStringContainsString('Compra ya', $rendered, 'sanity: the block resolved');
        $this->assertMatchesRegularExpression(
            '/>[\s\x{00A0}]Compra ya[\s\x{00A0}]</u',
            $rendered,
            'padding around a block text node must survive too'
        );
    }

    /**
     * A phrase wrapped in inline markup keeps its markup (SEVERE, pre-existing).
     *
     * `replaceTextContent()` looked only at DIRECT child text nodes and then
     * fell back to `$element->textContent = $translated`, which replaces every
     * child with a string. So a phrase whose text sits one level down - a link,
     * a `<strong>`, a `<span>` - had its wrapper DELETED from the served bytes
     * on translation. `<li><a href="#">Label</a></li>` rendered as
     * `<li>X:Label</li>`: the link is gone, and with it the navigation.
     *
     * Present on `main` (224dc8b) as well as this branch, so not a regression
     * of the canonicalization work - but it is the same apply path, and the
     * shapes below are ordinary: nav items, card links, buttons, table cells.
     *
     * @dataProvider inlineWrapperProvider
     */
    public function testAPhraseWrappedInInlineMarkupKeepsItsMarkup($html, $phrase, $mustKeep): void
    {
        $rendered = $this->translatePageWithCatalog(
            '<html><body>' . $html . '</body></html>',
            [$phrase => 'X:' . $phrase]
        );

        $this->assertStringContainsString('X:' . $phrase, $rendered, 'sanity: the phrase translated');
        $this->assertStringContainsString($mustKeep, $rendered, 'the wrapper must survive translation');
    }

    public function inlineWrapperProvider(): array
    {
        return [
            'link in a list item' => ['<li><a href="#">Label</a></li>', 'Label', '<a href="#"'],
            'link in a heading'   => ['<h1><a href="/">Home</a></h1>', 'Home', '<a href="/"'],
            'strong in a p'       => ['<p><strong>Bold</strong></p>', 'Bold', '<strong>'],
            'span in a cell'      => ['<td><span>Cell</span></td>', 'Cell', '<span>'],
            'nested wrappers'     => ['<p><a href="/"><strong>Deep</strong></a></p>', 'Deep', '<strong>'],
        ];
    }

    /**
     * And an element whose text is split by a `<br>` keeps the `<br>`.
     */
    public function testABreakInsideATranslatedElementSurvives(): void
    {
        $rendered = $this->translatePageWithCatalog(
            '<html><body><p>Hello<br></p></body></html>',
            ['Hello' => 'X:Hello']
        );

        $this->assertStringContainsString('X:Hello', $rendered);
        $this->assertStringContainsString('<br', $rendered, 'the break must survive');
    }

    /**
     * The THIRD route: a tokenized run (data-ls-phrase) containing an icon.
     *
     * `MarkupTokenizer::OPAQUE_ELEMENTS` still listed `svg`, under a comment
     * claiming it mirrored `SKIP_ELEMENTS` - which stopped being true the
     * moment the page path learned to translate SVG text. So a marked run
     * registered `Hi {m0o}{m0c}` with the label swallowed, and "both paths
     * translate SVG text" was two routes out of three.
     */
    public function testATokenizedRunExposesSvgText(): void
    {
        $registered = $this->registeredTexts(
            '<html><body><p data-ls-phrase>Hi <svg><path/><text>Label</text></svg></p></body></html>'
        );

        $this->assertCount(1, $registered, 'a marked run is one phrase');
        $this->assertStringContainsString('Label', $registered[0], 'the icon label must be inside the tokenized phrase');
        $this->assertStringContainsString('Hi', $registered[0]);
    }

    /**
     * The four canonicalisation sites that were live but unpinned - each an
     * apply or registration site where reverting to a collapse-only
     * normalisation changed behaviour and reddened nothing.
     *
     * `%name%` is the discriminator: it is normalised by Canonical::phrase and
     * NOT by Whitespace::collapse, so a site using the wrong one still handles
     * whitespace correctly and silently fails on placeholders.
     */
    public function testABlockTextNodeResolvesAPercentPlaceholder(): void
    {
        $parser = new HtmlParser();
        $id = $parser->generateCustomId('__uncategorized__', ['Hello {name}', 'Later']);

        $rendered = $this->translatePageWithCatalog(
            '<html><body><p>Hello %name% <span>Later</span></p></body></html>',
            [$id => ['Hello {name}' => 'Hola {name}', 'Later' => 'X:Later']]
        );

        $this->assertStringContainsString('Hola Sarah', str_replace('{name}', 'Sarah', $rendered));
        $this->assertStringNotContainsString('%name%', $rendered, 'the placeholder must not reach the reader');
    }

    public function testASinglePhraseElementResolvesAPercentPlaceholder(): void
    {
        $rendered = $this->translatePageWithCatalog(
            '<html><body><p>Hello %name%<br></p></body></html>',
            ['Hello {name}' => 'Hola {name}']
        );

        $this->assertStringContainsString('Hola', $rendered);
        $this->assertStringContainsString('<br', $rendered, 'and the break survives');
    }

    /**
     * getTextContent() decides whether an element is a simple phrase or a
     * content block, by comparing its text against the extracted phrase list.
     * Canonicalising one side and not the other changed which of the two it
     * became - an identity change, since the id is derived differently.
     */
    public function testAPercentPlaceholderDoesNotChangeAnElementsShape(): void
    {
        $registered = [];
        foreach ($this->registeredPayloadFor('<html><body><p>Hello %name%<br></p></body></html>') as $item) {
            $registered[] = $item['type'] . ':' . (isset($item['phrase']) ? $item['phrase'] : '');
        }

        $this->assertSame(['phrase:Hello {name}'], $registered, 'must register as a phrase, not a content block');
    }

    /**
     * An element's OWN translatable attribute, inside a tokenized run.
     */
    public function testATokenizedRunsOwnAttributeIsCanonicalised(): void
    {
        $registered = $this->registeredTexts(
            "<html><body><p data-ls-phrase title=\"A long\n   title\">Hi</p></body></html>"
        );

        $this->assertContains('A long title', $registered, 'the element\'s own attribute must register collapsed');
    }

    /**
     * The fallback that loses markup must LOG, not fatal.
     *
     * A `debug()` call was added here against a `$this->logger` the class did
     * not have, so on the one path that reaches it the warning became "Call to
     * a member function debug() on null" and took the render down. A line whose
     * whole purpose is to avoid failing silently failed loudly instead.
     *
     * It also made the mutation evidence for the descendant-replace fix weaker
     * than reported: that mutant reddened via this fatal rather than via the
     * assertions, and a mutant killed by a crash proves the crash, not the
     * guard.
     *
     * Driven directly, because the simple-phrase gate makes the fallback hard
     * to reach from translatePage() - which is exactly why it went unnoticed.
     */
    public function testTheMarkupLosingFallbackLogsRatherThanFatals(): void
    {
        // A CONSTRUCTED translator, which is the contract that actually holds.
        //
        // An earlier version of this test said it was "deliberately NOT
        // injecting a logger" and then injected one by reflection three lines
        // later - the comment describing a stronger guarantee than the code
        // tested. The property cannot default to a NullLogger, because PHP
        // forbids `new` in a property initializer; the constructor supplies it.
        // So the honest claim is that every constructed instance is safe, and
        // that is what this asserts.
        $translator = new PageTranslator($this->createMockClient());

        $reflection = new \ReflectionClass(PageTranslator::class);
        $method = $reflection->getMethod('replaceTextContent');
        $method->setAccessible(true);

        $doc = new \DOMDocument();
        libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="UTF-8"><p>Hello<b></b>World</p>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $element = $doc->getElementsByTagName('p')->item(0);

        $method->invokeArgs($translator, [$element, 'HelloWorld', 'X']);

        $this->assertSame('X', $element->textContent, 'the fallback still runs');
    }

    /**
     * And a constructed translator has a usable logger without being given one.
     */
    public function testAConstructedTranslatorAlwaysHasALogger(): void
    {
        $translator = new PageTranslator($this->createMockClient());

        $logger = (new \ReflectionClass($translator))->getProperty('logger');
        $logger->setAccessible(true);

        $this->assertNotNull($logger->getValue($translator));
        $this->assertTrue(method_exists($logger->getValue($translator), 'debug'));
    }

    /**
     * The own-attribute site, pinned with a PLACEHOLDER rather than whitespace.
     *
     * The first version of this test used `title="A long\n   title"`, which
     * `Whitespace::collapse` handles too - so a collapse-only revert at that
     * site passed. `%name%` is the discriminator: only `Canonical::phrase`
     * rewrites it, so a site using the wrong normaliser still looks correct on
     * whitespace and fails only on placeholders.
     */
    public function testATokenizedRunsOwnAttributeCanonicalisesPlaceholders(): void
    {
        $registered = $this->registeredTexts(
            '<html><body><p data-ls-phrase title="Hello %name%">Hi</p></body></html>'
        );

        $this->assertContains('Hello {name}', $registered, 'the element\'s own attribute must canonicalise placeholders');
    }
}
