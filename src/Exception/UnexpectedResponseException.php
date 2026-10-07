<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * The API answered with a body the library could not understand, for example HTML
 * from a proxy instead of JSON.
 */
class UnexpectedResponseException extends WthaiqException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus = 0,
        private readonly string $httpBody = '',
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, 0, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    public function getHttpBody(): string
    {
        return $this->httpBody;
    }
}
