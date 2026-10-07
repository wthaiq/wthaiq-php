<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * One delivery attempt record of an event to an endpoint.
 *
 * @property-read string   $object        `webhook_delivery`
 * @property-read string   $event         `evt_…`
 * @property-read string   $event_type
 * @property-read string   $status        `pending`, `sending`, `succeeded` or `failed`
 * @property-read int      $attempts
 * @property-read int|null $response_code
 * @property-read int|null $created_at
 * @property-read int|null $updated_at
 */
class WebhookDelivery extends WthaiqObject
{
    public const OBJECT_NAME = 'webhook_delivery';
}
