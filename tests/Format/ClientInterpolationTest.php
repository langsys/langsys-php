<?php

namespace Langsys\SDK\Tests\Format;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests for $params threading through the Client translate methods.
 *
 * The load-bearing tests here are the ones asserting that the RAW,
 * placeholder-bearing phrase is what gets queued for registration. If an
 * interpolated string were ever queued, every distinct runtime value would mint
 * a new catalog entry - polluting the catalog shared with the JS SDKs.
 */
class ClientInterpolationTest extends TestCase
{
    protected function makeClient(array $translations = [])
    {
        $mockHttp = new MockHttpClient();
        $mockHttp->setResponse('GET', 'authorize-project/project-id', [
            'data' => ['key_type' => 'write'],
        ]);
        $mockHttp->setResponse('GET', 'translations', [
            'data' => $translations,
        ]);

        $client = new Client('test-api-key', 'project-id', [
            'cache' => new NullCache(),
        ]);

        $reflection = new \ReflectionClass($client);

        $httpProperty = $reflection->getProperty('http');
        $httpProperty->setAccessible(true);
        $httpProperty->setValue($client, $mockHttp);

        $transProperty = $reflection->getProperty('translations');
        $transProperty->setAccessible(true);
        $translationsResource = $transProperty->getValue($client);
        $transReflection = new \ReflectionClass($translationsResource);
        $transHttpProperty = $transReflection->getProperty('http');
        $transHttpProperty->setAccessible(true);
        $transHttpProperty->setValue($translationsResource, $mockHttp);

        $itemsProperty = $reflection->getProperty('translatableItems');
        $itemsProperty->setAccessible(true);
        $items = $itemsProperty->getValue($client);
        $itemsReflection = new \ReflectionClass($items);
        $itemsHttpProperty = $itemsReflection->getProperty('http');
        $itemsHttpProperty->setAccessible(true);
        $itemsHttpProperty->setValue($items, $mockHttp);

        return $client;
    }

    // ---------------------------------------------------------------
    // translate()
    // ---------------------------------------------------------------

    public function testTranslateInterpolatesExistingTranslation()
    {
        $client = $this->makeClient([
            '__uncategorized__' => [
                'Hello, {name}!' => '¡Hola, {name}!',
            ],
        ]);
        $client->setLocale('es-es');

        $this->assertEquals(
            '¡Hola, Sarah!',
            $client->translate('Hello, {name}!', null, '__uncategorized__', null, ['name' => 'Sarah'])
        );
    }

    /**
     * A brand-new phrase must still render with placeholders resolved,
     * otherwise end users see raw {name} until registration round-trips.
     */
    public function testTranslateInterpolatesUntranslatedFallback()
    {
        $client = $this->makeClient(['__uncategorized__' => []]);
        $client->setLocale('es-es');

        $this->assertEquals(
            'Hello, Sarah!',
            $client->translate('Hello, {name}!', null, '__uncategorized__', null, ['name' => 'Sarah'])
        );
    }

    /**
     * The catalog-pollution guarantee: the queued phrase keeps its placeholders.
     */
    public function testTranslateQueuesRawPhraseNotInterpolatedValue()
    {
        $client = $this->makeClient(['__uncategorized__' => []]);
        $client->setLocale('es-es');

        $client->translate('Hello, {name}!', null, '__uncategorized__', null, ['name' => 'Sarah']);

        $pending = $client->getPendingPhrases();

        $this->assertArrayHasKey('__uncategorized__::Hello, {name}!', $pending);

        $serialized = json_encode($pending);
        $this->assertStringNotContainsString('Sarah', $serialized, 'Interpolated value must never be queued');
    }

    /**
     * Rendering the same phrase with many different values must produce exactly
     * ONE catalog entry - the whole point of the params argument.
     */
    public function testManyDistinctValuesProduceASinglePendingPhrase()
    {
        $client = $this->makeClient(['__uncategorized__' => []]);
        $client->setLocale('es-es');

        foreach (['Sarah', 'Ahmed', 'Priya', 'Yuki'] as $name) {
            $client->translate('Hello, {name}!', null, '__uncategorized__', null, ['name' => $name]);
        }

        $this->assertCount(1, $client->getPendingPhrases());
    }

    public function testTranslateWithoutParamsIsUnchanged()
    {
        $client = $this->makeClient([
            '__uncategorized__' => ['Hello' => 'Hola'],
        ]);
        $client->setLocale('es-es');

        $this->assertEquals('Hola', $client->translate('Hello'));
    }

