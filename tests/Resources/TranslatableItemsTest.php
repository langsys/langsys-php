<?php

namespace Langsys\SDK\Tests\Resources;

use Langsys\SDK\Resources\TranslatableItems;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the TranslatableItems resource.
 */
class TranslatableItemsTest extends TestCase
{
    /**
     * @var MockHttpClient
     */
    protected $http;

    /**
     * @var TranslatableItems
     */
    protected $items;

    protected function setUp(): void
    {
        $this->http = new MockHttpClient();
        $this->items = new TranslatableItems($this->http, 'test-project-id');
    }

    public function testCreatePhrases()
    {
        $expectedResponse = ['status' => true, 'created' => 2];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        $phrases = [
            ['phrase' => 'Hello', 'category' => 'Greetings'],
            ['phrase' => 'Goodbye', 'category' => 'Greetings'],
        ];

        $result = $this->items->createPhrases($phrases);

        $this->assertEquals($expectedResponse, $result);

        $request = $this->http->getLastRequest();
        $this->assertEquals('POST', $request['method']);
        $this->assertEquals('translatable-items', $request['endpoint']);
        $this->assertEquals('test-project-id', $request['data']['project_id']);
        $this->assertEquals([
            ['type' => 'phrase', 'phrase' => 'Hello', 'category' => 'Greetings', 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'Goodbye', 'category' => 'Greetings', 'translatable' => true],
        ], $request['data']['translatable_items']);
    }

    public function testCreatePhrasesWithStrings()
    {
        $expectedResponse = ['status' => true, 'created' => 2];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        $phrases = ['Hello', 'Goodbye'];

        $result = $this->items->createPhrases($phrases);

        $this->assertEquals($expectedResponse, $result);

        $request = $this->http->getLastRequest();
        $this->assertEquals([
            ['type' => 'phrase', 'phrase' => 'Hello', 'category' => null, 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'Goodbye', 'category' => null, 'translatable' => true],
        ], $request['data']['translatable_items']);
    }

    public function testCreatePhrasesMixedFormat()
    {
        $expectedResponse = ['status' => true, 'created' => 3];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        $phrases = [
            'Simple phrase',
            ['phrase' => 'With category', 'category' => 'UI'],
            ['phrase' => 'Not translatable', 'translatable' => false],
        ];

        $result = $this->items->createPhrases($phrases);

        $request = $this->http->getLastRequest();
        $this->assertEquals([
            ['type' => 'phrase', 'phrase' => 'Simple phrase', 'category' => null, 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'With category', 'category' => 'UI', 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'Not translatable', 'category' => null, 'translatable' => false],
        ], $request['data']['translatable_items']);
    }

    public function testCreateContentBlock()
    {
        $expectedResponse = ['status' => true, 'id' => 'cb-123'];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        // New API: createContentBlock($content, $category, $label, $customId)
        $result = $this->items->createContentBlock(
            '<nav><a>Home</a><a>About</a></nav>',
            'Navigation',
            'Main Menu',
            'main-menu'
        );

        $this->assertEquals($expectedResponse, $result);

        $request = $this->http->getLastRequest();
        $this->assertEquals('POST', $request['method']);
        $this->assertEquals('test-project-id', $request['data']['project_id']);
        $this->assertCount(1, $request['data']['translatable_items']);

        $item = $request['data']['translatable_items'][0];
        $this->assertEquals('content_block', $item['type']);
        $this->assertEquals('main-menu', $item['custom_id']);
        $this->assertEquals('<nav><a>Home</a><a>About</a></nav>', $item['content']);
        $this->assertEquals('Navigation', $item['category']);
        $this->assertEquals('Main Menu', $item['label']);
        // Phrases are now auto-extracted from HTML
        $this->assertEquals([
            ['phrase' => 'Home'],
            ['phrase' => 'About'],
        ], $item['phrases']);
    }

    public function testCreateContentBlockWithoutOptionalParams()
    {
        $expectedResponse = ['status' => true, 'id' => 'cb-123'];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        // Only content is required - category, label, customId are optional
        $result = $this->items->createContentBlock(
            '<footer>Contact Us</footer>'
        );

        $request = $this->http->getLastRequest();
        $item = $request['data']['translatable_items'][0];
        $this->assertArrayNotHasKey('category', $item);
        $this->assertArrayNotHasKey('label', $item);
        // customId should be auto-generated
        $this->assertArrayHasKey('custom_id', $item);
        $this->assertEquals(32, strlen($item['custom_id'])); // md5 hash length
        // Phrases auto-extracted
        $this->assertEquals([
            ['phrase' => 'Contact Us'],
        ], $item['phrases']);
    }

    public function testCreateContentBlockAutoGeneratesCustomId()
    {
        $expectedResponse = ['status' => true];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        // Same content with same category should generate same customId
        $this->items->createContentBlock('<p>Hello</p>', 'UI');
        $request1 = $this->http->getLastRequest();

        $this->items->createContentBlock('<p>Hello</p>', 'UI');
        $request2 = $this->http->getLastRequest();

        $this->assertEquals(
            $request1['data']['translatable_items'][0]['custom_id'],
            $request2['data']['translatable_items'][0]['custom_id']
        );

        // Different category should generate different customId
        $this->items->createContentBlock('<p>Hello</p>', 'Marketing');
        $request3 = $this->http->getLastRequest();

        $this->assertNotEquals(
            $request1['data']['translatable_items'][0]['custom_id'],
            $request3['data']['translatable_items'][0]['custom_id']
        );
    }

