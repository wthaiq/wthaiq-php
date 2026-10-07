<?php

declare(strict_types=1);

/*
 * Registers a webhook endpoint, prints its secret, lists endpoints and deletes the new one.
 *
 *   php examples/manage-webhook-endpoints.php https://your-server.example/hooks/wthaiq
 */

use Wthaiq\EventType;

/** @var Wthaiq\Client $wthaiq */
$wthaiq = require __DIR__ . '/bootstrap.php';

$url = $argv[1] ?? null;
if (!is_string($url)) {
    fwrite(STDERR, "usage: php examples/manage-webhook-endpoints.php <https-url>\n");
    exit(1);
}

$endpoint = $wthaiq->webhookEndpoints->create([
    'url' => $url,
    'enabled_events' => [EventType::SIGNATURE_REQUEST_COMPLETED, EventType::SIGNER_SIGNED],
]);
printf("Created %s; store this secret now: %s\n", $endpoint->id, $endpoint->secret);

foreach ($wthaiq->webhookEndpoints->list() as $item) {
    printf("  %s  %-8s  %s\n", $item->id, $item->status, $item->url);
}

$wthaiq->webhookEndpoints->delete($endpoint->id);
printf("Deleted %s\n", $endpoint->id);
