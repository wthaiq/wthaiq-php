<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use Wthaiq\HttpClient\HttpRequest;
use Wthaiq\HttpClient\HttpResponse;
use Wthaiq\Resource\SignatureRequest;

final class PaginationTest extends TestCase
{
    /**
     * @param list<string> $ids
     */
    private static function page(array $ids, bool $hasMore, ?string $cursor): HttpResponse
    {
        return self::json(200, [
            'object' => 'list',
            'data' => array_map(static fn (string $id): array => self::signatureRequest($id), $ids),
            'has_more' => $hasMore,
            'next_cursor' => $cursor,
        ]);
    }

    public function testAllWalksEveryPage(): void
    {
        $client = $this->client([
            self::page(['sr_1', 'sr_2'], true, 'sr_2'),
            self::page(['sr_3', 'sr_4'], true, 'sr_4'),
            self::page(['sr_5'], false, null),
        ]);

        $ids = [];
        foreach ($client->signatureRequests->all(['status' => 'completed', 'limit' => 2]) as $request) {
            self::assertInstanceOf(SignatureRequest::class, $request);
            $ids[] = $request->id;
        }

        self::assertSame(['sr_1', 'sr_2', 'sr_3', 'sr_4', 'sr_5'], $ids);
        $urls = array_map(static fn (HttpRequest $r): string => $r->url, $this->http->requests);
        self::assertSame([
            'https://wthaiq.com/api/v1/signature_requests?status=completed&limit=2',
            'https://wthaiq.com/api/v1/signature_requests?status=completed&limit=2&starting_after=sr_2',
            'https://wthaiq.com/api/v1/signature_requests?status=completed&limit=2&starting_after=sr_4',
        ], $urls);
    }

    public function testAllIsLazy(): void
    {
        $client = $this->client([self::page(['sr_1', 'sr_2'], true, 'sr_2')]);
        $iterator = $client->signatureRequests->all();
        self::assertSame([], $this->http->requests);

        foreach ($iterator as $request) {
            break;
        }
        self::assertCount(1, $this->http->requests);
    }

    public function testAllStopsWhenTheServerRepeatsACursor(): void
    {
        $client = $this->client([
            self::page(['sr_1'], true, 'sr_1'),
            self::page(['sr_1'], true, 'sr_1'),
            self::page(['sr_1'], true, 'sr_1'),
        ]);
        $items = $client->signatureRequests->all()->toArray();
        self::assertCount(2, $items);
        self::assertCount(2, $this->http->requests);
    }

    public function testAllStopsWhenHasMoreIsTrueButTheCursorIsMissing(): void
    {
        $client = $this->client([self::page(['sr_1'], true, null)]);
        self::assertCount(1, $client->webhookEndpoints->all()->toArray());
        self::assertCount(1, $this->http->requests);
    }

    public function testToArrayLimit(): void
    {
        $client = $this->client([self::page(['sr_1', 'sr_2', 'sr_3'], true, 'sr_3')]);
        self::assertCount(2, $client->events->all()->toArray(2));
        self::assertCount(1, $this->http->requests);
    }

    public function testEachIterationStartsAgain(): void
    {
        $client = $this->client([self::page(['sr_1'], false, null), self::page(['sr_1'], false, null)]);
        $iterator = $client->sessions->all();
        self::assertCount(1, iterator_to_array($iterator, false));
        self::assertCount(1, iterator_to_array($iterator, false));
        self::assertCount(2, $this->http->requests);
    }
}
