<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * Wallet, plan, pricing and request counters (`GET /usage`).
 *
 * Money values are objects with `amount` (decimal), `amount_minor` (integer) and `currency` (`EGP` or `USD`).
 *
 * @property-read string       $object             `usage`
 * @property-read bool         $livemode
 * @property-read WthaiqObject $balance
 * @property-read WthaiqObject $month_spent
 * @property-read WthaiqObject $plan               `id`, `name`, `paid`, `period_start`, `period_end`, `sends_quota`, `sends_used`, `sends_left`, `auto_renew`
 * @property-read WthaiqObject $pricing            `overage_per_request`, `identity_per_signer`, `min_topup`, `currency`
 * @property-read WthaiqObject $signature_requests `total`, `last_30_days`, `completed`
 */
class Usage extends WthaiqObject
{
    public const OBJECT_NAME = 'usage';
}
