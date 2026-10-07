<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use Wthaiq\Client;
use Wthaiq\Resource;

/**
 * Every endpoint: HTTP method, path, query/body and the returned type.
 */
final class ServicesTest extends TestCase
{
    /**
     * @return iterable<string, array{\Closure(Client): mixed, string, string, array<string, mixed>|null, array<string, mixed>, class-string}>
     */
    public static function endpoints(): iterable
    {
        $sr = self::signatureRequest();
        $list = static fn (array $item): array => ['object' => 'list', 'data' => [$item], 'has_more' => false, 'next_cursor' => null];

        yield 'account.retrieve' => [fn (Client $c) => $c->account->retrieve(), 'GET', '/', null, ['object' => 'api_root', 'livemode' => false], Resource\Account::class];
        yield 'usage.retrieve' => [fn (Client $c) => $c->usage->retrieve(), 'GET', '/usage', null, ['object' => 'usage'], Resource\Usage::class];
        yield 'balance.retrieve' => [fn (Client $c) => $c->balance->retrieve(), 'GET', '/balance', null, ['object' => 'balance'], Resource\Balance::class];

        yield 'templates.create' => [fn (Client $c) => $c->templates->create(['name' => 'NDA', 'content' => '<p>{{x}}</p>']), 'POST', '/templates', ['name' => 'NDA', 'content' => '<p>{{x}}</p>'], ['id' => 'tpl_1', 'object' => 'template'], Resource\Template::class];
        yield 'templates.retrieve' => [fn (Client $c) => $c->templates->retrieve('tpl_1', ['include' => 'content']), 'GET', '/templates/tpl_1?include=content', null, ['id' => 'tpl_1', 'object' => 'template'], Resource\Template::class];
        yield 'templates.list' => [fn (Client $c) => $c->templates->list(['scope' => 'mine']), 'GET', '/templates?scope=mine', null, $list(['id' => 'tpl_1', 'object' => 'template']), Resource\Collection::class];

        yield 'signatureRequests.create' => [fn (Client $c) => $c->signatureRequests->create(['title' => 'T', 'send' => false]), 'POST', '/signature_requests', ['title' => 'T', 'send' => false], $sr, Resource\SignatureRequest::class];
        yield 'signatureRequests.createBulk' => [fn (Client $c) => $c->signatureRequests->createBulk(['recipients' => [['name' => 'A', 'email' => 'a@x.com']]]), 'POST', '/signature_requests/bulk', ['recipients' => [['name' => 'A', 'email' => 'a@x.com']]], ['object' => 'bulk_send', 'created' => 1, 'failed' => 0, 'requests' => [], 'errors' => []], Resource\BulkSend::class];
        yield 'signatureRequests.retrieve' => [fn (Client $c) => $c->signatureRequests->retrieve('sr_1'), 'GET', '/signature_requests/sr_1', null, $sr, Resource\SignatureRequest::class];
        yield 'signatureRequests.list' => [fn (Client $c) => $c->signatureRequests->list(['limit' => 2]), 'GET', '/signature_requests?limit=2', null, $list($sr), Resource\Collection::class];
        yield 'signatureRequests.send' => [fn (Client $c) => $c->signatureRequests->send('sr_1'), 'POST', '/signature_requests/sr_1/send', [], $sr, Resource\SignatureRequest::class];
        yield 'signatureRequests.remind' => [fn (Client $c) => $c->signatureRequests->remind('sr_1'), 'POST', '/signature_requests/sr_1/reminders', [], ['object' => 'reminder', 'signature_request' => 'sr_1', 'reminders_sent' => 1], Resource\Reminder::class];
        yield 'signatureRequests.cancel' => [fn (Client $c) => $c->signatureRequests->cancel('sr_1'), 'POST', '/signature_requests/sr_1/cancel', [], ['status' => 'canceled'] + $sr, Resource\SignatureRequest::class];
        yield 'signatureRequests.listEvents' => [fn (Client $c) => $c->signatureRequests->listEvents('sr_1'), 'GET', '/signature_requests/sr_1/events', null, $list(['id' => 'evt_1', 'object' => 'event', 'type' => 'audit.created']), Resource\Collection::class];
        yield 'signatureRequests.certificate' => [fn (Client $c) => $c->signatureRequests->certificate('sr_1'), 'GET', '/signature_requests/sr_1/certificate', null, ['object' => 'certificate', 'signature_request' => 'sr_1'], Resource\Certificate::class];
        yield 'signatureRequests.download' => [fn (Client $c) => $c->signatureRequests->download('sr_1'), 'GET', '/signature_requests/sr_1/download', null, ['object' => 'download', 'pdf_status' => 'ready'], Resource\Download::class];
        yield 'signatureRequests.evidence' => [fn (Client $c) => $c->signatureRequests->evidence('sr_1'), 'GET', '/signature_requests/sr_1/evidence', null, ['object' => 'evidence', 'seal' => null], Resource\Evidence::class];

        yield 'sessions.create' => [fn (Client $c) => $c->sessions->create(['workflow_id' => 'default', 'channel' => 'link']), 'POST', '/sessions', ['workflow_id' => 'default', 'channel' => 'link'], ['id' => 'sr_1', 'object' => 'session'], Resource\Session::class];
        yield 'sessions.retrieve' => [fn (Client $c) => $c->sessions->retrieve('sr_1'), 'GET', '/sessions/sr_1', null, ['id' => 'sr_1', 'object' => 'session'], Resource\Session::class];
        yield 'sessions.list' => [fn (Client $c) => $c->sessions->list(['vendor_data' => 'u-1']), 'GET', '/sessions?vendor_data=u-1', null, $list(['id' => 'sr_1', 'object' => 'session']), Resource\Collection::class];

        yield 'signers.retrieve' => [fn (Client $c) => $c->signers->retrieve('sgr_1'), 'GET', '/signers/sgr_1', null, ['id' => 'sgr_1', 'object' => 'signer'], Resource\Signer::class];
        yield 'signers.createSigningSession' => [fn (Client $c) => $c->signers->createSigningSession('sgr_1', ['mode' => 'redirect', 'success_url' => 'https://acme.example/done']), 'POST', '/signers/sgr_1/signing_session', ['mode' => 'redirect', 'success_url' => 'https://acme.example/done'], ['object' => 'signing_session', 'signer' => 'sgr_1'], Resource\SigningSession::class];

        yield 'documents.retrieve' => [fn (Client $c) => $c->documents->retrieve('doc_1'), 'GET', '/documents/doc_1', null, ['id' => 'doc_1', 'object' => 'document'], Resource\Document::class];
        yield 'identityVerifications.retrieve' => [fn (Client $c) => $c->identityVerifications->retrieve('idv_7'), 'GET', '/identity_verifications/idv_7', null, ['id' => 'idv_7', 'object' => 'identity_verification'], Resource\IdentityVerification::class];
        yield 'verifications.retrieve' => [fn (Client $c) => $c->verifications->retrieve('WTQ-1A2B'), 'GET', '/verifications/WTQ-1A2B', null, ['object' => 'verification', 'found' => true], Resource\Verification::class];

        yield 'webhookEndpoints.create' => [fn (Client $c) => $c->webhookEndpoints->create(['url' => 'https://acme.example/hooks', 'enabled_events' => ['signer.signed']]), 'POST', '/webhook_endpoints', ['url' => 'https://acme.example/hooks', 'enabled_events' => ['signer.signed']], ['id' => 'we_1', 'object' => 'webhook_endpoint', 'secret' => 'whsec_x'], Resource\WebhookEndpoint::class];
        yield 'webhookEndpoints.retrieve' => [fn (Client $c) => $c->webhookEndpoints->retrieve('we_1'), 'GET', '/webhook_endpoints/we_1', null, ['id' => 'we_1', 'object' => 'webhook_endpoint'], Resource\WebhookEndpoint::class];
        yield 'webhookEndpoints.update' => [fn (Client $c) => $c->webhookEndpoints->update('we_1', ['status' => 'disabled']), 'POST', '/webhook_endpoints/we_1', ['status' => 'disabled'], ['id' => 'we_1', 'object' => 'webhook_endpoint', 'status' => 'disabled'], Resource\WebhookEndpoint::class];
        yield 'webhookEndpoints.delete' => [fn (Client $c) => $c->webhookEndpoints->delete('we_1'), 'DELETE', '/webhook_endpoints/we_1', null, ['id' => 'we_1', 'object' => 'webhook_endpoint', 'deleted' => true], Resource\WebhookEndpoint::class];
        yield 'webhookEndpoints.list' => [fn (Client $c) => $c->webhookEndpoints->list(['limit' => 10]), 'GET', '/webhook_endpoints?limit=10', null, $list(['id' => 'we_1', 'object' => 'webhook_endpoint']), Resource\Collection::class];
        yield 'webhookEndpoints.deliveries' => [fn (Client $c) => $c->webhookEndpoints->deliveries('we_1'), 'GET', '/webhook_endpoints/we_1/deliveries', null, $list(['object' => 'webhook_delivery', 'event' => 'evt_1']), Resource\Collection::class];
        yield 'webhookEndpoints.test' => [fn (Client $c) => $c->webhookEndpoints->test('we_1'), 'POST', '/webhook_endpoints/we_1/test', [], ['object' => 'webhook_test', 'ok' => true], Resource\WebhookTestResult::class];

        yield 'events.retrieve' => [fn (Client $c) => $c->events->retrieve('evt_1'), 'GET', '/events/evt_1', null, ['id' => 'evt_1', 'object' => 'event', 'type' => 'signer.signed', 'data' => ['object' => ['id' => 'sgr_1', 'object' => 'signer']]], Resource\Event::class];
        yield 'events.list' => [fn (Client $c) => $c->events->list(['type' => 'signer.signed']), 'GET', '/events?type=signer.signed', null, $list(['id' => 'evt_1', 'object' => 'event']), Resource\Collection::class];
        yield 'events.redeliver' => [fn (Client $c) => $c->events->redeliver('evt_1', ['endpoint' => 'we_1']), 'POST', '/events/evt_1/redeliver', ['endpoint' => 'we_1'], ['object' => 'event_redelivery', 'event' => 'evt_1', 'deliveries' => []], Resource\EventRedelivery::class];

        yield 'flows.retrieve' => [fn (Client $c) => $c->flows->retrieve('flow_1'), 'GET', '/flows/flow_1', null, ['id' => 'flow_1', 'object' => 'flow'], Resource\Flow::class];
        yield 'flows.list' => [fn (Client $c) => $c->flows->list(), 'GET', '/flows', null, $list(['id' => 'flow_1', 'object' => 'flow']), Resource\Collection::class];
    }

