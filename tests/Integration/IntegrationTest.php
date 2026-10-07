<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Integration;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Wthaiq\Client;
use Wthaiq\EventType;
use Wthaiq\Exception\AuthenticationException;
use Wthaiq\Exception\ConflictException;
use Wthaiq\Exception\InvalidRequestException;
use Wthaiq\Exception\NotFoundException;
use Wthaiq\Exception\PermissionException;
use Wthaiq\Exception\ValidationException;
use Wthaiq\Resource\Account;
use Wthaiq\Resource\Event;
use Wthaiq\Resource\Session;
use Wthaiq\Resource\SignatureRequest;
use Wthaiq\Resource\Signer;
use Wthaiq\Resource\Template;

/**
 * Runs against a real Wthaiq API in TEST mode.
 *
 * Environment:
 * - WTHAIQ_API_KEY            a test secret key (sk_test_...); live keys are refused
 * - WTHAIQ_API_BASE           base URL, e.g. https://wthaiq.com/api/v1
 * - WTHAIQ_TEST_SIGNER_EMAIL  optional; defaults to the account email (test mode only accepts your own addresses)
 * - WTHAIQ_API_KEY_READONLY   optional read-only test key, to check the read-only refusal
 * - WTHAIQ_TEST_WEBHOOK_URL   optional public https URL for the endpoint tests (default https://example.com/...)
 *
 * The suite creates drafts (nothing is sent), one session on the "link" channel (no message is sent),
 * and a webhook endpoint subscribed only to a live-only event; everything it creates is canceled or deleted.
 */
final class IntegrationTest extends TestCase
{
    private static ?Client $client = null;

    /** @var list<string> */
    private static array $toCancel = [];

