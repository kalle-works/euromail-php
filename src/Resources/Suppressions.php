<?php

namespace EuroMail\Resources;

use EuroMail\Client;

final class Suppressions
{
    /**
     * Mirrors the server-side cap on POST /v1/suppressions/import — enforced
     * here too so an oversized import fails before a network round trip.
     */
    private const MAX_IMPORT_SIZE = 10_000;

    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    /**
     * Add a single address to the suppression list.
     *
     * @return array<string, mixed>
     */
    public function create(string $emailAddress, ?string $reason = null): array
    {
        $body = ['email_address' => $emailAddress];
        if ($reason !== null) {
            $body['reason'] = $reason;
        }

        $response = $this->client->request('POST', '/v1/suppressions', $body);

        return $response['data'] ?? [];
    }

    /**
     * @param array<string, mixed> $filters e.g. ['page' => 1, 'per_page' => 25]
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function all(array $filters = []): array
    {
        $query = http_build_query($filters);
        $path = '/v1/suppressions' . ($query !== '' ? '?' . $query : '');

        $response = $this->client->request('GET', $path);

        return [
            'data' => $response['data'] ?? [],
            'pagination' => $response['pagination'] ?? [],
        ];
    }

    public function delete(string $emailAddress): void
    {
        $this->client->request('DELETE', '/v1/suppressions/' . rawurlencode($emailAddress));
    }

    /**
     * Bulk-import addresses onto the suppression list.
     *
     * @param string[] $emails
     * @return array{inserted: int, total_requested: int, invalid_addresses: string[]}
     */
    public function import(array $emails, ?string $reason = null): array
    {
        if ($emails === []) {
            throw new \InvalidArgumentException('emails must not be empty.');
        }
        if (count($emails) > self::MAX_IMPORT_SIZE) {
            throw new \InvalidArgumentException(sprintf(
                'Import size cannot exceed the server-side limit of %d addresses.',
                self::MAX_IMPORT_SIZE
            ));
        }

        $body = ['emails' => array_values($emails)];
        if ($reason !== null) {
            $body['reason'] = $reason;
        }

        $response = $this->client->request('POST', '/v1/suppressions/import', $body);

        return $response['data'] ?? [];
    }

    /**
     * Export the full suppression list as CSV (`email_address,reason,created_at`).
     */
    public function export(): string
    {
        return $this->client->requestRaw('GET', '/v1/suppressions/export');
    }
}
