<?php

declare(strict_types=1);

namespace Wthaiq\Exception;

/**
 * HTTP 5xx. Notable codes: `delivery_failed` (502: no signer could be reached, the charge
 * was reversed and the request stays a draft) and `feature_unavailable` (503).
 */
class ServerException extends ApiException
{
}
