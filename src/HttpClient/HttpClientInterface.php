<?php

declare(strict_types=1);

namespace Wthaiq\HttpClient;

use Wthaiq\Exception\ApiConnectionException;

/**
 * The transport used by the client. Implement it to plug in your own HTTP stack;
 * the library ships {@see CurlClient} (default) and {@see Psr18Client}.
 *
 * Implementations must return every HTTP response (including 4xx and 5xx) and throw
 * {@see ApiConnectionException} only when no response was received.
 */
interface HttpClientInterface
{
    /**
     * @throws ApiConnectionException
     */
    public function sendRequest(HttpRequest $request): HttpResponse;
}
