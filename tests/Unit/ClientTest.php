<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use Wthaiq\Client;
use Wthaiq\Exception\InvalidArgumentException;
use Wthaiq\Service\SignatureRequestService;

final class ClientTest extends TestCase
{
    public function testItRequiresAnApiKey(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Client('');
    }

    public function testItRejectsAKeyWithAnUnknownPrefix(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Client('rk_live_123');
    }

    public function testItAcceptsAConfigArray(): void
    {
        $client = new Client(['api_key' => self::KEY, 'base_url' => 'http://127.0.0.1:8782/api/v1/', 'http_client' => new MockHttpClient()]);
        self::assertSame('http://127.0.0.1:8782/api/v1', $client->getBaseUrl());
        self::assertInstanceOf(SignatureRequestService::class, $client->signatureRequests);
    }

    public function testDefaultBaseUrl(): void
    {
        $client = new Client(self::KEY, ['http_client' => new MockHttpClient()]);
        self::assertSame('https://wthaiq.com/api/v1', $client->getBaseUrl());
        self::assertSame(Client::DEFAULT_BASE_URL, $client->getBaseUrl());
    }

    public function testItRejectsUnknownOptions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('maxRetries');
        new Client(self::KEY, ['maxRetries' => 3]);
    }

    public function testItRejectsInvalidOptionValues(): void
    {
        foreach ([
            ['max_retries' => -1],
            ['timeout' => 0],
            ['connect_timeout' => 'x'],
            ['base_url' => 'ftp://example.com'],
            ['http_client' => new \stdClass()],
            ['retry_policy' => 'fast'],
            ['api_version' => ''],
            ['app_info' => ['version' => '1']],
        ] as $options) {
            try {
                new Client(self::KEY, $options);
                self::fail('Expected an exception for ' . json_encode(array_keys($options)));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function testModeIsDerivedFromTheKeyPrefix(): void
    {
        $http = new MockHttpClient();
        self::assertTrue((new Client('sk_test_abc', ['http_client' => $http]))->isTestMode());
        self::assertTrue((new Client('pk_test_abc', ['http_client' => $http]))->isTestMode());
        self::assertTrue((new Client('sk_abc', ['http_client' => $http]))->isLiveMode());
        self::assertTrue((new Client('pk_abc', ['http_client' => $http]))->isPublishableKey());
        self::assertFalse((new Client('sk_abc', ['http_client' => $http]))->isPublishableKey());
    }

    public function testUserAgentAndVersionHeaders(): void
    {
        $client = $this->client([self::json(200, ['object' => 'api_root', 'livemode' => false])], [
            'app_info' => ['name' => 'Acme CRM', 'version' => '2.1', 'url' => 'https://acme.example'],
        ]);
        $client->account->retrieve();
        $request = $this->http->lastRequest();

        self::assertSame('wthaiq-php/' . Client::VERSION . ' PHP/' . PHP_VERSION . ' Acme-CRM/2.1 (https://acme.example)', $request->getHeader('User-Agent'));
        self::assertSame('2026-07-01', $request->getHeader('Wthaiq-Version'));
        self::assertSame('Bearer ' . self::KEY, $request->getHeader('Authorization'));
        self::assertSame('application/json', $request->getHeader('Accept'));
    }

    public function testTimeoutsArePassedToTheTransport(): void
    {
        $client = $this->client([
            self::json(200, ['object' => 'usage']),
            self::json(200, ['object' => 'usage']),
        ], ['timeout' => 12.5, 'connect_timeout' => 3]);
        $client->usage->retrieve();
        self::assertSame(12.5, $this->http->lastRequest()->timeout);
        self::assertSame(3.0, $this->http->lastRequest()->connectTimeout);

        $client->usage->retrieve(['timeout' => 2]);
        self::assertSame(2.0, $this->http->lastRequest()->timeout);
    }

    public function testCustomApiVersion(): void
    {
        $client = $this->client([self::json(200, ['object' => 'balance'])], ['api_version' => '2027-01-01']);
        $client->balance->retrieve();
        self::assertSame('2027-01-01', $this->http->lastRequest()->getHeader('Wthaiq-Version'));
    }
}
