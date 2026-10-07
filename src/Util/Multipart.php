<?php

declare(strict_types=1);

namespace Wthaiq\Util;

/**
 * Builds a multipart/form-data body independent of the transport.
 *
 * @internal
 */
final class Multipart
{
    /**
     * @param array<string, string>                                                   $fields
     * @param array<string, array{filename: string, content: string, content_type: string}> $files
     *
     * @return array{0: string, 1: string} The body and the Content-Type header value
     */
    public static function build(array $fields, array $files): array
    {
        $boundary = '----wthaiq' . bin2hex(random_bytes(12));
        $body = '';
        foreach ($fields as $name => $value) {
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="' . self::quote($name) . "\"\r\n\r\n"
                . $value . "\r\n";
        }
        foreach ($files as $name => $file) {
            $body .= '--' . $boundary . "\r\n"
                . 'Content-Disposition: form-data; name="' . self::quote($name) . '"; filename="' . self::quote($file['filename']) . "\"\r\n"
                . 'Content-Type: ' . $file['content_type'] . "\r\n\r\n"
                . $file['content'] . "\r\n";
        }
        $body .= '--' . $boundary . "--\r\n";

        return [$body, 'multipart/form-data; boundary=' . $boundary];
    }

    private static function quote(string $value): string
    {
        return str_replace(['\\', '"', "\r", "\n"], ['\\\\', '\\"', '', ''], $value);
    }
}
