<?php

declare(strict_types=1);

namespace Wthaiq\HttpClient;

/**
 * A fully built HTTP request handed to a transport.
 */
final class HttpRequest
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $url,
        public readonly array $headers = [],
        public readonly ?string $body = null,
        public readonly float $timeout = 60.0,
        public readonly float $connectTimeout = 10.0,
    ) {
    }

    public function getHeader(string $name): ?string
    {
        foreach ($this->headers as $k => $v) {
            if (strcasecmp($k, $name) === 0) {
                return $v;
            }
        }

        return null;
    }
}
