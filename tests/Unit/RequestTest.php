<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use Wthaiq\ApiRequestor;
use Wthaiq\Exception\InvalidArgumentException;
use Wthaiq\RequestOptions;

final class RequestTest extends TestCase
{
    public function testPostSendsJsonAndAnAutomaticIdempotencyKey(): void
    {
        $client = $this->client([self::json(201, self::signatureRequest())]);
        $client->signatureRequests->create([
            'source' => ['type' => 'template', 'template_id' => 'tpl_12'],
            'signers' => [['name' => 'نهى', 'email' => 'noha@example.com']],
            'metadata' => ['url' => 'https://acme.example/orders/1', 'ratio' => 1.0],
        ]);
        $request = $this->http->lastRequest();

        self::assertSame('POST', $request->method);
        self::assertSame('https://wthaiq.com/api/v1/signature_requests', $request->url);
        self::assertSame('application/json', $request->getHeader('Content-Type'));
        self::assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', (string) $request->getHeader('Idempotency-Key'));
        self::assertStringContainsString('"name":"نهى"', (string) $request->body);
        self::assertStringContainsString('"url":"https://acme.example/orders/1"', (string) $request->body);
        self::assertStringContainsString('"ratio":1.0', (string) $request->body);
    }

    public function testCallerSuppliedIdempotencyKeyIsUsed(): void
    {
        $client = $this->client([self::json(201, self::signatureRequest())]);
        $client->signatureRequests->create(['source' => [], 'signers' => []], ['idempotency_key' => 'order-1024']);
        self::assertSame('order-1024', $this->http->lastRequest()->getHeader('Idempotency-Key'));
    }

    public function testGetRequestsHaveNoBodyAndNoIdempotencyKey(): void
    {
        $client = $this->client([self::json(200, ['object' => 'list', 'data' => [], 'has_more' => false, 'next_cursor' => null])]);
        $client->signatureRequests->list(['status' => 'completed', 'limit' => 5, 'created_after' => 1700000000, 'flag' => true, 'skip' => null]);
        $request = $this->http->lastRequest();

        self::assertSame('GET', $request->method);
        self::assertNull($request->body);
        self::assertNull($request->getHeader('Idempotency-Key'));
        self::assertSame('https://wthaiq.com/api/v1/signature_requests?status=completed&limit=5&created_after=1700000000&flag=true', $request->url);
    }

    public function testPostWithoutParamsSendsAnEmptyJsonObject(): void
    {
        $client = $this->client([self::json(200, self::signatureRequest('sr_x', 'canceled'))]);
        $client->signatureRequests->cancel('sr_x');
        self::assertSame('{}', $this->http->lastRequest()->body);
    }

    public function testIdsAreUrlEncoded(): void
    {
        $client = $this->client([self::json(200, ['object' => 'verification', 'found' => true])]);
        $client->verifications->retrieve('WTQ 1/2');
        self::assertSame('https://wthaiq.com/api/v1/verifications/WTQ%201%2F2', $this->http->lastRequest()->url);
    }

    public function testEmptyIdsAreRejectedBeforeAnyRequest(): void
    {
        $client = $this->client();
        $this->expectException(InvalidArgumentException::class);
        $client->signatureRequests->retrieve(' ');
    }

    public function testPerRequestApiKeyAndHeaders(): void
    {
        $client = $this->client([self::json(200, ['object' => 'usage'])]);
        $client->usage->retrieve(['api_key' => 'sk_test_other', 'headers' => ['X-Trace' => 'abc']]);
        $request = $this->http->lastRequest();
        self::assertSame('Bearer sk_test_other', $request->getHeader('Authorization'));
        self::assertSame('abc', $request->getHeader('X-Trace'));
    }

    public function testAuthorizationCannotBeOverriddenThroughHeaders(): void
    {
        $client = $this->client([self::json(200, ['object' => 'usage'])]);
        $this->expectException(InvalidArgumentException::class);
        $client->usage->retrieve(['headers' => ['Authorization' => 'Bearer sk_x']]);
    }

    public function testUnknownRequestOptionsAreRejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        RequestOptions::parse(['idempotencyKey' => 'x']);
    }

    public function testRequestOptionsValidation(): void
    {
        foreach ([
            ['idempotency_key' => str_repeat('k', 201)],
            ['idempotency_key' => 12],
            ['timeout' => -1],
            ['timeout' => 'slow'],
            ['max_retries' => -2],
            ['max_retries' => '2'],
            ['api_key' => 5],
            ['headers' => ['X' => ['a']]],
        ] as $options) {
            try {
                RequestOptions::parse($options);
                self::fail('Expected an exception for ' . json_encode($options));
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        $parsed = RequestOptions::parse(['idempotency_key' => 'k', 'timeout' => 3, 'max_retries' => 0]);
        self::assertSame('k', $parsed->idempotencyKey);
        self::assertSame(3.0, $parsed->timeout);
        self::assertSame(0, $parsed->maxRetries);
        self::assertSame($parsed, RequestOptions::parse($parsed));
    }

    public function testQueryEncoding(): void
    {
        self::assertSame('a=1&b=x%20y&c=false', ApiRequestor::encodeQuery(['a' => 1, 'b' => 'x y', 'c' => false, 'd' => null]));
        self::assertSame('{}', ApiRequestor::encodeJson([]));
    }

    public function testResponseMetadataIsExposed(): void
    {
        $client = $this->client([self::json(201, self::signatureRequest(), ['Idempotent-Replayed' => 'true', 'X-RateLimit-Remaining' => '97'])]);
        $request = $client->signatureRequests->create(['source' => [], 'signers' => []]);
        $response = $request->getLastResponse();

        self::assertNotNull($response);
        self::assertSame(201, $response->status);
        self::assertSame('req_aaaaaaaaaaaaaaaaaa', $response->getRequestId());
        self::assertTrue($response->isIdempotentReplayed());
        self::assertSame('97', $response->getHeader('x-ratelimit-remaining'));
    }
}