    public function testCreateContentBlockExtractsAttributePhrases()
    {
        $expectedResponse = ['status' => true];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        $result = $this->items->createContentBlock(
            '<form><input placeholder="Enter name"><button type="submit">Submit</button></form>',
            'Forms'
        );

        $request = $this->http->getLastRequest();
        $item = $request['data']['translatable_items'][0];
        // Should extract placeholder and button text
        $phrases = array_map(function($p) { return $p['phrase']; }, $item['phrases']);
        $this->assertContains('Enter name', $phrases);
        $this->assertContains('Submit', $phrases);
    }

    public function testCreatePhrasesWithCategory()
    {
        $expectedResponse = ['status' => true, 'created' => 3];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        $result = $this->items->createPhrasesWithCategory(
            ['Error 404', 'Error 500', 'Server Error'],
            'Errors'
        );

        $request = $this->http->getLastRequest();
        $this->assertEquals([
            ['type' => 'phrase', 'phrase' => 'Error 404', 'category' => 'Errors', 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'Error 500', 'category' => 'Errors', 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'Server Error', 'category' => 'Errors', 'translatable' => true],
        ], $request['data']['translatable_items']);
    }

    public function testCreatePhrasesWithCategoryNonTranslatable()
    {
        $expectedResponse = ['status' => true, 'created' => 2];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        $result = $this->items->createPhrasesWithCategory(
            ['API_KEY', 'SECRET_TOKEN'],
            'Config',
            false
        );

        $request = $this->http->getLastRequest();
        $this->assertEquals([
            ['type' => 'phrase', 'phrase' => 'API_KEY', 'category' => 'Config', 'translatable' => false],
            ['type' => 'phrase', 'phrase' => 'SECRET_TOKEN', 'category' => 'Config', 'translatable' => false],
        ], $request['data']['translatable_items']);
    }

    public function testCreateFromMap()
    {
        $expectedResponse = ['status' => true, 'created' => 5];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        $map = [
            'Navigation' => ['Home', 'About', 'Contact'],
            'Forms' => ['Submit', 'Cancel'],
        ];

        $result = $this->items->createFromMap($map);

        $request = $this->http->getLastRequest();
        $this->assertEquals([
            ['type' => 'phrase', 'phrase' => 'Home', 'category' => 'Navigation', 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'About', 'category' => 'Navigation', 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'Contact', 'category' => 'Navigation', 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'Submit', 'category' => 'Forms', 'translatable' => true],
            ['type' => 'phrase', 'phrase' => 'Cancel', 'category' => 'Forms', 'translatable' => true],
        ], $request['data']['translatable_items']);
    }

    public function testCreatePhrasesChunkingWithDefaultLimit()
    {
        $expectedResponse = ['status' => true, 'created' => 200];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        // Default batch limit is 200, so 450 phrases = 3 chunks: 200, 200, 50
        $phrases = [];
        for ($i = 0; $i < 450; $i++) {
            $phrases[] = 'Phrase ' . $i;
        }

        $result = $this->items->createPhrases($phrases);

        $requests = $this->http->getRequests();
        $this->assertCount(3, $requests);

        $this->assertCount(200, $requests[0]['data']['translatable_items']);
        $this->assertCount(200, $requests[1]['data']['translatable_items']);
        $this->assertCount(50, $requests[2]['data']['translatable_items']);

        foreach ($requests as $request) {
            $this->assertEquals('test-project-id', $request['data']['project_id']);
        }
    }

    public function testCreatePhrasesChunkingWithCustomLimit()
    {
        $expectedResponse = ['status' => true, 'created' => 50];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        // Set a custom batch limit (simulating API-provided value)
        $this->items->setBatchLimit(50);

        $phrases = [];
        for ($i = 0; $i < 120; $i++) {
            $phrases[] = 'Phrase ' . $i;
        }

        $result = $this->items->createPhrases($phrases);

        $requests = $this->http->getRequests();
        $this->assertCount(3, $requests);

        $this->assertCount(50, $requests[0]['data']['translatable_items']);
        $this->assertCount(50, $requests[1]['data']['translatable_items']);
        $this->assertCount(20, $requests[2]['data']['translatable_items']);
    }

    public function testCreatePhrasesSingleChunk()
    {
        $expectedResponse = ['status' => true, 'created' => 5];
        $this->http->setResponse('POST', 'translatable-items', $expectedResponse);

        $phrases = ['One', 'Two', 'Three', 'Four', 'Five'];
        $result = $this->items->createPhrases($phrases);

        // Should only make 1 request
        $requests = $this->http->getRequests();
        $this->assertCount(1, $requests);
        $this->assertCount(5, $requests[0]['data']['translatable_items']);
    }

    public function testBatchLimitDefaultValue()
    {
        $this->assertEquals(200, $this->items->getBatchLimit());
    }

    public function testSetBatchLimit()
    {
        $this->items->setBatchLimit(100);
        $this->assertEquals(100, $this->items->getBatchLimit());
    }
}
