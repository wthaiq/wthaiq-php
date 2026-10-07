<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * HTTP 429.
 *
 * `too_many_requests` is transient: wait {@see getRetryAfter()} seconds. The other codes are
 * quotas that do not clear by retrying soon: `daily_cap_reached`, `reminder_limit_reached`,
 * `reminder_too_soon`, `test_daily_limit` and `test_email_limit`.
 */
class RateLimitException extends ApiException
{
    /** Seconds to wait, from the `Retry-After` header, or null when the API sent none. */
    public function getRetryAfter(): ?int
    {
        $v = $this->getHttpHeader('retry-after');
        if ($v === null || !ctype_digit(trim($v))) {
            return null;
        }

        return (int) trim($v);
    }

    /** True when waiting a few seconds is enough (the request rate limit). */
    public function isTransient(): bool
    {
        $code = $this->getErrorCode();

        return $code === null || $code === 'too_many_requests';
    }
}
