<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * Completion certificate and audit trail of a signature request.
 *
 * @property-read string             $object            `certificate`
 * @property-read string             $signature_request `sr_…`
 * @property-read string             $status
 * @property-read string|null        $reference
 * @property-read string             $legal_level
 * @property-read string             $format
 * @property-read int|null           $completed_at
 * @property-read list<WthaiqObject> $signers           `name`, `email`, `phone`, `status`, `signed_at`
 * @property-read list<WthaiqObject> $audit_trail       `event`, `actor`, `ip`, `at`
 * @property-read string|null        $verify_url
 * @property-read bool|null          $test              Test mode only
 * @property-read string|null        $legal_effect      `none` in test mode
 * @property-read string|null        $watermark         Test mode only
 */
class Certificate extends WthaiqObject
{
    public const OBJECT_NAME = 'certificate';
}
