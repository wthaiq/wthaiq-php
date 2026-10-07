<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use Wthaiq\Resource\Collection;
use Wthaiq\Resource\SignatureRequest;
use Wthaiq\Resource\Signer;
use Wthaiq\Resource\WthaiqObject;

final class ObjectTest extends TestCase
{
    private function request(): SignatureRequest
    {
        $client = $this->client([self::json(200, self::signatureRequest() + ['future_field' => ['nested' => true]])]);

        return $client->signatureRequests->retrieve('sr_aaaaaaaaaaaaaaaaaaaa');
    }

    public function testPropertyAndArrayAccess(): void
    {
        $sr = $this->request();
        self::assertSame('sr_aaaaaaaaaaaaaaaaaaaa', $sr->id);
        self::assertSame('sr_aaaaaaaaaaaaaaaaaaaa', $sr['id']);
        self::assertSame('template', $sr->source->type);
        self::assertSame('tpl_12', $sr['source']['template_id']);
        self::assertSame('100', $sr->signers[0]->fields->amount);
        self::assertInstanceOf(Signer::class, $sr->signers[0]);
        self::assertTrue($sr->future_field->nested, 'Fields unknown to this version stay readable');
        self::assertNull($sr->missing);
        self::assertNull($sr['missing']);
        self::assertFalse(isset($sr->reference));
        self::assertTrue($sr->has('reference'));
        self::assertFalse($sr->has('missing'));
        self::assertTrue(isset($sr['id']));
        self::assertContains('signers', $sr->keys());
    }

    public function testObjectsAreReadOnly(): void
    {
        $sr = $this->request();
        foreach ([
            static function () use ($sr): void {
                $sr->__set('status', 'completed');
            },
            static function () use ($sr): void {
                $sr['status'] = 'completed';
            },
            static function () use ($sr): void {
                unset($sr->status);
            },
            static function () use ($sr): void {
                unset($sr['status']);
            },
        ] as $write) {
            try {
                $write();
                self::fail('Expected LogicException');
            } catch (\LogicException) {
                self::assertSame('sent', $sr->status);
            }
        }
    }

    public function testToArrayAndJsonKeepEmptyObjects(): void
    {
        $sr = $this->request();
        $array = $sr->toArray();
        self::assertSame('Noha', $array['signers'][0]['name']);
        self::assertSame([], $array['metadata']);
        self::assertSame([], $array['cc']);

        $json = $sr->toJson(0);
        self::assertStringContainsString('"metadata":{}', $json);
        self::assertStringContainsString('"cc":[]', $json);
        self::assertStringContainsString('"signing_url":"https://wthaiq.com/sign.php?token=abc"', $json);
    }

    public function testConstructFromPlainArrays(): void
    {
        $sr = SignatureRequest::constructFrom(['id' => 'sr_9', 'object' => 'signature_request', 'signers' => [['object' => 'signer', 'name' => 'A']], 'metadata' => []]);
        self::assertSame('sr_9', $sr->id);
        self::assertInstanceOf(Signer::class, $sr->signers[0]);
        self::assertInstanceOf(WthaiqObject::class, $sr->metadata);
        self::assertNull($sr->getLastResponse());

        $generic = WthaiqObject::constructFrom([]);
        self::assertSame([], $generic->toArray());
        self::assertSame('{}', $generic->toJson(0));
    }

    public function testFromObjectRetypesAndKeepsTheResponse(): void
    {
        $sr = $this->request();
        $generic = WthaiqObject::fromObject($sr);
        self::assertSame('sr_aaaaaaaaaaaaaaaaaaaa', $generic->id);
        self::assertSame($sr->getLastResponse(), $generic->getLastResponse());
    }

    public function testDebugInfoShowsPlainData(): void
    {
        $sr = $this->request();
        self::assertSame($sr->toArray(), $sr->__debugInfo());
    }

    public function testCollectionHelpersOnAnEmptyPage(): void
    {
        $page = Collection::constructFrom(['object' => 'list', 'data' => [], 'has_more' => false, 'next_cursor' => null]);
        self::assertTrue($page->isEmpty());
        self::assertNull($page->first());
        self::assertNull($page->nextCursor());
        self::assertNull($page->nextPageParams());
        self::assertCount(0, $page);
    }
}
