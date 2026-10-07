<?php

declare(strict_types=1);

namespace Wthaiq;

use Wthaiq\Exception\InvalidArgumentException;
use Wthaiq\HttpClient\CurlClient;
use Wthaiq\HttpClient\HttpClientInterface;
use Wthaiq\Service\AccountService;
use Wthaiq\Service\BalanceService;
use Wthaiq\Service\DocumentService;
use Wthaiq\Service\EventService;
use Wthaiq\Service\FlowService;
use Wthaiq\Service\IdentityVerificationService;
use Wthaiq\Service\SessionService;
use Wthaiq\Service\SignatureRequestService;
use Wthaiq\Service\SignerService;
use Wthaiq\Service\TemplateService;
use Wthaiq\Service\UsageService;
use Wthaiq\Service\VerificationService;
use Wthaiq\Service\WebhookEndpointService;

/**
 * Entry point of the library.
 *
 * ```php
 * $wthaiq = new \Wthaiq\Client(getenv('WTHAIQ_API_KEY'));
 * $request = $wthaiq->signatureRequests->retrieve('sr_...');
 * ```
 *
 * Options (second argument, or a single array including `api_key`):
 *
 * - `base_url` (string): default `https://wthaiq.com/api/v1`
 * - `timeout` (float seconds): total time per attempt, default 60
 * - `connect_timeout` (float seconds): default 10
 * - `max_retries` (int): automatic retries of transient failures, default 2
 * - `api_version` (string): sent as `Wthaiq-Version`, default {@see self::API_VERSION}
 * - `app_info` (array{name: string, version?: string, url?: string}): appended to the User-Agent
 * - `http_client` ({@see HttpClientInterface}): transport, default {@see CurlClient}
 * - `retry_policy` ({@see RetryPolicy}): backoff settings
 */
final class Client
{
    public const VERSION = '1.0.0';
    public const API_VERSION = '2026-07-01';
    public const DEFAULT_BASE_URL = 'https://wthaiq.com/api/v1';

    private const OPTIONS = ['api_key', 'base_url', 'timeout', 'connect_timeout', 'max_retries', 'api_version', 'app_info', 'http_client', 'retry_policy'];

    public readonly AccountService $account;
    public readonly UsageService $usage;
    public readonly BalanceService $balance;
    public readonly TemplateService $templates;
    public readonly SignatureRequestService $signatureRequests;
    public readonly SessionService $sessions;
    public readonly SignerService $signers;
    public readonly DocumentService $documents;
    public readonly IdentityVerificationService $identityVerifications;
    public readonly VerificationService $verifications;
    public readonly WebhookEndpointService $webhookEndpoints;
    public readonly EventService $events;
    public readonly FlowService $flows;

    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly ApiRequestor $requestor;

    /**
     * @param string|array<string, mixed> $apiKeyOrConfig An API key, or a config array with `api_key`
     * @param array<string, mixed>        $options
     */
    public function __construct(string|array $apiKeyOrConfig, array $options = [])
    {
        $config = \is_array($apiKeyOrConfig) ? $apiKeyOrConfig + $options : ['api_key' => $apiKeyOrConfig] + $options;
        $unknown = array_diff(array_keys($config), self::OPTIONS);
        if ($unknown !== []) {
            throw new InvalidArgumentException(sprintf('Unknown client option(s): %s. Allowed: %s.', implode(', ', $unknown), implode(', ', self::OPTIONS)));
        }

        $this->apiKey = self::validateApiKey($config['api_key'] ?? null);
        $this->baseUrl = self::validateBaseUrl($config['base_url'] ?? self::DEFAULT_BASE_URL);
        $timeout = self::positiveFloat($config['timeout'] ?? 60.0, 'timeout');
        $connectTimeout = self::positiveFloat($config['connect_timeout'] ?? 10.0, 'connect_timeout');
        $maxRetries = $config['max_retries'] ?? 2;
        if (!\is_int($maxRetries) || $maxRetries < 0) {
            throw new InvalidArgumentException('max_retries must be a non-negative integer.');
        }
        $apiVersion = $config['api_version'] ?? self::API_VERSION;
        if (!\is_string($apiVersion) || $apiVersion === '') {
            throw new InvalidArgumentException('api_version must be a non-empty string.');
        }
        $httpClient = $config['http_client'] ?? new CurlClient();
        if (!$httpClient instanceof HttpClientInterface) {
            throw new InvalidArgumentException('http_client must implement ' . HttpClientInterface::class . '.');
        }
        $retryPolicy = $config['retry_policy'] ?? new RetryPolicy();
        if (!$retryPolicy instanceof RetryPolicy) {
            throw new InvalidArgumentException('retry_policy must be an instance of ' . RetryPolicy::class . '.');
        }

        $this->requestor = new ApiRequestor(
            $this->apiKey,
            $this->baseUrl,
            $httpClient,
            $timeout,
            $connectTimeout,
            $maxRetries,
            $apiVersion,
            self::userAgent($config['app_info'] ?? null),
            $retryPolicy,
        );

        $this->account = new AccountService($this->requestor);
        $this->usage = new UsageService($this->requestor);
        $this->balance = new BalanceService($this->requestor);
        $this->templates = new TemplateService($this->requestor);
        $this->signatureRequests = new SignatureRequestService($this->requestor);
        $this->sessions = new SessionService($this->requestor);
        $this->signers = new SignerService($this->requestor);
        $this->documents = new DocumentService($this->requestor);
        $this->identityVerifications = new IdentityVerificationService($this->requestor);
        $this->verifications = new VerificationService($this->requestor);
        $this->webhookEndpoints = new WebhookEndpointService($this->requestor);
        $this->events = new EventService($this->requestor);
        $this->flows = new FlowService($this->requestor);
    }

