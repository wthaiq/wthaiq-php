<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * Marker implemented by every exception thrown by this library.
 *
 * Catch it to handle any SDK failure in one place.
 */
interface ExceptionInterface extends \Throwable
{
}
