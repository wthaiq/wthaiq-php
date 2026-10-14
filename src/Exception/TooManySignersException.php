<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * HTTP 400 `too_many_signers`: a live signature request has more signers than allowed.
 *
 * Every signer of a live request counts as one send from the plan quota. Split the signers
 * over several requests of at most {@see getMaxSigners()} signers each.
 */
class TooManySignersException extends ValidationException
{
    /** The documented limit, used when the error body does not carry `max_signers`. */
    public const MAX_SIGNERS = 5;

    /** Most signers allowed in one request (`max_signers` from the error body). */
    public function getMaxSigners(): int
    {
        $v = $this->getError()['max_signers'] ?? null;

        return \is_int($v) && $v > 0 ? $v : self::MAX_SIGNERS;
    }
}
