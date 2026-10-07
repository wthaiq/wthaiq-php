<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * Wallet balance and plan (`GET /balance`).
 *
 * @property-read string       $object      `balance`
 * @property-read bool         $livemode
 * @property-read WthaiqObject $balance     `amount`, `amount_minor`, `currency`
 * @property-read WthaiqObject $month_spent `amount`, `amount_minor`, `currency`
 * @property-read WthaiqObject $plan
 */
class Balance extends WthaiqObject
{
    public const OBJECT_NAME = 'balance';
}
