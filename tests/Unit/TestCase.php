<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use Wthaiq\Client;
use Wthaiq\HttpClient\HttpRequest;
use Wthaiq\HttpClient\HttpResponse;
use Wthaiq\RetryPolicy;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    public const KEY = 'sk_test_0123456789abcdef0123456789abcdef01234567';

    protected MockHttpClient $http;

    /** @var list<float> */
    protected array $sleeps = [];

    /**
     * @param list<HttpResponse|\Wthaiq\Exception\ApiConnectionException|\Closure(HttpRequest): HttpResponse> $responses
     * @param array<string, mixed>                                                                           $options
     */
    protected function client(array $responses = [], array $options = []): Client
    {
        $this->http = new MockHttpClient($responses);
        $this->sleeps = [];
        $sleeps = &$this->sleeps;
        $policy = new RetryPolicy(sleeper: static function (float $s) use (&$sleeps): void {
            $sleeps[] = $s;
        }, random: static fn (): float => 0.5);

        return new Client(self::KEY, $options + ['http_client' => $this->http, 'retry_policy' => $policy]);
    }

    /**
     * @param array<string, mixed>|list<mixed> $body
     * @param array<string, string>            $headers
     */
    protected static function json(int $status, array $body, array $headers = []): HttpResponse
    {
        return new HttpResponse($status, $headers + [
            'Content-Type' => 'application/json; charset=utf-8',
            'Wthaiq-Request-Id' => 'req_' . str_repeat('a', 18),
        ], json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    /**
     * @param array<string, mixed>  $extra
     * @param array<string, string> $headers
     */
    protected static function error(int $status, string $type, string $code, ?string $param = null, array $extra = [], array $headers = []): HttpResponse
    {
        $error = ['type' => $type, 'code' => $code, 'message' => 'Error message for ' . $code, 'request_id' => 'req_err000000000000001'];
        if ($param !== null) {
            $error['param'] = $param;
        }

        return self::json($status, ['error' => $error + $extra], $headers);
    }

    /**
     * @return array<string, mixed>
     */
    protected static function signatureRequest(string $id = 'sr_aaaaaaaaaaaaaaaaaaaa', string $status = 'sent'): array
    {
        return [
            'id' => $id,
            'object' => 'signature_request',
            'livemode' => false,
            'status' => $status,
            'title' => 'Service agreement',
            'legal_level' => 'ses',
            'format' => 'pades-lt',
            'source' => ['type' => 'template', 'template_id' => 'tpl_12'],
            'signers' => [[
                'id' => 'sgr_bbbbbbbbbbbbbbbbbbbb',
                'object' => 'signer',
                'name' => 'Noha',
                'email' => 'noha@example.com',
                'phone' => null,
                'type' => 'individual',
                'method' => 'draw',
                'require_identity' => false,
                'order' => 1,
                'status' => 'sent',
                'signing_url' => 'https://wthaiq.com/sign.php?token=abc',
                'viewed_at' => null,
                'signed_at' => null,
                'fields' => ['amount' => '100'],
            ]],
            'ordered' => false,
            'require_identity' => false,
            'reference' => null,
            'cc' => [],
            'reminders' => ['enabled' => false],
            'metadata' => new \stdClass(),
            'expires_at' => 1791958089,
            'completed_at' => null,
            'download_url' => null,
            'created_at' => 1791353289,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected static function decodeBody(HttpRequest $request): array
    {
        $decoded = json_decode((string) $request->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
