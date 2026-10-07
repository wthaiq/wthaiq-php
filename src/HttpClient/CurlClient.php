<?php

declare(strict_types=1);

namespace Wthaiq\HttpClient;

use Wthaiq\Exception\ApiConnectionException;

/**
 * Default transport built on ext-curl. Reuses one handle so connections are kept alive
 * between calls. TLS peer and host verification are always on.
 */
final class CurlClient implements HttpClientInterface
{
    private ?\CurlHandle $handle = null;

    /**
     * @param array<int, mixed> $curlOptions Extra CURLOPT_* values (for example a proxy or a CA bundle path)
     */
    public function __construct(private readonly array $curlOptions = [])
    {
        if (!\extension_loaded('curl')) {
            throw new \LogicException('The curl extension is required by Wthaiq\HttpClient\CurlClient.');
        }
    }

    public function __destruct()
    {
        if ($this->handle !== null) {
            curl_close($this->handle);
        }
    }

    public function sendRequest(HttpRequest $request): HttpResponse
    {
        if ($this->handle === null) {
            $handle = curl_init();
            if ($handle === false) {
                throw new ApiConnectionException('Could not initialise a cURL handle.');
            }
            $this->handle = $handle;
        } else {
            curl_reset($this->handle);
        }
        $ch = $this->handle;

        $headerLines = [];
        foreach ($request->headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        $headerLines[] = 'Expect:';

        $responseHeaders = [];
        $options = [
            CURLOPT_URL => $request->url,
            CURLOPT_CUSTOMREQUEST => $request->method,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS | CURLPROTO_HTTP,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_ENCODING => '',
            CURLOPT_TIMEOUT_MS => (int) max(1, round($request->timeout * 1000)),
            CURLOPT_CONNECTTIMEOUT_MS => (int) max(1, round($request->connectTimeout * 1000)),
            CURLOPT_HEADERFUNCTION => static function ($handle, string $line) use (&$responseHeaders): int {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, 'HTTP/')) {
                    if (str_starts_with($trimmed, 'HTTP/')) {
                        $responseHeaders = [];
                    }

                    return \strlen($line);
                }
                $pos = strpos($trimmed, ':');
                if ($pos !== false) {
                    $responseHeaders[strtolower(trim(substr($trimmed, 0, $pos)))][] = trim(substr($trimmed, $pos + 1));
                }

                return \strlen($line);
            },
        ];
        if ($request->method === 'HEAD') {
            $options[CURLOPT_NOBODY] = true;
        }
        if ($request->body !== null) {
            $options[CURLOPT_POSTFIELDS] = $request->body;
        }
        foreach ($this->curlOptions as $k => $v) {
            $options[$k] = $v;
        }
        curl_setopt_array($ch, $options);

        $body = curl_exec($ch);
        if ($body === false || !\is_string($body)) {
            $errno = curl_errno($ch);
            $error = curl_error($ch);
            throw new ApiConnectionException(
                sprintf('Could not reach the Wthaiq API (%s): %s', self::host($request->url), $error !== '' ? $error : 'unknown cURL error ' . $errno),
                $errno === CURLE_OPERATION_TIMEDOUT,
            );
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($status, $responseHeaders, $body);
    }

    private static function host(string $url): string
    {
        $host = parse_url($url, PHP_URL_HOST);

        return \is_string($host) ? $host : $url;
    }
}
