<?php

namespace EuroMail\Resources;

use EuroMail\Client;

final class Domains
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function all(): array
    {
        $response = $this->client->request('GET', '/v1/domains');

        return $response['data'] ?? [];
    }

    public function get(string $id): array
    {
        $response = $this->client->request('GET', '/v1/domains/' . rawurlencode($id));

        return $response['data'] ?? [];
    }
}
