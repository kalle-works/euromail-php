<?php

namespace EuroMail;

use EuroMail\Exceptions\EuroMailException;
use EuroMail\Exceptions\TransportException;
use EuroMail\Http\CurlTransport;
use EuroMail\Http\Request;
use EuroMail\Http\Response;
use EuroMail\Http\StreamTransport;
use EuroMail\Http\TransportInterface;
use EuroMail\Resources\Account;
use EuroMail\Resources\Analytics;
use EuroMail\Resources\ApiKeys;
use EuroMail\Resources\AuditLogs;
use EuroMail\Resources\ContactLists;
use EuroMail\Resources\DeadLetters;
use EuroMail\Resources\Domains;
use EuroMail\Resources\Emails;
use EuroMail\Resources\Inbound;
use EuroMail\Resources\InboundRoutes;
use EuroMail\Resources\Newsletters;
use EuroMail\Resources\Operations;
use EuroMail\Resources\SignupForms;
use EuroMail\Resources\SubAccounts;
use EuroMail\Resources\Suppressions;
use EuroMail\Resources\Templates;
use EuroMail\Resources\Webhooks;

final class Client
{
    public const API_KEY_ENV = 'EUROMAIL_API_KEY';
    public const DEFAULT_BASE_URL = 'https://api.euromail.dev';

    private string $apiKey;
    private string $baseUrl;
    private TransportInterface $transport;
    private int $timeout;
    private int $maxRetries;
    private int $maxRetryDelay;

    public Emails $emails;
    public Account $account;
    public Domains $domains;
    public Suppressions $suppressions;
    public Templates $templates;
    public Webhooks $webhooks;
    public ContactLists $contactLists;
    public Newsletters $newsletters;
    public SignupForms $signupForms;
    public Inbound $inbound;
    public InboundRoutes $inboundRoutes;
    public SubAccounts $subAccounts;
    public ApiKeys $apiKeys;
    public Analytics $analytics;
    public AuditLogs $auditLogs;
    public Operations $operations;
    public DeadLetters $deadLetters;

    /**
     * @param string|null $apiKey falls back to the `EUROMAIL_API_KEY` environment variable when null
     * @param array{
     *     base_url?: string,
     *     timeout?: int,
     *     max_retries?: int,
     *     max_retry_delay?: int,
     *     transport?: TransportInterface
     * } $options
     */
    public function __construct(?string $apiKey = null, array $options = [])
    {
        $this->apiKey = self::resolveApiKey($apiKey);
        $this->baseUrl = self::resolveBaseUrl($options['base_url'] ?? null);
        $this->timeout = $options['timeout'] ?? 15;
        $this->maxRetries = $options['max_retries'] ?? 0;
        $this->maxRetryDelay = $options['max_retry_delay'] ?? 30;

        if (isset($options['transport'])) {
            if (!$options['transport'] instanceof TransportInterface) {
                throw new \InvalidArgumentException(
                    'The "transport" option must be an instance of ' . TransportInterface::class . '.'
                );
            }
            $this->transport = $options['transport'];
        } elseif (extension_loaded('curl')) {
            $this->transport = new CurlTransport($this->timeout);
        } else {
            $this->transport = new StreamTransport($this->timeout);
        }

        $this->emails = new Emails($this);
        $this->account = new Account($this);
        $this->domains = new Domains($this);
        $this->suppressions = new Suppressions($this);
        $this->templates = new Templates($this);
        $this->webhooks = new Webhooks($this);
        $this->contactLists = new ContactLists($this);
        $this->newsletters = new Newsletters($this);
        $this->signupForms = new SignupForms($this);
        $this->inbound = new Inbound($this);
        $this->inboundRoutes = new InboundRoutes($this);
        $this->subAccounts = new SubAccounts($this);
        $this->apiKeys = new ApiKeys($this);
        $this->analytics = new Analytics($this);
        $this->auditLogs = new AuditLogs($this);
        $this->operations = new Operations($this);
        $this->deadLetters = new DeadLetters($this);
    }

    /**
     * A null or blank base_url means the production API, so a cleared config
     * field behaves like an absent one (SDK 1.x accepted any string). Only
     * http(s) URLs make sense otherwise: both transports speak HTTP, and the
     * stream transport reads its response headers from a variable PHP only
     * populates for the http wrapper. A trailing slash is dropped so paths
     * can be appended verbatim.
     */
    private static function resolveBaseUrl(?string $baseUrl): string
    {
        $baseUrl = rtrim(trim((string) $baseUrl), '/');

        if ($baseUrl === '') {
            return self::DEFAULT_BASE_URL;
        }

        if (preg_match('#^https?://[^/]+#i', $baseUrl) !== 1) {
            throw new \InvalidArgumentException('The "base_url" option must be an http(s) URL.');
        }

        return $baseUrl;
    }

