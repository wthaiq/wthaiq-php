<?php

declare(strict_types=1);

namespace Wthaiq;

use Wthaiq\Exception\ApiConnectionException;
use Wthaiq\Exception\ApiException;
use Wthaiq\Exception\InvalidArgumentException;
use Wthaiq\HttpClient\HttpClientInterface;
use Wthaiq\HttpClient\HttpRequest;
use Wthaiq\HttpClient\HttpResponse;
use Wthaiq\Util\Uuid;

/**
 * Builds authenticated requests, applies retries and idempotency, and maps errors.
 *
 * @internal Use {@see Client} and its services.
 */
final class ApiRequestor
{
    public function __construct(
        private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly HttpClientInterface $httpClient,
        private readonly float $timeout,
        private readonly float $connectTimeout,
        private readonly int $maxRetries,
        private readonly string $apiVersion,
        private readonly string $userAgent,
        private readonly RetryPolicy $retryPolicy,
    ) {
    }

    /**
     * Sends a JSON request. For GET and DELETE `$params` become the query string; for POST they
     * are encoded as the JSON body.
     *
     * @param array<string, mixed> $params
     *
     * @throws ApiException
     * @throws ApiConnectionException
     */
    public function request(string $method, string $path, array $params, RequestOptions $options, string $accept = 'application/json'): HttpResponse
    {
        $method = strtoupper($method);
        $url = $this->baseUrl . $path;
        $body = null;
        $headers = ['Accept' => $accept];
        if ($method === 'POST') {
            $body = self::encodeJson($params);
            $headers['Content-Type'] = 'application/json';
        } elseif ($params !== []) {
            $url .= (str_contains($url, '?') ? '&' : '?') . self::encodeQuery($params);
        }

        return $this->send($method, $url, $headers, $body, $options);
    }

    /**
     * Sends a request with a pre-encoded body (used for multipart uploads).
     *
     * @throws ApiException
     * @throws ApiConnectionException
     */
    public function requestRaw(string $method, string $path, string $body, string $contentType, RequestOptions $options, string $accept = 'application/json'): HttpResponse
    {
        return $this->send(strtoupper($method), $this->baseUrl . $path, ['Accept' => $accept, 'Content-Type' => $contentType], $body, $options);
    }

    /**
     * @param array<string, string> $headers
     */
    private function send(string $method, string $url, array $headers, ?string $body, RequestOptions $options): HttpResponse
    {
        $apiKey = $options->apiKey ?? $this->apiKey;
        $headers = [
            'Authorization' => 'Bearer ' . $apiKey,
            'User-Agent' => $this->userAgent,
            'Wthaiq-Version' => $this->apiVersion,
        ] + $headers;
        if ($method === 'POST') {
            $headers['Idempotency-Key'] = $options->idempotencyKey ?? Uuid::v4();
        }
        foreach ($options->headers as $name => $value) {
            if (strcasecmp($name, 'Authorization') === 0) {
                throw new InvalidArgumentException('Use the api_key option instead of an Authorization header.');
            }
            $headers[$name] = $value;
        }

        $request = new HttpRequest($method, $url, $headers, $body, $options->timeout ?? $this->timeout, $this->connectTimeout);
        $maxRetries = $options->maxRetries ?? $this->maxRetries;

        for ($attempt = 0; ; ++$attempt) {
            try {
                $response = $this->httpClient->sendRequest($request);
            } catch (ApiConnectionException $e) {
                if ($attempt < $maxRetries && $this->retryPolicy->shouldRetry(null, null)) {
                    $this->retryPolicy->sleep($this->retryPolicy->delay($attempt, null));
                    continue;
                }
                throw $e;
            }

            if ($response->isSuccessful()) {
                return $response;
            }

            $exception = ErrorFactory::fromResponse($response);
            if ($attempt < $maxRetries && $this->retryPolicy->shouldRetry($response, $exception->getErrorCode())) {
                $this->retryPolicy->sleep($this->retryPolicy->delay($attempt, $response));
                continue;
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function encodeJson(array $params): string
    {
        if ($params === []) {
            return '{}';
        }

        try {
            return json_encode($params, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw new InvalidArgumentException('The request parameters cannot be encoded as JSON: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $params
     */
    public static function encodeQuery(array $params): string
    {
        $clean = [];
        foreach ($params as $k => $v) {
            if ($v === null) {
                continue;
            }
            if (\is_bool($v)) {
                $v = $v ? 'true' : 'false';
            }
            $clean[$k] = $v;
        }

        return http_build_query($clean, '', '&', PHP_QUERY_RFC3986);
    }
}
