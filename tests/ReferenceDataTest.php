<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Exception\ApiException;
use Langsys\SDK\Exception\ConfigurationException;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use Langsys\SDK\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;

/**
 * The reference lists an app shows in its own forms - currencies, countries,
 * dial codes and locales, each named in a display locale (REF-1): the current
 * locale by default, held per display locale beyond the request, a failure
 * remembered for CACHE-2's window, and an empty list, never an exception, when
 * the API cannot answer (WIRE-4).
 */
class ReferenceDataTest extends TestCase
{
    /** @var MockHttpClient */
    private $http;

    /** @var SpyLogger */
    private $logger;

    const CURRENCIES = [['code' => 'USD', 'name' => 'dólar estadounidense', 'symbol' => '$', 'symbol_native' => '$', 'decimal_digits' => 2, 'rounding' => 0]];
    const COUNTRIES = [['label' => 'Costa Rica', 'code' => 'CR', 'eu' => false]];
    const DIAL_CODES = [['country_code' => 'CR', 'dial_code' => '+506', 'name' => 'Costa Rica (+506)']];

    private function client(array $options = [], $apiKey = 'test-api-key', $projectId = 'project-id', $clocked = false)
    {
        $this->http = new class extends MockHttpClient {
            /** @var array<string, \Throwable> */
            public $failures = [];

            public function get($endpoint, array $params = [])
            {
                $response = parent::get($endpoint, $params);
                if (isset($this->failures[$endpoint])) {
                    throw $this->failures[$endpoint];
                }

                return $response;
            }
        };
        $this->http->setResponse('GET', 'authorize-project/project-id', ['data' => ['key_type' => 'read', 'write_enabled' => false, 'base_locale' => 'en-us']]);
        $this->http->setResponse('GET', 'currencies/es-es', ['status' => true, 'data' => self::CURRENCIES]);
        $this->http->setResponse('GET', 'currencies/fr-fr', ['status' => true, 'data' => self::CURRENCIES]);
        $this->http->setResponse('GET', 'countries/es-es', ['status' => true, 'data' => self::COUNTRIES]);
        $this->http->setResponse('GET', 'countries/dial-codes/es-es', ['status' => true, 'data' => self::DIAL_CODES]);
        $this->http->setResponse('GET', 'locales', ['status' => true, 'data' => ['es-ES' => ['Español' => [['code' => 'es-cr', 'name' => 'Español (Costa Rica)']]]]]);
        $this->http->setResponse('GET', 'locales/flat', ['status' => true, 'data' => ['es-ES' => [['code' => 'es-cr', 'name' => 'Español (Costa Rica)']]]]);
        $this->http->setResponse('GET', 'locales/data', ['status' => true, 'data' => ['es-ES' => [['code' => 'es-cr', 'locale_name' => 'Español (Costa Rica)', 'lang_name' => 'Español']]]]);

        $this->logger = new SpyLogger();
        $options += ['cache' => new NullCache(), 'error_log' => false, 'logger' => $this->logger];
        $client = $clocked
            ? new class ($apiKey, $projectId, $options) extends Client {
                public $now = 1000.0;

                protected function currentTime()
                {
                    return $this->now;
                }
            }
            : new Client($apiKey, $projectId, $options);
        $property = (new \ReflectionClass(Client::class))->getProperty('http');
        $property->setAccessible(true);
        $property->setValue($client, $this->http);

        return $client;
    }

    /** @return array[] [endpoint, params] of every reference read sent */
    private function reads()
    {
        return array_values(array_map(function ($request) {
            return [$request['endpoint'], $request['params']];
        }, array_filter($this->http->getRequests(), function ($request) {
            return strpos($request['endpoint'], 'authorize-project') !== 0;
        })));
    }

    public function testEachListIsReadInTheCurrentLocale(): void
    {
        $client = $this->client();
        $client->setLocale('es-es');

        $this->assertSame(self::CURRENCIES, $client->getCurrencies());
        $this->assertSame(self::COUNTRIES, $client->getCountries());
        $this->assertSame(self::DIAL_CODES, $client->getDialCodes());
        $this->assertSame([['currencies/es-es', []], ['countries/es-es', []], ['countries/dial-codes/es-es', []]], $this->reads());
    }

