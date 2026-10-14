<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use Wthaiq\EventType;
use Wthaiq\Exception\InvalidArgumentException;
use Wthaiq\Exception\SignatureVerificationException;
use Wthaiq\Resource\Event;
use Wthaiq\Resource\SignatureRequest;
use Wthaiq\Resource\Signer;
use Wthaiq\Webhook;

final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_00112233445566778899aabbccddeeff0011223344556677';

    /**
     * A delivery captured from the platform's own webhook delivery code.
     *
     * @return array{secret: string, signature: string, payload: string, event_id: string, event_id_header: string, user_agent: string}
     */
    private static function serverFixture(): array
    {
        $json = file_get_contents(__DIR__ . '/../Fixtures/webhook-server-signed.json');
        self::assertIsString($json);
        $fixture = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($fixture);
        $headers = $fixture['headers'] ?? null;
        self::assertIsArray($headers);
        $out = [
            'secret' => $fixture['secret'] ?? null,
            'signature' => $headers['Wthaiq-Signature'] ?? null,
            'payload' => $fixture['payload'] ?? null,
            'event_id' => $fixture['event_id'] ?? null,
            'event_id_header' => $headers['Wthaiq-Event-Id'] ?? null,
            'user_agent' => $headers['User-Agent'] ?? null,
        ];
        foreach ($out as $value) {
            self::assertIsString($value);
        }
        /** @var array{secret: string, signature: string, payload: string, event_id: string, event_id_header: string, user_agent: string} $out */
        return $out;
    }

    public function testVerifiesADeliverySignedByThePlatform(): void
    {
        $fixture = self::serverFixture();
        $event = Webhook::constructEvent($fixture['payload'], $fixture['signature'], $fixture['secret'], 0);

        self::assertInstanceOf(Event::class, $event);
        self::assertSame($fixture['event_id'], $event->id);
        self::assertSame($fixture['event_id_header'], $event->id);
        self::assertSame(EventType::SIGNATURE_REQUEST_COMPLETED, $event->type);
        self::assertFalse($event->livemode);
        self::assertInstanceOf(SignatureRequest::class, $event->data->object);
        self::assertSame('عقد خدمات — SDK fixture', $event->data->object->title);
        self::assertSame('A/1024', $event->data->object->metadata->order_id);
        self::assertInstanceOf(Signer::class, $event->getDataObject()?->signers[0]);
        self::assertSame('Wthaiq-Webhooks/1.0', $fixture['user_agent']);
    }

    public function testThePlatformSignatureFailsWithAnotherSecretOrAChangedPayload(): void
    {
        $fixture = self::serverFixture();
        self::assertFalse(Webhook::isValid($fixture['payload'], $fixture['signature'], self::SECRET, 0));
        self::assertFalse(Webhook::isValid($fixture['payload'] . ' ', $fixture['signature'], $fixture['secret'], 0));
        $reencoded = json_encode(json_decode($fixture['payload'], true), JSON_THROW_ON_ERROR);
        self::assertFalse(Webhook::isValid($reencoded, $fixture['signature'], $fixture['secret'], 0), 'A re-encoded body must not verify; use the raw body');
    }

    public function testSignatureAlgorithmMatchesTheSpecification(): void
    {
        $payload = '{"id":"evt_1","object":"event","type":"signer.signed","created_at":1791353999,"livemode":true,"data":{"object":{"id":"sgr_1","object":"signer"}}}';
        $expected = hash_hmac('sha256', '1791353999.' . $payload, self::SECRET);
        self::assertSame($expected, Webhook::computeSignature('1791353999', $payload, self::SECRET));
        self::assertSame('t=1791353999,v1=' . $expected, Webhook::generateTestHeader($payload, self::SECRET, 1791353999));
    }

    public function testRoundTripWithAFreshHeader(): void
    {
        $payload = '{"id":"evt_2","object":"event","type":"signer.signed","created_at":1,"livemode":false,"data":{"object":{"id":"sgr_1","object":"signer","name":"نهى"}}}';
        $event = Webhook::constructEvent($payload, Webhook::generateTestHeader($payload, self::SECRET), self::SECRET);
        self::assertInstanceOf(Signer::class, $event->data->object);
        self::assertSame('نهى', $event->data->object->name);
    }

    public function testOldTimestampsAreRejected(): void
    {
        $payload = '{"id":"evt_3","object":"event"}';
        $header = Webhook::generateTestHeader($payload, self::SECRET, time() - 301);
        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent($payload, $header, self::SECRET);
    }

    public function testFutureTimestampsAreRejected(): void
    {
        $payload = '{"id":"evt_3","object":"event"}';
        $header = Webhook::generateTestHeader($payload, self::SECRET, time() + 600);
        self::assertFalse(Webhook::isValid($payload, $header, self::SECRET));
        self::assertTrue(Webhook::isValid($payload, $header, self::SECRET, 3600));
    }

    public function testAnyMatchingV1SignatureIsAccepted(): void
    {
        $payload = '{"id":"evt_4","object":"event"}';
        $t = (string) time();
        $good = Webhook::computeSignature($t, $payload, self::SECRET);
        $header = 't=' . $t . ', v1=' . str_repeat('a', 64) . ', v1=' . strtoupper($good) . ', v0=ignored';
        self::assertTrue(Webhook::isValid($payload, $header, self::SECRET));
    }

    public function testMalformedHeaders(): void
    {
        $payload = '{"id":"evt_5","object":"event"}';
        $sig = Webhook::computeSignature('1', $payload, self::SECRET);
        foreach (['', 'garbage', 'v1=' . $sig, 't=abc,v1=' . $sig, 't=1', 't=1,v1='] as $header) {
            try {
                Webhook::verifyHeader($payload, $header, self::SECRET, 0);
                self::fail('Expected SignatureVerificationException for "' . $header . '"');
            } catch (SignatureVerificationException $e) {
                self::assertSame($header, $e->getSigHeader());
            }
        }
    }

    public function testInvalidJsonWithAValidSignature(): void
    {
        $payload = 'not json';
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('not valid JSON');
        Webhook::constructEvent($payload, Webhook::generateTestHeader($payload, self::SECRET), self::SECRET);
    }

    public function testNonObjectJsonWithAValidSignature(): void
    {
        $payload = '[1,2,3]';
        $this->expectException(SignatureVerificationException::class);
        Webhook::constructEvent($payload, Webhook::generateTestHeader($payload, self::SECRET), self::SECRET);
    }

    public function testEmptySecretIsAProgrammingError(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Webhook::verifyHeader('{}', 't=1,v1=00', '');
    }

    public function testEventTypeListMatchesThePlatform(): void
    {
        $types = EventType::all();
        self::assertCount(21, $types);
        self::assertContains(EventType::SIGNER_IDENTITY_MATCH_HOLD, $types);
        self::assertContains('signer.identity_match_decided', $types);
        self::assertContains('signer.identity_resubmission_requested', $types);
        self::assertContains('verification.created', $types);
        self::assertNotContains('*', $types);
    }
}
