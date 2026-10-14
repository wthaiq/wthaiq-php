<?php

declare(strict_types=1);

namespace Wthaiq;

/**
 * Webhook event types sent by the platform.
 */
final class EventType
{
    public const SIGNATURE_REQUEST_CREATED = 'signature_request.created';
    public const SIGNATURE_REQUEST_SENT = 'signature_request.sent';
    public const SIGNATURE_REQUEST_VIEWED = 'signature_request.viewed';
    public const SIGNATURE_REQUEST_PARTIALLY_SIGNED = 'signature_request.partially_signed';
    public const SIGNATURE_REQUEST_COMPLETED = 'signature_request.completed';
    public const SIGNATURE_REQUEST_DECLINED = 'signature_request.declined';
    public const SIGNATURE_REQUEST_EXPIRED = 'signature_request.expired';
    public const SIGNATURE_REQUEST_CANCELED = 'signature_request.canceled';
    public const SIGNER_SENT = 'signer.sent';
    public const SIGNER_VIEWED = 'signer.viewed';
    public const SIGNER_OTP_VERIFIED = 'signer.otp_verified';
    public const SIGNER_IDENTITY_VERIFIED = 'signer.identity_verified';
    public const SIGNER_IDENTITY_IN_REVIEW = 'signer.identity_in_review';
    public const SIGNER_IDENTITY_RESUBMISSION_REQUESTED = 'signer.identity_resubmission_requested';
    public const SIGNER_IDENTITY_DECLINED = 'signer.identity_declined';
    public const SIGNER_IDENTITY_MATCH_HOLD = 'signer.identity_match_hold';
    public const SIGNER_IDENTITY_MATCH_DECIDED = 'signer.identity_match_decided';
    public const SIGNER_SIGNED = 'signer.signed';
    public const SIGNER_DECLINED = 'signer.declined';
    public const DOCUMENT_SEALED = 'document.sealed';
    public const VERIFICATION_CREATED = 'verification.created';

    /** Subscribe to every event type. */
    public const ALL = '*';

    /**
     * @return list<string>
     */
    public static function all(): array
    {
        $types = [];
        foreach ((new \ReflectionClass(self::class))->getConstants() as $name => $value) {
            if ($name !== 'ALL' && \is_string($value)) {
                $types[] = $value;
            }
        }

        return $types;
    }
}
