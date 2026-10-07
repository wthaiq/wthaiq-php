<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * A signer of a signature request.
 *
 * @property-read string            $id               `sgr_…`
 * @property-read string            $object           `signer`
 * @property-read string            $name
 * @property-read string|null       $email
 * @property-read string|null       $phone            International digits, e.g. `201012345678`
 * @property-read string            $type             `individual` or `company`
 * @property-read string            $method           `draw` or `token`
 * @property-read bool              $require_identity
 * @property-read int               $order
 * @property-read string            $status           One of the STATUS_* constants
 * @property-read string|null       $signing_url      Null while the request is a draft
 * @property-read int|null          $viewed_at
 * @property-read int|null          $signed_at
 * @property-read WthaiqObject      $fields
 * @property-read WthaiqObject|null $identity         `status`, `provider`, `level`, `verification_id`, `reason_code`, `updated_at`, `resubmit_steps`
 * @property-read string|null       $signature_request Only inside webhook events
 */
class Signer extends WthaiqObject
{
    public const OBJECT_NAME = 'signer';

    public const STATUS_PENDING = 'pending';
    public const STATUS_SENT = 'sent';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_OTP_VERIFIED = 'otp_verified';
    public const STATUS_IDENTITY_VERIFIED = 'identity_verified';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_DECLINED = 'declined';
}
