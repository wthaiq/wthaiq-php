<?php

declare(strict_types=1);

namespace Wthaiq;

use Wthaiq\HttpClient\HttpResponse;

/**
 * Decides which failures are retried and how long to wait.
 *
 * Retried: connection errors and timeouts, `429 too_many_requests`, `409 idempotency_in_progress`,
 * `500`, `502` (except `delivery_failed`), `503` (except `feature_unavailable`) and `504`.
 * The wait honours `Retry-After` (capped) and otherwise uses exponential backoff with jitter.
 */
final class RetryPolicy
{
    private const NEVER_RETRY_CODES = ['delivery_failed', 'feature_unavailable'];

    /** @var \Closure(float): void */
    private readonly \Closure $sleeper;

    /** @var \Closure(): float */
    private readonly \Closure $random;

    /**
     * @param float                      $initialDelay  Seconds before the first retry (before jitter)
     * @param float                      $maxDelay      Upper bound of the exponential backoff, in seconds
     * @param float                      $maxRetryAfter Upper bound applied to a server `Retry-After`, in seconds
     * @param (\Closure(float): void)|null $sleeper     Replaces the real sleep (useful in tests)
     * @param (\Closure(): float)|null     $random      Returns a float in [0, 1) used for jitter
     */
    public function __construct(
        private readonly float $initialDelay = 0.5,
        private readonly float $maxDelay = 8.0,
        private readonly float $maxRetryAfter = 60.0,
        ?\Closure $sleeper = null,
        ?\Closure $random = null,
    ) {
        $this->sleeper = $sleeper ?? static function (float $seconds): void {
            if ($seconds > 0) {
                usleep((int) round($seconds * 1_000_000));
            }
        };
        $this->random = $random ?? static fn (): float => mt_rand() / (mt_getrandmax() + 1);
    }

    /**
     * @param HttpResponse|null $response  Null when the request failed without a response
     * @param string|null       $errorCode The API `error.code`, when there was one
     */
    public function shouldRetry(?HttpResponse $response, ?string $errorCode): bool
    {
        if ($response === null) {
            return true;
        }
        if ($errorCode !== null && \in_array($errorCode, self::NEVER_RETRY_CODES, true)) {
            return false;
        }

        return match ($response->status) {
            429 => $errorCode === null || $errorCode === 'too_many_requests',
            409 => $errorCode === 'idempotency_in_progress',
            500, 502, 503, 504 => true,
            default => false,
        };
    }

    /**
     * Seconds to wait before retry number `$attempt` (0 for the first retry).
     */
    public function delay(int $attempt, ?HttpResponse $response): float
    {
        $retryAfter = $response?->getHeader('retry-after');
        if ($retryAfter !== null && is_numeric(trim($retryAfter))) {
            return min($this->maxRetryAfter, max(0.0, (float) trim($retryAfter)));
        }
        $base = min($this->maxDelay, $this->initialDelay * (2 ** $attempt));

        return $base / 2 + ($base / 2) * ($this->random)();
    }

    public function sleep(float $seconds): void
    {
        ($this->sleeper)($seconds);
    }
}
