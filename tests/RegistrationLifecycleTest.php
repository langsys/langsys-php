<?php

namespace Langsys\SDK\Tests;

use Langsys\SDK\Cache\NullCache;
use Langsys\SDK\Client;
use Langsys\SDK\Exception\LangsysException;
use Langsys\SDK\Tests\Mock\MockHttpClient;
use Langsys\SDK\Tests\Support\SpyLogger;
use PHPUnit\Framework\TestCase;

/**
 * REG-8 backoff, the request-scoped queue, REG-11's ellipsis signal and
 * OBS-1's unusable-capability diagnostic.
 */
class RegistrationLifecycleTest extends TestCase
{
    /**
     * @var MockHttpClient
     */
    private $http;

    /**
     * @var SpyLogger
     */
    private $logger;

    /**
     * A client on a controllable clock, whose sends fail while $http->failPosts.
     */
    private function client(array $catalog = ['UI' => []], array $authorize = ['key_type' => 'write', 'write_enabled' => true], $project = 'project-id')
    {
        $this->http = new class extends MockHttpClient {
            public $failPosts = false;
            public $failAuthorize = false;

            public function get($endpoint, array $params = [])
            {
                $response = parent::get($endpoint, $params);

                if ($this->failAuthorize && strpos($endpoint, 'authorize-project/') === 0) {
                    throw new LangsysException('The authorization call failed');
                }

                return $response;
            }

            public function post($endpoint, array $data = [])
            {
                $response = parent::post($endpoint, $data);

                if ($this->failPosts) {
                    throw new LangsysException('The API refused the send');
                }

                return $response;
            }
        };
        $this->http->setResponse('GET', 'authorize-project/' . $project, ['data' => $authorize]);
        $this->http->setResponse('GET', 'translations', ['data' => $catalog]);
        $this->http->setResponse('POST', 'translatable-items', ['status' => true]);

        $this->logger = new SpyLogger();

        $client = new class ('test-api-key', $project, ['cache' => new NullCache(), 'logger' => $this->logger]) extends Client {
            public $now = 1000.0;

            protected function currentTime()
            {
                return $this->now;
            }
        };

        foreach (['http', 'translations', 'translatableItems'] as $property) {
            $prop = new \ReflectionProperty(Client::class, $property);
            $prop->setAccessible(true);
            if ($property === 'http') {
                $prop->setValue($client, $this->http);
                continue;
            }
            $resource = $prop->getValue($client);
            $inner = (new \ReflectionClass($resource))->getProperty('http');
            $inner->setAccessible(true);
            $inner->setValue($resource, $this->http);
        }

        $client->setLocale('es-es');

        return $client;
    }

    private function sends(): int
    {
        return count(array_filter($this->http->getRequests(), function ($request) {
            return $request['method'] === 'POST';
        }));
    }

    // REG-8

    public function testAFailedSendStaysQueuedAndBacksOffExponentiallyToACeiling(): void
    {
        $client = $this->client();
        $client->translate('Save', null, 'UI');
        $this->http->failPosts = true;

        $expectedWaits = [3, 6, 12, 24, 48, 96, 192, 300, 300];

        foreach ($expectedWaits as $wait) {
            $sendsBefore = $this->sends();
            $result = $client->flushPendingRegistrations();
            $this->assertFalse($result['success']);
            $this->assertSame(1, $result['retained'], 'the failed item stays queued');
            $this->assertSame($sendsBefore + 1, $this->sends(), 'the attempt reached the endpoint');

            $client->now += $wait - 0.5;
            $deferred = $client->flushPendingRegistrations();
            $this->assertSame($sendsBefore + 1, $this->sends(), 'no request inside the ' . $wait . 's wait');
            $this->assertSame(1, $deferred['retained']);
            $this->assertTrue($client->hasPendingRegistrations());

            $client->now += 0.5;
        }
    }

    public function testTheFirstSuccessResetsTheBackoff(): void
    {
        $client = $this->client();
        $client->translate('Save', null, 'UI');
        $this->http->failPosts = true;
        $client->flushPendingRegistrations();
        $client->now += 3;
        $client->flushPendingRegistrations();
        $client->now += 6;

        $this->http->failPosts = false;
        $this->assertTrue($client->flushPendingRegistrations()['success']);
        $this->assertFalse($client->hasPendingRegistrations());

        // The next failure waits the initial 3s again, not 12s.
        $client->translate('Cancel', null, 'UI');
        $this->http->failPosts = true;
        $client->flushPendingRegistrations();
        $client->now += 3;
        $sends = $this->sends();
        $client->flushPendingRegistrations();
        $this->assertSame($sends + 1, $this->sends());
    }

    /**
     * On a long-lived worker the clock outlives the request, and the queue does
     * not: the next request sends only its own misses, and not inside the wait.
     */
    public function testTheClockOutlivesTheRequestAndTheQueueDoesNot(): void
    {
        $client = $this->client();
        $client->translate('From the first request', null, 'UI');
        $this->http->failPosts = true;
        $client->flushPendingRegistrations();

        $client->resetRequestState();
        $this->assertFalse($client->hasPendingRegistrations(), 'one request\'s phrases never ride another\'s send');

        $client->translate('From the second request', null, 'UI');
        $this->http->failPosts = false;
        $sends = $this->sends();
        $client->flushPendingRegistrations();
        $this->assertSame($sends, $this->sends(), 'the second request waits out the first request\'s failure');

        $client->now += 3;
        $client->flushPendingRegistrations();
        $posted = $this->http->getLastRequest()['data']['translatable_items'];
        $this->assertSame(['From the second request'], array_column($posted, 'phrase'));
    }

