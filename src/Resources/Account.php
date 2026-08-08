<?php

namespace EuroMail\Resources;

use EuroMail\Client;

final class Account
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function get(): array
    {
        $response = $this->client->request('GET', '/v1/account');

        return $response['data'] ?? [];
    }
}
