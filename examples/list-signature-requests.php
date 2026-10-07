<?php

declare(strict_types=1);

/*
 * Lists signature requests, following every page.
 *
 *   php examples/list-signature-requests.php [status]
 */

/** @var Wthaiq\Client $wthaiq */
$wthaiq = require __DIR__ . '/bootstrap.php';

$params = ['limit' => 50];
if (isset($argv[1])) {
    $params['status'] = $argv[1];
}

$count = 0;
foreach ($wthaiq->signatureRequests->all($params) as $request) {
    printf("%s  %-17s  %s\n", $request->id, $request->status, $request->title);
    ++$count;
}
printf("%d request(s)\n", $count);
