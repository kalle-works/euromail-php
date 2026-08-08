<?php

namespace EuroMail;

use EuroMail\Exceptions\EuroMailException;
use EuroMail\Exceptions\RateLimitException;
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

    public Emails $emails;
    public Account $account;
    public Domains $domains;

    public function __construct(string $apiKey, array $options = [])
    {
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($options['base_url'] ?? 'https://api.euromail.dev', '/');
        $this->timeout = $options['timeout'] ?? 15;
        $this->maxRetries = $options['max_retries'] ?? 0;

        if (isset($options['transport']) && $options['transport'] instanceof TransportInterface) {
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
        $encodedBody = $body !== null ? json_encode($body) : null;
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
                $retryAfter = $exception instanceof RateLimitException
                    ? $exception->getRetryAfter()
                    : null;
                $this->waitBeforeRetry($attempt, $retryAfter);
                $attempt++;
                continue;
            }

            throw $exception;
        }
    }

    private function waitBeforeRetry(int $attempt, ?int $retryAfter): void
    {
        if ($retryAfter !== null) {
            usleep(max(0, $retryAfter) * 1000000);
            return;
        }

        usleep((int) (2 ** $attempt) * 1000000);
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
