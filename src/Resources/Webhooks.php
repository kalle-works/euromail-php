<?php

namespace EuroMail\Resources;

use EuroMail\Paginator;

/**
 * Webhook subscriptions. For verifying the signature on an incoming webhook
 * request see {@see \EuroMail\Webhooks\WebhookSignature}.
 */
final class Webhooks extends Resource
{
    /**
     * Subscribe a URL to events. The response includes the signing secret
     * used to verify deliveries; it is only returned once.
     *
     * @param array<string, mixed> $params `url` and `events` (e.g. `['delivered', 'bounced']`)
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/webhooks', $params));
    }

    /**
     * @param array<string, mixed> $filters e.g. `['page' => 1, 'per_page' => 25]`
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function all(array $filters = []): array
    {
        return $this->unwrapPage($this->client->request('GET', $this->path('/v1/webhooks', $filters)));
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Generator<int, array<string, mixed>>
     */
    public function iterate(array $filters = []): \Generator
    {
        yield from Paginator::iterate(function (int $page) use ($filters): array {
            return $this->all(['page' => $page] + $filters);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->unwrap($this->client->request('GET', '/v1/webhooks/' . $this->segment($id)));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->unwrap($this->client->request('PUT', '/v1/webhooks/' . $this->segment($id), $params));
    }

    /**
     * Deliver a synthetic event to the webhook URL to confirm it is reachable.
     *
     * @return array<string, mixed>
     */
    public function test(string $id): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/webhooks/' . $this->segment($id) . '/test'));
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/webhooks/' . $this->segment($id));
    }
}
