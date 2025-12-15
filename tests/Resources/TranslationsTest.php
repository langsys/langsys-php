<?php

namespace Langsys\SDK\Tests\Resources;

use Langsys\SDK\Resources\Translations;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Translations resource.
 */
class TranslationsTest extends TestCase
{
    /**
     * @var MockHttpClient
     */
    protected $http;

    /**
     * @var Translations
     */
    protected $translations;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $this->translations = new Translations($this->http, 'test-project-id');
    }

    public function testGetFlat()
    {
        $expectedResponse = [
            'status' => true,
            'words' => 100,
            'untranslated' => 10,
            'data' => [
                'UI' => [
                    'Home' => 'Inicio',
                    'About' => 'Acerca de',
                ],
            ],
        ];

        $this->http->setResponse('GET', 'translations', $expectedResponse);

        $result = $this->translations->getFlat('es-es');

        $this->assertEquals($expectedResponse, $result);

        $request = $this->http->getLastRequest();
        $this->assertEquals('GET', $request['method']);
        $this->assertEquals('translations', $request['endpoint']);
        $this->assertEquals('test-project-id', $request['params']['project_id']);
        $this->assertEquals('es-es', $request['params']['locale']);
        $this->assertEquals('flat', $request['params']['format']);
    }

    public function testGetData()
    {
        $expectedResponse = [
            'status' => true,
            'data' => [
                'UI' => [
                    ['phrase' => 'Home', 'translation' => 'Inicio'],
                ],
            ],
        ];

        $this->http->setResponse('GET', 'translations/data', $expectedResponse);

        $result = $this->translations->getData('es-es');

        $this->assertEquals($expectedResponse, $result);

        $request = $this->http->getLastRequest();
        $this->assertEquals('translations/data', $request['endpoint']);
    }

    public function testGetTranslationMap()
    {
        $response = [
            'status' => true,
            'data' => [
                'UI' => [
                    'Home' => 'Inicio',
                    'About' => 'Acerca de',
                ],
                '__uncategorized__' => [
                    'Welcome' => 'Bienvenido',
                ],
            ],
        ];

        $this->http->setResponse('GET', 'translations', $response);

        $result = $this->translations->getTranslationMap('es-es');

        $this->assertEquals($response['data'], $result);
    }

    public function testGetTranslationMapEmptyResponse()
    {
        $this->http->setResponse('GET', 'translations', ['status' => true]);

        $result = $this->translations->getTranslationMap('es-es');

        $this->assertEquals([], $result);
    }

    public function testGetAllPhrases()
    {
        $response = [
            'status' => true,
            'data' => [
                'UI' => [
                    'Home' => 'Inicio',
                    'About' => 'Acerca de',
                ],
                '__uncategorized__' => [
                    'Welcome' => 'Bienvenido',
                ],
            ],
        ];

        $this->http->setResponse('GET', 'translations', $response);

        $result = $this->translations->getAllPhrases('es-es');

        $this->assertContains('Home', $result);
        $this->assertContains('About', $result);
        $this->assertContains('Welcome', $result);
        $this->assertCount(3, $result);
    }

    public function testGetAllPhrasesWithContentBlocks()
    {
        $response = [
            'status' => true,
            'data' => [
                'UI' => [
                    'Home' => 'Inicio',
                    'menu-block' => [
                        'Menu' => 'Menú',
                        'Navigation' => 'Navegación',
                    ],
                ],
            ],
        ];

        $this->http->setResponse('GET', 'translations', $response);

        $result = $this->translations->getAllPhrases('es-es');

        $this->assertContains('Home', $result);
        $this->assertContains('Menu', $result);
        $this->assertContains('Navigation', $result);
        $this->assertCount(3, $result);
    }

    public function testGetStats()
    {
        $response = [
            'status' => true,
            'words' => 752,
            'untranslated' => 25,
            'data' => [],
        ];

        $this->http->setResponse('GET', 'translations', $response);

        $result = $this->translations->getStats('es-es');

        $this->assertEquals(752, $result['words']);
        $this->assertEquals(25, $result['untranslated']);
    }

    public function testGetStatsDefaultValues()
    {
        $response = [
            'status' => true,
            'data' => [],
        ];

        $this->http->setResponse('GET', 'translations', $response);

        $result = $this->translations->getStats('es-es');

        $this->assertEquals(0, $result['words']);
        $this->assertEquals(0, $result['untranslated']);
    }
}
