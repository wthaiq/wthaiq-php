<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * Raised before any network call when the library is used with invalid arguments,
 * for example an API key with an unknown prefix or an unknown request option.
 */
class InvalidArgumentException extends \InvalidArgumentException implements ExceptionInterface
{
}
