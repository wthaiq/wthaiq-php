<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * An identity verification of a signer.
 *
 * @property-read string      $id               `idv_<number>`
 * @property-read string      $object           `identity_verification`
 * @property-read string      $provider         Always `external`
 * @property-read string|null $signer           `sgr_…`
 * @property-read string      $status           `pending`, `approved`, `in_review`, `resubmission_requested`, `declined` or `expired`
 * @property-read string      $level            `aes`
 * @property-read string|null $verification_url
 * @property-read int|null    $created_at
 */
class IdentityVerification extends WthaiqObject
{
    public const OBJECT_NAME = 'identity_verification';
}
