<?php

declare(strict_types=1);

namespace Wthaiq\Service;

use Wthaiq\Exception\NotFoundException;
use Wthaiq\HttpClient\HttpResponse;
use Wthaiq\RequestOptions;
use Wthaiq\Resource\ObjectFactory;
use Wthaiq\Resource\Verification;

final class VerificationService extends AbstractService
{
    /**
     * Looks up a verification reference such as `WTQ-1A2B3C4D5E6F`.
     *
     * An unknown reference does not throw: the result has `found === false` and `status === 'not_found'`.
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function retrieve(string $reference, array|RequestOptions|null $options = null): Verification
    {
        try {
            return $this->requestObject(Verification::class, 'GET', self::path('/verifications/%s', $reference), [], $options);
        } catch (NotFoundException $e) {
            $response = new HttpResponse($e->getHttpStatus(), $e->getHttpHeaders(), $e->getHttpBody());
            $decoded = json_decode($e->getHttpBody(), true);
            if (\is_array($decoded) && ($decoded['object'] ?? null) === 'verification' && !isset($decoded['error'])) {
                $object = ObjectFactory::fromResponse($response);

                return $object instanceof Verification ? $object : Verification::fromObject($object);
            }
            throw $e;
        }
    }
}
