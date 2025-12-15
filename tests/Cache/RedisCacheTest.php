<?php

namespace Langsys\SDK\Tests\Cache;

use Langsys\SDK\Cache\RedisCache;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the RedisCache class.
 * These tests are skipped if the Redis extension is not available.
 */
class RedisCacheTest extends TestCase
{
    /**
     * @var RedisCache
     */
    protected $cache;

    /**
     * @var string
     */
    protected $prefix;

    protected function setUp(): void
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available.');
            return;
        }

        // Try to connect to Redis
        try {
            $redis = new \Redis();
            $connected = @$redis->connect('127.0.0.1', 6379, 1);
            if (!$connected) {
                $this->markTestSkipped('Cannot connect to Redis server.');
                return;
            }
            $redis->close();
        } catch (\Exception $e) {
            $this->markTestSkipped('Cannot connect to Redis server: ' . $e->getMessage());
            return;
        }

        $this->prefix = 'langsys_test_' . uniqid() . '::';
        $this->cache = new RedisCache([
            'host' => '127.0.0.1',
            'port' => 6379,
            'prefix' => $this->prefix,
        ]);
    }

    protected function tearDown(): void
    {
        if ($this->cache !== null) {
            $this->cache->clear();
        }
    }

    public function testSetAndGet()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available.');
            return;
        }

        $this->assertTrue($this->cache->set('test-key', 'test-value'));
        $this->assertEquals('test-value', $this->cache->get('test-key'));
    }

    public function testSetAndGetArray()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available.');
            return;
        }

        $data = [
            'foo' => 'bar',
            'nested' => ['a' => 1, 'b' => 2],
        ];

        $this->assertTrue($this->cache->set('array-key', $data));
        $this->assertEquals($data, $this->cache->get('array-key'));
    }

    public function testGetNonExistent()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available.');
            return;
        }

        $this->assertNull($this->cache->get('non-existent-key'));
    }

    public function testHas()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available.');
            return;
        }

        $this->assertFalse($this->cache->has('my-key'));

        $this->cache->set('my-key', 'my-value');
        $this->assertTrue($this->cache->has('my-key'));
    }

    public function testDelete()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available.');
            return;
        }

        $this->cache->set('delete-me', 'value');
        $this->assertTrue($this->cache->has('delete-me'));

        $this->assertTrue($this->cache->delete('delete-me'));
        $this->assertFalse($this->cache->has('delete-me'));
    }

    public function testClear()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available.');
            return;
        }

        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');

        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));

        $this->assertTrue($this->cache->clear());

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
    }

    public function testPrefixIsolation()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available.');
            return;
        }

        $otherPrefix = 'other_prefix_' . uniqid() . '::';
        $otherCache = new RedisCache([
            'host' => '127.0.0.1',
            'port' => 6379,
            'prefix' => $otherPrefix,
        ]);

        $this->cache->set('shared-key', 'value-from-first');
        $otherCache->set('shared-key', 'value-from-second');

        $this->assertEquals('value-from-first', $this->cache->get('shared-key'));
        $this->assertEquals('value-from-second', $otherCache->get('shared-key'));

        // Clean up
        $otherCache->clear();
    }

    public function testConstructWithRedisInstance()
    {
        if (!extension_loaded('redis')) {
            $this->markTestSkipped('Redis extension is not available.');
            return;
        }

        $redis = new \Redis();
        $connected = @$redis->connect('127.0.0.1', 6379, 1);
        if (!$connected) {
            $this->markTestSkipped('Cannot connect to Redis server.');
            return;
        }

        $prefix = 'instance_test_' . uniqid() . '::';
        $cache = new RedisCache($redis, $prefix, 3600);

        $this->assertTrue($cache->set('test', 'value'));
        $this->assertEquals('value', $cache->get('test'));

        $cache->clear();
    }
}
