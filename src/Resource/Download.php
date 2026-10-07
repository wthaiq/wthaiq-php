<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * Where to get the final signed document of a completed request.
 *
 * @property-read string      $object             `download`
 * @property-read string      $signature_request  `sr_…`
 * @property-read string|null $pdf_url            Null when `pdf_status` is `unavailable`
 * @property-read string      $pdf_status         `ready`, `pending` or `unavailable`
 * @property-read string|null $pdf_sha256
 * @property-read int|null    $pdf_bytes
 * @property-read int|null    $pdf_generated_at
 * @property-read string|null $final_document_url
 * @property-read string|null $reference
 * @property-read string      $format
 * @property-read string      $legal_level
 * @property-read bool|null   $test
 * @property-read string|null $legal_effect
 * @property-read string|null $watermark
 */
class Download extends WthaiqObject
{
    public const OBJECT_NAME = 'download';

    public function isPdfReady(): bool
    {
        return $this->pdf_status === 'ready';
    }
}
