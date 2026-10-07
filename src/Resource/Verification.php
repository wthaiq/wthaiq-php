<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * The result of looking up a verification reference. When the reference is unknown,
 * `found` is false and `status` is `not_found`.
 *
 * @property-read string             $object    `verification`
 * @property-read string             $reference
 * @property-read bool               $found
 * @property-read string             $status    `completed`, `in_progress` or `not_found`
 * @property-read string             $integrity `intact`, `modified` or `unknown`
 * @property-read string|null        $structure
 * @property-read WthaiqObject|null  $document  `title`, `sha256`, `hash_alg`, `format`, `legal_level`
 * @property-read int|null           $signed_at
 * @property-read list<WthaiqObject> $parties
 * @property-read list<WthaiqObject> $events
 * @property-read string|null        $qr
 * @property-read string|null        $verify_url Present in `verification.created` webhook events
 * @property-read string|null        $signature_request Present in `verification.created` webhook events
 */
class Verification extends WthaiqObject
{
    public const OBJECT_NAME = 'verification';

    public function isFound(): bool
    {
        return $this->found === true;
    }
}
