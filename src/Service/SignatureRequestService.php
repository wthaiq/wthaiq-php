<?php

declare(strict_types=1);

namespace Wthaiq\Service;

use Wthaiq\Exception\ApiConnectionException;
use Wthaiq\Exception\ApiException;
use Wthaiq\Exception\UnexpectedResponseException;
use Wthaiq\Exception\WthaiqException;
use Wthaiq\RequestOptions;
use Wthaiq\Resource\AutoPagingIterator;
use Wthaiq\Resource\BulkSend;
use Wthaiq\Resource\Certificate;
use Wthaiq\Resource\Collection;
use Wthaiq\Resource\Download;
use Wthaiq\Resource\Event;
use Wthaiq\Resource\Evidence;
use Wthaiq\Resource\Reminder;
use Wthaiq\Resource\SignatureRequest;

final class SignatureRequestService extends AbstractService
{
    /**
     * Creates a signature request and, unless `send` is false, sends it (`POST /signature_requests`).
     *
     * Required: `source` (`['type' => 'template', 'template_id' => 'tpl_…']`) and `signers`
     * (each with `name` and `email` or `phone`). See the README for every field.
     *
     * @param array<string, mixed>                     $params
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function create(array $params, array|RequestOptions|null $options = null): SignatureRequest
    {
        return $this->requestObject(SignatureRequest::class, 'POST', '/signature_requests', $params, $options);
    }

    /**
     * Creates one signature request per recipient (`POST /signature_requests/bulk`).
     * Same fields as {@see create()}, with `recipients` instead of `signers`.
     *
     * @param array<string, mixed>                     $params
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function createBulk(array $params, array|RequestOptions|null $options = null): BulkSend
    {
        return $this->requestObject(BulkSend::class, 'POST', '/signature_requests/bulk', $params, $options);
    }

    /**
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function retrieve(string $id, array|RequestOptions|null $options = null): SignatureRequest
    {
        return $this->requestObject(SignatureRequest::class, 'GET', self::path('/signature_requests/%s', $id), [], $options);
    }

    /**
     * One page of signature requests, newest first.
     *
     * @param array{status?: string, created_after?: int|string, created_before?: int|string, limit?: int, starting_after?: string}|array<string, mixed> $params
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @return Collection<SignatureRequest>
     */
    public function list(array $params = [], array|RequestOptions|null $options = null): Collection
    {
        return $this->requestCollection(SignatureRequest::class, '/signature_requests', $params, $options);
    }

    /**
     * Every signature request matching the filters, fetched page by page.
     *
     * @param array<string, mixed>                     $params
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @return AutoPagingIterator<SignatureRequest>
     */
    public function all(array $params = [], array|RequestOptions|null $options = null): AutoPagingIterator
    {
        return $this->autoPaging(SignatureRequest::class, '/signature_requests', $params, $options);
    }

    /**
     * Sends a draft. On a request that was already sent, sends the invitations again
     * (subject to the reminder limits).
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function send(string $id, array|RequestOptions|null $options = null): SignatureRequest
    {
        return $this->requestObject(SignatureRequest::class, 'POST', self::path('/signature_requests/%s/send', $id), [], $options);
    }

    /**
     * Reminds signers who have not signed yet (at most once per hour per signer, 10 per request).
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function remind(string $id, array|RequestOptions|null $options = null): Reminder
    {
        return $this->requestObject(Reminder::class, 'POST', self::path('/signature_requests/%s/reminders', $id), [], $options);
    }

    /**
     * Cancels a request that is not completed, declined or expired. Pending links stop working.
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function cancel(string $id, array|RequestOptions|null $options = null): SignatureRequest
    {
        return $this->requestObject(SignatureRequest::class, 'POST', self::path('/signature_requests/%s/cancel', $id), [], $options);
    }

    /**
     * The audit trail of the request (oldest first, up to 200 entries).
     *
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @return Collection<Event>
     */
    public function listEvents(string $id, array|RequestOptions|null $options = null): Collection
    {
        return $this->requestCollection(Event::class, self::path('/signature_requests/%s/events', $id), [], $options);
    }

    /**
     * Completion certificate with signers and audit trail (available at any status).
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function certificate(string $id, array|RequestOptions|null $options = null): Certificate
    {
        return $this->requestObject(Certificate::class, 'GET', self::path('/signature_requests/%s/certificate', $id), [], $options);
    }

    /**
     * Links and fingerprint of the final document. Only for completed requests.
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function download(string $id, array|RequestOptions|null $options = null): Download
    {
        return $this->requestObject(Download::class, 'GET', self::path('/signature_requests/%s/download', $id), [], $options);
    }

    /**
     * Downloads the final PDF and returns its bytes. The `X-File-Sha256` header is checked
     * against the content. Throws a ConflictException with code `pdf_not_ready` while the file
     * is being prepared.
     *
     * @param array<string, mixed>|RequestOptions|null $options
     *
     * @throws ApiException                When the API refuses (for example `pdf_not_ready`)
     * @throws ApiConnectionException      When the API cannot be reached
     * @throws UnexpectedResponseException When the content is not a PDF or its fingerprint does not match
     */
    public function downloadPdf(string $id, array|RequestOptions|null $options = null): string
    {
        $response = $this->requestor->request('GET', self::path('/signature_requests/%s/download/pdf', $id), [], RequestOptions::parse($options), 'application/pdf');
        $body = $response->body;
        if (!str_starts_with($body, '%PDF-')) {
            throw new UnexpectedResponseException('The Wthaiq API did not return a PDF file.', $response->status, substr($body, 0, 500));
        }
        $expected = $response->getHeader('x-file-sha256');
        if ($expected !== null && $expected !== '' && !hash_equals(strtolower($expected), hash('sha256', $body))) {
            throw new UnexpectedResponseException('The downloaded PDF does not match the X-File-Sha256 fingerprint sent by the API.', $response->status);
        }

        return $body;
    }

    /**
     * Downloads the final PDF to a file (written atomically) and returns the number of bytes written.
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function downloadPdfTo(string $id, string $path, array|RequestOptions|null $options = null): int
    {
        $bytes = $this->downloadPdf($id, $options);
        $dir = \dirname($path);
        if (!is_dir($dir) || !is_writable($dir)) {
            throw new WthaiqException(sprintf('The directory "%s" does not exist or is not writable.', $dir));
        }
        $tmp = $path . '.part-' . bin2hex(random_bytes(4));
        if (file_put_contents($tmp, $bytes) !== \strlen($bytes) || !rename($tmp, $path)) {
            @unlink($tmp);
            throw new WthaiqException(sprintf('Could not write the PDF to "%s".', $path));
        }

        return \strlen($bytes);
    }

    /**
     * The sealed evidence record (live, completed requests) or the unsealed test record.
     *
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function evidence(string $id, array|RequestOptions|null $options = null): Evidence
    {
        return $this->requestObject(Evidence::class, 'GET', self::path('/signature_requests/%s/evidence', $id), [], $options);
    }
}
