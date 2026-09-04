<?php

namespace EuroMail\Resources;

use EuroMail\Paginator;

final class Suppressions extends Resource
{
    /**
     * Mirrors the server-side cap on POST /v1/suppressions/import — enforced
     * here too so an oversized import fails before a network round trip.
     */
    private const MAX_IMPORT_SIZE = 10_000;

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

        return $this->unwrap($this->client->request('POST', '/v1/suppressions', $body));
    }

    /**
     * @param array<string, mixed> $filters e.g. ['page' => 1, 'per_page' => 25]
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function all(array $filters = []): array
    {
        return $this->unwrapPage($this->client->request('GET', $this->path('/v1/suppressions', $filters)));
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

    public function delete(string $emailAddress): void
    {
        $this->client->request('DELETE', '/v1/suppressions/' . $this->segment($emailAddress));
    }

    /**
     * Bulk-import addresses onto the suppression list.
     *
     * @param string[] $emails
     * @return array<string, mixed> `{inserted: int, total_requested: int, invalid_addresses: string[]}`
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

        return $this->unwrap($this->client->request('POST', '/v1/suppressions/import', $body));
    }

    /**
     * Export the full suppression list as CSV (`email_address,reason,created_at`).
     */
    public function export(): string
    {
        return $this->client->requestRaw('GET', '/v1/suppressions/export');
    }
}
