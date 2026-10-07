<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * HTTP 402: live sending is not paid for.
 *
 * Codes: `plan_required` (no paid plan), `plan_expired` (the plan year ended) and
 * `insufficient_balance` (plan quota used up and the wallet cannot cover the cost).
 */
class PaymentRequiredException extends ApiException
{
    public const PLANS_URL = 'https://developers.wthaiq.com/plans';

    public function isPlanRequired(): bool
    {
        return $this->getErrorCode() === 'plan_required';
    }

    public function isPlanExpired(): bool
    {
        return $this->getErrorCode() === 'plan_expired';
    }

    public function isInsufficientBalance(): bool
    {
        return $this->getErrorCode() === 'insufficient_balance';
    }

    public function getUpgradeUrl(): string
    {
        $v = $this->getError()['upgrade_url'] ?? null;

        return is_string($v) && $v !== '' ? $v : self::PLANS_URL;
    }
}
