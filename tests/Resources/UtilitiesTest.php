<?php

namespace Langsys\SDK\Tests\Resources;

use Langsys\SDK\Resources\Utilities;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Utilities resource.
 */
class UtilitiesTest extends TestCase
{
    /**
     * @var MockHttpClient
     */
    protected $http;

    /**
     * @var Utilities
     */
    protected $utilities;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $this->utilities = new Utilities($this->http, 'test-project-id');
    }

    public function testGetCountries()
    {
        $expectedResponse = [
            'status' => true,
            'page' => 1,
            'data' => [
                ['label' => 'United States', 'code' => 'US'],
                ['label' => 'Costa Rica', 'code' => 'CR'],
            ],
        ];

        $this->http->setResponse('GET', 'countries/en-us', $expectedResponse);

        $result = $this->utilities->getCountries('en-us');

        $this->assertEquals($expectedResponse, $result);

        $request = $this->http->getLastRequest();
        $this->assertEquals('GET', $request['method']);
        $this->assertEquals('countries/en-us', $request['endpoint']);
    }

    public function testGetCountriesWithOptions()
    {
        $expectedResponse = ['status' => true, 'data' => []];
        $this->http->setResponse('GET', 'countries/es-es', $expectedResponse);

        $options = [
            'page' => 2,
            'records_per_page' => 50,
            'order_by' => 'label',
        ];

        $this->utilities->getCountries('es-es', $options);

        $request = $this->http->getLastRequest();
        $this->assertEquals(2, $request['params']['page']);
        $this->assertEquals(50, $request['params']['records_per_page']);
        $this->assertEquals('label', $request['params']['order_by']);
    }

    public function testGetAllCountries()
    {
        $response = [
            'status' => true,
            'data' => [
                ['label' => 'United States', 'code' => 'US'],
                ['label' => 'Costa Rica', 'code' => 'CR'],
                ['label' => 'Canada', 'code' => 'CA'],
            ],
        ];

        $this->http->setResponse('GET', 'countries/en-us', $response);

        $result = $this->utilities->getAllCountries('en-us');

        $this->assertEquals($response['data'], $result);

        $request = $this->http->getLastRequest();
        $this->assertEquals(300, $request['params']['records_per_page']);
    }

    public function testGetAllCountriesEmptyResponse()
    {
        $this->http->setResponse('GET', 'countries/en-us', ['status' => true]);

        $result = $this->utilities->getAllCountries('en-us');

        $this->assertEquals([], $result);
    }

    public function testGetCountrySelectOptions()
    {
        $response = [
            'status' => true,
            'data' => [
                ['label' => 'United States', 'code' => 'US'],
                ['label' => 'Costa Rica', 'code' => 'CR'],
            ],
        ];

        $this->http->setResponse('GET', 'countries/en-us', $response);

        $result = $this->utilities->getCountrySelectOptions('en-us');

        $this->assertEquals([
            'US' => 'United States',
            'CR' => 'Costa Rica',
        ], $result);
    }

    public function testGetDialCodes()
    {
        $expectedResponse = [
            'status' => true,
            'data' => [
                ['country_code' => 'US', 'dial_code' => '1', 'name' => 'United States (+1)'],
                ['country_code' => 'CR', 'dial_code' => '506', 'name' => 'Costa Rica (+506)'],
            ],
        ];

        $this->http->setResponse('GET', 'countries/dial-codes/en-us', $expectedResponse);

        $result = $this->utilities->getDialCodes('en-us');

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetAllDialCodes()
    {
        $response = [
            'status' => true,
            'data' => [
                ['country_code' => 'US', 'dial_code' => '1', 'name' => 'United States (+1)'],
            ],
        ];

        $this->http->setResponse('GET', 'countries/dial-codes/en-us', $response);

        $result = $this->utilities->getAllDialCodes('en-us');

        $this->assertEquals($response['data'], $result);
    }

    public function testGetDialCodeSelectOptions()
    {
        $response = [
            'status' => true,
            'data' => [
                ['country_code' => 'US', 'dial_code' => '1', 'name' => 'United States (+1)'],
                ['country_code' => 'CR', 'dial_code' => '506', 'name' => 'Costa Rica (+506)'],
            ],
        ];

        $this->http->setResponse('GET', 'countries/dial-codes/en-us', $response);

        $result = $this->utilities->getDialCodeSelectOptions('en-us');

        $this->assertEquals([
            'US' => 'United States (+1)',
            'CR' => 'Costa Rica (+506)',
        ], $result);
    }

    public function testGetLocalesGrouped()
    {
        $expectedResponse = [
            'status' => true,
            'data' => [
                'en-us' => [
                    'Spanish' => [
                        ['code' => 'es-es', 'name' => 'Spanish (Spain)'],
                        ['code' => 'es-mx', 'name' => 'Spanish (Mexico)'],
                    ],
                ],
            ],
        ];

        $this->http->setResponse('GET', 'locales', $expectedResponse);

        $result = $this->utilities->getLocalesGrouped(['en-us']);

        $this->assertEquals($expectedResponse, $result);

        $request = $this->http->getLastRequest();
        $this->assertEquals(['en-us'], $request['params']['locales']);
        $this->assertEquals('test-project-id', $request['params']['project_id']);
    }

    public function testGetLocalesGroupedWithTargetLocales()
    {
        $this->http->setResponse('GET', 'locales', ['status' => true, 'data' => []]);

        $this->utilities->getLocalesGrouped(['en-us'], true);

        $request = $this->http->getLastRequest();
        $this->assertEquals('true', $request['params']['append_target_locales']);
    }

    public function testGetLocalesFlat()
    {
        $expectedResponse = [
            'status' => true,
            'data' => [
                'en-us' => [
                    ['code' => 'es-es', 'name' => 'Spanish (Spain)'],
                    ['code' => 'fr-fr', 'name' => 'French (France)'],
                ],
            ],
        ];

        $this->http->setResponse('GET', 'locales/flat', $expectedResponse);

        $result = $this->utilities->getLocalesFlat(['en-us']);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetLocalesDetailed()
    {
        $expectedResponse = [
            'status' => true,
            'data' => [
                'en-us' => [
                    ['code' => 'es-es', 'name' => 'Spanish (Spain)', 'language' => 'Spanish'],
                ],
            ],
        ];

        $this->http->setResponse('GET', 'locales/data', $expectedResponse);

        $result = $this->utilities->getLocalesDetailed(['en-us']);

        $this->assertEquals($expectedResponse, $result);
    }

    public function testGetLocaleList()
    {
        $response = [
            'status' => true,
            'data' => [
                'en-us' => [
                    ['code' => 'es-es', 'name' => 'Spanish (Spain)'],
                    ['code' => 'fr-fr', 'name' => 'French (France)'],
                ],
            ],
        ];

        $this->http->setResponse('GET', 'locales/flat', $response);

        $result = $this->utilities->getLocaleList('en-us');

        $this->assertEquals([
            ['code' => 'es-es', 'name' => 'Spanish (Spain)'],
            ['code' => 'fr-fr', 'name' => 'French (France)'],
        ], $result);
    }

    public function testGetLocaleListNotFound()
    {
        $response = [
            'status' => true,
            'data' => [],
        ];

        $this->http->setResponse('GET', 'locales/flat', $response);

        $result = $this->utilities->getLocaleList('xx-xx');

        $this->assertEquals([], $result);
    }

    public function testGetLocaleSelectOptions()
    {
        $response = [
            'status' => true,
            'data' => [
                'en-us' => [
                    ['code' => 'es-es', 'name' => 'Spanish (Spain)'],
                    ['code' => 'fr-fr', 'name' => 'French (France)'],
                ],
            ],
        ];

        $this->http->setResponse('GET', 'locales/flat', $response);

        $result = $this->utilities->getLocaleSelectOptions('en-us');

        $this->assertEquals([
            'es-es' => 'Spanish (Spain)',
            'fr-fr' => 'French (France)',
        ], $result);
    }

    public function testUtilitiesWithoutProjectId()
    {
        $utilities = new Utilities($this->http);

        $this->http->setResponse('GET', 'locales', ['status' => true, 'data' => []]);

        $utilities->getLocalesGrouped(['en-us']);

        $request = $this->http->getLastRequest();
        $this->assertArrayNotHasKey('project_id', $request['params']);
    }
}
