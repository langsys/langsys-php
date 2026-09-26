<?php

namespace Langsys\SDK\Tests\Locale;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Locale\RequestLocale;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use PHPUnit\Framework\TestCase;

/**
 * SRV-6: the request locale comes from the URL, then a cookie or session value,
 * then Accept-Language negotiated against the project's locales, else the base
 * locale; every candidate is validated, and Vary names what the choice
 * depended on.
 */
class RequestLocaleTest extends TestCase
{
    const SERVED = ['en-us', 'es-es', 'fr-fr'];

    private function resolve(array $request, array $options = [])
    {
        return RequestLocale::resolve(self::SERVED, 'en-us', $request, $options);
    }

    /**
     * The spec's vector: one URL, four requests.
     */
    public function testOneUrlFourRequests(): void
    {
        $this->assertSame(
            ['locale' => 'es-es', 'source' => 'url', 'vary' => null],
            $this->resolve(['path' => '/es/pricing', 'cookies' => ['locale' => 'fr-fr'], 'accept_language' => 'fr-FR,fr;q=0.9']),
            'the URL wins over a conflicting cookie and header, and adds no Vary'
        );

        $this->assertSame(
            ['locale' => 'fr-fr', 'source' => 'cookie', 'vary' => 'Cookie'],
            $this->resolve(['path' => '/pricing', 'cookies' => ['locale' => 'fr-fr'], 'accept_language' => 'es-ES']),
            'the cookie wins over the header'
        );

        $this->assertSame(
            ['locale' => 'es-es', 'source' => 'accept-language', 'vary' => 'Accept-Language'],
            $this->resolve(['path' => '/pricing', 'accept_language' => 'es-ES,en;q=0.5'])
        );

        $this->assertSame(
            ['locale' => 'es-es', 'source' => 'accept-language', 'vary' => 'Accept-Language'],
            $this->resolve(['path' => '/pricing', 'cookies' => ['locale' => 'de-de'], 'accept_language' => 'es-ES']),
            'an unsupported cookie falls through to the header'
        );
    }

    /**
     * @dataProvider validationProvider
     */
    public function testEveryCandidateIsValidated(array $request, $locale, $source): void
    {
        $result = $this->resolve($request);

        $this->assertSame($locale, $result['locale']);
        $this->assertSame($source, $result['source']);
    }

    public function validationProvider(): array
    {
        return [
            'an unsupported URL locale falls through' => [['path' => '/de/pricing', 'cookies' => ['locale' => 'fr-fr']], 'fr-fr', 'cookie'],
            'a path that is not a locale falls through' => [['path' => '/pricing', 'accept_language' => 'fr'], 'fr-fr', 'accept-language'],
            'the query parameter is a URL source' => [['query' => ['locale' => 'fr-FR'], 'cookies' => ['locale' => 'es-es']], 'fr-fr', 'url'],
            'the subdomain is a URL source' => [['host' => 'fr.example.com', 'cookies' => ['locale' => 'es-es']], 'fr-fr', 'url'],
            'a bare domain has no subdomain' => [['host' => 'example.com', 'accept_language' => 'es'], 'es-es', 'accept-language'],
            'a language matches its one served region' => [['cookies' => ['locale' => 'es-MX']], 'es-es', 'cookie'],
            'a session value is a stored source' => [['session' => ['locale' => 'fr_FR']], 'fr-fr', 'cookie'],
            'the header in quality order' => [['accept_language' => 'de;q=1, fr;q=0.4, es;q=0.8'], 'es-es', 'accept-language'],
            'q=0 is never chosen' => [['accept_language' => 'fr;q=0, de'], 'en-us', 'base'],
            'nothing usable serves the base' => [['path' => '/de/', 'cookies' => ['locale' => 'xx'], 'accept_language' => 'de-DE'], 'en-us', 'base'],
        ];
    }

    public function testALanguageServedInTwoRegionsNeedsTheRegion(): void
    {
        $served = ['en-us', 'es-es', 'es-mx'];

        $this->assertSame('en-us', RequestLocale::resolve($served, 'en-us', ['cookies' => ['locale' => 'es']])['locale']);
        $this->assertSame('es-mx', RequestLocale::resolve($served, 'en-us', ['cookies' => ['locale' => 'es-MX']])['locale']);
    }

