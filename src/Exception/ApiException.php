<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * The API returned an HTTP error. Carries everything the API said about it.
 *
 * Branch on {@see getErrorCode()} (stable machine codes such as `parameter_missing`
 * or `insufficient_balance`). The message is human-readable text and may change: Arabic by
 * default, English with the client option `language => 'en'`. Codes never change with the language.
 */
class ApiException extends WthaiqException
{
    /**
     * @param array<string, mixed>        $error   The decoded `error` object (empty when the body had none)
     * @param array<string, list<string>> $headers Response headers keyed by lower-case name
     */
    public function __construct(
        string $message,
        private readonly int $httpStatus,
        private readonly ?string $errorType = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $param = null,
        private readonly ?string $requestId = null,
        private readonly array $error = [],
        private readonly string $httpBody = '',
        private readonly array $headers = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $previous);
    }

    public function getHttpStatus(): int
    {
        return $this->httpStatus;
    }

    /** API error type, e.g. `invalid_request_error` or `quota_error`. */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    /** API error code, e.g. `parameter_missing`, `plan_required`, `too_many_requests`. */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** The request field the error refers to, e.g. `signers[0].email`. */
    public function getParam(): ?string
    {
        return $this->param;
    }

    /** Value of `Wthaiq-Request-Id`. Include it when contacting support. */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * The decoded `error` object, including any extra fields.
     *
     * @return array<string, mixed>
     */
    public function getError(): array
    {
        return $this->error;
    }

    public function getHttpBody(): string
    {
        return $this->httpBody;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getHttpHeaders(): array
    {
        return $this->headers;
    }

    public function getHttpHeader(string $name): ?string
    {
        $values = $this->headers[strtolower($name)] ?? [];

        return $values[0] ?? null;
    }
}
