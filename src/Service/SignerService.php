<?php

declare(strict_types=1);

namespace Wthaiq\Service;

use Wthaiq\RequestOptions;
use Wthaiq\Resource\Signer;
use Wthaiq\Resource\SigningSession;

final class SignerService extends AbstractService
{
    /**
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function retrieve(string $id, array|RequestOptions|null $options = null): Signer
    {
        return $this->requestObject(Signer::class, 'GET', self::path('/signers/%s', $id), [], $options);
    }

    /**
     * Creates a 30-minute signing link for embedding in an iframe (`mode => 'iframe'`, the default)
     * or for a redirect flow (`mode => 'redirect'` with `success_url` and optional `cancel_url`).
     *
     * @param array{mode?: 'iframe'|'redirect', return_url?: string, success_url?: string, cancel_url?: string}|array<string, mixed> $params
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function createSigningSession(string $id, array $params = [], array|RequestOptions|null $options = null): SigningSession
    {
        return $this->requestObject(SigningSession::class, 'POST', self::path('/signers/%s/signing_session', $id), $params, $options);
    }

    /**
     * Decides a name difference held for review (`identity->name_match->state === 'awaiting'`,
     * announced by the `signer.identity_match_hold` event): `approve` lets the signer finish signing,
     * `reject` stops this signer. Secret keys only.
     *
     * Throws {@see \Wthaiq\Exception\ConflictException} (`identity_match_not_pending`) when nothing
     * awaits a decision, and {@see \Wthaiq\Exception\NotFoundException} when the signer has no name
     * difference to decide.
     *
     * @param array{decision: 'approve'|'reject', note?: string}|array<string, mixed> $params
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function decideIdentityMatch(string $id, array $params, array|RequestOptions|null $options = null): Signer
    {
        return $this->requestObject(Signer::class, 'POST', self::path('/signers/%s/identity_match', $id), $params, $options);
    }
}
