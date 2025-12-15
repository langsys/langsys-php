<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Config;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the Config class.
 */
class ConfigTest extends TestCase
{
    /**
     * @var array Environment variables to restore after tests
     */
    protected $originalEnv = [];

    /**
     * @var array Original $_SERVER values
     */
    protected $originalServer = [];

    protected function setUp(): void
    {
        $this->originalEnv = [
            'LANGSYS_API_KEY' => getenv('LANGSYS_API_KEY'),
            'LANGSYS_PROJECT_ID' => getenv('LANGSYS_PROJECT_ID'),
            'LANGSYS_API_URL' => getenv('LANGSYS_API_URL'),
            'LANGSYS_CACHE_DRIVER' => getenv('LANGSYS_CACHE_DRIVER'),
            'LANGSYS_CACHE_PATH' => getenv('LANGSYS_CACHE_PATH'),
            'LANGSYS_CACHE_TTL' => getenv('LANGSYS_CACHE_TTL'),
            'LANGSYS_BASE_URL' => getenv('LANGSYS_BASE_URL'),
        ];

        // Save $_SERVER values
        $serverKeys = ['HTTP_HOST', 'HTTPS', 'HTTP_X_FORWARDED_PROTO', 'SERVER_PORT'];
        foreach ($serverKeys as $key) {
            $this->originalServer[$key] = isset($_SERVER[$key]) ? $_SERVER[$key] : null;
        }

        // Clear all env vars
        foreach (array_keys($this->originalEnv) as $key) {
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        // Restore original env vars
        foreach ($this->originalEnv as $key => $value) {
            if ($value === false) {
                putenv($key);
            } else {
                putenv($key . '=' . $value);
            }
        }

        // Restore original $_SERVER values
        foreach ($this->originalServer as $key => $value) {
            if ($value === null) {
                unset($_SERVER[$key]);
            } else {
                $_SERVER[$key] = $value;
            }
        }
    }

    public function testDefaultValues()
    {
        $config = new Config();

        $this->assertEquals('https://api.langsys.dev/api', $config->getApiUrl());
        $this->assertEquals('file', $config->getCacheDriver());
        $this->assertEquals(3600, $config->getCacheTtl());
        $this->assertStringContainsString('langsys-cache', $config->getCachePath());
    }

    public function testLoadFromOptions()
    {
        $config = new Config([
            'api_key' => 'my-api-key',
            'project_id' => 'my-project-id',
            'api_url' => 'https://custom.api.com/api',
            'cache_driver' => 'redis',
            'cache_path' => '/custom/cache/path',
            'cache_ttl' => 7200,
        ]);

        $this->assertEquals('my-api-key', $config->getApiKey());
        $this->assertEquals('my-project-id', $config->getProjectId());
        $this->assertEquals('https://custom.api.com/api', $config->getApiUrl());
        $this->assertEquals('redis', $config->getCacheDriver());
        $this->assertEquals('/custom/cache/path', $config->getCachePath());
        $this->assertEquals(7200, $config->getCacheTtl());
    }

    public function testLoadFromEnvironment()
    {
        putenv('LANGSYS_API_KEY=env-api-key');
        putenv('LANGSYS_PROJECT_ID=env-project-id');
        putenv('LANGSYS_API_URL=https://env.api.com/api');
        putenv('LANGSYS_CACHE_DRIVER=none');
        putenv('LANGSYS_CACHE_PATH=/env/cache');
        putenv('LANGSYS_CACHE_TTL=1800');

        $config = new Config();

        $this->assertEquals('env-api-key', $config->getApiKey());
        $this->assertEquals('env-project-id', $config->getProjectId());
        $this->assertEquals('https://env.api.com/api', $config->getApiUrl());
        $this->assertEquals('none', $config->getCacheDriver());
        $this->assertEquals('/env/cache', $config->getCachePath());
        $this->assertEquals(1800, $config->getCacheTtl());
    }

    public function testOptionsOverrideEnvironment()
    {
        putenv('LANGSYS_API_KEY=env-api-key');
        putenv('LANGSYS_PROJECT_ID=env-project-id');

        $config = new Config([
            'api_key' => 'option-api-key',
            'project_id' => 'option-project-id',
        ]);

        $this->assertEquals('option-api-key', $config->getApiKey());
        $this->assertEquals('option-project-id', $config->getProjectId());
    }

    public function testHasApiKey()
    {
        $configWithoutKey = new Config();
        $this->assertFalse($configWithoutKey->hasApiKey());

        $configWithKey = new Config(['api_key' => 'test-key']);
        $this->assertTrue($configWithKey->hasApiKey());
    }

    public function testHasProjectId()
    {
        $configWithoutId = new Config();
        $this->assertFalse($configWithoutId->hasProjectId());

        $configWithId = new Config(['project_id' => 'test-id']);
        $this->assertTrue($configWithId->hasProjectId());
    }

    public function testGetApiUrlTrimsTrailingSlash()
    {
        $config = new Config(['api_url' => 'https://api.example.com/api/']);
        $this->assertEquals('https://api.example.com/api', $config->getApiUrl());
    }

    public function testCacheTtlIsInteger()
    {
        $config = new Config(['cache_ttl' => '1234']);
        $this->assertSame(1234, $config->getCacheTtl());
        $this->assertIsInt($config->getCacheTtl());
    }

    // =========================================================================
    // Base URL Tests
    // =========================================================================

    public function testGetBaseUrlFromOption()
    {
        $config = new Config(['base_url' => 'https://example.com']);
        $this->assertEquals('https://example.com', $config->getBaseUrl());
    }

    public function testGetBaseUrlFromEnvironment()
    {
        putenv('LANGSYS_BASE_URL=https://env-example.com');
        $config = new Config();
        $this->assertEquals('https://env-example.com', $config->getBaseUrl());
    }

    public function testGetBaseUrlStripsTrailingSlash()
    {
        $config = new Config(['base_url' => 'https://example.com/']);
        $this->assertEquals('https://example.com', $config->getBaseUrl());
    }

    public function testGetBaseUrlFromServerHttp()
    {
        // Clear any env setting
        putenv('LANGSYS_BASE_URL');

        // Simulate HTTP request
        $_SERVER['HTTP_HOST'] = 'example.com';
        unset($_SERVER['HTTPS']);
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        unset($_SERVER['SERVER_PORT']);

        $config = new Config();
        $this->assertEquals('http://example.com', $config->getBaseUrl());
    }

    public function testGetBaseUrlFromServerHttps()
    {
        putenv('LANGSYS_BASE_URL');

        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = 'on';

        $config = new Config();
        $this->assertEquals('https://example.com', $config->getBaseUrl());
    }

    public function testGetBaseUrlFromServerHttpsOff()
    {
        putenv('LANGSYS_BASE_URL');

        $_SERVER['HTTP_HOST'] = 'example.com';
        $_SERVER['HTTPS'] = 'off';

        $config = new Config();
        $this->assertEquals('http://example.com', $config->getBaseUrl());
    }

    public function testGetBaseUrlFromServerForwardedProto()
    {
        putenv('LANGSYS_BASE_URL');

        $_SERVER['HTTP_HOST'] = 'example.com';
        unset($_SERVER['HTTPS']);
        $_SERVER['HTTP_X_FORWARDED_PROTO'] = 'https';

        $config = new Config();
        $this->assertEquals('https://example.com', $config->getBaseUrl());
    }

    public function testGetBaseUrlFromServerPort443()
    {
        putenv('LANGSYS_BASE_URL');

        $_SERVER['HTTP_HOST'] = 'example.com';
        unset($_SERVER['HTTPS']);
        unset($_SERVER['HTTP_X_FORWARDED_PROTO']);
        $_SERVER['SERVER_PORT'] = 443;

        $config = new Config();
        $this->assertEquals('https://example.com', $config->getBaseUrl());
    }

    public function testGetBaseUrlReturnsNullWithoutServer()
    {
        putenv('LANGSYS_BASE_URL');
        unset($_SERVER['HTTP_HOST']);

        $config = new Config();
        $this->assertNull($config->getBaseUrl());
    }

    public function testGetBaseUrlOptionOverridesServer()
    {
        $_SERVER['HTTP_HOST'] = 'server-host.com';
        $_SERVER['HTTPS'] = 'on';

        $config = new Config(['base_url' => 'https://option-host.com']);
        $this->assertEquals('https://option-host.com', $config->getBaseUrl());
    }
}
