<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * HTTP 401: the API key is missing, malformed or revoked, or its account is inactive.
 */
class AuthenticationException extends ApiException
{
}
