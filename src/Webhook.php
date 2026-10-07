<?php

declare(strict_types=1);

namespace Wthaiq;

use Wthaiq\Exception\InvalidArgumentException;
use Wthaiq\Exception\SignatureVerificationException;
use Wthaiq\Exception\UnexpectedResponseException;
use Wthaiq\Resource\Event;
use Wthaiq\Resource\ObjectFactory;
use Wthaiq\Resource\WthaiqObject;

/**
 * Verifies and decodes webhook deliveries.
 *
 * The platform sends `Wthaiq-Signature: t=<unix time>,v1=<hex>` where
 * `v1 = HMAC-SHA256(endpoint secret, "<t>.<raw body>")`. Always verify the raw request body
 * exactly as received, before any JSON decoding.
 *
 * ```php
 * $event = \Wthaiq\Webhook::constructEvent(
 *     file_get_contents('php://input'),
 *     $_SERVER['HTTP_WTHAIQ_SIGNATURE'] ?? '',
 *     getenv('WTHAIQ_WEBHOOK_SECRET'),
 * );
 * ```
 */
final class Webhook
{
    public const SIGNATURE_HEADER = 'Wthaiq-Signature';
    public const EVENT_ID_HEADER = 'Wthaiq-Event-Id';
    public const ENDPOINT_ID_HEADER = 'Wthaiq-Endpoint-Id';
    public const DEFAULT_TOLERANCE = 300;

    /**
     * Verifies the signature and returns the decoded event.
     *
     * @param string $payload   The raw request body
     * @param string $sigHeader The `Wthaiq-Signature` header value
     * @param string $secret    The endpoint secret (`whsec_…`)
     * @param int    $tolerance Maximum age of the signature in seconds; 0 disables the check
     *
     * @throws SignatureVerificationException
     */
    public static function constructEvent(string $payload, string $sigHeader, string $secret, int $tolerance = self::DEFAULT_TOLERANCE): Event
    {
        self::verifyHeader($payload, $sigHeader, $secret, $tolerance);

        try {
            $decoded = ObjectFactory::decode($payload);
        } catch (UnexpectedResponseException $e) {
            throw new SignatureVerificationException('The webhook payload is not valid JSON.', $sigHeader, $e);
        }
        if (!$decoded instanceof \stdClass) {
            throw new SignatureVerificationException('The webhook payload is not a JSON object.', $sigHeader);
        }
        $object = ObjectFactory::convert($decoded);
        if (!$object instanceof WthaiqObject) {
            throw new SignatureVerificationException('The webhook payload could not be decoded.', $sigHeader);
        }

        return $object instanceof Event ? $object : Event::fromObject($object);
    }

    /**
     * Verifies the signature header without decoding the payload.
     *
     * @throws SignatureVerificationException
     */
    public static function verifyHeader(string $payload, string $sigHeader, string $secret, int $tolerance = self::DEFAULT_TOLERANCE): void
    {
        if ($secret === '') {
            throw new InvalidArgumentException('The webhook endpoint secret is empty. Use the whsec_... value shown when the endpoint was created.');
        }
        [$timestamp, $signatures] = self::parseHeader($sigHeader);
        if ($timestamp === null) {
            throw new SignatureVerificationException('Unable to read the timestamp from the Wthaiq-Signature header.', $sigHeader);
        }
        if ($signatures === []) {
            throw new SignatureVerificationException('No v1 signature found in the Wthaiq-Signature header.', $sigHeader);
        }

        $expected = self::computeSignature($timestamp, $payload, $secret);
        $match = false;
        foreach ($signatures as $signature) {
            if (hash_equals($expected, strtolower($signature))) {
                $match = true;
            }
        }
        if (!$match) {
            throw new SignatureVerificationException('The webhook signature does not match the payload. Check that you use the raw request body and the right endpoint secret.', $sigHeader);
        }
        if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
            throw new SignatureVerificationException(sprintf('The webhook timestamp is outside the tolerance of %d seconds.', $tolerance), $sigHeader);
        }
    }

    /**
     * Returns true when the header is valid for the payload, false otherwise.
     */
    public static function isValid(string $payload, string $sigHeader, string $secret, int $tolerance = self::DEFAULT_TOLERANCE): bool
    {
        try {
            self::verifyHeader($payload, $sigHeader, $secret, $tolerance);

            return true;
        } catch (SignatureVerificationException) {
            return false;
        }
    }

    /**
     * Hex HMAC-SHA256 of `"<timestamp>.<payload>"`, exactly as the platform computes it.
     */
    public static function computeSignature(string $timestamp, string $payload, string $secret): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    /**
     * Builds a valid `Wthaiq-Signature` header, for testing your own webhook handler.
     */
    public static function generateTestHeader(string $payload, string $secret, ?int $timestamp = null): string
    {
        $t = (string) ($timestamp ?? time());

        return 't=' . $t . ',v1=' . self::computeSignature($t, $payload, $secret);
    }

    /**
     * @return array{0: string|null, 1: list<string>}
     */
    private static function parseHeader(string $header): array
    {
        $timestamp = null;
        $signatures = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            if (\count($pair) !== 2) {
                continue;
            }
            [$key, $value] = [trim($pair[0]), trim($pair[1])];
            if ($key === 't' && ctype_digit($value)) {
                $timestamp = $value;
            } elseif ($key === 'v1' && $value !== '') {
                $signatures[] = $value;
            }
        }

        return [$timestamp, $signatures];
    }
}
