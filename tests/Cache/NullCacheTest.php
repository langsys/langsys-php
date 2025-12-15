<?php

namespace Langsys\SDK\Tests\Cache;

use Langsys\SDK\Cache\NullCache;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the NullCache class.
 */
class NullCacheTest extends TestCase
{
    /**
     * @var NullCache
     */
    protected $cache;

    protected function setUp(): void
    {
        $this->cache = new NullCache();
    }

    public function testGetReturnsNull()
    {
        $this->assertNull($this->cache->get('any-key'));
        $this->assertNull($this->cache->get('another-key'));
    }

    public function testSetReturnsTrue()
    {
        $this->assertTrue($this->cache->set('key', 'value'));
        $this->assertTrue($this->cache->set('key', ['complex' => 'data']));
        $this->assertTrue($this->cache->set('key', 123));
    }

    public function testSetDoesNotStore()
    {
        $this->cache->set('test-key', 'test-value');
        $this->assertNull($this->cache->get('test-key'));
    }

    public function testHasReturnsFalse()
    {
        $this->assertFalse($this->cache->has('any-key'));

        // Even after setting, should return false
        $this->cache->set('any-key', 'value');
        $this->assertFalse($this->cache->has('any-key'));
    }

    public function testDeleteReturnsTrue()
    {
        $this->assertTrue($this->cache->delete('any-key'));
        $this->assertTrue($this->cache->delete('non-existent-key'));
    }

    public function testClearReturnsTrue()
    {
        $this->assertTrue($this->cache->clear());
    }
}
