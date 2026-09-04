<?php

namespace EuroMail\Resources;

final class Newsletters extends Resource
{
    /**
     * @param array<string, mixed> $params `list_id`, `subject`, `from_address`, `html_body` and/or `text_body`
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/newsletters', $params));
    }

    /**
     * Newsletters, newest first. Unlike the other list endpoints this one
     * pages by `limit` (max 100, default 20) and `offset`, and returns the
     * total count alongside the items instead of a pagination block.
     *
     * @param array<string, mixed> $filters e.g. `['limit' => 50, 'offset' => 100]`
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function all(array $filters = []): array
    {
        $response = $this->client->request('GET', $this->path('/v1/newsletters', $filters));
        $total = $response['total'] ?? 0;

        return [
            'data' => array_values($this->unwrap($response)),
            'total' => is_int($total) ? $total : (int) $total,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->unwrap($this->client->request('GET', '/v1/newsletters/' . $this->segment($id)));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->unwrap($this->client->request('PUT', '/v1/newsletters/' . $this->segment($id), $params));
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/newsletters/' . $this->segment($id));
    }

    /**
     * Start sending. The send runs in the background; poll the returned
     * `operation_id` through {@see Operations::get()} for progress.
     *
     * @return array<string, mixed> `{operation_id, total_recipients, message}`
     */
    public function send(string $id): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/newsletters/' . $this->segment($id) . '/send'));
    }
}
