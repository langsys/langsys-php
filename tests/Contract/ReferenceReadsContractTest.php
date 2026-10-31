<?php

namespace Langsys\SDK\Tests\Contract;

use Langsys\SDK\Cache\FileCache;
use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Tests\Support\SpyLogger;

/**
 * REF-1 against the contract fixture: the Client serves the API's reference
 * lists, one call per list, each in a display locale that defaults to the
 * current one.
 *
 * The API answers each request, and a case's crafted lists answer the ones it
 * accepts, each named in the display locale as the backend names its own, so
 * each display locale answers a distinct list and every assertion is on which
 * list came back. A call that must not send is shown by the case's lists
 * changing, so that a read would answer a different list, and the call still
 * answering the one it held. What no answer can show - a refused display
 * locale, an unconfigured client - is proven at the transport seam in
 * tests/ReferenceDataTest.php.
 */
class ReferenceReadsContractTest extends ContractTestCase
{
    protected static $seed = 'reference-reads';

    const REFERENCE = [
        'locales' => ['fr-fr', 'es-es', 'es-cr', 'en-us', 'de-de'],
        'countries' => [['code' => 'ES'], ['code' => 'CR'], ['code' => 'US']],
        'dial_codes' => [['country_code' => 'ES', 'dial_code' => '34'], ['country_code' => 'CR', 'dial_code' => '506']],
        'currencies' => [['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€'], ['code' => 'CRC', 'name' => 'Costa Rican Colón', 'symbol' => '₡']],
    ];

    /** A second set of tables, so a read made after they change answers a different list. */
    const CHANGED = [
        'locales' => ['it-it', 'es-es', 'en-us', 'fr-fr', 'de-de'],
        'countries' => [['code' => 'IT']],
        'dial_codes' => [['country_code' => 'IT', 'dial_code' => '39']],
        'currencies' => [['code' => 'CHF', 'name' => 'Swiss Franc', 'symbol' => 'CHF']],
    ];

    /** @var string[] */
    private $dirs = [];

