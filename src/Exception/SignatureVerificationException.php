<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * A webhook payload failed verification: missing or malformed `Wthaiq-Signature`
 * header, timestamp outside the tolerance, signature mismatch or invalid JSON.
 * Respond with HTTP 400 and do not process the payload.
 */
class SignatureVerificationException extends WthaiqException
{
    public function __construct(string $message, private readonly ?string $sigHeader = null, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function getSigHeader(): ?string
    {
        return $this->sigHeader;
    }
}