    public function testTheBaseVariesOnTheHeaderOnlyWhenOneWasSent(): void
    {
        $this->assertSame('Accept-Language', $this->resolve(['accept_language' => 'de'])['vary']);
        $this->assertNull($this->resolve([])['vary']);
    }

    /**
     * Framework knobs choose where the values live, not the order.
     */
    public function testTheQueryAndCookieNamesAreWiring(): void
    {
        $request = ['query' => ['lang' => 'fr'], 'cookies' => ['lang' => 'es-es', 'locale' => 'fr-fr']];

        $this->assertSame('fr-fr', $this->resolve($request, ['query_param' => 'lang'])['locale']);
        $this->assertSame(['es-es', 'cookie'], array_values(array_slice($this->resolve($request, ['query_param' => null, 'cookie' => 'lang']), 0, 2)));
    }

    public function testAResolversAnswerIsValidatedAndStandsInForUrlAndCookie(): void
    {
        $request = ['path' => '/fr/', 'cookies' => ['locale' => 'fr-fr'], 'accept_language' => 'es'];

        $this->assertSame(['locale' => 'fr-fr', 'source' => 'cookie', 'vary' => 'Cookie'], $this->resolve($request, ['resolver' => function () {
            return 'fr';
        }]));

        $this->assertSame(['locale' => 'fr-fr', 'source' => 'url', 'vary' => null], $this->resolve($request, ['resolver' => function () {
            return ['locale' => 'fr-FR', 'from' => 'url'];
        }]));

        $this->assertSame(
            ['locale' => 'es-es', 'source' => 'accept-language', 'vary' => 'Accept-Language'],
            $this->resolve($request, ['resolver' => function () {
                return 'de-de';
            }]),
            'an unsupported answer falls through to the header, and the URL and cookie are not read'
        );
    }

    // The Client: getLocale() resolves once per request and sends Vary.

