<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * An uploaded PDF.
 *
 * @property-read string             $id         `doc_…`
 * @property-read string             $object     `document`
 * @property-read string             $kind       `pdf` or `contract`
 * @property-read string|null        $filename
 * @property-read int|null           $pages
 * @property-read int|null           $bytes
 * @property-read string|null        $sha256
 * @property-read list<WthaiqObject> $fields     The stored field map
 * @property-read int                $created_at
 */
class Document extends WthaiqObject
{
    public const OBJECT_NAME = 'document';
}
