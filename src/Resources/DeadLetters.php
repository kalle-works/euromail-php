<?php

namespace EuroMail\Resources;

/**
 * Emails that exhausted every delivery attempt and were parked for review.
 */
final class DeadLetters extends Resource
{
    /**
     * The most recent dead letters, newest first.
     *
     * @return array{data: array<int, array<string, mixed>>, total: int}
     */
    public function all(?int $count = null): array
    {
        return $this->unwrapTotal($this->client->request(
            'GET',
            $this->path('/v1/dead-letters', $count === null ? [] : ['count' => $count])
        ));
    }

    /**
     * Put the email back on the send queue.
     */
    public function retry(string $id): void
    {
        $this->client->request('POST', '/v1/dead-letters/' . $this->segment($id) . '/retry');
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/dead-letters/' . $this->segment($id));
    }
}
