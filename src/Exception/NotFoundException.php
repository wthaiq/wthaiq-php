<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * HTTP 404: the object does not exist, belongs to another account, or belongs to the
 * other mode (a test-mode object requested with a live key, or the reverse).
 */
class NotFoundException extends InvalidRequestException
{
}
