<?php

namespace EuroMail\Resources;

/**
 * Sending analytics. Every method takes the same `$query`: either a
 * `period` (`7d`, `30d`, `90d`) or an explicit `from`/`to` date range.
 * Responses are returned whole, since they carry the resolved `period`
 * next to `data`.
 */
final class Analytics extends Resource
{
    /**
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function overview(array $query = []): array
    {
        return $this->client->request('GET', $this->path('/v1/analytics/overview', $query));
    }

    /**
     * @param array<string, mixed> $query also accepts `metrics`, a comma-separated list
     * @return array<string, mixed>
     */
    public function timeseries(array $query = []): array
    {
        return $this->client->request('GET', $this->path('/v1/analytics/timeseries', $query));
    }

    /**
     * Per-recipient-domain breakdown.
     *
     * @param array<string, mixed> $query also accepts `limit`
     * @return array<string, mixed>
     */
    public function domains(array $query = []): array
    {
        return $this->client->request('GET', $this->path('/v1/analytics/domains', $query));
    }

    /**
     * Per-tag breakdown.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function tags(array $query = []): array
    {
        return $this->client->request('GET', $this->path('/v1/analytics/tags', $query));
    }

    /**
     * The account and all of its sub-accounts combined.
     *
     * @param array<string, mixed> $query
     * @return array<string, mixed>
     */
    public function aggregate(array $query = []): array
    {
        return $this->client->request('GET', $this->path('/v1/analytics/aggregate', $query));
    }

    /**
     * @param array<string, mixed> $query
     */
    public function export(array $query = []): string
    {
        return $this->client->requestRaw('GET', $this->path('/v1/analytics/export', $query));
    }
}
