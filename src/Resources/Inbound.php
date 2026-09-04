<?php

namespace EuroMail\Resources;

use EuroMail\Paginator;

/**
 * Emails received on inbound routes.
 */
final class Inbound extends Resource
{
    /**
     * @param array<string, mixed> $filters e.g. `['page' => 1, 'per_page' => 25]`
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function all(array $filters = []): array
    {
        return $this->unwrapPage($this->client->request('GET', $this->path('/v1/inbound', $filters)));
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
        return $this->unwrap($this->client->request('GET', '/v1/inbound/' . $this->segment($id)));
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/inbound/' . $this->segment($id));
    }
}
