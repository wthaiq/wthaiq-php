<?php

declare(strict_types=1);

namespace Wthaiq\Resource;

/**
 * Lazily walks every page of a list endpoint, following `next_cursor`.
 *
 * Each `foreach` starts again from the first page. Pages are fetched only as needed, so
 * breaking out of the loop stops further requests.
 *
 * @template TItem
 *
 * @implements \IteratorAggregate<int, TItem>
 */
final class AutoPagingIterator implements \IteratorAggregate
{
    /**
     * @param \Closure(array<string, mixed>): Collection<TItem> $fetchPage
     * @param array<string, mixed>                              $params
     */
    public function __construct(private readonly \Closure $fetchPage, private readonly array $params = [])
    {
    }

    /**
     * @return \Generator<int, TItem, mixed, void>
     */
    public function getIterator(): \Generator
    {
        $params = $this->params;
        $seen = [];
        while (true) {
            $page = ($this->fetchPage)($params);
            foreach ($page->data() as $item) {
                yield $item;
            }
            $next = $page->nextPageParams($params);
            $cursor = $page->nextCursor();
            if ($next === null || $cursor === null || $page->isEmpty() || isset($seen[$cursor])) {
                return;
            }
            $seen[$cursor] = true;
            $params = $next;
        }
    }

    /**
     * Collects items into an array.
     *
     * @param int|null $limit Stop after this many items
     *
     * @return list<TItem>
     */
    public function toArray(?int $limit = null): array
    {
        $out = [];
        foreach ($this as $item) {
            if ($limit !== null && \count($out) >= $limit) {
                break;
            }
            $out[] = $item;
        }

        return $out;
    }
}
