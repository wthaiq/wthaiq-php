<?php

declare(strict_types=1);

namespace Wthaiq;

use Wthaiq\Exception\ApiException;
use Wthaiq\Exception\AuthenticationException;
use Wthaiq\Exception\ConflictException;
use Wthaiq\Exception\InvalidRequestException;
use Wthaiq\Exception\NotFoundException;
use Wthaiq\Exception\PaymentRequiredException;
use Wthaiq\Exception\PermissionException;
use Wthaiq\Exception\RateLimitException;
use Wthaiq\Exception\ServerException;
use Wthaiq\Exception\TooManySignersException;
use Wthaiq\Exception\ValidationException;
use Wthaiq\HttpClient\HttpResponse;

/**
 * Turns an HTTP error response into the matching exception.
 *
 * @internal
 */
final class ErrorFactory
{
    /**
     * @return array<string, mixed> The decoded `error` object, or an empty array
     */
    public static function errorBody(HttpResponse $response): array
    {
        if ($response->body === '') {
            return [];
        }
        try {
            $decoded = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING);
        } catch (\JsonException) {
            return [];
        }
        if (!\is_array($decoded) || !isset($decoded['error']) || !\is_array($decoded['error'])) {
            return [];
        }
        $out = [];
        foreach ($decoded['error'] as $k => $v) {
            $out[(string) $k] = $v;
        }

        return $out;
    }

    public static function fromResponse(HttpResponse $response): ApiException
    {
        $error = self::errorBody($response);
        $str = static fn (string $k): ?string => isset($error[$k]) && \is_scalar($error[$k]) && (string) $error[$k] !== '' ? (string) $error[$k] : null;

        $type = $str('type');
        $code = $str('code');
        $param = $str('param');
        $requestId = $str('request_id') ?? $response->getRequestId();
        $message = $str('message');
        if ($message === null) {
            $snippet = trim(substr(strip_tags($response->body), 0, 200));
            $message = sprintf('The Wthaiq API returned HTTP %d%s', $response->status, $snippet !== '' ? ': ' . $snippet : '.');
        }

        $status = $response->status;
        $class = match (true) {
            $status === 400 && $code === 'too_many_signers' => TooManySignersException::class,
            $status === 400 => ValidationException::class,
            $status === 401 => AuthenticationException::class,
            $status === 402 => PaymentRequiredException::class,
            $status === 403 => PermissionException::class,
            $status === 404 => NotFoundException::class,
            $status === 409 => ConflictException::class,
            $status === 429 => RateLimitException::class,
            $status >= 500 => ServerException::class,
            $status >= 400 => InvalidRequestException::class,
            default => ApiException::class,
        };

        return new $class(
            $message,
            $status,
            $type,
            $code,
            $param,
            $requestId,
            $error,
            $response->body,
            $response->getHeaders(),
        );
    }
}
