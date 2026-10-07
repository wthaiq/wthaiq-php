<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * A signature request: one document sent to one or more signers.
 *
 * @property-read string       $id               `sr_…`
 * @property-read string       $object           `signature_request`
 * @property-read bool         $livemode
 * @property-read string       $status           One of the STATUS_* constants
 * @property-read string       $title
 * @property-read string       $legal_level      `ses`, `aes` or `qes`
 * @property-read string       $format           `pades-b`, `pades-t`, `pades-lt` or `pades-lta`
 * @property-read WthaiqObject $source           `type` with `template_id` or `document_id`
 * @property-read list<Signer> $signers
 * @property-read bool         $ordered
 * @property-read bool         $require_identity
 * @property-read string|null  $reference        Verification reference, once completed
 * @property-read list<WthaiqObject> $cc
 * @property-read WthaiqObject $reminders
 * @property-read WthaiqObject $metadata
 * @property-read int|null     $expires_at
 * @property-read int|null     $completed_at
 * @property-read string|null  $download_url
 * @property-read int          $created_at
 * @property-read string|null  $workflow_id      Present for requests created through sessions
 * @property-read int|null     $workflow_version
 * @property-read string|null  $vendor_data
 * @property-read string|null  $channel          `email`, `whatsapp` or `link`
 * @property-read string|null  $return_url
 */
class SignatureRequest extends WthaiqObject
{
    public const OBJECT_NAME = 'signature_request';

    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_PARTIALLY_SIGNED = 'partially_signed';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_DECLINED = 'declined';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_CANCELED = 'canceled';

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }

    /** True once the request can no longer change (completed, declined, expired or canceled). */
    public function isFinal(): bool
    {
        return \in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_DECLINED, self::STATUS_EXPIRED, self::STATUS_CANCELED], true);
    }
}
