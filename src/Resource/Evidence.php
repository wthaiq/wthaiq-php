<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * The sealed evidence record of a completed live request (`wthaiq-evidence-v1`), or the
 * unsealed record of a test request (`wthaiq-evidence-test-v1`, `seal` is null).
 *
 * To verify a live record independently, check `seal.signature` (base64 Ed25519) over the
 * exact bytes of `record_raw` with `seal.public_key`, and the RFC 3161 token in
 * `seal.timestamp_token` whose message imprint is sha256(record_raw).
 *
 * @property-read string            $object                `evidence`
 * @property-read string            $signature_request     `sr_…`
 * @property-read string            $format
 * @property-read string            $issued_at             ISO-8601 date-time
 * @property-read string|null       $verify_ref
 * @property-read WthaiqObject      $record
 * @property-read string|null       $record_raw            The exact sealed JSON string
 * @property-read WthaiqObject|null $seal                  `signature_alg`, `signature`, `public_key`, `key_id`, `trust_url`, `timestamp_token`, `timestamp_gentime`
 * @property-read WthaiqObject|null $platform_verification `signature_ok`, `timestamp_ok`, `level`, `independent_layers`, `checked_at`
 * @property-read string|null       $how_to_verify
 * @property-read bool|null         $test
 * @property-read string|null       $legal_effect
 */
class Evidence extends WthaiqObject
{
    public const OBJECT_NAME = 'evidence';

    public function isSealed(): bool
    {
        return $this->seal instanceof WthaiqObject;
    }
}
