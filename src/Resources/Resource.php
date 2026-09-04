<?php

namespace EuroMail\Resources;

use EuroMail\Client;

/**
 * Shared plumbing for the resource classes hanging off {@see Client}: path
 * building, query-string encoding, and unwrapping the API's `{"data": ...}`
 * and `{"data": [...], "pagination": {...}}` envelopes.
 */
abstract class Resource
{
    protected Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Appends `$filters` as a query string. An empty filter set yields the
     * bare path, so a request with no filters has no trailing `?`.
     *
     * @param array<string, mixed> $filters
     */
    protected function path(string $path, array $filters = []): string
    {
        $query = http_build_query($filters);

        return $query === '' ? $path : $path . '?' . $query;
    }

    protected function segment(string $value): string
    {
        return rawurlencode($value);
    }

    /**
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    protected function unwrap(array $response): array
    {
        $data = $response['data'] ?? [];

        return is_array($data) ? $data : [];
    }

    /**
     * @param array<string, mixed> $response
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    protected function unwrapPage(array $response): array
    {
        $data = $response['data'] ?? [];
        $pagination = $response['pagination'] ?? [];

        return [
            'data' => is_array($data) ? array_values($data) : [],
            'pagination' => is_array($pagination) ? $pagination : [],
        ];
    }
}
