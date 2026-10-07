<?php

declare(strict_types=1);

namespace Wthaiq\HttpClient;

/**
 * An HTTP response as returned by a transport. Every object returned by the client
 * exposes the response that produced it through `getLastResponse()`.
 */
final class HttpResponse
{
    /** @var array<string, list<string>> */
    private readonly array $headers;

    /**
     * @param array<string, string|list<string>> $headers Header names are case-insensitive
     */
    public function __construct(
        public readonly int $status,
        array $headers = [],
        public readonly string $body = '',
    ) {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $key = strtolower((string) $name);
            foreach ((array) $value as $v) {
                $normalized[$key][] = (string) $v;
            }
        }
        $this->headers = $normalized;
    }

    /**
     * @return array<string, list<string>> Lower-case header names
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function getHeader(string $name): ?string
    {
        return $this->headers[strtolower($name)][0] ?? null;
    }

    /** The `Wthaiq-Request-Id` of the call, useful when contacting support. */
    public function getRequestId(): ?string
    {
        return $this->getHeader('wthaiq-request-id');
    }

    /** True when the API replayed a stored response for a repeated `Idempotency-Key`. */
    public function isIdempotentReplayed(): bool
    {
        return strtolower((string) $this->getHeader('idempotent-replayed')) === 'true';
    }

    public function isSuccessful(): bool
    {
        return $this->status >= 200 && $this->status < 300;
    }
}
