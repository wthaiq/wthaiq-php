<?php

declare(strict_types=1);

namespace Wthaiq\Service;

use Wthaiq\Exception\InvalidArgumentException;
use Wthaiq\RequestOptions;
use Wthaiq\Resource\Document;
use Wthaiq\Util\Multipart;

final class DocumentService extends AbstractService
{
    private const MAX_BYTES = 20 * 1024 * 1024;

    /**
     * Uploads a PDF (`POST /documents`, multipart).
     *
     * `$file` is a file path, an \SplFileInfo, an open stream, or `['content' => $bytes, 'filename' => 'x.pdf']`.
     * `$params`: `kind` (`pdf` or `contract`), `field_map` (array of fields with `key`, `page`, `x`, `y`, `w`, `h`, ...)
     * and `filename` (overrides the uploaded file name).
     *
     * @param string|\SplFileInfo|resource|array{content: string, filename?: string} $file
     * @param array<string, mixed>                                                    $params
     * @param array<string, mixed>|RequestOptions|null                                $options
     */
    public function create(mixed $file, array $params = [], array|RequestOptions|null $options = null): Document
    {
        [$content, $filename] = self::readFile($file);
        if (\strlen($content) > self::MAX_BYTES) {
            throw new InvalidArgumentException('The file is larger than the 20 MB limit.');
        }
        if (!str_starts_with($content, '%PDF-')) {
            throw new InvalidArgumentException('The file must be a PDF.');
        }
        if (isset($params['filename'])) {
            if (!\is_string($params['filename']) || $params['filename'] === '') {
                throw new InvalidArgumentException('filename must be a non-empty string.');
            }
            $filename = $params['filename'];
        }

        $fields = [];
        if (isset($params['kind'])) {
            if (!\is_string($params['kind'])) {
                throw new InvalidArgumentException('kind must be a string.');
            }
            $fields['kind'] = $params['kind'];
        }
        if (isset($params['field_map'])) {
            $map = $params['field_map'];
            if (\is_array($map)) {
                $map = json_encode($map, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            }
            if (!\is_string($map)) {
                throw new InvalidArgumentException('field_map must be an array or a JSON string.');
            }
            $fields['field_map'] = $map;
        }
        $unknown = array_diff(array_keys($params), ['kind', 'field_map', 'filename']);
        if ($unknown !== []) {
            throw new InvalidArgumentException('Unknown document parameter(s): ' . implode(', ', $unknown) . '.');
        }

        [$body, $contentType] = Multipart::build($fields, ['file' => ['filename' => $filename, 'content' => $content, 'content_type' => 'application/pdf']]);
        $response = $this->requestor->requestRaw('POST', '/documents', $body, $contentType, RequestOptions::parse($options));

        return self::hydrate(Document::class, $response);
    }

    /**
     * @param array<string, mixed>|RequestOptions|null $options
     */
    public function retrieve(string $id, array|RequestOptions|null $options = null): Document
    {
        return $this->requestObject(Document::class, 'GET', self::path('/documents/%s', $id), [], $options);
    }

    /**
     * @return array{0: string, 1: string}
     */
    private static function readFile(mixed $file): array
    {
        if ($file instanceof \SplFileInfo) {
            $file = $file->getPathname();
        }
        if (\is_string($file)) {
            if (!is_file($file) || !is_readable($file)) {
                throw new InvalidArgumentException(sprintf('The file "%s" does not exist or is not readable.', $file));
            }
            $content = file_get_contents($file);
            if ($content === false) {
                throw new InvalidArgumentException(sprintf('Could not read "%s".', $file));
            }

            return [$content, basename($file)];
        }
        if (\is_resource($file)) {
            $content = stream_get_contents($file);
            if ($content === false) {
                throw new InvalidArgumentException('Could not read the given stream.');
            }
            $meta = stream_get_meta_data($file);
            $uri = $meta['uri'] ?? '';
            $name = $uri !== '' && !str_contains($uri, '://') ? basename($uri) : 'document.pdf';

            return [$content, $name];
        }
        if (\is_array($file) && isset($file['content']) && \is_string($file['content'])) {
            $name = isset($file['filename']) && \is_string($file['filename']) && $file['filename'] !== '' ? $file['filename'] : 'document.pdf';

            return [$file['content'], $name];
        }

        throw new InvalidArgumentException('The file must be a path, an SplFileInfo, a stream resource or an array with "content".');
    }
}
