<?php

declare(strict_types=1);

namespace Wthaiq\Service;

use Wthaiq\Exception\UnexpectedResponseException;
use Wthaiq\RequestOptions;
use Wthaiq\Resource\AutoPagingIterator;
use Wthaiq\Resource\Collection;
use Wthaiq\Resource\WebhookDelivery;
use Wthaiq\Resource\WebhookEndpoint;
use Wthaiq\Resource\WebhookTestResult;

final class WebhookEndpointService extends AbstractService
{
    /**
     * Registers an HTTPS endpoint. The response includes the signing `secret` (`whsec_…`)
     * exactly once: store it.
     *
     * @param array{url: string, enabled_events?: list<string>}|array<string, mixed> $params
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function create(array $params, array|RequestOptions|null $options = null): WebhookEndpoint
    {
        return $this->requestObject(WebhookEndpoint::class, 'POST', '/webhook_endpoints', $params, $options);
    }

    /**
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function retrieve(string $id, array|RequestOptions|null $options = null): WebhookEndpoint
    {
        return $this->requestObject(WebhookEndpoint::class, 'GET', self::path('/webhook_endpoints/%s', $id), [], $options);
    }

    /**
     * Changes the subscribed events or enables/disables the endpoint. The URL cannot be changed.
     *
     * @param array{enabled_events?: list<string>, status?: 'enabled'|'disabled'}|array<string, mixed> $params
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function update(string $id, array $params, array|RequestOptions|null $options = null): WebhookEndpoint
    {
        return $this->requestObject(WebhookEndpoint::class, 'POST', self::path('/webhook_endpoints/%s', $id), $params, $options);
    }

    /**
     * Deletes the endpoint. The result has `deleted === true`.
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function delete(string $id, array|RequestOptions|null $options = null): WebhookEndpoint
    {
        return $this->requestObject(WebhookEndpoint::class, 'DELETE', self::path('/webhook_endpoints/%s', $id), [], $options);
    }

    /**
     * @param array{limit?: int}|array<string, mixed>    $params
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @return Collection<WebhookEndpoint>
     */
    public function list(array $params = [], array|RequestOptions|null $options = null): Collection
    {
        return $this->requestCollection(WebhookEndpoint::class, '/webhook_endpoints', $params, $options);
    }

    /**
     * @param array<string, mixed>                     $params
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @return AutoPagingIterator<WebhookEndpoint>
     */
    public function all(array $params = [], array|RequestOptions|null $options = null): AutoPagingIterator
    {
        return $this->autoPaging(WebhookEndpoint::class, '/webhook_endpoints', $params, $options);
    }

    /**
     * The last 50 deliveries to the endpoint.
     *
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @return Collection<WebhookDelivery>
     */
    public function deliveries(string $id, array|RequestOptions|null $options = null): Collection
    {
        return $this->requestCollection(WebhookDelivery::class, self::path('/webhook_endpoints/%s/deliveries', $id), [], $options);
    }

    /**
     * Sends a synthetic `signature_request.completed` event to the endpoint right away.
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function test(string $id, array|RequestOptions|null $options = null): WebhookTestResult
    {
        return $this->requestObject(WebhookTestResult::class, 'POST', self::path('/webhook_endpoints/%s/test', $id), [], $options);
    }

    /**
     * Every event type an endpoint can subscribe to.
     *
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @return list<string>
     */
    public function eventTypes(array|RequestOptions|null $options = null): array
    {
        $response = $this->requestor->request('GET', '/webhook_endpoints/event_types', [], RequestOptions::parse($options));
        $decoded = json_decode($response->body, true);
        if (!\is_array($decoded) || !isset($decoded['data']) || !\is_array($decoded['data'])) {
            throw new UnexpectedResponseException('Unexpected response for the event type list.', $response->status, $response->body);
        }

        return array_values(array_map('strval', array_filter($decoded['data'], 'is_string')));
    }
}
