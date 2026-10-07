<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * The API root (`GET /`): account, plan and pricing summary for the key in use.
 *
 * @property-read string       $object   `api_root`
 * @property-read string       $name
 * @property-read string       $version  API version, e.g. `2026-07-01`
 * @property-read bool         $livemode
 * @property-read string       $base_url
 * @property-read WthaiqObject $account  `id` (`acct_…`), `name`, `email`
 * @property-read WthaiqObject $plan     `id` (`ap_trial`, `ap_dev`, `ap_biz`, `ap_ent`, `ap_custom`), `paid`, `sends_left`
 * @property-read WthaiqObject $pricing  `overage_per_request`, `identity_per_signer`, `currency`
 * @property-read string       $docs
 */
class Account extends WthaiqObject
{
    public const OBJECT_NAME = 'api_root';
}
