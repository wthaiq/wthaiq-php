<?php

declare(strict_types=1);

namespace Wthaiq\Service;

use Wthaiq\RequestOptions;
use Wthaiq\Resource\IdentityVerification;

final class IdentityVerificationService extends AbstractService
{
    /**
     * Retrieves an identity verification (`idv_…`, see `signer.identity.verification_id`).
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function retrieve(string $id, array|RequestOptions|null $options = null): IdentityVerification
    {
        return $this->requestObject(IdentityVerification::class, 'GET', self::path('/identity_verifications/%s', $id), [], $options);
    }
}
