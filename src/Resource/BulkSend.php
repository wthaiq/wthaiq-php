<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * Result of a bulk send: one signature request per recipient.
 *
 * @property-read string             $object   `bulk_send`
 * @property-read int                $created
 * @property-read int                $failed
 * @property-read list<WthaiqObject> $requests Each has `signature_request`, `email`, `phone`, `status`
 * @property-read list<WthaiqObject> $errors   Each has `email`, `phone`, `error`, `code`
 */
class BulkSend extends WthaiqObject
{
    public const OBJECT_NAME = 'bulk_send';
}