    /**
     * @param \Closure(Client): mixed   $call
     * @param array<string, mixed>|null $expectedBody
     * @param array<string, mixed>      $response
     * @param class-string              $expectedClass
     */
    #[DataProvider('endpoints')]
    public function testEndpoint(\Closure $call, string $method, string $path, ?array $expectedBody, array $response, string $expectedClass): void
    {
        $client = $this->client([self::json(200, $response)]);
        $result = $call($client);
        $request = $this->http->lastRequest();

        self::assertSame($method, $request->method);
        self::assertSame('https://wthaiq.com/api/v1' . $path, $request->url);
        if ($expectedBody === null) {
            self::assertNull($request->body);
        } else {
            self::assertSame($expectedBody, self::decodeBody($request));
        }
        self::assertInstanceOf($expectedClass, $result);
        self::assertInstanceOf(Resource\WthaiqObject::class, $result);
        self::assertNotNull($result->getLastResponse());
    }

    public function testListItemsAreTyped(): void
    {
        $client = $this->client([self::json(200, ['object' => 'list', 'data' => [self::signatureRequest('sr_1'), self::signatureRequest('sr_2')], 'has_more' => true, 'next_cursor' => 'sr_2'])]);
        $page = $client->signatureRequests->list();

        self::assertCount(2, $page);
        self::assertTrue($page->hasMore());
        self::assertSame('sr_2', $page->nextCursor());
        self::assertSame(['limit' => 2, 'starting_after' => 'sr_2'], $page->nextPageParams(['limit' => 2]));
        foreach ($page as $item) {
            self::assertInstanceOf(Resource\SignatureRequest::class, $item);
            self::assertInstanceOf(Resource\Signer::class, $item->signers[0]);
        }
        self::assertSame('sr_1', $page->first()?->id);
    }

