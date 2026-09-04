<?php

namespace EuroMail\Resources;


final class Domains extends Resource
{
    /**
     * Register a sending domain. Returns the full response: the domain under
     * `data`, carrying the DNS records (SPF, DKIM, DMARC) to publish before
     * {@see verify()} can succeed, plus an optional `warnings` list. The
     * sending subdomain is changed afterwards with
     * {@see setSendingSubdomain()}.
     *
     * @return array<string, mixed>
     */
    public function create(string $domain): array
    {
        return $this->client->request('POST', '/v1/domains', ['domain' => $domain]);
    }

    /**
     * The domains on the first page (or the page selected in `$filters`),
     * without the pagination envelope. Kept for callers written against
     * SDK 1.x; {@see page()} returns the envelope and {@see iterate()} walks
     * every page.
     *
     * @param array<string, mixed> $filters e.g. `['page' => 1, 'per_page' => 25]`
     * @return array<int, array<string, mixed>>
     */
    public function all(array $filters = []): array
    {
        return $this->page($filters)['data'];
    }

    /**
     * @param array<string, mixed> $filters e.g. `['page' => 1, 'per_page' => 25]`
     * @return array{data: array<int, array<string, mixed>>, pagination: array<string, mixed>}
     */
    public function page(array $filters = []): array
    {
        return $this->unwrapPage($this->client->request('GET', $this->path('/v1/domains', $filters)));
    }

    /**
     * @param array<string, mixed> $filters
     * @return \Generator<int, array<string, mixed>>
     */
    public function iterate(array $filters = []): \Generator
    {
        yield from $this->paginate(fn (array $f): array => $this->page($f), $filters);
    }

    /**
     * @return array<string, mixed>
     */
    public function get(string $id): array
    {
        return $this->unwrap($this->client->request('GET', '/v1/domains/' . $this->segment($id)));
    }

    /**
     * Check DNS and mark the domain verified once SPF/DKIM/DMARC are
     * published. Safe to call repeatedly while waiting for propagation.
     *
     * @return array<string, mixed>
     */
    public function verify(string $id): array
    {
        return $this->unwrap($this->client->request('POST', '/v1/domains/' . $this->segment($id) . '/verify'));
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/domains/' . $this->segment($id));
    }

    /**
     * Change the subdomain sending mail for this domain. DNS records are
     * regenerated, so the domain must be verified again.
     *
     * @return array<string, mixed>
     */
    public function setSendingSubdomain(string $id, string $sendingSubdomain): array
    {
        return $this->unwrap($this->client->request(
            'PUT',
            '/v1/domains/' . $this->segment($id) . '/sending-subdomain',
            ['sending_subdomain' => $sendingSubdomain]
        ));
    }

    /**
     * Set the vanity domain used for open/click tracking links. Returns the
     * full response: the domain under `data` plus the `cname_target` to point
     * the tracking domain at.
     *
     * @return array<string, mixed>
     */
    public function setTrackingDomain(string $id, string $trackingDomain): array
    {
        return $this->client->request(
            'PUT',
            '/v1/domains/' . $this->segment($id) . '/tracking-domain',
            ['tracking_domain' => $trackingDomain]
        );
    }

    /**
     * Verify the tracking domain's CNAME. Returns the full response: the
     * domain under `data` plus `tracking_check` with `verified` and `detail`.
     *
     * @return array<string, mixed>
     */
    public function verifyTrackingDomain(string $id): array
    {
        return $this->client->request('POST', '/v1/domains/' . $this->segment($id) . '/verify-tracking');
    }

    /**
     * @return array<string, mixed>
     */
    public function removeTrackingDomain(string $id): array
    {
        return $this->unwrap($this->client->request('DELETE', '/v1/domains/' . $this->segment($id) . '/tracking-domain'));
    }
}
