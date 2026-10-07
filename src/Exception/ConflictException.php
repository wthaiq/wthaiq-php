<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * HTTP 409: the request conflicts with the current state. Codes include
 * `idempotency_key_reused`, `idempotency_in_progress`, `request_not_cancelable`,
 * `pdf_not_ready`, `pdf_unavailable` and `workflow_not_live`.
 */
class ConflictException extends InvalidRequestException
{
}
