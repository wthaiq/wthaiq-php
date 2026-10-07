<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * A contract template. Placeholders `{{key}}` in its content are filled from `fields`.
 *
 * @property-read string             $id         `tpl_<number>`
 * @property-read string             $object     `template`
 * @property-read string             $name
 * @property-read string|null        $category
 * @property-read list<string>       $languages
 * @property-read list<WthaiqObject> $fields     Each has `key`, `label`, `type`, `required`
 * @property-read int|null           $created_at
 * @property-read string|null        $content    Only with `include=content` on your own templates
 */
class Template extends WthaiqObject
{
    public const OBJECT_NAME = 'template';
}
