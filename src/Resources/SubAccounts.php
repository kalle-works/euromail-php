<?php

namespace EuroMail\Resources;

use EuroMail\Paginator;

final class SubAccounts extends Resource
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/accounts', $params));
    }

    /**
     * @param array<string, mixed> $filters e.g. `['page' => 1, 'per_page' => 25]`
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function all(array $filters = []): array
    {
        return $this->unwrapPage($this->client->request('GET', $this->path('/v1/accounts', $filters)));
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
        return $this->unwrap($this->client->request('GET', '/v1/accounts/' . $this->segment($id)));
    }

    /**
     * Partial update: only the fields given in `$params` change.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->unwrap($this->client->request('PATCH', '/v1/accounts/' . $this->segment($id), $params));
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/accounts/' . $this->segment($id));
    }

    /**
     * Sending analytics for one sub-account. Same shape as
     * {@see Analytics::overview()}.
     *
     * @param array<string, mixed> $query e.g. `['period' => '30d']` or `['from' => ..., 'to' => ...]`
     * @return array<string, mixed>
     */
    public function analytics(string $id, array $query = []): array
    {
        return $this->client->request('GET', $this->path('/v1/accounts/' . $this->segment($id) . '/analytics', $query));
    }

    /**
     * Mint an API key scoped to a sub-account. The key material is only
     * returned in this response.
     *
     * @param array<string, mixed> $params `name` and optional `scopes`
     * @return array<string, mixed>
     */
    public function createApiKey(string $id, array $params): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/accounts/' . $this->segment($id) . '/api-keys', $params));
    }
}
