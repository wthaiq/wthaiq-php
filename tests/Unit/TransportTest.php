<?php

declare(strict_types=1);

namespace Wthaiq\Tests\Unit;

use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Client\NetworkExceptionInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Wthaiq\Client;
use Wthaiq\Exception\ApiConnectionException;
use Wthaiq\HttpClient\CurlClient;
use Wthaiq\HttpClient\HttpRequest;
use Wthaiq\HttpClient\Psr18Client;

final class TransportTest extends TestCase
{
    /** @var resource|null */
    private static $server = null;
    private static int $port = 0;

    public static function setUpBeforeClass(): void
    {
        $router = sys_get_temp_dir() . '/wthaiq-sdk-router-' . getmypid() . '.php';
        file_put_contents($router, <<<'PHP'
            <?php
            $headers = [];
            foreach ($_SERVER as $k => $v) {
                if (strncmp($k, 'HTTP_', 5) === 0) {
                    $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = $v;
                }
            }
            if (str_starts_with($_SERVER['REQUEST_URI'], '/status/')) {
                http_response_code((int) substr($_SERVER['REQUEST_URI'], 8));
            }
            header('Content-Type: application/json');
            header('Wthaiq-Request-Id: req_local00000000001');
            header('X-Multi: a');
            header('X-Multi: b', false);
            echo json_encode(['object' => 'echo', 'method' => $_SERVER['REQUEST_METHOD'], 'uri' => $_SERVER['REQUEST_URI'], 'headers' => $headers, 'body' => file_get_contents('php://input')]);
            PHP);
        for ($i = 0; $i < 5 && self::$server === null; ++$i) {
            $port = random_int(20000, 40000);
            $process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, $router], [1 => ['file', \PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w'], 2 => ['file', \PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null', 'w']], $pipes);
            if (!\is_resource($process)) {
                continue;
            }
            for ($wait = 0; $wait < 50; ++$wait) {
                $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 0.1);
                if ($socket !== false) {
                    fclose($socket);
                    self::$server = $process;
                    self::$port = $port;
                    break;
                }
                usleep(100_000);
            }
            if (self::$server === null) {
                proc_terminate($process);
            }
        }
    }

    public static function tearDownAfterClass(): void
    {
        if (self::$server !== null) {
            proc_terminate(self::$server);
            proc_close(self::$server);
            self::$server = null;
        }
    }

    private function requireServer(): void
    {
        if (self::$server === null) {
            self::markTestSkipped('Could not start a local PHP server.');
        }
    }

    public function testCurlClientSendsAndReceives(): void
    {
        $this->requireServer();
        $curl = new CurlClient();
        $response = $curl->sendRequest(new HttpRequest('POST', 'http://127.0.0.1:' . self::$port . '/echo?x=1', ['Content-Type' => 'application/json', 'X-Test' => 'yes'], '{"a":"ب"}', 5.0, 2.0));

        self::assertSame(200, $response->status);
        self::assertSame('req_local00000000001', $response->getRequestId());
        self::assertSame(['a', 'b'], $response->getHeaders()['x-multi']);
        $echo = json_decode($response->body, true, 512, JSON_THROW_ON_ERROR);
        self::assertSame('POST', $echo['method']);
        self::assertSame('/echo?x=1', $echo['uri']);
        self::assertSame('yes', $echo['headers']['x-test']);
        self::assertSame('{"a":"ب"}', $echo['body']);

        $second = $curl->sendRequest(new HttpRequest('GET', 'http://127.0.0.1:' . self::$port . '/status/404'));
        self::assertSame(404, $second->status, 'The handle is reused and error statuses are returned, not thrown');
    }

    public function testFullClientOverCurl(): void
    {
        $this->requireServer();
        $client = new Client(self::KEY, ['base_url' => 'http://127.0.0.1:' . self::$port . '/api/v1', 'max_retries' => 0]);
        $echo = $client->account->retrieve();
        self::assertSame('echo', $echo->object);
        self::assertSame('/api/v1/', $echo->uri);
        self::assertSame('Bearer ' . self::KEY, $echo->headers->authorization);
        self::assertSame('2026-07-01', $echo->headers->{'wthaiq-version'});
        self::assertStringStartsWith('wthaiq-php/', $echo->headers->{'user-agent'});
    }

    public function testCurlClientReportsConnectionFailures(): void
    {
        $curl = new CurlClient();
        try {
            $curl->sendRequest(new HttpRequest('GET', 'http://127.0.0.1:1/', [], null, 2.0, 1.0));
            self::fail('Expected ApiConnectionException');
        } catch (ApiConnectionException $e) {
            self::assertStringContainsString('127.0.0.1', $e->getMessage());
        }
    }

    public function testPsr18Adapter(): void
    {
        $factory = new Psr17Factory();
        $psr = new class () implements ClientInterface {
            public ?RequestInterface $last = null;

            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                $this->last = $request;

                return new Response(201, ['Content-Type' => 'application/json', 'Wthaiq-Request-Id' => 'req_psr'], '{"id":"we_1","object":"webhook_endpoint","secret":"whsec_1"}');
            }
        };
        $client = new Client(self::KEY, ['http_client' => new Psr18Client($psr, $factory, $factory)]);
        $endpoint = $client->webhookEndpoints->create(['url' => 'https://acme.example/hooks'], ['idempotency_key' => 'k-1']);

        self::assertSame('whsec_1', $endpoint->secret);
        self::assertSame('req_psr', $endpoint->getLastResponse()?->getRequestId());
        self::assertNotNull($psr->last);
        self::assertSame('POST', $psr->last->getMethod());
        self::assertSame('https://wthaiq.com/api/v1/webhook_endpoints', (string) $psr->last->getUri());
        self::assertSame('k-1', $psr->last->getHeaderLine('Idempotency-Key'));
        self::assertSame('{"url":"https://acme.example/hooks"}', (string) $psr->last->getBody());
    }

    public function testPsr18NetworkErrorsBecomeConnectionExceptions(): void
    {
        $factory = new Psr17Factory();
        $psr = new class () implements ClientInterface {
            public function sendRequest(RequestInterface $request): ResponseInterface
            {
                throw new class ('connection refused', $request) extends \RuntimeException implements NetworkExceptionInterface {
                    public function __construct(string $message, private readonly RequestInterface $request)
                    {
                        parent::__construct($message);
                    }

                    public function getRequest(): RequestInterface
                    {
                        return $this->request;
                    }
                };
            }
        };
        $client = new Client(self::KEY, ['http_client' => new Psr18Client($psr, $factory, $factory), 'max_retries' => 0]);
        $this->expectException(ApiConnectionException::class);
        $this->expectExceptionMessage('connection refused');
        $client->account->retrieve();
    }
}
