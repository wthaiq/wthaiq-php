<?php

declare(strict_types=1);

namespace Wthaiq\Service;

use Wthaiq\RequestOptions;
use Wthaiq\Resource\AutoPagingIterator;
use Wthaiq\Resource\Collection;
use Wthaiq\Resource\Session;

final class SessionService extends AbstractService
{
    /**
     * Creates a signature request from a workflow (`POST /sessions`).
     *
     * Required: `workflow_id` (`wf_…` of a published workflow, or `default`) and `signers`.
     * Workflows whose document is chosen per request also need `template_id`.
     *
     * @param array<string, mixed>                     $params
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function create(array $params, array|RequestOptions|null $options = null): Session
    {
        return $this->requestObject(Session::class, 'POST', '/sessions', $params, $options);
    }

    /**
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function retrieve(string $id, array|RequestOptions|null $options = null): Session
    {
        return $this->requestObject(Session::class, 'GET', self::path('/sessions/%s', $id), [], $options);
    }

    /**
     * @param array{workflow_id?: string, vendor_data?: string, status?: string, limit?: int, starting_after?: string}|array<string, mixed> $params
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @return Collection<Session>
     */
    public function list(array $params = [], array|RequestOptions|null $options = null): Collection
    {
        return $this->requestCollection(Session::class, '/sessions', $params, $options);
    }

    /**
     * @param array<string, mixed>                     $params
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @return AutoPagingIterator<Session>
     */
    public function all(array $params = [], array|RequestOptions|null $options = null): AutoPagingIterator
    {
        return $this->autoPaging(Session::class, '/sessions', $params, $options);
    }
}
