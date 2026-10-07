<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * A saved configuration for the embeddable signing console.
 *
 * @property-read string             $id            `flow_…`
 * @property-read string             $object        `flow`
 * @property-read string             $name
 * @property-read string|null        $template_id
 * @property-read string             $legal_level
 * @property-read bool               $reminders
 * @property-read bool               $lock_contract
 * @property-read list<mixed>        $cc
 * @property-read int|null           $created_at
 */
class Flow extends WthaiqObject
{
    public const OBJECT_NAME = 'flow';
}
