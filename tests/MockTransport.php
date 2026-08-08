<?php

namespace EuroMail\Tests;

use EuroMail\Http\Request;
use EuroMail\Http\Response;
use EuroMail\Http\TransportInterface;

final class MockTransport implements TransportInterface
{
    /** @var array<int, Response|\Throwable> */
    private array $queue = [];

    /** @var Request[] */
    private array $requests = [];

    public function queueResponse(Response $response): void
    {
        $this->queue[] = $response;
    }

    public function queueException(\Throwable $exception): void
    {
        $this->queue[] = $exception;
    }

    public function send(Request $request): Response
    {
        $this->requests[] = $request;

        if ($this->queue === []) {
            throw new \RuntimeException('MockTransport: no queued response available.');
        }

        $item = array_shift($this->queue);

        if ($item instanceof \Throwable) {
            throw $item;
        }

        return $item;
    }

    /** @return Request[] */
    public function getRequests(): array
    {
        return $this->requests;
    }

    public function getLastRequest(): ?Request
    {
        return $this->requests === [] ? null : $this->requests[count($this->requests) - 1];
    }

    public function getRequestCount(): int
    {
        return count($this->requests);
    }
}