    public static function setUpBeforeClass(): void
    {
        $key = (string) getenv('WTHAIQ_API_KEY');
        $base = (string) getenv('WTHAIQ_API_BASE');
        if ($key === '' || $base === '') {
            return;
        }
        if (!str_starts_with($key, 'sk_test_')) {
            throw new \RuntimeException('The integration suite only runs with a test secret key (sk_test_...).');
        }
        self::$client = new Client($key, ['base_url' => $base, 'max_retries' => 1, 'app_info' => ['name' => 'wthaiq-php-integration']]);
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$client === null) {
            return;
        }
        foreach (self::$toCancel as $id) {
            try {
                self::$client->signatureRequests->cancel($id);
            } catch (ConflictException) {
            }
        }
    }

    private function client(): Client
    {
        if (self::$client === null) {
            self::markTestSkipped('Set WTHAIQ_API_KEY (sk_test_...) and WTHAIQ_API_BASE to run the integration suite.');
        }

        return self::$client;
    }

    public function testAccountUsageAndBalance(): Account
    {
        $client = $this->client();
        $account = $client->account->retrieve();
        self::assertSame('api_root', $account->object);
        self::assertFalse($account->livemode);
        self::assertSame(Client::API_VERSION, $account->version);
        self::assertStringStartsWith('acct_', (string) $account->account->id);
        $response = $account->getLastResponse();
        self::assertNotNull($response);
        self::assertSame(Client::API_VERSION, $response->getHeader('Wthaiq-Version'));
        self::assertStringStartsWith('req_', (string) $response->getRequestId());

        $usage = $client->usage->retrieve();
        self::assertSame('usage', $usage->object);
        self::assertContains($usage->balance->currency, ['EGP', 'USD']);
        self::assertIsInt($usage->signature_requests->total);

        $balance = $client->balance->retrieve();
        self::assertSame('balance', $balance->object);
        self::assertIsInt($balance->balance->amount_minor);

        return $account;
    }

    public function testTemplates(): Template
    {
        $client = $this->client();
        $template = $client->templates->create([
            'name' => 'SDK integration ' . date('Y-m-d H:i:s'),
            'content' => '<p>أقرّ أنا {{signer_name}} بقبول مبلغ {{amount}}.</p>',
            'fields' => [['key' => 'amount', 'label' => 'Amount', 'type' => 'number', 'required' => true]],
        ]);
        self::assertStringStartsWith('tpl_', $template->id);
        self::assertSame(201, $template->getLastResponse()?->status);

        $again = $client->templates->retrieve($template->id, ['include' => 'content']);
        self::assertSame($template->id, $again->id);
        self::assertStringContainsString('{{amount}}', (string) $again->content);

        $mine = $client->templates->list(['scope' => 'mine', 'limit' => 100]);
        self::assertContains($template->id, array_map(static fn (Template $t): string => $t->id, $mine->data()));

        $catalogue = $client->templates->list(['limit' => 2]);
        self::assertLessThanOrEqual(2, \count($catalogue));

        return $template;
    }

    #[Depends('testAccountUsageAndBalance')]
    #[Depends('testTemplates')]
    public function testSignatureRequestLifecycle(Account $account, Template $template): SignatureRequest
    {
        $client = $this->client();
        $email = (string) (getenv('WTHAIQ_TEST_SIGNER_EMAIL') ?: $account->account->email);
        $params = [
            'title' => 'SDK integration draft',
            'source' => ['type' => 'template', 'template_id' => $template->id],
            'signers' => [['name' => 'SDK Signer', 'email' => $email, 'fields' => ['amount' => '100']]],
            'metadata' => ['suite' => 'wthaiq-php', 'path' => '/a/b'],
            'reminders' => ['enabled' => true, 'interval_hours' => 48, 'max' => 3],
            'send' => false,
        ];
        $key = 'sdk-it-' . bin2hex(random_bytes(8));
        $draft = $client->signatureRequests->create($params, ['idempotency_key' => $key]);
        self::$toCancel[] = $draft->id;

        self::assertStringStartsWith('sr_', $draft->id);
        self::assertSame(SignatureRequest::STATUS_DRAFT, $draft->status);
        self::assertFalse($draft->livemode);
        self::assertInstanceOf(Signer::class, $draft->signers[0]);
        self::assertNull($draft->signers[0]->signing_url, 'Draft links stay hidden until sent');
        self::assertSame('/a/b', $draft->metadata->path);
        self::assertFalse((bool) $draft->getLastResponse()?->isIdempotentReplayed());

        $replay = $client->signatureRequests->create($params, ['idempotency_key' => $key]);
        self::assertSame($draft->id, $replay->id);
        self::assertTrue((bool) $replay->getLastResponse()?->isIdempotentReplayed());

        try {
            $client->signatureRequests->create(['title' => 'changed'] + $params, ['idempotency_key' => $key]);
            self::fail('Expected idempotency_key_reused');
        } catch (ConflictException $e) {
            self::assertSame('idempotency_key_reused', $e->getErrorCode());
        }

        $fetched = $client->signatureRequests->retrieve($draft->id);
        self::assertSame($draft->id, $fetched->id);

        $page = $client->signatureRequests->list(['status' => 'draft', 'limit' => 5]);
        self::assertContains($draft->id, array_map(static fn (SignatureRequest $r): string => $r->id, $page->data()));

        $seen = 0;
        foreach ($client->signatureRequests->all(['limit' => 1]) as $item) {
            self::assertInstanceOf(SignatureRequest::class, $item);
            if (++$seen === 3) {
                break;
            }
        }
        self::assertGreaterThanOrEqual(1, $seen);

        $events = $client->signatureRequests->listEvents($draft->id);
        self::assertInstanceOf(Event::class, $events->first());
        self::assertSame('audit.created', $events->first()->type);

        $certificate = $client->signatureRequests->certificate($draft->id);
        self::assertSame('certificate', $certificate->object);
        self::assertTrue((bool) $certificate->test);
        self::assertSame('none', $certificate->legal_effect);

        $evidence = $client->signatureRequests->evidence($draft->id);
        self::assertSame('wthaiq-evidence-test-v1', $evidence->format);
        self::assertFalse($evidence->isSealed());

        try {
            $client->signatureRequests->download($draft->id);
            self::fail('Expected a 400 for a request that is not completed');
        } catch (ValidationException $e) {
            self::assertSame('invalid_request', $e->getErrorCode());
        }
        try {
            $client->signatureRequests->downloadPdf($draft->id);
            self::fail('Expected a 400 for a request that is not completed');
        } catch (ValidationException $e) {
            self::assertSame(400, $e->getHttpStatus());
        }
        try {
            $client->signatureRequests->remind($draft->id);
            self::fail('Expected a 400 for reminding a draft');
        } catch (ValidationException $e) {
            self::assertSame('invalid_request', $e->getErrorCode());
        }

        $canceled = $client->signatureRequests->cancel($draft->id);
        self::assertSame(SignatureRequest::STATUS_CANCELED, $canceled->status);
        try {
            $client->signatureRequests->cancel($draft->id);
            self::fail('Expected request_not_cancelable');
        } catch (ConflictException $e) {
            self::assertSame('request_not_cancelable', $e->getErrorCode());
        }

        return $draft;
    }

    #[Depends('testAccountUsageAndBalance')]
    #[Depends('testTemplates')]
    public function testValidationErrorsCarryTheParameter(Account $account, Template $template): void
    {
        $client = $this->client();
        try {
            $client->signatureRequests->create(['source' => ['type' => 'template', 'template_id' => $template->id], 'send' => false]);
            self::fail('Expected parameter_missing');
        } catch (ValidationException $e) {
            self::assertSame('parameter_missing', $e->getErrorCode());
            self::assertSame('signers', $e->getParam());
            self::assertArrayHasKey('signers', $e->getFieldErrors());
            self::assertStringStartsWith('req_', (string) $e->getRequestId());
        }
        try {
            $client->signatureRequests->create([
                'source' => ['type' => 'template', 'template_id' => $template->id],
                'signers' => [['name' => 'Someone', 'email' => 'not-an-email']],
                'send' => false,
            ]);
            self::fail('Expected invalid email');
        } catch (ValidationException $e) {
            self::assertSame('signers[0].email', $e->getParam());
        }
    }

    #[Depends('testAccountUsageAndBalance')]
    #[Depends('testTemplates')]
    public function testBulkDrafts(Account $account, Template $template): void
    {
        $client = $this->client();
        $email = (string) (getenv('WTHAIQ_TEST_SIGNER_EMAIL') ?: $account->account->email);
        try {
            $bulk = $client->signatureRequests->createBulk([
                'source' => ['type' => 'template', 'template_id' => $template->id],
                'recipients' => [['name' => 'Bulk Signer', 'email' => $email]],
                'fields' => ['amount' => '1'],
                'send' => false,
            ]);
        } catch (PermissionException $e) {
            self::assertTrue($e->isPlanFeatureUnavailable());
            self::markTestSkipped('Bulk sending is not available on this plan: ' . $e->getMessage());
        }
        self::assertSame('bulk_send', $bulk->object);
        self::assertSame(1, $bulk->created);
        self::assertSame(0, $bulk->failed);
        $id = (string) $bulk->requests[0]->signature_request;
        self::$toCancel[] = $id;
        self::assertSame(SignatureRequest::STATUS_DRAFT, $client->signatureRequests->retrieve($id)->status);
    }

    #[Depends('testAccountUsageAndBalance')]
    #[Depends('testTemplates')]
    public function testSessionsAndSigners(Account $account, Template $template): void
    {
        $client = $this->client();
        $email = (string) (getenv('WTHAIQ_TEST_SIGNER_EMAIL') ?: $account->account->email);
        $vendor = 'sdk-' . bin2hex(random_bytes(4));
        $session = $client->sessions->create([
            'workflow_id' => 'default',
            'template_id' => $template->id,
            'channel' => 'link',
            'language' => 'en',
            'vendor_data' => $vendor,
            'signers' => [['name' => 'Session Signer', 'email' => $email]],
            'fields' => ['amount' => '5'],
        ]);
        self::$toCancel[] = $session->id;

        self::assertInstanceOf(Session::class, $session);
        self::assertSame('default', $session->workflow_id);
        self::assertSame('link', $session->channel);
        self::assertSame($vendor, $session->vendor_data);
        self::assertStringContainsString('token=', (string) $session->sign_url);

        self::assertSame($session->id, $client->sessions->retrieve($session->id)->id);
        $found = $client->sessions->list(['vendor_data' => $vendor]);
        self::assertSame([$session->id], array_map(static fn (Session $s): string => $s->id, $found->data()));

        $signerId = (string) $session->signers[0]->id;
        $signer = $client->signers->retrieve($signerId);
        self::assertSame($signerId, $signer->id);
        self::assertSame(Signer::STATUS_SENT, $signer->status);

        try {
            $embedded = $client->signers->createSigningSession($signerId);
            self::assertSame('iframe', $embedded->mode);
            self::assertStringContainsString('/embed/sign/', $embedded->signing_url);
            self::assertGreaterThan(time(), $embedded->expires_at);
        } catch (PermissionException $e) {
            self::assertTrue($e->isPlanFeatureUnavailable());
        }

        try {
            $client->signatureRequests->remind($session->id);
            self::fail('Expected channel_has_no_messages');
        } catch (ValidationException $e) {
            self::assertSame('channel_has_no_messages', $e->getErrorCode());
        }

        try {
            $client->sessions->create(['workflow_id' => 'default', 'template_id' => $template->id, 'ordered' => true, 'signers' => [['name' => 'X', 'email' => $email]]]);
            self::fail('Expected invalid_override');
        } catch (ValidationException $e) {
            self::assertSame('invalid_override', $e->getErrorCode());
            self::assertSame('ordered', $e->getParam());
        }

        try {
            $client->sessions->create(['workflow_id' => 'wf_doesnotexist', 'signers' => [['name' => 'X', 'email' => $email]]]);
            self::fail('Expected workflow_not_found');
        } catch (NotFoundException $e) {
            self::assertSame('workflow_not_found', $e->getErrorCode());
        }

        $canceled = $client->signatureRequests->cancel($session->id);
        self::assertSame(SignatureRequest::STATUS_CANCELED, $canceled->status);
    }

    public function testWebhookEndpointsAndEvents(): void
    {
        $client = $this->client();
        $types = $client->webhookEndpoints->eventTypes();
        self::assertSame(EventType::all(), $types);

        $url = (string) (getenv('WTHAIQ_TEST_WEBHOOK_URL') ?: 'https://example.com/wthaiq-sdk-integration');
        try {
            $endpoint = $client->webhookEndpoints->create(['url' => $url, 'enabled_events' => [EventType::DOCUMENT_SEALED]]);
        } catch (ValidationException $e) {
            self::markTestSkipped('The API could not accept the webhook URL (DNS lookup of the host failed?): ' . $e->getMessage());
        }
        self::assertStringStartsWith('we_', $endpoint->id);
        self::assertStringStartsWith('whsec_', (string) $endpoint->secret);
        self::assertSame([EventType::DOCUMENT_SEALED], $endpoint->enabled_events);
        self::assertFalse($endpoint->livemode);

        try {
            $fetched = $client->webhookEndpoints->retrieve($endpoint->id);
            self::assertNull($fetched->secret, 'The secret is only returned at creation');
            self::assertContains($endpoint->id, array_map(static fn ($e): string => (string) $e->id, $client->webhookEndpoints->list()->data()));

            $updated = $client->webhookEndpoints->update($endpoint->id, ['status' => 'disabled', 'enabled_events' => [EventType::DOCUMENT_SEALED, EventType::VERIFICATION_CREATED]]);
            self::assertSame('disabled', $updated->status);
            self::assertCount(2, $updated->enabled_events);

            self::assertCount(0, $client->webhookEndpoints->deliveries($endpoint->id));

            try {
                $client->webhookEndpoints->create(['url' => $url, 'enabled_events' => ['not.a_type']]);
                self::fail('Expected unknown event type');
            } catch (ValidationException $e) {
                self::assertSame('enabled_events', $e->getParam());
            }
        } finally {
            $deleted = $client->webhookEndpoints->delete($endpoint->id);
            self::assertTrue((bool) $deleted->deleted);
        }

        try {
            $client->webhookEndpoints->retrieve($endpoint->id);
            self::fail('Expected NotFoundException after delete');
        } catch (NotFoundException $e) {
            self::assertSame('resource_missing', $e->getErrorCode());
        }

        $events = $client->events->list(['limit' => 3]);
        self::assertLessThanOrEqual(3, \count($events));
        $first = $events->first();
        if ($first instanceof Event) {
            self::assertSame($first->id, $client->events->retrieve($first->id)->id);
            self::assertInstanceOf(\Wthaiq\Resource\WthaiqObject::class, $first->getDataObject());
            try {
                $client->events->redeliver($first->id);
                self::fail('Expected no_endpoint');
            } catch (InvalidRequestException $e) {
                self::assertContains($e->getErrorCode(), ['no_endpoint', 'too_many_requests']);
            }
        }
    }

    public function testLookupsAndErrors(): void
    {
        $client = $this->client();

        $verification = $client->verifications->retrieve('WTQ-SDKNOTFOUND');
        self::assertFalse($verification->isFound());
        self::assertSame('not_found', $verification->status);

        try {
            $client->identityVerifications->retrieve('idv_999999999');
            self::fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            self::assertSame('resource_missing', $e->getErrorCode());
        }

        try {
            $client->documents->retrieve('doc_doesnotexist000000');
            self::fail('Expected NotFoundException');
        } catch (NotFoundException $e) {
            self::assertSame(404, $e->getHttpStatus());
        }

        self::assertSame('list', $client->flows->list()->object);

        $bad = new Client('sk_test_' . str_repeat('0', 40), ['base_url' => $client->getBaseUrl(), 'max_retries' => 0]);
        try {
            $bad->account->retrieve();
            self::fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            self::assertSame('invalid_api_key', $e->getErrorCode());
        }

        $readOnly = (string) getenv('WTHAIQ_API_KEY_READONLY');
        if ($readOnly !== '') {
            $ro = new Client($readOnly, ['base_url' => $client->getBaseUrl(), 'max_retries' => 0]);
            self::assertSame('api_root', $ro->account->retrieve()->object);
            try {
                $ro->templates->create(['name' => 'x', 'content' => '<p>x</p>']);
                self::fail('Expected PermissionException for a read-only key');
            } catch (PermissionException $e) {
                self::assertSame('forbidden', $e->getErrorCode());
            }
        }
    }
}
