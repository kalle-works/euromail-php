<?php

namespace EuroMail\Resources;


final class Templates extends Resource
{
    /**
     * @param array<string, mixed> $params `alias`, `name`, `subject`, and `html_body` and/or `text_body`
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/templates', $params));
    }

    /**
     * @param array<string, mixed> $filters e.g. `['page' => 1, 'per_page' => 25]`
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function all(array $filters = []): array
    {
        return $this->unwrapPage($this->client->request('GET', $this->path('/v1/templates', $filters)));
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Generator<int, array<string, mixed>>
     */
    public function iterate(array $filters = []): \Generator
    {
        yield from $this->paginate(fn (array $f): array => $this->all($f), $filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->unwrap($this->client->request('GET', '/v1/templates/' . $this->segment($id)));
    }

    /**
     * Full replacement, not a patch: `name` and `subject` are required,
     * `html_body` and `text_body` optional.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->unwrap($this->client->request('PUT', '/v1/templates/' . $this->segment($id), $params));
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/templates/' . $this->segment($id));
    }
}
