<?php

declare(strict_types=1);

/*
 * Shared setup for the examples. Run any example from the repository root, for example:
 *
 *   WTHAIQ_API_KEY=sk_test_... php examples/create-signature-request.php tpl_123 you@example.com
 *
 * WTHAIQ_API_BASE overrides the base URL (default https://wthaiq.com/api/v1).
 */

require __DIR__ . '/../vendor/autoload.php';

$apiKey = getenv('WTHAIQ_API_KEY');
if (!is_string($apiKey) || $apiKey === '') {
    fwrite(STDERR, "Set WTHAIQ_API_KEY to a test key (sk_test_...) from https://developers.wthaiq.com\n");
    exit(1);
}

$options = [];
$base = getenv('WTHAIQ_API_BASE');
if (is_string($base) && $base !== '') {
    $options['base_url'] = $base;
}

return new Wthaiq\Client($apiKey, $options);
