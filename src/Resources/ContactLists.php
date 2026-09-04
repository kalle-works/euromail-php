<?php

namespace EuroMail\Resources;


final class ContactLists extends Resource
{
    /**
     * Mirrors the server-side cap on one contacts request.
     */
    private const MAX_CONTACTS_PER_REQUEST = 1000;

    /**
     * @param array<string, mixed> $params `name`, optional `description` and `double_opt_in`
     * @return array<string, mixed>
     */
    public function create(array $params): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/contact-lists', $params));
    }

    /**
     * Every contact list on the account. This endpoint is not paginated.
     *
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return array_values($this->unwrap($this->client->request('GET', '/v1/contact-lists')));
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->unwrap($this->client->request('GET', '/v1/contact-lists/' . $this->segment($id)));
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function update(string $id, array $params): array
    {
        return $this->unwrap($this->client->request('PUT', '/v1/contact-lists/' . $this->segment($id), $params));
    }

    /**
     * Delete a list and every contact on it. Irreversible.
     */
    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/contact-lists/' . $this->segment($id));
    }

    /**
     * Add one contact. For many, use {@see addContacts()}.
     *
     * @param array<string, mixed> $params `email` and optional `metadata`
     * @return array<string, mixed>
     */
    public function addContact(string $listId, array $params): array
    {
        return $this->unwrap($this->client->request('POST', $this->contactsPath($listId), $params));
    }

    /**
     * Add up to 1000 contacts in one request. Validation is all-or-nothing:
     * one invalid address fails the whole request with a
     * ValidationException. Addresses already on the list are skipped, so
     * the result is `{inserted, total_requested}`.
     *
     * @param array<int, array<string, mixed>> $contacts each `['email' => ..., 'metadata' => [...]]`
     * @return array<string, mixed>
     */
    public function addContacts(string $listId, array $contacts): array
    {
        if ($contacts === []) {
            throw new \InvalidArgumentException('contacts must not be empty.');
        }
        if (count($contacts) > self::MAX_CONTACTS_PER_REQUEST) {
            throw new \InvalidArgumentException(sprintf(
                'A contacts request cannot exceed the server-side limit of %d contacts.',
                self::MAX_CONTACTS_PER_REQUEST
            ));
        }

        return $this->unwrap($this->client->request(
            'POST',
            $this->contactsPath($listId),
            ['contacts' => array_values($contacts)]
        ));
    }

    /**
     * @param array<string, mixed> $filters e.g. `['status' => 'subscribed', 'page' => 1, 'per_page' => 100]`
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function contacts(string $listId, array $filters = []): array
    {
        return $this->unwrapPage($this->client->request('GET', $this->path($this->contactsPath($listId), $filters)));
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Generator<int, array<string, mixed>>
     */
    public function iterateContacts(string $listId, array $filters = []): \Generator
    {
        yield from $this->paginate(fn (array $f): array => $this->contacts($listId, $f), $filters);
    }

    public function removeContact(string $listId, string $email): void
    {
        $this->client->request('DELETE', $this->contactsPath($listId) . '/' . $this->segment($email));
    }

    /**
     * The list's welcome-email settings: `enabled`, `subject`, body or
     * template, `from_address`, `delay_seconds`.
     *
     * @return array<string, mixed>
     */
    public function getWelcomeEmail(string $listId): array
    {
        return $this->unwrap($this->client->request('GET', $this->welcomeEmailPath($listId)));
    }

    /**
     * Configure the email sent automatically to new subscribers. `$params`
     * takes `enabled`, `subject`, either `html_body`/`text_body` or a
     * `template_id`, optional `from_address` and `delay_seconds`.
     *
     * @param array<string, mixed> $params
     * @return array<string, mixed> the updated contact list
     */
    public function configureWelcomeEmail(string $listId, array $params): array
    {
        return $this->unwrap($this->client->request('PUT', $this->welcomeEmailPath($listId), $params));
    }

    private function contactsPath(string $listId): string
    {
        return '/v1/contact-lists/' . $this->segment($listId) . '/contacts';
    }

    private function welcomeEmailPath(string $listId): string
    {
        return '/v1/contact-lists/' . $this->segment($listId) . '/welcome-email';
    }
}
