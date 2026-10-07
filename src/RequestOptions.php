<?php

declare(strict_types=1);

namespace Wthaiq;

use Wthaiq\Exception\InvalidArgumentException;

/**
 * Per-call options. Every service method accepts them as its last argument, either as an
 * instance or as an array with the keys below:
 *
 * - `idempotency_key` (string, POST only): reuse it to make a retried call safe. When omitted,
 *   the library generates one per call and reuses it across its own automatic retries.
 * - `timeout` (float seconds): total time limit for this call.
 * - `max_retries` (int): overrides the client setting for this call.
 * - `headers` (array<string, string>): extra request headers.
 * - `api_key` (string): use another key for this call only.
 */
final class RequestOptions
{
    private const KEYS = ['idempotency_key', 'timeout', 'max_retries', 'headers', 'api_key'];

    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly ?string $idempotencyKey = null,
        public readonly ?float $timeout = null,
        public readonly ?int $maxRetries = null,
        public readonly array $headers = [],
        public readonly ?string $apiKey = null,
    ) {
        if ($idempotencyKey !== null && ($idempotencyKey === '' || \strlen($idempotencyKey) > 200)) {
            throw new InvalidArgumentException('idempotency_key must be 1 to 200 characters long.');
        }
        if ($timeout !== null && $timeout <= 0) {
            throw new InvalidArgumentException('timeout must be greater than zero.');
        }
        if ($maxRetries !== null && $maxRetries < 0) {
            throw new InvalidArgumentException('max_retries cannot be negative.');
        }
    }

    /**
     * @param array<string, mixed>|self|null $options
     */
    public static function parse(array|self|null $options): self
    {
        if ($options instanceof self) {
            return $options;
        }
        if ($options === null || $options === []) {
            return new self();
        }
        $unknown = array_diff(array_keys($options), self::KEYS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf(
                'Unknown request option(s): %s. Allowed: %s.',
                implode(', ', $unknown),
                implode(', ', self::KEYS),
            ));
        }

        $headers = [];
        foreach ((array) ($options['headers'] ?? []) as $name => $value) {
            if (!\is_string($name) || !\is_scalar($value)) {
                throw new InvalidArgumentException('headers must be an array of name => value strings.');
            }
            $headers[$name] = (string) $value;
        }

        $key = $options['idempotency_key'] ?? null;
        $timeout = $options['timeout'] ?? null;
        $retries = $options['max_retries'] ?? null;
        $apiKey = $options['api_key'] ?? null;
        if ($key !== null && !\is_string($key)) {
            throw new InvalidArgumentException('idempotency_key must be a string.');
        }
        if ($timeout !== null && !\is_int($timeout) && !\is_float($timeout)) {
            throw new InvalidArgumentException('timeout must be a number of seconds.');
        }
        if ($retries !== null && !\is_int($retries)) {
            throw new InvalidArgumentException('max_retries must be an integer.');
        }
        if ($apiKey !== null && !\is_string($apiKey)) {
            throw new InvalidArgumentException('api_key must be a string.');
        }

        return new self($key, $timeout !== null ? (float) $timeout : null, $retries, $headers, $apiKey);
    }
}
