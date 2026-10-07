<?php

declare(strict_types=1);

/*
 * Downloads the final PDF and evidence record of a completed request.
 *
 *   php examples/download-signed-pdf.php <signature_request_id> [output.pdf]
 */

use Wthaiq\Exception\ConflictException;
use Wthaiq\Exception\NotFoundException;

/** @var Wthaiq\Client $wthaiq */
$wthaiq = require __DIR__ . '/bootstrap.php';

$id = $argv[1] ?? null;
if (!is_string($id)) {
    fwrite(STDERR, "usage: php examples/download-signed-pdf.php <signature_request_id> [output.pdf]\n");
    exit(1);
}
$output = $argv[2] ?? $id . '.pdf';

try {
    $request = $wthaiq->signatureRequests->retrieve($id);
} catch (NotFoundException $e) {
    fwrite(STDERR, "No signature request $id in this mode (" . $e->getRequestId() . ')
');
    exit(1);
}
if (!$request->isCompleted()) {
    printf("%s is %s; the PDF exists once every signer has signed.\n", $id, $request->status);
    exit(0);
}

try {
    $bytes = $wthaiq->signatureRequests->downloadPdfTo($id, $output);
    printf("Saved %s (%d bytes, reference %s)\n", $output, $bytes, $request->reference);
} catch (ConflictException $e) {
    printf("The PDF is not ready yet (%s). Try again shortly.\n", $e->getErrorCode());
}

$evidence = $wthaiq->signatureRequests->evidence($id);
printf("Evidence format %s, sealed: %s\n", $evidence->format, $evidence->isSealed() ? 'yes' : 'no');
