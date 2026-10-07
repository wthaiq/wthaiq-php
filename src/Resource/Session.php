<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * A signature request created from a workflow (`POST /sessions`). Its id is the request id,
 * so every signature request method also works with it.
 *
 * @property-read string             $id               `sr_…`
 * @property-read string             $object           `session`
 * @property-read string             $status
 * @property-read string|null        $workflow_id      `wf_…` or `default`
 * @property-read int|null           $workflow_version
 * @property-read string|null        $vendor_data
 * @property-read string|null        $channel
 * @property-read list<WthaiqObject> $signers          Each has `id`, `name`, `email`, `phone`, `status`, `sign_url`
 * @property-read string|null        $sign_url         Only when there is exactly one signer
 * @property-read int                $created_at
 * @property-read int|null           $expires_at
 * @property-read bool               $livemode
 * @property-read WthaiqObject       $cost             `amount`, `currency`, `source` (`plan`, `overage` or null)
 * @property-read string|null        $return_url
 */
class Session extends WthaiqObject
{
    public const OBJECT_NAME = 'session';
}
