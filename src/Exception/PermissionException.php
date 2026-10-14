<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * HTTP 403: the key may not perform this action.
 *
 * Common codes: `plan_feature_unavailable` (see {@see getFeature()} and {@see getRequiredPlan()}),
 * `kyb_required` (live sending needs a verified company), `forbidden` (read-only key or
 * publishable-key restriction) and `ip_not_allowed`.
 */
class PermissionException extends ApiException
{
    public const PLANS_URL = 'https://developers.wthaiq.com/plans';
    public const VERIFY_URL = 'https://developers.wthaiq.com/verify';

    /** The capability that needs a higher plan, e.g. `bulk`, `embed` or `ordered`. */
    public function getFeature(): ?string
    {
        $v = $this->getError()['feature'] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    /** The lowest plan id that includes the feature, e.g. `ap_biz`. */
    public function getRequiredPlan(): ?string
    {
        $v = $this->getError()['required_plan'] ?? null;

        return is_string($v) && $v !== '' ? $v : null;
    }

    /**
     * Where to upgrade the plan (`upgrade_url`). For `kyb_required` it returns {@see getVerifyUrl()},
     * so that one method always points to the page that unblocks the call.
     */
    public function getUpgradeUrl(): ?string
    {
        $v = $this->getError()['upgrade_url'] ?? null;
        if (is_string($v) && $v !== '') {
            return $v;
        }
        if ($this->isKybRequired()) {
            return $this->getVerifyUrl();
        }

        return $this->isPlanFeatureUnavailable() ? self::PLANS_URL : null;
    }

    /** Where to verify the company (`verify_url`, with `kyb_required`), or null for other codes. */
    public function getVerifyUrl(): ?string
    {
        $v = $this->getError()['verify_url'] ?? null;
        if (is_string($v) && $v !== '') {
            return $v;
        }

        return $this->isKybRequired() ? self::VERIFY_URL : null;
    }

    public function isPlanFeatureUnavailable(): bool
    {
        return $this->getErrorCode() === 'plan_feature_unavailable';
    }

    public function isKybRequired(): bool
    {
        return $this->getErrorCode() === 'kyb_required';
    }
}
