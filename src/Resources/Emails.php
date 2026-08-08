<?php

namespace EuroMail\Resources;

use EuroMail\Client;
use EuroMail\Idempotency;
use EuroMail\Types\EmailDetails;
use EuroMail\Types\SentEmail;

final class Emails
{
    private Client $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function send(array $params): SentEmail
    {
        $params = $this->withIdempotencyKey($params);

        $response = $this->client->request('POST', '/v1/emails', $params);

        return SentEmail::fromArray($response['data'] ?? []);
    }

    public function sendBatch(array $emails): array
    {
        if (count($emails) > 500) {
            throw new \InvalidArgumentException(
                'Batch size cannot exceed the server-side limit of 500 emails.'
            );
        }

        $emails = array_map([$this, 'withIdempotencyKey'], $emails);

        $response = $this->client->request('POST', '/v1/emails/batch', ['emails' => $emails]);

        $results = [];
        foreach ($response['data'] ?? [] as $item) {
            $results[] = SentEmail::fromArray($item);
        }

        return [
            'operation_id' => $response['operation_id'] ?? null,
            'data' => $results,
            'errors' => $response['errors'] ?? [],
        ];
    }

    public function get(string $id): EmailDetails
    {
        $response = $this->client->request('GET', '/v1/emails/' . rawurlencode($id));

        return EmailDetails::fromArray($response['data'] ?? []);
    }

    public function all(array $filters = []): array
    {
        $query = http_build_query($filters);
        $path = '/v1/emails' . ($query !== '' ? '?' . $query : '');

        $response = $this->client->request('GET', $path);
        $items = $response['data'] ?? [];

        $data = [];
        foreach ($items as $item) {
            $data[] = SentEmail::fromArray($item);
        }

        return [
            'data' => $data,
            'pagination' => $response['pagination'] ?? [],
        ];
    }

    public function cancel(string $id): SentEmail
    {
        $response = $this->client->request('POST', '/v1/emails/' . rawurlencode($id) . '/cancel');

        return SentEmail::fromArray($response['data'] ?? []);
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    private function withIdempotencyKey(array $params): array
    {
        if (!isset($params['idempotency_key']) || $params['idempotency_key'] === '') {
            $params['idempotency_key'] = Idempotency::generate();
        }

        return $params;
    }
}
