<?php

namespace EuroMail\Resources;

final class SignupForms extends Resource
{
    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/signup-forms', $params));
    }

    /**
     * Every signup form on the account. This endpoint is not paginated.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->unwrap($this->client->request('GET', '/v1/signup-forms')));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->unwrap($this->client->request('GET', '/v1/signup-forms/' . $this->segment($id)));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->unwrap($this->client->request('PUT', '/v1/signup-forms/' . $this->segment($id), $params));
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/signup-forms/' . $this->segment($id));
    }

    /**
     * Flip the form between active and inactive.
     *
     * @return array<string, mixed>
     */
    public function toggle(string $id): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/signup-forms/' . $this->segment($id) . '/toggle'));
    }
}