    protected function tearDown(): void
    {
        foreach ($this->dirs as $dir) {
            foreach (glob($dir . '/*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    /**
     * Each list: the call that reads it, and its route.
     *
     * @return array<string, array{0: callable, 1: string}>
     */
    private static function lists()
    {
        return [
            'getLocalesFormat()' => [function (Client $client, $locale = null) {
                return $client->getLocalesFormat('', $locale);
            }, 'locales'],
            'getLocalesFormat(flat)' => [function (Client $client, $locale = null) {
                return $client->getLocalesFormat('flat', $locale);
            }, 'locales/flat'],
            'getLocalesFormat(data)' => [function (Client $client, $locale = null) {
                return $client->getLocalesFormat('data', $locale);
            }, 'locales/data'],
            'getCurrencies' => [function (Client $client, $locale = null) {
                return $client->getCurrencies($locale);
            }, 'currencies'],
            'getCountries' => [function (Client $client, $locale = null) {
                return $client->getCountries($locale);
            }, 'countries'],
            'getDialCodes' => [function (Client $client, $locale = null) {
                return $client->getDialCodes($locale);
            }, 'countries/dial-codes'],
        ];
    }

    /**
     * A client on key k1 whose clock the test moves, so a failure window opens
     * and ends where the test says, whatever the calls around it take.
     */
    private function clocked(World $world, SpyLogger $logger)
    {
        $client = new class ($world->key('k1'), $world->project(), ['api_url' => self::$baseUrl, 'cache' => new NullCache(), 'logger' => $logger, 'error_log' => false]) extends Client {
            public $now = 1000.0;

            protected function currentTime()
            {
                return $this->now;
            }
        };
        $client->setLocale('es-es');

        return $client;
    }

    private function configured(World $world, $key = 'k1', $cache = null, $logger = null)
    {
        $client = $this->client($world->key($key), $world->project(), $cache, $logger !== null ? ['logger' => $logger, 'error_log' => false] : []);
        $client->setLocale('es-es');

        return $client;
    }

    /**
     * The list the fixture answers for `locale` right now, read from it
     * directly, with every code in WIRE-3's form.
     */
    private function fixtureList(World $world, $route, $locale, $key = 'k1')
    {
        $byLocale = strpos($route, 'locales') === 0;
        $path = $byLocale ? "/$route?" . http_build_query(['locales' => [$locale], 'append_target_locales' => 0]) : "/$route/$locale";
        $answer = $this->api($path, ['key' => $world->key($key)]);
        $this->assertSame(200, $answer['status'], "$path answered {$answer['status']}");
        if (!$byLocale) {
            return $answer['body']['data'];
        }

        foreach ($answer['body']['data'] as $entryLocale => $entry) {
            if (strtolower(str_replace('_', '-', $entryLocale)) === $locale) {
                return self::wire3Codes($entry);
            }
        }

        return [];
    }

    private static function wire3Codes(array $list)
    {
        foreach ($list as $i => $row) {
            if (isset($row['code'])) {
                $list[$i]['code'] = strtolower(str_replace('_', '-', $row['code']));
            } elseif (is_array($row)) {
                $list[$i] = self::wire3Codes($row);
            }
        }

        return $list;
    }

    public function testEachListAnswersItsDisplayLocalesListDefaultingToTheCurrentOne(): void
    {
        $world = $this->world('lists', ['reference' => self::REFERENCE]);

        foreach (self::lists() as $name => list($call, $route)) {
            $es = $call($this->configured($world));
            $this->assertSame($this->fixtureList($world, $route, 'es-es'), $es, "$name with no locale answers the current locale's list");
            $this->assertNotSame([], $es, "$name: control, the list is not empty");
            $this->assertSame($this->fixtureList($world, $route, 'en-us'), $call($this->configured($world), 'en_US'), "$name with a locale answers that locale's list, canonical");
            $this->assertNotSame($es, $this->fixtureList($world, $route, 'en-us'), "$name: each display locale's list is its own");
        }
    }

    /**
     * A second call for the same display locale sends no request, a call for
     * another does, and a third for the first sends none: the case's lists
     * change after the first read, and only the call for a locale not held
     * answers the changed one.
     */
    public function testAListIsHeldPerDisplayLocale(): void
    {
        $world = $this->world('lists', ['reference' => self::REFERENCE]);
        $clients = [];
        $first = [];

        foreach (self::lists() as $name => list($call, $route)) {
            $clients[$name] = $this->configured($world);
            $first[$name] = $call($clients[$name]);
        }

        $this->update($world, ['reference' => self::CHANGED]);

        foreach (self::lists() as $name => list($call, $route)) {
            $this->assertSame($first[$name], $call($clients[$name]), "$name: a second call for es-es answers the held list");
            $this->assertSame($this->fixtureList($world, $route, 'fr-fr'), $call($clients[$name], 'fr-fr'), "$name: a call for another locale reads it");
            $this->assertNotSame($first[$name], $this->fixtureList($world, $route, 'es-es'), "$name: control, a read for es-es now answers another list");
            $this->assertSame($first[$name], $call($clients[$name]), "$name: a third call for es-es sends none");
        }
    }

    /**
     * On the server profile the hold outlives the request: a Client built for
     * the next request, sharing the cache, answers the held list with no
     * request.
     */
    public function testANewRequestAnswersTheHeldListWithNoRequest(): void
    {
        $world = $this->world('lists', ['reference' => self::REFERENCE]);
        $dir = sys_get_temp_dir() . '/langsys-reference-contract-' . bin2hex(random_bytes(4));
        $this->dirs[] = $dir;
        $first = [];

        foreach (self::lists() as $name => list($call)) {
            $first[$name] = $call($this->configured($world, 'k1', new FileCache($dir)));
        }

        $this->update($world, ['reference' => self::CHANGED]);

        foreach (self::lists() as $name => list($call)) {
            $this->assertSame($first[$name], $call($this->configured($world, 'k1', new FileCache($dir))), "$name: the next request answers the held list");
        }
    }

    /**
     * A locale list keyed in the organisation's own code format (es_ES, with
     * codes es_CR) is answered for es-es with codes in WIRE-3's form.
     */
    public function testALocaleListInAnotherCodeFormatIsAnsweredInWireForm(): void
    {
        $world = $this->world('posix', ['reference' => self::REFERENCE]);

        $raw = $this->api('/locales/flat?' . http_build_query(['locales' => ['es-es'], 'append_target_locales' => 0]), ['key' => $world->key('k1')]);
        $this->assertArrayHasKey('es_ES', $raw['body']['data'], 'control: the organisation keys its locales as es_ES');

        $flat = $this->configured($world)->getLocalesFormat('flat');
        $this->assertNotSame([], $flat);
        $this->assertContains('es-cr', array_column($flat, 'code'));
        foreach (array_column($flat, 'code') as $code) {
            $this->assertMatchesRegularExpression('/^[a-z0-9]+(-[a-z0-9]+)*$/', $code);
        }
    }

    /**
     * A display locale the API holds no list for answers an empty list.
     */
    public function testALocaleTheApiDoesNotHoldAnswersAnEmptyList(): void
    {
        $world = $this->world('lists', ['reference' => self::REFERENCE]);

        foreach (['getLocalesFormat()', 'getLocalesFormat(flat)', 'getLocalesFormat(data)'] as $name) {
            list($call) = self::lists()[$name];
            $this->assertSame([], $call($this->configured($world), 'pt-br'), $name);
        }
    }

    /**
     * With the fixture failing, each call answers the empty value and logs
     * the cause; a call inside the window sends nothing, shown by the fault
     * ending and the call still answering empty; a call under other
     * credentials inside it sends and answers; and after the window the call
     * answers the list.
     */
    public function testAFailedReadIsEmptyLoggedAndRememberedForItsWindow(): void
    {
        $faults = [];
        foreach (self::lists() as list(, $route)) {
            $faults[] = ['method' => 'GET', 'path' => strpos($route, 'locales') === 0 ? "/$route" : "/$route/es-es", 'status' => 503];
        }
        $world = $this->world('lists', ['reference' => self::REFERENCE, 'faults' => $faults]);
        $logger = new SpyLogger();
        $client = $this->clocked($world, $logger);

        foreach (self::lists() as $name => list($call)) {
            $this->assertSame([], $call($client), "$name: a failed read answers empty");
        }
        // The transport logs the failed request too; the list's own error names its route and cause.
        $failed = array_filter($logger->entries, function ($entry) {
            return $entry['level'] === 'error' && isset($entry['context']['route']) && !empty($entry['context']['error']);
        });
        $this->assertCount(6, array_unique(array_map(function ($entry) {
            return $entry['context']['route'];
        }, $failed)), 'each failure logs its cause');
        $this->assertCount(6, $failed, 'once per list');

        foreach (self::lists() as $name => list($call, $route)) {
            $this->assertSame([], $call($client), "$name: inside the window the call sends nothing");
            $this->assertSame($this->fixtureList($world, $route, 'es-es', 'k2'), $call($this->configured($world, 'k2')), "$name: under other credentials the call sends and answers");
        }

        $client->now += 3;
        foreach (self::lists() as $name => list($call, $route)) {
            $this->assertSame($this->fixtureList($world, $route, 'es-es'), $call($client), "$name: after the window the call answers the list");
        }
    }
}
