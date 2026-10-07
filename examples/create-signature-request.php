<?php

declare(strict_types=1);

/*
 * Creates a signature request from a template and prints the signing link.
 *
 *   php examples/create-signature-request.php <template_id> <signer_email> [--draft]
 *
 * In test mode the signer email must be your own account email.
 */

use Wthaiq\Exception\ApiException;

/** @var Wthaiq\Client $wthaiq */
$wthaiq = require __DIR__ . '/bootstrap.php';

[$script, $templateId, $email] = $argv + [null, null, null];
if (!is_string($templateId) || !is_string($email)) {
    fwrite(STDERR, "usage: php examples/create-signature-request.php <template_id> <signer_email> [--draft]\n");
    exit(1);
}

try {
    $request = $wthaiq->signatureRequests->create([
        'title' => 'Example agreement',
        'source' => ['type' => 'template', 'template_id' => $templateId],
        'signers' => [['name' => 'Example Signer', 'email' => $email]],
        'metadata' => ['created_by' => 'examples/create-signature-request.php'],
        'send' => !in_array('--draft', $argv, true),
    ]);
} catch (ApiException $e) {
    fwrite(STDERR, sprintf("Error %d %s: %s (request %s)\n", $e->getHttpStatus(), $e->getErrorCode(), $e->getMessage(), $e->getRequestId()));
    exit(1);
}

printf("Created %s (%s, %s mode)\n", $request->id, $request->status, $request->livemode ? 'live' : 'test');
foreach ($request->signers as $signer) {
    printf("  %s <%s>: %s\n", $signer->name, $signer->email, $signer->signing_url ?? '(link available once sent)');
}
