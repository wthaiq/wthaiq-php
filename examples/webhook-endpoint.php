<?php

declare(strict_types=1);

/*
 * A minimal webhook receiver. Serve it with:
 *
 *   WTHAIQ_WEBHOOK_SECRET=whsec_... php -S 127.0.0.1:8000 examples/webhook-endpoint.php
 */

use Wthaiq\EventType;
use Wthaiq\Exception\SignatureVerificationException;
use Wthaiq\Webhook;

require __DIR__ . '/../vendor/autoload.php';

$payload = (string) file_get_contents('php://input');
$header = $_SERVER['HTTP_WTHAIQ_SIGNATURE'] ?? '';
$secret = (string) getenv('WTHAIQ_WEBHOOK_SECRET');

try {
    $event = Webhook::constructEvent($payload, $header, $secret);
} catch (SignatureVerificationException $e) {
    http_response_code(400);
    echo 'invalid signature';
    exit;
}

$object = $event->getDataObject();
switch ($event->type) {
    case EventType::SIGNATURE_REQUEST_COMPLETED:
        error_log(sprintf('Request %s completed, reference %s', $object?->id, $object?->reference));
        break;
    case EventType::SIGNER_SIGNED:
        error_log(sprintf('Signer %s signed request %s', $object?->name, $object?->signature_request));
        break;
    default:
        error_log('Received ' . $event->type);
}

http_response_code(200);
header('Content-Type: application/json');
echo '{"received":true}';
