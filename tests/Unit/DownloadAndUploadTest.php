<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use Wthaiq\Exception\ConflictException;
use Wthaiq\Exception\InvalidArgumentException;
use Wthaiq\Exception\UnexpectedResponseException;
use Wthaiq\HttpClient\HttpResponse;
use Wthaiq\Resource\Document;

final class DownloadAndUploadTest extends TestCase
{
    private const PDF = "%PDF-1.4\n1 0 obj<</Type/Catalog>>endobj\ntrailer<<>>\n%%EOF\n";

    public function testDownloadPdfReturnsVerifiedBytes(): void
    {
        $client = $this->client([new HttpResponse(200, ['Content-Type' => 'application/pdf', 'X-File-Sha256' => hash('sha256', self::PDF)], self::PDF)]);
        self::assertSame(self::PDF, $client->signatureRequests->downloadPdf('sr_1'));
        $request = $this->http->lastRequest();
        self::assertSame('https://wthaiq.com/api/v1/signature_requests/sr_1/download/pdf', $request->url);
        self::assertSame('application/pdf', $request->getHeader('Accept'));
    }

    public function testDownloadPdfDetectsAFingerprintMismatch(): void
    {
        $client = $this->client([new HttpResponse(200, ['X-File-Sha256' => str_repeat('0', 64)], self::PDF)]);
        $this->expectException(UnexpectedResponseException::class);
        $client->signatureRequests->downloadPdf('sr_1');
    }

    public function testDownloadPdfRejectsNonPdfContent(): void
    {
        $client = $this->client([new HttpResponse(200, [], '{"object":"download"}')]);
        $this->expectException(UnexpectedResponseException::class);
        $client->signatureRequests->downloadPdf('sr_1');
    }

    public function testPdfNotReadyIsAConflict(): void
    {
        $client = $this->client([self::error(409, 'invalid_request_error', 'pdf_not_ready')]);
        try {
            $client->signatureRequests->downloadPdf('sr_1');
            self::fail('Expected ConflictException');
        } catch (ConflictException $e) {
            self::assertSame('pdf_not_ready', $e->getErrorCode());
        }
    }

    public function testDownloadPdfToWritesTheFile(): void
    {
        $client = $this->client([new HttpResponse(200, ['X-File-Sha256' => hash('sha256', self::PDF)], self::PDF)]);
        $path = sys_get_temp_dir() . '/wthaiq-sdk-' . bin2hex(random_bytes(4)) . '.pdf';
        try {
            self::assertSame(\strlen(self::PDF), $client->signatureRequests->downloadPdfTo('sr_1', $path));
            self::assertSame(self::PDF, file_get_contents($path));
            self::assertSame([], glob($path . '.part-*') ?: []);
        } finally {
            @unlink($path);
        }
    }

    public function testDocumentUploadIsMultipart(): void
    {
        $path = sys_get_temp_dir() . '/contract-' . bin2hex(random_bytes(4)) . '.pdf';
        file_put_contents($path, self::PDF);
        try {
            $client = $this->client([self::json(201, ['id' => 'doc_1', 'object' => 'document', 'kind' => 'contract'])]);
            $doc = $client->documents->create($path, [
                'kind' => 'contract',
                'field_map' => [['key' => 'sig', 'type' => 'signature', 'page' => 1, 'x' => 10, 'y' => 20, 'w' => 100, 'h' => 30]],
            ]);
        } finally {
            @unlink($path);
        }

        self::assertInstanceOf(Document::class, $doc);
        $request = $this->http->lastRequest();
        self::assertSame('POST', $request->method);
        self::assertSame('https://wthaiq.com/api/v1/documents', $request->url);
        $contentType = (string) $request->getHeader('Content-Type');
        self::assertMatchesRegularExpression('/^multipart\/form-data; boundary=(.+)$/', $contentType);
        self::assertNotNull($request->getHeader('Idempotency-Key'));
        $body = (string) $request->body;
        self::assertStringContainsString("name=\"kind\"\r\n\r\ncontract\r\n", $body);
        self::assertStringContainsString('"key":"sig"', $body);
        self::assertStringContainsString('name="file"; filename="' . basename($path) . '"', $body);
        self::assertStringContainsString("Content-Type: application/pdf\r\n\r\n" . self::PDF, $body);
    }

    public function testDocumentUploadFromContentAndStream(): void
    {
        $client = $this->client([
            self::json(201, ['id' => 'doc_1', 'object' => 'document']),
            self::json(201, ['id' => 'doc_2', 'object' => 'document']),
        ]);
        $client->documents->create(['content' => self::PDF, 'filename' => 'offer.pdf']);
        self::assertStringContainsString('filename="offer.pdf"', (string) $this->http->lastRequest()->body);

        $stream = fopen('php://memory', 'r+');
        self::assertIsResource($stream);
        fwrite($stream, self::PDF);
        rewind($stream);
        $client->documents->create($stream, ['filename' => 'from-stream.pdf']);
        self::assertStringContainsString('filename="from-stream.pdf"', (string) $this->http->lastRequest()->body);
    }

    private static function untyped(mixed $value): mixed
    {
        return $value;
    }

    public function testDocumentUploadValidation(): void
    {
        $client = $this->client();
        foreach ([
            static fn () => $client->documents->create('/no/such/file.pdf'),
            static fn () => $client->documents->create(['content' => 'not a pdf']),
            static fn () => $client->documents->create(['content' => self::PDF], ['unknown' => 1]),
            static fn () => $client->documents->create(['content' => self::PDF], ['kind' => 5]),
            static fn () => $client->documents->create(self::untyped(12)),
        ] as $call) {
            try {
                $call();
                self::fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
        self::assertSame([], $this->http->requests);
    }
}
