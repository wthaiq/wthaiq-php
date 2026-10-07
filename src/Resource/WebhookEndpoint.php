<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * A URL that receives webhook events.
 *
 * @property-read string       $id             `we_…`
 * @property-read string       $object         `webhook_endpoint`
 * @property-read string       $url
 * @property-read list<string> $enabled_events Event types, or `['*']`
 * @property-read string       $status         `enabled` or `disabled`
 * @property-read bool         $livemode
 * @property-read int          $created_at
 * @property-read string|null  $secret         `whsec_…`, returned only when the endpoint is created
 * @property-read bool|null    $deleted        True in the response of a delete
 */
class WebhookEndpoint extends WthaiqObject
{
    public const OBJECT_NAME = 'webhook_endpoint';
}
