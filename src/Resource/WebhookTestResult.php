<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * Result of sending a synthetic test event to an endpoint.
 *
 * @property-read string $object        `webhook_test`
 * @property-read string $endpoint      `we_…`
 * @property-read int    $response_code 0 when the endpoint could not be reached
 * @property-read string $error
 * @property-read bool   $ok
 */
class WebhookTestResult extends WthaiqObject
{
    public const OBJECT_NAME = 'webhook_test';
}
