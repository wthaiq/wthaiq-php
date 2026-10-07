<?php

declare(strict_types=1);

namespace Wthaiq\Service;

use Wthaiq\RequestOptions;
use Wthaiq\Resource\AutoPagingIterator;
use Wthaiq\Resource\Collection;
use Wthaiq\Resource\Template;

final class TemplateService extends AbstractService
{
    /**
     * Creates a private template (`POST /templates`).
     *
     * @param array{name: string, content: string, fields?: list<array{key: string, label?: string, type?: string, required?: bool}>}|array<string, mixed> $params
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function create(array $params, array|RequestOptions|null $options = null): Template
    {
        return $this->requestObject(Template::class, 'POST', '/templates', $params, $options);
    }

    /**
     * Retrieves a template by `tpl_…` id.
     *
     * @param array{include?: 'content', scope?: 'mine'}|array<string, mixed> $params
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function retrieve(string $id, array $params = [], array|RequestOptions|null $options = null): Template
    {
        return $this->requestObject(Template::class, 'GET', self::path('/templates/%s', $id), $params, $options);
    }

    /**
     * One page of templates. Pass `scope => 'mine'` for your own templates; otherwise the public catalogue.
     *
     * @param array{scope?: string, search?: string, limit?: int, starting_after?: string}|array<string, mixed> $params
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @return Collection<Template>
     */
    public function list(array $params = [], array|RequestOptions|null $options = null): Collection
    {
        return $this->requestCollection(Template::class, '/templates', $params, $options);
    }

    /**
     * Every template matching the filters, fetched page by page.
     *
     * @param array<string, mixed>                     $params
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @return AutoPagingIterator<Template>
     */
    public function all(array $params = [], array|RequestOptions|null $options = null): AutoPagingIterator
    {
        return $this->autoPaging(Template::class, '/templates', $params, $options);
    }
}