    /**
     * An explicit key wins; otherwise the `EUROMAIL_API_KEY` environment
     * variable is used. A missing or blank key is rejected here rather than
     * surfacing later as a 401 on the first request. Surrounding whitespace
     * is dropped and control characters are refused: a key read from a
     * secret file with a trailing newline would otherwise end the
     * Authorization header early and turn the rest into extra headers.
     */
    private static function resolveApiKey(?string $apiKey): string
    {
        if ($apiKey === null) {
            $fromEnv = getenv(self::API_KEY_ENV);
            $apiKey = is_string($fromEnv) ? $fromEnv : null;
        }

        $apiKey = trim((string) $apiKey);

        if ($apiKey === '') {
            throw new \InvalidArgumentException(sprintf(
                'An API key is required. Pass it to the Client constructor or set the %s environment variable.',
                self::API_KEY_ENV
            ));
        }

        if (preg_match('/[\x00-\x1F\x7F]/', $apiKey) === 1) {
            throw new \InvalidArgumentException('The API key contains control characters.');
        }

        return $apiKey;
    }

    /**
     * `$options` takes `headers` (extra request headers) and `retry`
     * (default true). Pass `retry => false` for a request that is neither
     * idempotent nor keyed: the retry loop re-sends on transport failures,
     * and a timeout after the server already accepted such a request would
     * otherwise perform it twice.
     *
     * @param array<string, mixed>|null $body
     * @param array{headers?: array<string, string>, retry?: bool} $options
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $body = null, array $options = []): array
    {
        return $this->decodeBody($this->sendRequest($method, $path, $body, $options), $method, $path);
    }

    /**
     * Like {@see request()}, but returns the raw response body instead of
     * JSON-decoding it, for the CSV export endpoints (suppressions,
     * analytics).
     *
     * @param array<string, mixed>|null $body
     * @param array{headers?: array<string, string>, retry?: bool} $options
     */
    public function requestRaw(string $method, string $path, ?array $body = null, array $options = []): string
    {
        return $this->sendRequest($method, $path, $body, $options)->body;
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array{headers?: array<string, string>, retry?: bool} $options
     */
    private function sendRequest(string $method, string $path, ?array $body, array $options): Response
    {
        $url = $this->baseUrl . $path;
        $headers = ($options['headers'] ?? []) + [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'User-Agent' => 'euromail-php/' . Version::SDK_VERSION . ' PHP/' . PHP_VERSION,
        ];
        $maxRetries = ($options['retry'] ?? true) ? $this->maxRetries : 0;

        $encodedBody = null;
        if ($body !== null) {
            $encodedBody = json_encode($body);
            if ($encodedBody === false) {
                throw new \InvalidArgumentException(
                    'Failed to JSON-encode request body: ' . json_last_error_msg()
                );
            }
        }

        $request = new Request($method, $url, $headers, $encodedBody);

        $attempt = 0;

        while (true) {
            try {
                $response = $this->transport->send($request);
            } catch (TransportException $exception) {
                if ($attempt < $maxRetries) {
                    $this->waitBeforeRetry($attempt, null);
                    $attempt++;
                    continue;
                }
                throw $exception;
            }

            if ($response->statusCode >= 200 && $response->statusCode < 300) {
                return $response;
            }

            $exception = EuroMailException::fromResponse($response);

            if ($attempt < $maxRetries && $exception->isRetryable()) {
                $this->waitBeforeRetry($attempt, $exception->getRetryAfter());
                $attempt++;
                continue;
            }

            throw $exception;
        }
    }

    private function waitBeforeRetry(int $attempt, ?int $retryAfter): void
    {
        $delay = $retryAfter !== null ? max(0, $retryAfter) : (2 ** $attempt);
        $delay = min($delay, $this->maxRetryDelay);

        // Split into whole seconds handled by sleep() and, at most, a sub-second
        // remainder handled by usleep(). usleep() takes a microsecond count that
        // must not exceed 2^31-1; routing the bulk of any large delay through
        // sleep() instead keeps that value bounded regardless of how large a
        // retry-after header or max_retry_delay is configured.
        $wholeSeconds = (int) floor($delay);
        $remainderMicros = (int) round(($delay - $wholeSeconds) * 1_000_000);

        if ($wholeSeconds > 0) {
            sleep($wholeSeconds);
        }
        if ($remainderMicros > 0) {
            usleep($remainderMicros);
        }
    }

    /**
     * An empty or whitespace-only body (204, or a DELETE that returns
     * nothing) decodes to an empty array. Anything else must be a JSON
     * object: a 2xx whose body is not one (an HTML page from a proxy, a
     * truncated response) is raised as an exception, because silently
     * returning `[]` would let callers read missing fields as "no data" and
     * treat a broken response as a success.
     *
     * @return array<string, mixed>
     */
    private function decodeBody(Response $response, string $method, string $path): array
    {
        $body = trim($response->body);
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            $reason = json_last_error() === JSON_ERROR_NONE
                ? 'the body is not a JSON object'
                : json_last_error_msg();

            throw new EuroMailException(
                sprintf('Malformed response from %s %s: %s.', $method, $path, $reason),
                $response->statusCode,
                null,
                null,
                $response->getHeader('x-request-id')
            );
        }

        return $decoded;
    }
}
