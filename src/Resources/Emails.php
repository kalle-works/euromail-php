<?php

namespace EuroMail\Resources;

use EuroMail\Idempotency;
use EuroMail\Types\EmailDetails;
use EuroMail\Types\SentEmail;

final class Emails extends Resource
{
    /**
     * Mirrors the server-side cap on POST /v1/emails/batch.
     */
    private const MAX_BATCH_SIZE = 500;

    /**
     * @param array<string, mixed> $params
     */
    public function send(array $params): SentEmail
    {
        $params = $this->withIdempotencyKey($params);

        return SentEmail::fromArray($this->unwrap($this->client->request('POST', '/v1/emails', $params)));
    }

    /**
     * @param array<int, array<string, mixed>> $emails
     * @return array{operation_id: string|null, data: SentEmail[], errors: array<int, mixed>}
     */
    public function sendBatch(array $emails): array
    {
        if (count($emails) > self::MAX_BATCH_SIZE) {
            throw new \InvalidArgumentException(sprintf(
                'Batch size cannot exceed the server-side limit of %d emails.',
                self::MAX_BATCH_SIZE
            ));
        }

        $emails = array_map([$this, 'withIdempotencyKey'], array_values($emails));

        $response = $this->client->request('POST', '/v1/emails/batch', ['emails' => $emails]);

        $results = [];
        foreach ($this->unwrap($response) as $item) {
            $results[] = SentEmail::fromArray(is_array($item) ? $item : []);
        }

        $operationId = $response['operation_id'] ?? null;
        $errors = $response['errors'] ?? [];

        return [
            'operation_id' => is_string($operationId) ? $operationId : null,
            'data' => $results,
            'errors' => is_array($errors) ? $errors : [],
        ];
    }

    /**
     * Send one message to every subscribed contact on a list. `$params` takes
     * `contact_list_id`, `from_address` and either `subject` + `html_body` /
     * `text_body` or a `template_alias`.
     *
     * The endpoint has no idempotency key, so this request is never retried
     * automatically: a timeout after the server had accepted it would send
     * the whole list a second copy. Check the returned `operation_id` (see
     * {@see Operations::get()}) before deciding to resend.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function broadcast(array $params): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/emails/broadcast', $params, ['retry' => false]));
    }

    public function get(string $id): EmailDetails
    {
        return EmailDetails::fromArray($this->unwrap($this->client->request('GET', '/v1/emails/' . $this->segment($id))));
    }

    /**
     * @param array<string, mixed> $filters e.g. `['status' => 'delivered', 'page' => 2, 'per_page' => 50]`
     * @return array{data: SentEmail[], pagination: array<string, mixed>}
     */
    public function all(array $filters = []): array
    {
        $page = $this->unwrapPage($this->client->request('GET', $this->path('/v1/emails', $filters)));

        $data = [];
        foreach ($page['data'] as $item) {
            $data[] = SentEmail::fromArray($item);
        }

        return [
            'data' => $data,
            'pagination' => $page['pagination'],
        ];
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Generator<int, SentEmail>
     */
    public function iterate(array $filters = []): \Generator
    {
        yield from $this->paginate(fn (array $f): array => $this->all($f), $filters);
    }

    public function cancel(string $id): SentEmail
    {
        return SentEmail::fromArray($this->unwrap($this->client->request('POST', '/v1/emails/' . $this->segment($id) . '/cancel')));
    }

    /**
     * Per-link click statistics for an email (requires click tracking).
     *
     * @return array<int, array<string, mixed>>
     */
    public function links(string $id): array
    {
        return array_values($this->unwrap($this->client->request('GET', '/v1/emails/' . $this->segment($id) . '/links')));
    }

    /**
     * Check an address for valid syntax, a real MX record, and disposable or
     * role-based patterns. Returns the verdict as the API sends it, with
     * `valid` as the headline field.
     *
     * @return array<string, mixed>
     */
    public function validate(string $email): array
    {
        return $this->client->request('POST', '/v1/validate', ['email' => $email]);
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
