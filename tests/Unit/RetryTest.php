<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use Wthaiq\Exception\ApiConnectionException;
use Wthaiq\Exception\ConflictException;
use Wthaiq\Exception\RateLimitException;
use Wthaiq\Exception\ServerException;
use Wthaiq\HttpClient\HttpResponse;
use Wthaiq\RetryPolicy;

final class RetryTest extends TestCase
{
    public function testConnectionErrorsAreRetriedWithTheSameIdempotencyKey(): void
    {
        $client = $this->client([
            new ApiConnectionException('timeout', true),
            new ApiConnectionException('reset'),
            self::json(201, self::signatureRequest()),
        ]);
        $request = $client->signatureRequests->create(['source' => [], 'signers' => []]);

        self::assertSame('sr_aaaaaaaaaaaaaaaaaaaa', $request->id);
        self::assertCount(3, $this->http->requests);
        $keys = array_map(static fn ($r) => $r->getHeader('Idempotency-Key'), $this->http->requests);
        self::assertCount(1, array_unique($keys));
        self::assertSame([0.375, 0.75], $this->sleeps);
    }

    public function testConnectionErrorIsThrownAfterTheLastRetry(): void
    {
        $client = $this->client([
            new ApiConnectionException('refused'),
            new ApiConnectionException('refused'),
            new ApiConnectionException('refused', true),
        ]);
        try {
            $client->account->retrieve();
            self::fail('Expected ApiConnectionException');
        } catch (ApiConnectionException $e) {
            self::assertTrue($e->isTimeout());
        }
        self::assertCount(3, $this->http->requests);
    }

    public function testTooManyRequestsHonoursRetryAfter(): void
    {
        $client = $this->client([
            self::error(429, 'rate_limit_error', 'too_many_requests', null, [], ['Retry-After' => '4']),
            self::json(200, ['object' => 'usage']),
        ]);
        $client->usage->retrieve();
        self::assertSame([4.0], $this->sleeps);
    }

    public function testRetryAfterIsCapped(): void
    {
        $policy = new RetryPolicy(maxRetryAfter: 10.0);
        self::assertSame(10.0, $policy->delay(0, new HttpResponse(429, ['Retry-After' => '86400'])));
    }

    public function testIdempotencyInProgressIsRetried(): void
    {
        $client = $this->client([
            self::error(409, 'idempotency_error', 'idempotency_in_progress', null, [], ['Retry-After' => '5']),
            self::json(201, self::signatureRequest()),
        ]);
        $client->signatureRequests->create(['source' => [], 'signers' => []], ['idempotency_key' => 'k1']);
        self::assertCount(2, $this->http->requests);
        self::assertSame([5.0], $this->sleeps);
    }

    public function testIdempotencyKeyReusedIsNotRetried(): void
    {
        $client = $this->client([self::error(409, 'idempotency_error', 'idempotency_key_reused')]);
        $this->expectException(ConflictException::class);
        try {
            $client->signatureRequests->create(['source' => [], 'signers' => []]);
        } finally {
            self::assertCount(1, $this->http->requests);
        }
    }

    public function testServerErrorsAreRetried(): void
    {
        $client = $this->client([
            self::error(500, 'api_error', 'internal_error'),
            self::error(503, 'api_error', 'unavailable'),
            self::json(200, ['object' => 'usage']),
        ]);
        $client->usage->retrieve();
        self::assertCount(3, $this->http->requests);
    }

    public function testDeliveryFailedIsNotRetried(): void
    {
        $client = $this->client([self::error(502, 'api_error', 'delivery_failed')]);
        try {
            $client->signatureRequests->send('sr_x');
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            self::assertSame('delivery_failed', $e->getErrorCode());
        }
        self::assertCount(1, $this->http->requests);
    }

    public function testFeatureUnavailableIsNotRetried(): void
    {
        $client = $this->client([self::error(503, 'api_error', 'feature_unavailable')]);
        $this->expectException(ServerException::class);
        try {
            $client->sessions->create(['workflow_id' => 'default', 'signers' => []]);
        } finally {
            self::assertCount(1, $this->http->requests);
        }
    }

    public function testQuotaStyleRateLimitsAreNotRetried(): void
    {
        foreach (['daily_cap_reached', 'reminder_limit_reached', 'reminder_too_soon', 'test_daily_limit', 'test_email_limit'] as $code) {
            $client = $this->client([self::error(429, 'rate_limit_error', $code)]);
            try {
                $client->signatureRequests->remind('sr_x');
                self::fail('Expected RateLimitException for ' . $code);
            } catch (RateLimitException $e) {
                self::assertSame($code, $e->getErrorCode());
            }
            self::assertCount(1, $this->http->requests, $code);
        }
    }

    public function testClientErrorsAreNotRetried(): void
    {
        $client = $this->client([self::error(400, 'invalid_request_error', 'parameter_missing', 'source')]);
        try {
            $client->signatureRequests->create([]);
            self::fail('Expected an exception');
        } catch (\Wthaiq\Exception\ValidationException) {
            self::assertCount(1, $this->http->requests);
        }
    }

    public function testMaxRetriesCanBeChangedPerClientAndPerRequest(): void
    {
        $client = $this->client([
            self::error(500, 'api_error', 'internal_error'),
            self::error(500, 'api_error', 'internal_error'),
        ], ['max_retries' => 5]);
        try {
            $client->usage->retrieve(['max_retries' => 1]);
            self::fail('Expected ServerException');
        } catch (ServerException) {
            self::assertCount(2, $this->http->requests);
        }

        $client = $this->client([self::error(500, 'api_error', 'internal_error')], ['max_retries' => 0]);
        try {
            $client->usage->retrieve();
            self::fail('Expected ServerException');
        } catch (ServerException) {
            self::assertCount(1, $this->http->requests);
        }
    }

    public function testBackoffIsExponentialWithJitterAndCapped(): void
    {
        $low = new RetryPolicy(random: static fn (): float => 0.0);
        $high = new RetryPolicy(random: static fn (): float => 0.999999);
        self::assertSame(0.25, $low->delay(0, null));
        self::assertEqualsWithDelta(0.5, $high->delay(0, null), 0.0001);
        self::assertSame(1.0, $low->delay(2, null));
        self::assertSame(4.0, $low->delay(10, null));
        self::assertEqualsWithDelta(8.0, $high->delay(10, null), 0.0001);
    }

    public function testRetryDecisions(): void
    {
        $policy = new RetryPolicy();
        self::assertTrue($policy->shouldRetry(null, null));
        self::assertTrue($policy->shouldRetry(new HttpResponse(429), 'too_many_requests'));
        self::assertTrue($policy->shouldRetry(new HttpResponse(504), null));
        self::assertFalse($policy->shouldRetry(new HttpResponse(501), 'not_implemented'));
        self::assertFalse($policy->shouldRetry(new HttpResponse(404), 'resource_missing'));
        self::assertFalse($policy->shouldRetry(new HttpResponse(402), 'plan_required'));
    }
}
