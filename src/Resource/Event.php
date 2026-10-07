<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * A webhook event (also returned by `GET /events`).
 *
 * `data->object` is the affected object, typed by its own `object` field (for example a
 * {@see SignatureRequest} or a {@see Signer}). Entries of a signature request's audit trail
 * (`signatureRequests->listEvents()`) share this class: their `type` starts with `audit.` and
 * they carry `actor`, `ip` and `signature_request` instead of `data`.
 *
 * @property-read string            $id         `evt_…`
 * @property-read string            $object     `event`
 * @property-read string            $type       See {@see \Wthaiq\EventType}
 * @property-read int               $created_at
 * @property-read bool|null         $livemode
 * @property-read WthaiqObject      $data       `object`: the affected object (absent on audit entries)
 * @property-read string|null       $actor      Audit entries only
 * @property-read string|null       $ip         Audit entries only
 * @property-read string|null       $signature_request Audit entries only
 */
class Event extends WthaiqObject
{
    public const OBJECT_NAME = 'event';

    /** The object the event is about (`data.object`). */
    public function getDataObject(): ?WthaiqObject
    {
        $data = $this->offsetGet('data');
        $object = $data instanceof WthaiqObject ? $data['object'] : null;

        return $object instanceof WthaiqObject ? $object : null;
    }
}
