<?php

namespace EuroMail\Resources;

use EuroMail\Client;
use EuroMail\Exceptions\EuroMailException;
use EuroMail\Paginator;

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
     * The record or list under `data`. A 2xx envelope without a `data` array
     * is an error, not an empty result: returning `[]` would let a caller
     * read a broken response as "nothing there".
     *
     * @param array<string, mixed> $response
     * @return array<string, mixed>
     */
    protected function unwrap(array $response): array
    {
        $data = $response['data'] ?? null;

        if (!is_array($data)) {
            throw new EuroMailException('Response envelope has no "data" object.');
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $response
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    protected function unwrapPage(array $response): array
    {
        $pagination = $response['pagination'] ?? [];

        return [
            'data' => array_values($this->unwrap($response)),
            'pagination' => is_array($pagination) ? $pagination : [],
        ];
    }

    /**
     * For the two list endpoints that answer `{data, total}` instead of a
     * pagination block.
     *
     * @param array<string, mixed> $response
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    protected function unwrapTotal(array $response): array
    {
        return [
            'data' => array_values($this->unwrap($response)),
            'total' => (int) ($response['total'] ?? 0),
        ];
    }

    /**
     * Walks every page of a list method. `$fetchPage` receives the filters
     * with `page` set and returns the `{data, pagination}` envelope.
     *
     * @param callable(array<string, mixed>): array{data: array<int, mixed>, pagination: array<string, mixed>} $fetchPage
     * @param array<string, mixed> $filters
     * @return \Generator<int, mixed>
     */
    protected function paginate(callable $fetchPage, array $filters): \Generator
    {
        yield from Paginator::iterate(static function (int $page) use ($fetchPage, $filters): array {
            return $fetchPage(['page' => $page] + $filters);
        });
    }
}
