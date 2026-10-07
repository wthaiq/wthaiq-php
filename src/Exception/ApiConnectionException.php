<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * The request never produced an HTTP response: DNS failure, refused connection,
 * TLS error or timeout. The library has already retried it automatically.
 */
class ApiConnectionException extends WthaiqException
{
    public function __construct(string $message, private readonly bool $timeout = false, ?\Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }

    public function isTimeout(): bool
    {
        return $this->timeout;
    }
}