    /**
     * A flush whose authorization fails is a failed send too: the endpoint is
     * not asked again inside the wait.
     */
    public function testAFailedAuthorizationBacksOffLikeAFailedSend(): void
    {
        $client = $this->client();
        $client->translate('Save', null, 'UI');

        $writeEnabled = new \ReflectionProperty(Client::class, 'writeEnabled');
        $writeEnabled->setAccessible(true);
        $writeEnabled->setValue($client, null);
        $this->http->failAuthorize = true;

        $this->assertSame(1, $client->flushPendingRegistrations()['retained']);
        $calls = count($this->http->getRequests());

        $client->now += 2.5;
        $client->flushPendingRegistrations();
        $this->assertSame($calls, count($this->http->getRequests()), 'no authorization call inside the wait');

        $client->now += 0.5;
        $client->flushPendingRegistrations();
        $this->assertGreaterThan($calls, count($this->http->getRequests()));
    }

    public function testAFailedBlockSendIsRetainedAndBacksOff(): void
    {
        $client = $this->client();
        $client->translateContentBlock('<p>One</p><p>Two</p>', 'UI');
        $this->http->failPosts = true;

        $result = $client->flushPendingRegistrations();
        $this->assertSame(1, $result['retained']);

        $sends = $this->sends();
        $client->flushPendingRegistrations();
        $this->assertSame($sends, $this->sends());
    }

    public function testOneProjectsFailureDoesNotHoldBackAnother(): void
    {
        $failing = $this->client(['UI' => []], ['key_type' => 'write', 'write_enabled' => true], 'project-a');
        $failing->translate('Save', null, 'UI');
        $this->http->failPosts = true;
        $failing->flushPendingRegistrations();

        $healthy = $this->client(['UI' => []], ['key_type' => 'write', 'write_enabled' => true], 'project-b');
        $healthy->translate('Save', null, 'UI');
        $this->assertTrue($healthy->flushPendingRegistrations()['success']);
    }

    // REG-11

    public function testATruncationOfAKnownPhraseIsNotRegistered(): void
    {
        $client = $this->client(['UI' => ['Read more about our pricing plans' => 'Lee más sobre nuestros planes']]);

        $client->translate("Read more about our pri\u{2026}", null, 'UI');
        $client->translate('Read more about our pricing pl...', null, 'UI');

        $this->assertFalse($client->hasPendingRegistrations());
        $this->assertNotEmpty($this->debugNaming("Read more about our pri\u{2026}"));
    }

    /**
     * The other side of the vector set: an ellipsis alone is legitimate copy.
     */
    public function testAnEllipsisAloneRegistersAndWarns(): void
    {
        $client = $this->client(['UI' => ['Saving your changes' => 'Guardando']]);

        $client->translate("Loading\u{2026}", null, 'UI');
        $client->translate('Please wait...', null, 'UI');

        $this->assertSame(["Loading\u{2026}", 'Please wait...'], array_values(array_column($client->getPendingPhrases(), 'phrase')));
        $this->assertNotEmpty($this->debugNaming("Loading\u{2026}"));
        $this->assertNotEmpty($this->debugNaming('Please wait...'));
    }

    public function testAPageTeaserOfItsOwnParagraphIsNotRegistered(): void
    {
        $client = $this->client(['UI' => []]);

        $client->translatePage('<html><body><p>Our new pricing plans start today</p><p>Our new pricing pl…</p></body></html>', 'UI');

        $this->assertSame(['Our new pricing plans start today'], array_values(array_column($client->getPendingPhrases(), 'phrase')));
    }

    private function debugNaming($phrase): array
    {
        return array_values(array_filter($this->logger->entries, function ($entry) use ($phrase) {
            return $entry['level'] === 'debug' && isset($entry['context']['phrase']) && $entry['context']['phrase'] === $phrase
                && stripos($entry['message'], 'ellipsis') !== false;
        }));
    }

    // OBS-1

    /**
     * @dataProvider capabilityProvider
     */
    public function testAnUnusableWriteCapabilityIsReportedOnce(array $authorize, $reported): void
    {
        $client = $this->client(['UI' => []], $authorize);

        foreach (['One', 'Two', 'Three'] as $miss) {
            $client->translate($miss, null, 'UI');
            $client->flushPendingRegistrations();
        }
        $client->resetRequestState();
        $client->authorize(true);
        $client->translate('Four', null, 'UI');

        $warnings = array_values(array_filter($this->logger->entries, function ($entry) {
            return $entry['level'] === 'warning' && strpos($entry['message'], 'cannot register new text') !== false;
        }));

        $this->assertCount($reported ? 1 : 0, $warnings);
        if ($reported) {
            $this->assertSame($authorize['key_type'], $warnings[0]['context']['key_type']);
        }
    }

    public function capabilityProvider(): array
    {
        return [
            'a write key the server refuses' => [['key_type' => 'write', 'write_enabled' => false], true],
            'an ip_write key off its list' => [['key_type' => 'ip_write', 'write_enabled' => false], true],
            'control: a read key' => [['key_type' => 'read', 'write_enabled' => false], false],
            'control: a write key that writes' => [['key_type' => 'write', 'write_enabled' => true], false],
        ];
    }
}
