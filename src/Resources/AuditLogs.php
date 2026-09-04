<?php

namespace EuroMail\Resources;


final class AuditLogs extends Resource
{
    /**
     * @param array<string, mixed> $filters e.g. `['page' => 1, 'per_page' => 50]`
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function all(array $filters = []): array
    {
        return $this->unwrapPage($this->client->request('GET', $this->path('/v1/audit-logs', $filters)));
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Generator<int, array<string, mixed>>
     */
    public function iterate(array $filters = []): \Generator
    {
        yield from $this->paginate(fn (array $f): array => $this->all($f), $filters);
    }
}
