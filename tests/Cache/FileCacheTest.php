<?php

namespace Langsys\SDK\Tests\Cache;

use Langsys\SDK\Cache\FileCache;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the FileCache class.
 */
class FileCacheTest extends TestCase
{
    /**
     * @var string
     */
    protected $cachePath;

    /**
     * @var FileCache
     */
    protected $cache;

    protected function setUp(): void
    {
        $this->cachePath = sys_get_temp_dir() . '/langsys-test-cache-' . uniqid();
        $this->cache = new FileCache($this->cachePath, 3600);
    }

    protected function tearDown(): void
    {
        // Clean up cache directory
        $this->recursiveDelete($this->cachePath);
    }

    protected function recursiveDelete($path)
    {
        if (!is_dir($path)) {
            return;
        }

        $files = scandir($path);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            $filePath = $path . '/' . $file;
            if (is_dir($filePath)) {
                $this->recursiveDelete($filePath);
            } else {
                unlink($filePath);
            }
        }
        rmdir($path);
    }

    public function testSetAndGet()
    {
        $this->assertTrue($this->cache->set('test-key', 'test-value'));
        $this->assertEquals('test-value', $this->cache->get('test-key'));
    }

    public function testSetAndGetArray()
    {
        $data = [
            'foo' => 'bar',
            'nested' => ['a' => 1, 'b' => 2],
        ];

        $this->assertTrue($this->cache->set('array-key', $data));
        $this->assertEquals($data, $this->cache->get('array-key'));
    }

    public function testGetNonExistent()
    {
        $this->assertNull($this->cache->get('non-existent-key'));
    }

    public function testHas()
    {
        $this->assertFalse($this->cache->has('my-key'));

        $this->cache->set('my-key', 'my-value');
        $this->assertTrue($this->cache->has('my-key'));
    }

    public function testDelete()
    {
        $this->cache->set('delete-me', 'value');
        $this->assertTrue($this->cache->has('delete-me'));

        $this->assertTrue($this->cache->delete('delete-me'));
        $this->assertFalse($this->cache->has('delete-me'));
        $this->assertNull($this->cache->get('delete-me'));
    }

    public function testDeleteNonExistent()
    {
        $this->assertTrue($this->cache->delete('never-existed'));
    }

    public function testClear()
    {
        $this->cache->set('key1', 'value1');
        $this->cache->set('key2', 'value2');
        $this->cache->set('key3', 'value3');

        $this->assertTrue($this->cache->has('key1'));
        $this->assertTrue($this->cache->has('key2'));
        $this->assertTrue($this->cache->has('key3'));

        $this->assertTrue($this->cache->clear());

        $this->assertFalse($this->cache->has('key1'));
        $this->assertFalse($this->cache->has('key2'));
        $this->assertFalse($this->cache->has('key3'));
    }

    public function testExpiration()
    {
        // Create cache with 1 second TTL
        $cache = new FileCache($this->cachePath, 1);
        $cache->set('expiring-key', 'expiring-value');

        $this->assertEquals('expiring-value', $cache->get('expiring-key'));

        // Wait for expiration
        sleep(2);

        $this->assertNull($cache->get('expiring-key'));
        $this->assertFalse($cache->has('expiring-key'));
    }

    public function testDirectoryCreation()
    {
        $newPath = sys_get_temp_dir() . '/langsys-new-cache-' . uniqid() . '/nested/dir';
        $cache = new FileCache($newPath, 3600);

        $this->assertTrue($cache->set('test', 'value'));
        $this->assertEquals('value', $cache->get('test'));
        $this->assertTrue(is_dir($newPath));

        // Clean up
        $this->recursiveDelete(dirname(dirname($newPath)));
    }

    public function testInvalidCacheFile()
    {
        // Set a valid cache entry
        $this->cache->set('valid-key', 'valid-value');

        // Manually corrupt the cache file
        $cacheDir = $this->cachePath;
        $files = glob($cacheDir . '/*.cache');
        if (!empty($files)) {
            file_put_contents($files[0], 'invalid json content');
        }

        // Should return null for corrupted file
        $this->assertNull($this->cache->get('valid-key'));
    }

    public function testSpecialCharactersInKey()
    {
        $key = 'key:with/special.chars!@#$%';
        $this->cache->set($key, 'special-value');
        $this->assertEquals('special-value', $this->cache->get($key));
    }
}
