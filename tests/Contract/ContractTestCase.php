<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Cache\CacheInterface;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use PHPUnit\Framework\TestCase;

/**
 * Tests that run against the shared contract fixture (CONF-2): the Langsys API
 * double in tests/contract-fixture/, vendored byte for byte from
 * langsys-js-typescript at tree 0bf98665a99d9e6b9956d84c785fbe58cf633575.
 *
 * The double can refuse a request and holds state, so these tests assert on
 * what the server accepted - the state read back - never on what the SDK sent,
 * and never on error text. One double serves a whole test class; every test
 * starts from an empty state.
 */
abstract class ContractTestCase extends TestCase
{
    const PROJECT = 'p1';

    /** @var resource|null */
    private static $process;

    /** @var array */
    private static $pipes = [];

    /** @var string|null */
    protected static $baseUrl;

    /** @var string|null */
    protected static $fixtureUrl;

    /** @var string|null */
    private static $unavailable;

    public static function setUpBeforeClass(): void
    {
        self::$unavailable = null;
        $server = dirname(__DIR__) . '/contract-fixture/server.mjs';
        $node = trim((string) shell_exec('command -v node 2>/dev/null'));

        if ($node === '') {
            self::$unavailable = 'Node is not installed; the contract fixture needs Node 18 or later';

            return;
        }

        self::$process = proc_open([$node, $server], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], self::$pipes);

        if (!is_resource(self::$process)) {
            self::$unavailable = 'The contract fixture could not be started';

            return;
        }

        $read = [self::$pipes[1]];
        $none = null;
        $line = stream_select($read, $none, $none, 10) ? fgets(self::$pipes[1]) : false;
        $ready = $line === false ? null : json_decode($line, true);

        if (!is_array($ready) || empty($ready['ready'])) {
            self::$unavailable = 'The contract fixture printed no ready line: ' . var_export($line, true);

            return;
        }

        self::$baseUrl = $ready['base_url'];
        self::$fixtureUrl = $ready['fixture_url'];
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
        }

        self::$process = null;
    }

    protected function setUp(): void
    {
        if (self::$unavailable !== null) {
            $this->markTestSkipped(self::$unavailable);
        }

        $this->fixture('POST', '/reset');
    }

    /**
     * Replace the double's state with a seed document (seed.schema.json).
     */
    protected function seed(array $seed)
    {
        $this->fixture('POST', '/seed', $seed);
    }

    /**
     * One project, keys by type, optional catalog, config and faults.
     *
     * @param array $keys key => [type, extra key fields]
     */
    protected function seedProject(array $keys, array $project = [], array $config = [], array $faults = [])
    {
        $seedKeys = [];

        foreach ($keys as $key => $fields) {
            $seedKeys[] = array_merge(['key' => $key, 'project' => self::PROJECT], $fields);
        }

        $seed = [
            'projects' => [array_merge(['id' => self::PROJECT, 'base_locale' => 'en-us', 'target_locales' => ['es-es']], $project)],
            'keys' => $seedKeys,
        ];

        if ($config !== []) {
            $seed['config'] = $config;
        }

        if ($faults !== []) {
            $seed['faults'] = $faults;
        }

        $this->seed($seed);
    }

    /**
     * A real Client against the double. Every call goes over HTTP.
     */
    protected function client($key, CacheInterface $cache = null, array $options = [])
    {
        return new Client($key, self::PROJECT, array_merge([
            'api_url' => self::$baseUrl,
            'cache' => $cache !== null ? $cache : new NullCache(),
        ], $options));
    }

    /**
     * Accepted state: [category|null, phrase] pairs.
     */
    protected function registeredPhrases()
    {
        $state = $this->fixture('GET', '/state');
        $project = isset($state['projects'][self::PROJECT]) ? $state['projects'][self::PROJECT] : ['phrases' => []];

        return array_map(function ($phrase) {
            return [$phrase['category'], $phrase['phrase']];
        }, $project['phrases']);
    }

    /**
     * Accepted state: [category|null, [phrase, ...]] per block.
     */
    protected function registeredBlocks()
    {
        $state = $this->fixture('GET', '/state');
        $project = isset($state['projects'][self::PROJECT]) ? $state['projects'][self::PROJECT] : ['blocks' => []];

        return array_map(function ($block) {
            return [$block['category'], array_column($block['phrases'], 'phrase')];
        }, $project['blocks']);
    }

    /**
     * Hints the server accepted and stored.
     */
    protected function storedHints()
    {
        $state = $this->fixture('GET', '/state');

        return isset($state['hints']) ? $state['hints'] : [];
    }

    private function fixture($method, $path, array $body = null)
    {
        $context = stream_context_create(['http' => [
            'method' => $method,
            'header' => "Content-Type: application/json\r\n",
            'content' => $body === null ? '' : json_encode($body),
            'ignore_errors' => true,
            'timeout' => 10,
        ]]);

        $response = file_get_contents(self::$fixtureUrl . $path, false, $context);
        $status = isset($http_response_header[0]) && preg_match('#\s(\d{3})\s#', $http_response_header[0], $m) ? (int) $m[1] : 0;

        if ($status < 200 || $status >= 300) {
            $this->fail("fixture $method $path answered $status: $response");
        }

        return json_decode((string) $response, true);
    }
}
