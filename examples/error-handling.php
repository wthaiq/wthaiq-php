<?php

declare(strict_types=1);

/*
 * Shows how errors are reported: an invalid request and an unknown id.
 *
 *   php examples/error-handling.php
 */

use Wthaiq\Exception\ApiException;
use Wthaiq\Exception\NotFoundException;
use Wthaiq\Exception\ValidationException;

/** @var Wthaiq\Client $wthaiq */
$wthaiq = require __DIR__ . '/bootstrap.php';

try {
    $wthaiq->signatureRequests->create(['source' => ['type' => 'template', 'template_id' => 'tpl_1'], 'send' => false]);
} catch (ValidationException $e) {
    printf("Validation: code=%s param=%s request=%s\n", $e->getErrorCode(), $e->getParam(), $e->getRequestId());
}

try {
    $wthaiq->signatureRequests->retrieve('sr_doesnotexist');
} catch (NotFoundException $e) {
    printf("Not found: code=%s status=%d\n", $e->getErrorCode(), $e->getHttpStatus());
} catch (ApiException $e) {
    printf("API error %d: %s\n", $e->getHttpStatus(), $e->getMessage());
}
