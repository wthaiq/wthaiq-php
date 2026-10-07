<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * The request was rejected as invalid (HTTP 400, 405, 409 and other unmapped 4xx).
 */
class InvalidRequestException extends ApiException
{
}
