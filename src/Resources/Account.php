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
     * Export the account's data as CSV (GDPR data access).
     */
    public function export(): string
    {
        return $this->client->requestRaw('GET', '/v1/account/export');
    }

    /**
     * Permanently delete the account and everything under it: emails,
     * contacts, templates and API keys. Irreversible.
     */
    public function delete(): void
    {
        $this->client->request('DELETE', '/v1/account');
    }
}
