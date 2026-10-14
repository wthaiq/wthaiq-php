<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * The comparison between the signer name sent in the request and the name on the signer's
 * identity document (`identity.name_match` on a signer, `name_match` on an identity verification,
 * and `identity_match` inside the `signer.identity_match_*` events).
 *
 * @property-read string   $state         One of the STATE_* constants
 * @property-read string   $verdict       One of the VERDICT_* constants
 * @property-read string   $expected_name The signer name sent in the request
 * @property-read string   $document_name The name read from the identity document
 * @property-read string   $document_type
 * @property-read string   $country       Issuing country of the document
 * @property-read string   $id_last4      Last four characters of the document number
 * @property-read string   $decided_by    Who decided (empty until a decision), e.g. `API`
 * @property-read int|null $decided_at
 * @property-read int      $deadline      Unix time after which an undecided hold expires
 */
class NameMatch extends WthaiqObject
{
    /** No comparison was possible. */
    public const STATE_NOT_COMPARED = 'not_compared';
    /** Same or near-identical name: nothing to decide. */
    public const STATE_AUTO_OK = 'auto_ok';
    /** Different name: signing waits for a decision (`signers->decideIdentityMatch()`). */
    public const STATE_AWAITING = 'awaiting';
    /** Different name, logged without holding the signer (account setting). */
    public const STATE_RECORDED = 'recorded';
    public const STATE_APPROVED = 'approved';
    public const STATE_REJECTED = 'rejected';
    /** Not decided before the deadline. */
    public const STATE_EXPIRED = 'expired';

    public const VERDICT_NOT_COMPARABLE = 'na';
    public const VERDICT_MATCH = 'match';
    /** One part of the name differs. */
    public const VERDICT_MINOR = 'minor';
    public const VERDICT_MAJOR = 'major';

    /** True while signing is held for a decision. */
    public function isAwaitingDecision(): bool
    {
        return $this->offsetGet('state') === self::STATE_AWAITING;
    }
}