    public function testTranslateLeavesUnknownKeysVisible()
    {
        $client = $this->makeClient(['__uncategorized__' => []]);
        $client->setLocale('es-es');

        $this->assertEquals(
            'Hi Sarah, meet {other}',
            $client->translate('Hi {name}, meet {other}', null, '__uncategorized__', null, ['name' => 'Sarah'])
        );
    }

    public function testTranslateInterpolatesWithoutLocale()
    {
        $client = $this->makeClient();

        // No locale set - translation impossible, but placeholders still resolve.
        $this->assertEquals(
            'Hello, Sarah!',
            $client->translate('Hello, {name}!', null, '__uncategorized__', null, ['name' => 'Sarah'])
        );
    }

    // ---------------------------------------------------------------
    // translateContentBlock()
    // ---------------------------------------------------------------

    public function testContentBlockInterpolatesTextNodes()
    {
        $client = $this->makeClient(['__uncategorized__' => []]);
        $client->setLocale('es-es');

        $result = $client->translateContentBlock(
            '<p>Welcome back, {name}</p>',
            '__uncategorized__',
            ['name' => 'Sarah']
        );

        $this->assertStringContainsString('Welcome back, Sarah', $result);
        $this->assertStringNotContainsString('{name}', $result);
    }

    public function testContentBlockInterpolatesTranslatableAttributes()
    {
        $client = $this->makeClient(['__uncategorized__' => []]);
        $client->setLocale('es-es');

        $result = $client->translateContentBlock(
            '<input placeholder="Search {siteName}">',
            '__uncategorized__',
            ['siteName' => 'Langsys']
        );

        $this->assertStringContainsString('Search Langsys', $result);
    }

    public function testContentBlockQueuesRawHtmlNotInterpolated()
    {
        $client = $this->makeClient(['__uncategorized__' => []]);
        $client->setLocale('es-es');

        $client->translateContentBlock(
            '<p>Welcome back, {name}</p>',
            '__uncategorized__',
            ['name' => 'Sarah']
        );

        $serialized = json_encode($client->getPendingContentBlocks());

        $this->assertStringNotContainsString('Sarah', $serialized, 'Interpolated value must never be queued');
        $this->assertStringContainsString('{name}', $serialized);
    }

    public function testContentBlockWithoutParamsUnchanged()
    {
        $client = $this->makeClient(['__uncategorized__' => []]);
        $client->setLocale('es-es');

        $result = $client->translateContentBlock('<p>Welcome back, {name}</p>');

        $this->assertStringContainsString('{name}', $result);
    }

    // ---------------------------------------------------------------
    // translatePage()
    // ---------------------------------------------------------------

    public function testTranslatePageInterpolatesBody()
    {
        $client = $this->makeClient(['homepage' => []]);
        $client->setLocale('es-es');

        $html = '<!DOCTYPE html><html><head><title>Test</title></head>'
            . '<body><p>Hello, {name}!</p></body></html>';

        $result = $client->translatePage($html, 'homepage', [], ['name' => 'Sarah']);

        $this->assertStringContainsString('Hello, Sarah!', $result);
        $this->assertStringNotContainsString('{name}', $result);
    }

    public function testTranslatePageWithoutParamsLeavesPlaceholders()
    {
        $client = $this->makeClient(['homepage' => []]);
        $client->setLocale('es-es');

        $html = '<!DOCTYPE html><html><head><title>Test</title></head>'
            . '<body><p>Hello, {name}!</p></body></html>';

        $result = $client->translatePage($html, 'homepage');

        $this->assertStringContainsString('{name}', $result);
    }

    /**
     * PageTranslator is cached on the Client and reused, so per-call params
     * must not leak from one translatePage() call into the next.
     */
    public function testPageParamsDoNotLeakBetweenCalls()
    {
        $client = $this->makeClient(['homepage' => []]);
        $client->setLocale('es-es');

        $html = '<!DOCTYPE html><html><head><title>Test</title></head>'
            . '<body><p>Hello, {name}!</p></body></html>';

        $client->translatePage($html, 'homepage', [], ['name' => 'Sarah']);
        $second = $client->translatePage($html, 'homepage');

        $this->assertStringNotContainsString('Sarah', $second);
        $this->assertStringContainsString('{name}', $second);
    }
}
