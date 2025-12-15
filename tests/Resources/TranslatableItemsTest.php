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
        $this->assertEquals('phrase', $request['data']['type']);
        $this->assertEquals($phrases, $request['data']['phrases']);
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
            ['phrase' => 'Hello'],
            ['phrase' => 'Goodbye'],
        ], $request['data']['phrases']);
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
            ['phrase' => 'Simple phrase'],
            ['phrase' => 'With category', 'category' => 'UI'],
            ['phrase' => 'Not translatable', 'translatable' => false],
        ], $request['data']['phrases']);
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
        $this->assertEquals('content_block', $request['data']['type']);
        $this->assertEquals('main-menu', $request['data']['custom_id']);
        $this->assertEquals('<nav><a>Home</a><a>About</a></nav>', $request['data']['content']);
        $this->assertEquals('Navigation', $request['data']['category']);
        $this->assertEquals('Main Menu', $request['data']['label']);
        // Phrases are now auto-extracted from HTML
        $this->assertEquals([
            ['phrase' => 'Home'],
            ['phrase' => 'About'],
        ], $request['data']['phrases']);
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
        $this->assertArrayNotHasKey('category', $request['data']);
        $this->assertArrayNotHasKey('label', $request['data']);
        // customId should be auto-generated
        $this->assertArrayHasKey('custom_id', $request['data']);
        $this->assertEquals(32, strlen($request['data']['custom_id'])); // md5 hash length
        // Phrases auto-extracted
        $this->assertEquals([
            ['phrase' => 'Contact Us'],
        ], $request['data']['phrases']);
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

        $this->assertEquals($request1['data']['custom_id'], $request2['data']['custom_id']);

        // Different category should generate different customId
        $this->items->createContentBlock('<p>Hello</p>', 'Marketing');
        $request3 = $this->http->getLastRequest();

        $this->assertNotEquals($request1['data']['custom_id'], $request3['data']['custom_id']);
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
        // Should extract placeholder and button text
        $phrases = array_map(function($p) { return $p['phrase']; }, $request['data']['phrases']);
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
            ['phrase' => 'Error 404', 'category' => 'Errors', 'translatable' => true],
            ['phrase' => 'Error 500', 'category' => 'Errors', 'translatable' => true],
            ['phrase' => 'Server Error', 'category' => 'Errors', 'translatable' => true],
        ], $request['data']['phrases']);
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
            ['phrase' => 'API_KEY', 'category' => 'Config', 'translatable' => false],
            ['phrase' => 'SECRET_TOKEN', 'category' => 'Config', 'translatable' => false],
        ], $request['data']['phrases']);
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
            ['phrase' => 'Home', 'category' => 'Navigation'],
            ['phrase' => 'About', 'category' => 'Navigation'],
            ['phrase' => 'Contact', 'category' => 'Navigation'],
            ['phrase' => 'Submit', 'category' => 'Forms'],
            ['phrase' => 'Cancel', 'category' => 'Forms'],
        ], $request['data']['phrases']);
    }
}
