<?php

declare(strict_types=1);

namespace Wthaiq\HttpClient;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Wthaiq\Exception\ApiConnectionException;

/**
 * Adapter for any PSR-18 HTTP client (Guzzle, Symfony HttpClient, ...).
 *
 * Requires `psr/http-client` and `psr/http-factory` implementations in your project.
 * Timeouts are controlled by the PSR-18 client you pass, not by the Wthaiq client options.
 */
final class Psr18Client implements HttpClientInterface
{
    public function __construct(
        private readonly ClientInterface $client,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function sendRequest(HttpRequest $request): HttpResponse
    {
        $psrRequest = $this->requestFactory->createRequest($request->method, $request->url);
        foreach ($request->headers as $name => $value) {
            $psrRequest = $psrRequest->withHeader($name, $value);
        }
        if ($request->body !== null) {
            $psrRequest = $psrRequest->withBody($this->streamFactory->createStream($request->body));
        }

        try {
            $psrResponse = $this->client->sendRequest($psrRequest);
        } catch (ClientExceptionInterface $e) {
            throw new ApiConnectionException('Could not reach the Wthaiq API: ' . $e->getMessage(), false, $e);
        }

        $headers = [];
        foreach ($psrResponse->getHeaders() as $name => $values) {
            foreach ($values as $value) {
                $headers[(string) $name][] = $value;
            }
        }

        return new HttpResponse($psrResponse->getStatusCode(), $headers, (string) $psrResponse->getBody());
    }
}