    public function testALocaleGivenIsNormalisedAndOutranksTheCurrentOne(): void
    {
        $client = $this->client();
        $client->setLocale('fr-fr');

        $this->assertSame(self::CURRENCIES, $client->getCurrencies('ES_es'));
        $this->assertSame([['currencies/es-es', []]], $this->reads());
    }

    /**
     * The locale lists come in the API's three forms, asked for in one
     * display locale and answered under that locale as the organisation
     * formats it ('es-ES' for 'es-es'): the one entry is the list.
     */
    public function testTheLocalesComeInEachOfTheirThreeForms(): void
    {
        $client = $this->client();
        $client->setLocale('es-es');

        $this->assertSame(['Español' => [['code' => 'es-cr', 'name' => 'Español (Costa Rica)']]], $client->getLocalesFormat());
        $this->assertSame([['code' => 'es-cr', 'name' => 'Español (Costa Rica)']], $client->getLocalesFormat('flat'));
        $this->assertSame([['code' => 'es-cr', 'locale_name' => 'Español (Costa Rica)', 'lang_name' => 'Español']], $client->getLocalesFormat('data'));
        $asked = ['locales' => ['es-es'], 'append_target_locales' => 0];
        $this->assertSame([['locales', $asked], ['locales/flat', $asked], ['locales/data', $asked]], $this->reads());
    }

    /**
     * A locale list answers the entry whose key is the display locale, in
     * whatever code format the organisation uses, never the first entry, and
     * writes every code in WIRE-3's form. A locale the API does not hold
     * answers an empty list, held like any answer.
     */
    public function testALocaleListAnswersTheDisplayLocalesOwnEntryWithCodesInWireForm(): void
    {
        $client = $this->client();
        $client->setLocale('es-es');
        $this->http->setResponse('GET', 'locales', ['status' => true, 'data' => [
            'it_IT' => ['Italiano' => [['code' => 'it_IT', 'name' => 'Italiano (Italia)']]],
            'es_ES' => ['Español' => [['code' => 'es_CR', 'name' => 'Español (Costa Rica)']]],
        ]]);
        $this->http->setResponse('GET', 'locales/flat', ['status' => true, 'data' => [
            'it_IT' => [['code' => 'it_IT', 'name' => 'Italiano (Italia)']],
            'es_ES' => [['code' => 'es_CR', 'name' => 'Español (Costa Rica)']],
        ]]);
        $this->http->setResponse('GET', 'locales/data', ['status' => true, 'data' => [
            'it-IT' => [['code' => 'it-IT', 'locale_name' => 'Italiano (Italia)', 'lang_name' => 'Italiano']],
        ]]);

        $this->assertSame(['Español' => [['code' => 'es-cr', 'name' => 'Español (Costa Rica)']]], $client->getLocalesFormat());
        $this->assertSame([['code' => 'es-cr', 'name' => 'Español (Costa Rica)']], $client->getLocalesFormat('flat'));
        $this->assertSame([], $client->getLocalesFormat('data'), 'the API holds no es-es entry');
        $this->assertSame([], $client->getLocalesFormat('data'));
        $this->assertCount(3, $this->reads(), 'an empty answer is held like any answer');
    }

    /**
     * An answer is of a list's shape when its data is a list (currencies,
     * countries, dial codes) or a map keyed by locale (a locale form);
     * anything else answers empty with the cause logged.
     */
    public function testAnAnswerNotOfTheListsShapeIsEmpty(): void
    {
        $client = $this->client();
        $client->setLocale('es-es');
        $this->http->setResponse('GET', 'currencies/es-es', ['status' => true, 'data' => ['USD' => self::CURRENCIES[0]]]);
        $this->http->setResponse('GET', 'locales/flat', ['status' => true, 'data' => [['code' => 'es-cr', 'name' => 'Español (Costa Rica)']]]);

        $this->assertSame([], $client->getCurrencies());
        $this->assertSame([], $client->getLocalesFormat('flat'));
        $this->assertCount(2, $this->logger->messagesAt('error'));
    }

