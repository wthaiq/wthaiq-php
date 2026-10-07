<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * Result of asking the platform to deliver an event again.
 *
 * @property-read string             $object     `event_redelivery`
 * @property-read string             $event      `evt_…`
 * @property-read list<WthaiqObject> $deliveries Each has `endpoint`, `status`, `response_code`, `error`
 */
class EventRedelivery extends WthaiqObject
{
    public const OBJECT_NAME = 'event_redelivery';
}
