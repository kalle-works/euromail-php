<?php

namespace EuroMail\Resources;

final class Account extends Resource
{
    /**
     * The authenticated account: plan, quota and usage.
     *
     * @return array<string, mixed>
     */
    public function get(): array
    {
        return $this->unwrap($this->client->request('GET', '/v1/account'));
    }

    /**
     * Everything the API holds about the account (GDPR data access), as a
     * JSON structure: the account record plus its domains, templates,
     * webhooks and recent emails. The server allows one export per hour.
     *
     * @return array<string, mixed>
     */
    public function export(): array
    {
        return $this->unwrap($this->client->request('GET', '/v1/account/export'));
    }

    /**
     * Permanently delete the account and everything under it: emails,
     * contacts, templates and API keys. Irreversible. The confirmation
     * header the server demands is sent for you.
     */
    public function delete(): void
    {
        $this->client->request('DELETE', '/v1/account', null, [
            'headers' => ['X-Confirm-Delete' => 'DELETE'],
        ]);
    }
}