    private function client()
    {
        $http = new MockHttpClient();
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => [
            'key_type' => 'read', 'write_enabled' => false, 'base_locale' => 'en-us', 'target_locales' => ['es-es', 'fr-fr'],
        ]]);

        $client = new class ('test-api-key', 'project-id', ['cache' => new NullCache(), 'request_locale' => ['cookie' => 'lang']]) extends Client {
            public $vary = [];
            public $request = [];

            protected function sendVaryHeader($value)
            {
                $this->vary[] = $value;
            }

            protected function currentRequest()
            {
                return $this->request;
            }
        };

        $prop = new \ReflectionProperty(Client::class, 'http');
        $prop->setAccessible(true);
        $prop->setValue($client, $http);

        return $client;
    }

    public function testGetLocaleResolvesTheRequestOnceAndSendsVary(): void
    {
        $client = $this->client();
        $client->request = ['cookies' => ['lang' => 'fr'], 'accept_language' => 'es'];

        $this->assertSame('fr-fr', $client->getLocale());
        $this->assertSame('fr-fr', $client->getLocale());
        $this->assertSame(['Cookie'], $client->vary, 'sent once for the request');

        $client->resetRequestState();
        $client->request = ['accept_language' => 'de, es;q=0.5'];
        $this->assertSame('es-es', $client->getLocale(), 'the next request resolves afresh');
        $this->assertSame(['Cookie', 'Accept-Language'], $client->vary);
    }

    public function testAnUnsupportedHeaderLocaleIsNeverServed(): void
    {
        $client = $this->client();
        $client->request = ['accept_language' => 'de-DE'];

        $this->assertSame('en-us', $client->getLocale());
    }

    public function testASetLocaleIsNotResolved(): void
    {
        $client = $this->client();
        $client->request = ['cookies' => ['lang' => 'fr']];
        $client->setLocale('es-es');

        $this->assertSame('es-es', $client->getLocale());
        $this->assertSame([], $client->vary);
    }

    /**
     * A binding that sets Vary on its own response turns the Client's off;
     * the decision still comes back, Vary included.
     */
    public function testSendVaryFalseReturnsTheDecisionAndSendsNothing(): void
    {
        $client = $this->client();

        $result = $client->resolveRequestLocale(['cookies' => ['lang' => 'fr']], ['cookie' => 'lang', 'send_vary' => false]);

        $this->assertSame(['locale' => 'fr-fr', 'source' => 'cookie', 'vary' => 'Cookie'], $result);
        $this->assertSame([], $client->vary);
    }

    public function testSendVaryFalseInTheClientOptionAppliesToGetLocale(): void
    {
        $http = new MockHttpClient();
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => [
            'key_type' => 'read', 'write_enabled' => false, 'base_locale' => 'en-us', 'target_locales' => ['es-es', 'fr-fr'],
        ]]);

        $client = new class ('test-api-key', 'project-id', ['cache' => new NullCache(), 'request_locale' => ['send_vary' => false]]) extends Client {
            public $vary = [];

            protected function sendVaryHeader($value)
            {
                $this->vary[] = $value;
            }

            protected function currentRequest()
            {
                return ['accept_language' => 'es'];
            }
        };

        $prop = new \ReflectionProperty(Client::class, 'http');
        $prop->setAccessible(true);
        $prop->setValue($client, $http);

        $this->assertSame('es-es', $client->getLocale());
        $this->assertSame([], $client->vary);
    }

    // A locale the framework or the app already resolved

    /**
     * The spec's vector: the framework's locale is served whatever the URL,
     * cookie and header say, and the SDK adds no Vary.
     */
    public function testTheFrameworksLocaleIsServedWhateverElseTheRequestSays(): void
    {
        $this->assertSame(
            ['locale' => 'es-es', 'source' => 'framework', 'vary' => null],
            $this->resolve(['framework' => 'es-ES', 'path' => '/fr/pricing', 'cookies' => ['locale' => 'fr-fr'], 'accept_language' => 'fr'])
        );
        $this->assertSame('es-es', $this->resolve(['framework' => 'es_ES'])['locale']);
    }

    public function testABareFrameworkLanguageIsTheProjectsDefaultLocaleForIt(): void
    {
        $served = ['en-us', 'es-es', 'es-mx'];

        $this->assertSame('es-mx', RequestLocale::resolve($served, 'en-us', ['framework' => 'es'], [], ['es' => 'es-mx'])['locale']);
        $this->assertSame('es-es', RequestLocale::resolve($served, 'en-us', ['framework' => 'es'], [], ['es' => 'es-es'])['locale']);
    }

    public function testAnUnsupportedFrameworkLocaleServesTheBase(): void
    {
        $this->assertSame(
            ['locale' => 'en-us', 'source' => 'framework', 'vary' => null],
            $this->resolve(['framework' => 'de-DE', 'query' => ['locale' => 'fr'], 'cookies' => ['locale' => 'es-es']]),
            'the framework decided; the URL and cookie are not consulted'
        );
    }

    public function testWithNothingResolvedTheResolverRunsAsBefore(): void
    {
        $this->assertSame('fr-fr', $this->resolve(['framework' => '', 'query' => ['locale' => 'fr']])['locale']);
        $this->assertSame('fr-fr', $this->resolve(['framework' => null, 'cookies' => ['locale' => 'fr']])['locale']);
    }

    public function testTheClientMapsAFrameworkLanguageThroughAuthorizationsDefaults(): void
    {
        $http = new MockHttpClient();
        $http->setResponse('GET', 'authorize-project/project-id', ['data' => [
            'key_type' => 'read', 'write_enabled' => false, 'base_locale' => 'en-us',
            'target_locales' => ['es-es', 'es-mx'], 'default_locales' => ['es' => 'es-mx'],
        ]]);

        $client = new class ('test-api-key', 'project-id', ['cache' => new NullCache()]) extends Client {
            public $vary = [];

            protected function sendVaryHeader($value)
            {
                $this->vary[] = $value;
            }
        };
        $prop = new \ReflectionProperty(Client::class, 'http');
        $prop->setAccessible(true);
        $prop->setValue($client, $http);

        $this->assertSame('es-mx', $client->resolveRequestLocale(['framework' => 'es', 'accept_language' => 'fr'])['locale']);
        $this->assertSame([], $client->vary, 'the framework varies on its own choice');
    }
}
