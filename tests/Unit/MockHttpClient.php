<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use Wthaiq\Exception\ApiConnectionException;
use Wthaiq\HttpClient\HttpClientInterface;
use Wthaiq\HttpClient\HttpRequest;
use Wthaiq\HttpClient\HttpResponse;

/**
 * Replays queued responses and records every request.
 */
final class MockHttpClient implements HttpClientInterface
{
    /** @var list<HttpRequest> */
    public array $requests = [];

    /** @var list<HttpResponse|ApiConnectionException|\Closure(HttpRequest): HttpResponse> */
    private array $queue;

    /**
     * @param list<HttpResponse|ApiConnectionException|\Closure(HttpRequest): HttpResponse> $queue
     */
    public function __construct(array $queue = [])
    {
        $this->queue = $queue;
    }

    public function push(HttpResponse|ApiConnectionException|\Closure $item): void
    {
        $this->queue[] = $item;
    }

    public function sendRequest(HttpRequest $request): HttpResponse
    {
        $this->requests[] = $request;
        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \LogicException('No queued response for ' . $request->method . ' ' . $request->url);
        }
        if ($next instanceof ApiConnectionException) {
            throw $next;
        }
        if ($next instanceof \Closure) {
            return $next($request);
        }

        return $next;
    }

    public function lastRequest(): HttpRequest
    {
        $last = end($this->requests);
        if ($last === false) {
            throw new \LogicException('No request was sent.');
        }

        return $last;
    }
}
