<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * One page of a list endpoint.
 *
 * Iterate it directly for the items of this page; use the service's `all()` method to walk
 * every page automatically.
 *
 * @template TItem
 *
 * @property-read list<TItem> $data
 * @property-read bool        $has_more
 * @property-read string|null $next_cursor Pass as `starting_after` to fetch the next page
 *
 * @implements \IteratorAggregate<int, TItem>
 */
class Collection extends WthaiqObject implements \IteratorAggregate, \Countable
{
    public const OBJECT_NAME = 'list';

    /**
     * @return list<TItem>
     */
    public function data(): array
    {
        $data = $this->offsetGet('data');

        return \is_array($data) ? array_values($data) : [];
    }

    public function hasMore(): bool
    {
        return $this->offsetGet('has_more') === true;
    }

    public function nextCursor(): ?string
    {
        $cursor = $this->offsetGet('next_cursor');

        return \is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    /**
     * Query parameters for the next page, or null on the last page.
     *
     * @param array<string, mixed> $params The parameters used for this page
     *
     * @return array<string, mixed>|null
     */
    public function nextPageParams(array $params = []): ?array
    {
        $cursor = $this->nextCursor();
        if (!$this->hasMore() || $cursor === null) {
            return null;
        }
        $params['starting_after'] = $cursor;

        return $params;
    }

    public function isEmpty(): bool
    {
        return $this->data() === [];
    }

    /**
     * @return TItem|null
     */
    public function first(): mixed
    {
        return $this->data()[0] ?? null;
    }

    public function count(): int
    {
        return \count($this->data());
    }

    /**
     * @return \ArrayIterator<int, TItem>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->data());
    }
}
