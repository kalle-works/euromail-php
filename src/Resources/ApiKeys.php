<?php

namespace EuroMail\Resources;

final class ApiKeys extends Resource
{
    /**
     * Create an API key. The key material is only returned in this response.
     *
     * @param array<string, mixed> $params `name` and optional `scopes` (e.g. `['emails:send']`)
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/api-keys', $params));
    }

    /**
     * Every key on the account, without the secret material.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->unwrap($this->client->request('GET', '/v1/api-keys')));
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/api-keys/' . $this->segment($id));
    }
}
