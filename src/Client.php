<?php

namespace EuroMail;

use EuroMail\Exceptions\EuroMailException;
use EuroMail\Exceptions\TransportException;
use EuroMail\Http\CurlTransport;
use EuroMail\Http\Request;
use EuroMail\Http\StreamTransport;
use EuroMail\Http\TransportInterface;
use EuroMail\Resources\Account;
use EuroMail\Resources\Domains;
use EuroMail\Resources\Emails;

final class Client
{
    private string $apiKey;
    private string $baseUrl;
    private TransportInterface $transport;
    private int $timeout;
    private int $maxRetries;
    private int $maxRetryDelay;

    public Emails $emails;
    public Account $account;
    public Domains $domains;

    public function __construct(string $apiKey, array $options = [])
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($options['base_url'] ?? 'https://api.euromail.dev', '/');
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
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>
     */
    public function request(string $method, string $path, ?array $body = null): array
    {
        $url = $this->baseUrl . $path;
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Content-Type' => 'application/json',
            'User-Agent' => 'euromail-php/' . Version::SDK_VERSION . ' PHP/' . PHP_VERSION,
        ];

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
                if ($attempt < $this->maxRetries) {
                    $this->waitBeforeRetry($attempt, null);
                    $attempt++;
                    continue;
                }
                throw $exception;
            }

            if ($response->statusCode >= 200 && $response->statusCode < 300) {
                return $this->decodeBody($response->body);
            }

            $exception = EuroMailException::fromResponse($response);

            if ($attempt < $this->maxRetries && $exception->isRetryable()) {
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
     * @return array<string, mixed>
     */
    private function decodeBody(string $body): array
    {
        if ($body === '') {
            return [];
        }

        $decoded = json_decode($body, true);

        return is_array($decoded) ? $decoded : [];
    }
}
