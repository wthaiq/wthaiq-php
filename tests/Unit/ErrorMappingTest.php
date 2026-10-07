<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Wthaiq\Exception\ApiException;
use Wthaiq\Exception\AuthenticationException;
use Wthaiq\Exception\ConflictException;
use Wthaiq\Exception\ExceptionInterface;
use Wthaiq\Exception\InvalidRequestException;
use Wthaiq\Exception\NotFoundException;
use Wthaiq\Exception\PaymentRequiredException;
use Wthaiq\Exception\PermissionException;
use Wthaiq\Exception\RateLimitException;
use Wthaiq\Exception\ServerException;
use Wthaiq\Exception\UnexpectedResponseException;
use Wthaiq\Exception\ValidationException;
use Wthaiq\HttpClient\HttpResponse;

final class ErrorMappingTest extends TestCase
{
    /**
     * @return iterable<string, array{int, string, string, class-string<ApiException>}>
     */
    public static function statuses(): iterable
    {
        yield '400' => [400, 'invalid_request_error', 'parameter_missing', ValidationException::class];
        yield '401' => [401, 'authentication_error', 'invalid_api_key', AuthenticationException::class];
        yield '402' => [402, 'quota_error', 'insufficient_balance', PaymentRequiredException::class];
        yield '403' => [403, 'permission_error', 'kyb_required', PermissionException::class];
        yield '404' => [404, 'invalid_request_error', 'resource_missing', NotFoundException::class];
        yield '405' => [405, 'invalid_request_error', 'method_not_allowed', InvalidRequestException::class];
        yield '409' => [409, 'invalid_request_error', 'request_not_cancelable', ConflictException::class];
        yield '429' => [429, 'rate_limit_error', 'reminder_limit_reached', RateLimitException::class];
        yield '500' => [500, 'api_error', 'internal_error', ServerException::class];
        yield '501' => [501, 'api_error', 'not_implemented', ServerException::class];
    }

    /**
     * @param class-string<ApiException> $class
     */
    #[DataProvider('statuses')]
    public function testStatusMapsToException(int $status, string $type, string $code, string $class): void
    {
        $client = $this->client([self::error($status, $type, $code, 'signers[0].email')], ['max_retries' => 0]);
        try {
            $client->signatureRequests->retrieve('sr_x');
            self::fail('Expected ' . $class);
        } catch (ApiException $e) {
            self::assertInstanceOf($class, $e);
            self::assertInstanceOf(ExceptionInterface::class, $e);
            self::assertSame($status, $e->getHttpStatus());
            self::assertSame($status, $e->getCode());
            self::assertSame($type, $e->getErrorType());
            self::assertSame($code, $e->getErrorCode());
            self::assertSame('signers[0].email', $e->getParam());
            self::assertSame('req_err000000000000001', $e->getRequestId());
            self::assertSame('Error message for ' . $code, $e->getMessage());
            self::assertStringContainsString($code, $e->getHttpBody());
        }
    }

    public function testValidationExceptionFieldErrors(): void
    {
        $client = $this->client([self::error(400, 'invalid_request_error', 'invalid_request', 'signers[0].email')]);
        try {
            $client->signatureRequests->create(['source' => [], 'signers' => []]);
            self::fail('Expected ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['signers[0].email' => 'Error message for invalid_request'], $e->getFieldErrors());
        }
    }

    public function testPlanFeatureUnavailableCarriesPlanDetails(): void
    {
        $client = $this->client([self::error(403, 'permission_error', 'plan_feature_unavailable', 'recipients', [
            'feature' => 'bulk', 'required_plan' => 'ap_ent', 'upgrade_url' => 'https://developers.wthaiq.com/plans',
        ])]);
        try {
            $client->signatureRequests->createBulk(['source' => [], 'recipients' => []]);
            self::fail('Expected PermissionException');
        } catch (PermissionException $e) {
            self::assertTrue($e->isPlanFeatureUnavailable());
            self::assertFalse($e->isKybRequired());
            self::assertSame('bulk', $e->getFeature());
            self::assertSame('ap_ent', $e->getRequiredPlan());
            self::assertSame('https://developers.wthaiq.com/plans', $e->getUpgradeUrl());
            self::assertSame('bulk', $e->getError()['feature']);
        }
    }

    public function testKybRequiredPointsToTheVerificationPage(): void
    {
        $client = $this->client([self::error(403, 'permission_error', 'kyb_required')]);
        try {
            $client->signatureRequests->send('sr_x');
            self::fail('Expected PermissionException');
        } catch (PermissionException $e) {
            self::assertTrue($e->isKybRequired());
            self::assertNull($e->getFeature());
            self::assertSame(PermissionException::VERIFY_URL, $e->getUpgradeUrl());
        }
    }

    public function testPaymentRequiredHelpers(): void
    {
        foreach (['plan_required' => 'isPlanRequired', 'plan_expired' => 'isPlanExpired', 'insufficient_balance' => 'isInsufficientBalance'] as $code => $method) {
            $client = $this->client([self::error(402, 'quota_error', $code)]);
            try {
                $client->signatureRequests->create(['source' => [], 'signers' => []]);
                self::fail('Expected PaymentRequiredException');
            } catch (PaymentRequiredException $e) {
                self::assertTrue($e->{$method}());
                self::assertSame(PaymentRequiredException::PLANS_URL, $e->getUpgradeUrl());
            }
        }
    }

    public function testRateLimitRetryAfter(): void
    {
        $client = $this->client([self::error(429, 'rate_limit_error', 'daily_cap_reached', null, [], ['Retry-After' => '3600'])]);
        try {
            $client->signatureRequests->create(['source' => [], 'signers' => []]);
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(3600, $e->getRetryAfter());
            self::assertFalse($e->isTransient());
        }
        self::assertCount(1, $this->http->requests, 'Quota-style 429 must not be retried');
    }

    public function testErrorWithoutJsonBody(): void
    {
        $client = $this->client([new HttpResponse(502, ['Content-Type' => 'text/html'], '<html><body>Bad gateway</body></html>')], ['max_retries' => 0]);
        try {
            $client->account->retrieve();
            self::fail('Expected ServerException');
        } catch (ServerException $e) {
            self::assertNull($e->getErrorCode());
            self::assertStringContainsString('HTTP 502', $e->getMessage());
            self::assertStringContainsString('Bad gateway', $e->getMessage());
        }
    }

    public function testRequestIdFallsBackToTheHeader(): void
    {
        $client = $this->client([new HttpResponse(404, ['Wthaiq-Request-Id' => 'req_header0000000001'], '{"error":{"type":"invalid_request_error","code":"resource_missing","message":"Not found"}}')]);
        try {
            $client->events->retrieve('evt_x');
            self::fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            self::assertSame('req_header0000000001', $e->getRequestId());
            self::assertSame('req_header0000000001', $e->getHttpHeader('Wthaiq-Request-Id'));
        }
    }

    public function testInvalidJsonOnSuccessThrowsUnexpectedResponse(): void
    {
        $client = $this->client([new HttpResponse(200, [], '<html>maintenance</html>')]);
        $this->expectException(UnexpectedResponseException::class);
        $client->account->retrieve();
    }

    public function testJsonThatIsNotAnObjectThrowsUnexpectedResponse(): void
    {
        $client = $this->client([new HttpResponse(200, [], '[1,2]')]);
        try {
            $client->account->retrieve();
            self::fail('Expected UnexpectedResponseException');
        } catch (UnexpectedResponseException $e) {
            self::assertSame(200, $e->getHttpStatus());
            self::assertSame('[1,2]', $e->getHttpBody());
        }
    }
}