    /** True for `sk_test_` and `pk_test_` keys: nothing is charged or delivered to third parties. */
    public function isTestMode(): bool
    {
        return str_starts_with($this->apiKey, 'sk_test_') || str_starts_with($this->apiKey, 'pk_test_');
    }

    public function isLiveMode(): bool
    {
        return !$this->isTestMode();
    }

    /** True for publishable (`pk_`) keys, which only reach a few read/console routes. */
    public function isPublishableKey(): bool
    {
        return str_starts_with($this->apiKey, 'pk_');
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    private static function validateApiKey(mixed $key): string
    {
        if (!\is_string($key) || trim($key) === '') {
            throw new InvalidArgumentException('An API key is required. Create one at https://developers.wthaiq.com and pass it as new Wthaiq\Client(\'sk_...\').');
        }
        $key = trim($key);
        if (!preg_match('/^(sk|pk)_[A-Za-z0-9_]+$/', $key)) {
            throw new InvalidArgumentException('The API key must start with sk_ (secret), sk_test_ (test), pk_ or pk_test_ (publishable).');
        }

        return $key;
    }

    private static function validateBaseUrl(mixed $url): string
    {
        if (!\is_string($url) || !preg_match('~^https?://[^\s/?#]+(/[^\s?#]*)?$~i', $url)) {
            throw new InvalidArgumentException('base_url must be an absolute http(s) URL such as https://wthaiq.com/api/v1.');
        }

        return rtrim($url, '/');
    }

    private static function positiveFloat(mixed $value, string $name): float
    {
        if ((!\is_int($value) && !\is_float($value)) || $value <= 0) {
            throw new InvalidArgumentException($name . ' must be a positive number of seconds.');
        }

        return (float) $value;
    }

    private static function userAgent(mixed $appInfo): string
    {
        $ua = 'wthaiq-php/' . self::VERSION . ' PHP/' . PHP_VERSION;
        if ($appInfo === null) {
            return $ua;
        }
        if (!\is_array($appInfo) || !isset($appInfo['name']) || !\is_string($appInfo['name']) || $appInfo['name'] === '') {
            throw new InvalidArgumentException('app_info must be an array with a non-empty name.');
        }
        $part = preg_replace('/[^A-Za-z0-9._\-]+/', '-', $appInfo['name']);
        if (isset($appInfo['version']) && \is_string($appInfo['version']) && $appInfo['version'] !== '') {
            $part .= '/' . preg_replace('/[^A-Za-z0-9._\-]+/', '-', $appInfo['version']);
        }
        if (isset($appInfo['url']) && \is_string($appInfo['url']) && $appInfo['url'] !== '') {
            $part .= ' (' . preg_replace('/[\s()]+/', '', $appInfo['url']) . ')';
        }

        return $ua . ' ' . $part;
    }
}