    /**
     * On a server the hold outlives the request: a list the API answered is
     * kept in the persistent cache with the catalog's lifetime, so a Client
     * built for the next request answers it with no request. A failure is
     * never held there.
     */
    public function testAListOutlivesTheRequestThroughTheCache(): void
    {
        $dir = sys_get_temp_dir() . '/langsys-reference-' . bin2hex(random_bytes(4));
        $cache = new \Langsys\SDK\Cache\FileCache($dir);

        try {
            $first = $this->client(['cache' => $cache]);
            $first->setLocale('es-es');
            $this->http->failures['countries/es-es'] = new ApiException('Service unavailable', 503);
            $this->assertSame(self::CURRENCIES, $first->getCurrencies());
            $this->assertSame([['code' => 'es-cr', 'name' => 'Español (Costa Rica)']], $first->getLocalesFormat('flat'));
            $this->assertSame([], $first->getCountries());

            $next = $this->client(['cache' => $cache]);
            $next->setLocale('es-es');
            $this->assertSame(self::CURRENCIES, $next->getCurrencies());
            $this->assertSame([['code' => 'es-cr', 'name' => 'Español (Costa Rica)']], $next->getLocalesFormat('flat'));
            $this->assertSame(self::COUNTRIES, $next->getCountries(), 'the failure was not held in the cache');
            $this->assertSame([['countries/es-es', []]], $this->reads(), 'the held lists sent nothing');
        } finally {
            $cache->clear();
            @rmdir($dir);
        }
    }

    public function testAnUnknownLocalesFormatSendsNothing(): void
    {
        $client = $this->client();
        $client->setLocale('es-es');

        $this->assertSame([], $client->getLocalesFormat('grouped'));
        $this->assertSame([], $this->reads());
        $this->assertNotEmpty($this->logger->messagesAt('warning'), 'it says why');
    }

    /**
     * A list is read once per display locale for the Client's life; another
     * locale is its own read, and the first is still held.
     */
    public function testEachListIsReadOncePerLocale(): void
    {
        $client = $this->client();
        $client->setLocale('es-es');

        $client->getCurrencies();
        $client->getCurrencies();
        $client->getCurrencies('fr-fr');
        $client->getCurrencies('es-es');
        $client->getLocalesFormat('flat');
        $client->getLocalesFormat('flat');
        $client->getLocalesFormat('data');
        $client->getLocalesFormat('flat', 'fr-fr');

        $this->assertSame([
            ['currencies/es-es', []],
            ['currencies/fr-fr', []],
            ['locales/flat', ['locales' => ['es-es'], 'append_target_locales' => 0]],
            ['locales/data', ['locales' => ['es-es'], 'append_target_locales' => 0]],
            ['locales/flat', ['locales' => ['fr-fr'], 'append_target_locales' => 0]],
        ], $this->reads());
    }

    /**
     * WIRE-4: the API failing, or answering with something that is not a
     * list, is an empty list and an error logged, never an exception. The
     * failure is remembered per list and display locale for CACHE-2's window:
     * inside it a call answers empty without a request, and after it the
     * call asks again.
     */
    public function testAFailedReadIsEmptyAndRememberedForItsWindow(): void
    {
        $client = $this->client([], 'test-api-key', 'project-id', true);
        $client->setLocale('es-es');
        $this->http->failures['currencies/es-es'] = new ApiException('Service unavailable', 503);
        $this->http->setResponse('GET', 'countries/es-es', ['status' => false, 'errors' => ['nope']]);
        $this->http->setResponse('GET', 'locales/flat', ['status' => true, 'data' => 'not a list']);

        $this->assertSame([], $client->getCurrencies());
        $this->assertSame([], $client->getCountries());
        $this->assertSame([], $client->getLocalesFormat('flat'));
        $this->assertCount(3, $this->logger->messagesAt('error'));
        $this->assertCount(3, $this->reads());

        unset($this->http->failures['currencies/es-es']);
        $client->now += 2.5;
        $this->assertSame([], $client->getCurrencies(), 'inside the window');
        $this->assertSame(self::CURRENCIES, $client->getCurrencies('fr-fr'), 'another display locale is its own read');
        $this->assertCount(4, $this->reads(), 'the call inside the window sent nothing');

        $client->now += 0.5;
        $this->assertSame(self::CURRENCIES, $client->getCurrencies(), 'after the window the call asks again');
    }

