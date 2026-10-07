<?php

declare(strict_types=1);

/*
 * Creates a session from the default workflow on the "link" channel (no message is sent), then an
 * embedded signing link for its signer.
 *
 *   php examples/embedded-signing.php <template_id> <signer_email>
 */

use Wthaiq\Exception\PermissionException;

/** @var Wthaiq\Client $wthaiq */
$wthaiq = require __DIR__ . '/bootstrap.php';

[$script, $templateId, $email] = $argv + [null, null, null];
if (!is_string($templateId) || !is_string($email)) {
    fwrite(STDERR, "usage: php examples/embedded-signing.php <template_id> <signer_email>\n");
    exit(1);
}

$session = $wthaiq->sessions->create([
    'workflow_id' => 'default',
    'template_id' => $templateId,
    'channel' => 'link',
    'vendor_data' => 'example-' . date('YmdHis'),
    'signers' => [['name' => 'Example Signer', 'email' => $email]],
]);
printf("Session %s, direct link: %s\n", $session->id, $session->sign_url);

try {
    $embed = $wthaiq->signers->createSigningSession((string) $session->signers[0]->id);
    printf("Embed this URL in an iframe (valid until %s):\n%s\n", date('c', $embed->expires_at), $embed->signing_url);
} catch (PermissionException $e) {
    printf("Embedded signing is not in your plan (%s): %s\n", $e->getFeature(), $e->getUpgradeUrl());
}
