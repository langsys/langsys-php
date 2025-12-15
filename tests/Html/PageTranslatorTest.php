<?php

namespace Langsys\SDK\Tests\Html;

use PHPUnit\Framework\TestCase;
use Langsys\SDK\Html\PageTranslator;
use Langsys\SDK\Client;
use Langsys\SDK\Config;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Tests\Mock\MockHttpClient;

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
        $customId = md5('__uncategorized__|Hello|World');

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
        $customId = md5('nav|Home|About|Contact');

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
        $customId = md5('__uncategorized__|Enter your name');

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
        $customId = md5('__uncategorized__|Logo image');

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
        $customId = md5('__uncategorized__|Submit');

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
        $navCustomId = md5('homepage|Home|About');
        $formCustomId = md5('homepage|Your email|Subscribe');

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
        $customId = md5('__uncategorized__|Header|First paragraph|Second paragraph');

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
        $customId = md5('widgets|Widget Title|Widget Content');

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

    public function testContentBlockAttributeEmptyIgnored(): void
    {
        // data-langsys-contentblock="" (empty) should be ignored
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

        $this->assertStringContainsString('Texto A', $result);
        $this->assertStringContainsString('Texto B', $result);
    }

    public function testContentBlockAttributeWithSelectorCategory(): void
    {
        // Selector category should work with data-langsys-contentblock
        $customId = md5('Cards|Card Title|Card Description');

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
        $customId = md5('__uncategorized__|Name|Email|Submit');

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
}