    /**
     * A cache that fails - a Redis connection dropped - is a miss on the read
     * and nothing on the write: each call still answers its list, and none
     * throws.
     */
    public function testACacheThatFailsIsAMissAndNeverAThrow(): void
    {
        $cache = new class implements \Langsys\SDK\Cache\CacheInterface {
            public function get($key)
            {
                throw new \RuntimeException('Connection lost');
            }

            public function set($key, $value, $ttl = 3600)
            {
                throw new \RuntimeException('Connection lost');
            }

            public function has($key)
            {
                throw new \RuntimeException('Connection lost');
            }

            public function delete($key)
            {
                throw new \RuntimeException('Connection lost');
            }

            public function clear()
            {
                throw new \RuntimeException('Connection lost');
            }
        };
        $client = $this->client(['cache' => $cache]);
        $client->setLocale('es-es');

        $this->assertSame(self::CURRENCIES, $client->getCurrencies());
        $this->assertSame(self::COUNTRIES, $client->getCountries());
        $this->assertSame(self::DIAL_CODES, $client->getDialCodes());
        $this->assertSame([['code' => 'es-cr', 'name' => 'Español (Costa Rica)']], $client->getLocalesFormat('flat'));
    }

    /**
     * WIRE-4: a client built without credentials sends nothing and answers
     * an empty list, with no error logged; with require_credentials the
     * first call throws, as every read does.
     */
    public function testAnUnconfiguredClientSendsNothing(): void
    {
        $client = $this->client([], '', '');
        $client->setLocale('es-es');

        $this->assertSame([], $client->getCurrencies());
        $this->assertSame([], $client->getCountries());
        $this->assertSame([], $client->getDialCodes());
        $this->assertSame([], $client->getLocalesFormat('data'));
        $this->assertSame([], $this->http->getRequests());
        $this->assertSame([], $this->logger->messagesAt('error'));

        $strict = $this->client(['require_credentials' => true], '', '');
        $strict->setLocale('es-es');
        $this->expectException(ConfigurationException::class);
        $strict->getCurrencies();
    }

    /**
     * The first-use check comes before anything else, an unknown locales
     * format included.
     */
    public function testRequireCredentialsThrowsBeforeAnUnknownFormatIsRefused(): void
    {
        $strict = $this->client(['require_credentials' => true], '', '');

        $this->expectException(ConfigurationException::class);
        $strict->getLocalesFormat('grouped');
    }

    /**
     * A display locale that is not one - a path, a query - is refused before
     * anything is sent, so it can never steer the authenticated request off
     * its route.
     */
    public function testALocaleThatIsNotOneSendsNothing(): void
    {
        $client = $this->client();

        foreach (['../authorize-project/project-id', '../../x?y=1#', 'es es', 'es/es', 'es?x=1', 'abcdefghi', 'e', ''] as $locale) {
            $this->assertSame([], $client->getCountries($locale === '' ? "\0" : $locale), json_encode($locale));
            $this->assertSame([], $client->getCurrencies($locale === '' ? "\0" : $locale));
            $this->assertSame([], $client->getDialCodes($locale === '' ? "\0" : $locale));
            $this->assertSame([], $client->getLocalesFormat('flat', $locale === '' ? "\0" : $locale));
        }

        $this->assertSame([], $this->reads());
        $this->assertNotEmpty($this->logger->messagesAt('warning'));
        $this->assertSame(self::CURRENCIES, $client->getCurrencies('ES-es'), 'a locale in any case is still one');

        $this->http->setResponse('GET', 'currencies/root', ['status' => true, 'data' => self::CURRENCIES]);
        $this->http->setResponse('GET', 'currencies/sr-latn-ba', ['status' => true, 'data' => self::CURRENCIES]);
        $this->assertSame(self::CURRENCIES, $client->getCurrencies('root'), 'a language of up to 8 letters');
        $this->assertSame(self::CURRENCIES, $client->getCurrencies('sr_Latn_BA'));
    }

    /**
     * With no locale set or given, the list is read in the project's base
     * locale, as the TS core falls back to its base locale.
     */
    public function testWithNoLocaleTheBaseLocaleIsUsed(): void
    {
        $client = $this->client();

        $this->http->setResponse('GET', 'currencies/en-us', ['status' => true, 'data' => self::CURRENCIES]);
        $this->assertSame(self::CURRENCIES, $client->getCurrencies());
        $this->assertSame([['currencies/en-us', []]], $this->reads());
    }
}