    public function testListItemsWithoutObjectFieldAreRetyped(): void
    {
        $client = $this->client([self::json(200, ['object' => 'list', 'data' => [['object' => 'webhook_delivery', 'status' => 'failed']], 'has_more' => false, 'next_cursor' => null])]);
        $page = $client->webhookEndpoints->deliveries('we_1');
        self::assertInstanceOf(Resource\WebhookDelivery::class, $page->first());
    }

    public function testEventTypes(): void
    {
        $client = $this->client([self::json(200, ['object' => 'list', 'data' => ['signature_request.created', 'signer.signed']])]);
        self::assertSame(['signature_request.created', 'signer.signed'], $client->webhookEndpoints->eventTypes());
        self::assertSame('https://wthaiq.com/api/v1/webhook_endpoints/event_types', $this->http->lastRequest()->url);
    }

    public function testUnknownReferenceReturnsANotFoundVerification(): void
    {
        $client = $this->client([self::json(404, ['object' => 'verification', 'reference' => 'WTQ-NOPE', 'found' => false, 'status' => 'not_found', 'integrity' => 'unknown'])]);
        $verification = $client->verifications->retrieve('WTQ-NOPE');

        self::assertFalse($verification->isFound());
        self::assertSame('not_found', $verification->status);
        self::assertSame(404, $verification->getLastResponse()?->status);
    }

    public function testRealNotFoundErrorsStillThrowForVerifications(): void
    {
        $client = $this->client([self::error(404, 'invalid_request_error', 'resource_missing')]);
        $this->expectException(\Wthaiq\Exception\NotFoundException::class);
        $client->verifications->retrieve('WTQ-1');
    }

    public function testTypedHelpers(): void
    {
        $client = $this->client([
            self::json(200, self::signatureRequest('sr_1', 'completed')),
            self::json(200, ['object' => 'download', 'pdf_status' => 'ready']),
            self::json(200, ['object' => 'evidence', 'seal' => ['signature_alg' => 'ed25519']]),
            self::json(200, ['id' => 'evt_1', 'object' => 'event', 'data' => ['object' => self::signatureRequest()]]),
        ]);
        $sr = $client->signatureRequests->retrieve('sr_1');
        self::assertTrue($sr->isCompleted());
        self::assertTrue($sr->isFinal());
        self::assertTrue($client->signatureRequests->download('sr_1')->isPdfReady());
        self::assertTrue($client->signatureRequests->evidence('sr_1')->isSealed());
        self::assertInstanceOf(Resource\SignatureRequest::class, $client->events->retrieve('evt_1')->getDataObject());
    }
}
