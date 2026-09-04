<?php

namespace EuroMail\Tests;

use EuroMail\Client;
use EuroMail\Http\Response;
use PHPUnit\Framework\TestCase;

/**
 * The wire contract of every resource method added for full API coverage:
 * HTTP method, path (with encoded segments and query string), request body,
 * and which part of the response envelope the caller gets back.
 */
final class ResourcesContractTest extends TestCase
{
    private const RECORD = ['id' => 'rec_1', 'name' => 'x'];

    /**
     * @dataProvider unwrappedRecordProvider
     * @param callable(Client): mixed $call
     * @param array<string, mixed>|null $expectedBody
     */
    public function testMethodPathBodyAndDataUnwrapping(
        callable $call,
        string $expectedMethod,
        string $expectedUrl,
        ?array $expectedBody
    ): void {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], (string) json_encode(['data' => self::RECORD])));
        $client = new Client('sk_test', ['transport' => $transport]);

        $result = $call($client);

        $request = $transport->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame($expectedMethod, $request->method);
        $this->assertSame('https://api.euromail.dev' . $expectedUrl, $request->url);
        $this->assertSame($expectedBody, $request->body === null ? null : json_decode($request->body, true));
        $this->assertSame(self::RECORD, $result, 'the record inside "data" is returned, not the envelope');
    }

    /**
     * @return iterable<string, array{callable(Client): mixed, string, string, array<string, mixed>|null}>
     */
    public function unwrappedRecordProvider(): iterable
    {
        $id = 'id/with space';
        $enc = rawurlencode($id);
        $params = ['name' => 'x'];

        yield 'templates.create' => [fn (Client $c) => $c->templates->create($params), 'POST', '/v1/templates', $params];
        yield 'templates.get' => [fn (Client $c) => $c->templates->get($id), 'GET', "/v1/templates/$enc", null];
        yield 'templates.update' => [fn (Client $c) => $c->templates->update($id, $params), 'PUT', "/v1/templates/$enc", $params];

        yield 'webhooks.create' => [fn (Client $c) => $c->webhooks->create($params), 'POST', '/v1/webhooks', $params];
        yield 'webhooks.get' => [fn (Client $c) => $c->webhooks->get($id), 'GET', "/v1/webhooks/$enc", null];
        yield 'webhooks.update' => [fn (Client $c) => $c->webhooks->update($id, $params), 'PUT', "/v1/webhooks/$enc", $params];
        yield 'webhooks.test' => [fn (Client $c) => $c->webhooks->test($id), 'POST', "/v1/webhooks/$enc/test", null];

        yield 'domains.create' => [fn (Client $c) => $c->domains->create('example.com'), 'POST', '/v1/domains', ['domain' => 'example.com']];
        yield 'domains.create with extra' => [
            fn (Client $c) => $c->domains->create('example.com', ['sending_subdomain' => 'mail']),
            'POST', '/v1/domains', ['domain' => 'example.com', 'sending_subdomain' => 'mail'],
        ];
        yield 'domains.verify' => [fn (Client $c) => $c->domains->verify($id), 'POST', "/v1/domains/$enc/verify", null];
        yield 'domains.setSendingSubdomain' => [
            fn (Client $c) => $c->domains->setSendingSubdomain($id, 'mail'),
            'PUT', "/v1/domains/$enc/sending-subdomain", ['sending_subdomain' => 'mail'],
        ];
        yield 'domains.removeTrackingDomain' => [fn (Client $c) => $c->domains->removeTrackingDomain($id), 'DELETE', "/v1/domains/$enc/tracking-domain", null];

        yield 'contactLists.create' => [fn (Client $c) => $c->contactLists->create($params), 'POST', '/v1/contact-lists', $params];
        yield 'contactLists.get' => [fn (Client $c) => $c->contactLists->get($id), 'GET', "/v1/contact-lists/$enc", null];
        yield 'contactLists.update' => [fn (Client $c) => $c->contactLists->update($id, $params), 'PUT', "/v1/contact-lists/$enc", $params];
        yield 'contactLists.addContact' => [
            fn (Client $c) => $c->contactLists->addContact($id, ['email' => 'a@example.com']),
            'POST', "/v1/contact-lists/$enc/contacts", ['email' => 'a@example.com'],
        ];
        yield 'contactLists.addContacts wraps in contacts' => [
            fn (Client $c) => $c->contactLists->addContacts($id, [5 => ['email' => 'a@example.com']]),
            'POST', "/v1/contact-lists/$enc/contacts", ['contacts' => [['email' => 'a@example.com']]],
        ];
        yield 'contactLists.getWelcomeEmail' => [fn (Client $c) => $c->contactLists->getWelcomeEmail($id), 'GET', "/v1/contact-lists/$enc/welcome-email", null];
        yield 'contactLists.configureWelcomeEmail' => [
            fn (Client $c) => $c->contactLists->configureWelcomeEmail($id, ['enabled' => true, 'subject' => 'Hi']),
            'PUT', "/v1/contact-lists/$enc/welcome-email", ['enabled' => true, 'subject' => 'Hi'],
        ];

        yield 'newsletters.create' => [fn (Client $c) => $c->newsletters->create($params), 'POST', '/v1/newsletters', $params];
        yield 'newsletters.get' => [fn (Client $c) => $c->newsletters->get($id), 'GET', "/v1/newsletters/$enc", null];
        yield 'newsletters.update' => [fn (Client $c) => $c->newsletters->update($id, $params), 'PUT', "/v1/newsletters/$enc", $params];
        yield 'newsletters.send' => [fn (Client $c) => $c->newsletters->send($id), 'POST', "/v1/newsletters/$enc/send", null];

        yield 'signupForms.create' => [fn (Client $c) => $c->signupForms->create($params), 'POST', '/v1/signup-forms', $params];
        yield 'signupForms.get' => [fn (Client $c) => $c->signupForms->get($id), 'GET', "/v1/signup-forms/$enc", null];
        yield 'signupForms.update' => [fn (Client $c) => $c->signupForms->update($id, $params), 'PUT', "/v1/signup-forms/$enc", $params];
        yield 'signupForms.toggle' => [fn (Client $c) => $c->signupForms->toggle($id), 'POST', "/v1/signup-forms/$enc/toggle", null];

        yield 'inbound.get' => [fn (Client $c) => $c->inbound->get($id), 'GET', "/v1/inbound/$enc", null];
        yield 'inboundRoutes.create' => [fn (Client $c) => $c->inboundRoutes->create($params), 'POST', '/v1/inbound-routes', $params];
        yield 'inboundRoutes.get' => [fn (Client $c) => $c->inboundRoutes->get($id), 'GET', "/v1/inbound-routes/$enc", null];
        yield 'inboundRoutes.update' => [fn (Client $c) => $c->inboundRoutes->update($id, $params), 'PUT', "/v1/inbound-routes/$enc", $params];

        yield 'subAccounts.create' => [fn (Client $c) => $c->subAccounts->create($params), 'POST', '/v1/accounts', $params];
        yield 'subAccounts.get' => [fn (Client $c) => $c->subAccounts->get($id), 'GET', "/v1/accounts/$enc", null];
        yield 'subAccounts.update is a PATCH' => [fn (Client $c) => $c->subAccounts->update($id, $params), 'PATCH', "/v1/accounts/$enc", $params];
        yield 'subAccounts.createApiKey' => [
            fn (Client $c) => $c->subAccounts->createApiKey($id, ['name' => 'ci']),
            'POST', "/v1/accounts/$enc/api-keys", ['name' => 'ci'],
        ];

        yield 'apiKeys.create' => [fn (Client $c) => $c->apiKeys->create(['name' => 'ci', 'scopes' => ['emails:send']]), 'POST', '/v1/api-keys', ['name' => 'ci', 'scopes' => ['emails:send']]];
        yield 'operations.get' => [fn (Client $c) => $c->operations->get($id), 'GET', "/v1/operations/$enc", null];
        yield 'emails.broadcast' => [
            fn (Client $c) => $c->emails->broadcast(['contact_list_id' => 'cl_1', 'from_address' => 'a@b.c']),
            'POST', '/v1/emails/broadcast', ['contact_list_id' => 'cl_1', 'from_address' => 'a@b.c'],
        ];
    }

    /**
     * @dataProvider deleteProvider
     * @param callable(Client): void $call
     */
    public function testDeletesSendDeleteToEncodedPathAndReturnNothing(callable $call, string $expectedUrl): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(204, [], ''));
        $client = new Client('sk_test', ['transport' => $transport]);

        $call($client);

        $request = $transport->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('DELETE', $request->method);
        $this->assertSame('https://api.euromail.dev' . $expectedUrl, $request->url);
        $this->assertNull($request->body);
    }

    /**
     * @return iterable<string, array{callable(Client): void, string}>
     */
    public function deleteProvider(): iterable
    {
        $id = 'id/with space';
        $enc = rawurlencode($id);

        yield 'account' => [fn (Client $c) => $c->account->delete(), '/v1/account'];
        yield 'templates' => [fn (Client $c) => $c->templates->delete($id), "/v1/templates/$enc"];
        yield 'webhooks' => [fn (Client $c) => $c->webhooks->delete($id), "/v1/webhooks/$enc"];
        yield 'domains' => [fn (Client $c) => $c->domains->delete($id), "/v1/domains/$enc"];
        yield 'contactLists' => [fn (Client $c) => $c->contactLists->delete($id), "/v1/contact-lists/$enc"];
        yield 'contactLists.removeContact' => [
            fn (Client $c) => $c->contactLists->removeContact($id, 'a+b@example.com'),
            "/v1/contact-lists/$enc/contacts/" . rawurlencode('a+b@example.com'),
        ];
        yield 'newsletters' => [fn (Client $c) => $c->newsletters->delete($id), "/v1/newsletters/$enc"];
        yield 'signupForms' => [fn (Client $c) => $c->signupForms->delete($id), "/v1/signup-forms/$enc"];
        yield 'inbound' => [fn (Client $c) => $c->inbound->delete($id), "/v1/inbound/$enc"];
        yield 'inboundRoutes' => [fn (Client $c) => $c->inboundRoutes->delete($id), "/v1/inbound-routes/$enc"];
        yield 'subAccounts' => [fn (Client $c) => $c->subAccounts->delete($id), "/v1/accounts/$enc"];
        yield 'apiKeys' => [fn (Client $c) => $c->apiKeys->delete($id), "/v1/api-keys/$enc"];
        yield 'deadLetters' => [fn (Client $c) => $c->deadLetters->delete($id), "/v1/dead-letters/$enc"];
    }

    /**
     * @dataProvider paginatedListProvider
     * @param callable(Client): array{data: array<int, mixed>, pagination: array<string, mixed>} $call
     */
    public function testPaginatedListsPassFiltersAsQueryAndReturnDataWithPagination(callable $call, string $expectedUrl): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], (string) json_encode([
            'data' => [self::RECORD],
            'pagination' => ['page' => 2, 'per_page' => 10, 'total' => 11, 'total_pages' => 2],
        ])));
        $client = new Client('sk_test', ['transport' => $transport]);

        $result = $call($client);

        $request = $transport->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('GET', $request->method);
        $this->assertSame('https://api.euromail.dev' . $expectedUrl . '?page=2&per_page=10', $request->url);
        $this->assertSame([self::RECORD], $result['data']);
        $this->assertSame(2, $result['pagination']['total_pages']);
    }

    /**
     * @return iterable<string, array{callable(Client): array{data: array<int, mixed>, pagination: array<string, mixed>}, string}>
     */
    public function paginatedListProvider(): iterable
    {
        $f = ['page' => 2, 'per_page' => 10];
        $enc = rawurlencode('id/with space');

        yield 'templates' => [fn (Client $c) => $c->templates->all($f), '/v1/templates'];
        yield 'webhooks' => [fn (Client $c) => $c->webhooks->all($f), '/v1/webhooks'];
        yield 'domains.page' => [fn (Client $c) => $c->domains->page($f), '/v1/domains'];
        yield 'contactLists.contacts' => [fn (Client $c) => $c->contactLists->contacts('id/with space', $f), "/v1/contact-lists/$enc/contacts"];
        yield 'inbound' => [fn (Client $c) => $c->inbound->all($f), '/v1/inbound'];
        yield 'inboundRoutes' => [fn (Client $c) => $c->inboundRoutes->all($f), '/v1/inbound-routes'];
        yield 'subAccounts' => [fn (Client $c) => $c->subAccounts->all($f), '/v1/accounts'];
        yield 'auditLogs' => [fn (Client $c) => $c->auditLogs->all($f), '/v1/audit-logs'];
        yield 'operations' => [fn (Client $c) => $c->operations->all($f), '/v1/operations'];
    }

    /**
     * @dataProvider unpaginatedListProvider
     * @param callable(Client): array<int, mixed> $call
     */
    public function testUnpaginatedListsReturnTheDataArray(callable $call, string $expectedUrl): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], (string) json_encode(['data' => [self::RECORD, self::RECORD]])));
        $client = new Client('sk_test', ['transport' => $transport]);

        $result = $call($client);

        $this->assertSame('https://api.euromail.dev' . $expectedUrl, $transport->getLastRequest()->url ?? null);
        $this->assertSame([self::RECORD, self::RECORD], $result);
    }

    /**
     * @return iterable<string, array{callable(Client): array<int, mixed>, string}>
     */
    public function unpaginatedListProvider(): iterable
    {
        $enc = rawurlencode('id/with space');

        yield 'contactLists' => [fn (Client $c) => $c->contactLists->all(), '/v1/contact-lists'];
        yield 'signupForms' => [fn (Client $c) => $c->signupForms->all(), '/v1/signup-forms'];
        yield 'apiKeys' => [fn (Client $c) => $c->apiKeys->all(), '/v1/api-keys'];
        yield 'emails.links' => [fn (Client $c) => $c->emails->links('id/with space'), "/v1/emails/$enc/links"];
        yield 'domains.all keeps its 1.x shape' => [fn (Client $c) => $c->domains->all(), '/v1/domains'];
    }

    /**
     * Analytics, tracking-domain and validation responses carry fields next
     * to `data` (period, cname_target, tracking_check, valid), so the whole
     * body is returned rather than only `data`.
     *
     * @dataProvider wholeBodyProvider
     * @param callable(Client): array<string, mixed> $call
     * @param array<string, mixed>|null $expectedBody
     */
    public function testWholeBodyResponsesAreNotUnwrapped(callable $call, string $expectedMethod, string $expectedUrl, ?array $expectedBody): void
    {
        $body = ['data' => self::RECORD, 'period' => ['from' => '2026-08-01', 'to' => '2026-08-31']];
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], (string) json_encode($body)));
        $client = new Client('sk_test', ['transport' => $transport]);

        $result = $call($client);

        $request = $transport->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame($expectedMethod, $request->method);
        $this->assertSame('https://api.euromail.dev' . $expectedUrl, $request->url);
        $this->assertSame($expectedBody, $request->body === null ? null : json_decode($request->body, true));
        $this->assertSame($body, $result);
    }

    /**
     * @return iterable<string, array{callable(Client): array<string, mixed>, string, string, array<string, mixed>|null}>
     */
    public function wholeBodyProvider(): iterable
    {
        $q = ['period' => '30d'];
        $enc = rawurlencode('id/with space');

        yield 'analytics.overview' => [fn (Client $c) => $c->analytics->overview($q), 'GET', '/v1/analytics/overview?period=30d', null];
        yield 'analytics.timeseries' => [fn (Client $c) => $c->analytics->timeseries($q + ['metrics' => 'sent,delivered']), 'GET', '/v1/analytics/timeseries?period=30d&metrics=sent%2Cdelivered', null];
        yield 'analytics.domains' => [fn (Client $c) => $c->analytics->domains($q + ['limit' => 5]), 'GET', '/v1/analytics/domains?period=30d&limit=5', null];
        yield 'analytics.tags' => [fn (Client $c) => $c->analytics->tags($q), 'GET', '/v1/analytics/tags?period=30d', null];
        yield 'analytics.aggregate' => [fn (Client $c) => $c->analytics->aggregate(), 'GET', '/v1/analytics/aggregate', null];
        yield 'subAccounts.analytics' => [fn (Client $c) => $c->subAccounts->analytics('id/with space', $q), 'GET', "/v1/accounts/$enc/analytics?period=30d", null];
        yield 'domains.setTrackingDomain' => [
            fn (Client $c) => $c->domains->setTrackingDomain('id/with space', 'click.example.com'),
            'PUT', "/v1/domains/$enc/tracking-domain", ['tracking_domain' => 'click.example.com'],
        ];
        yield 'domains.verifyTrackingDomain' => [fn (Client $c) => $c->domains->verifyTrackingDomain('id/with space'), 'POST', "/v1/domains/$enc/verify-tracking", null];
        yield 'emails.validate' => [fn (Client $c) => $c->emails->validate('a@example.com'), 'POST', '/v1/validate', ['email' => 'a@example.com']];
    }

    /**
     * @dataProvider csvExportProvider
     * @param callable(Client): string $call
     */
    public function testCsvExportsReturnTheRawBody(callable $call, string $expectedUrl): void
    {
        $csv = "a,b\n1,2\n";
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, ['Content-Type' => 'text/csv'], $csv));
        $client = new Client('sk_test', ['transport' => $transport]);

        $this->assertSame($csv, $call($client));
        $this->assertSame('https://api.euromail.dev' . $expectedUrl, $transport->getLastRequest()->url ?? null);
    }

    /**
     * @return iterable<string, array{callable(Client): string, string}>
     */
    public function csvExportProvider(): iterable
    {
        yield 'account.export' => [fn (Client $c) => $c->account->export(), '/v1/account/export'];
        yield 'analytics.export' => [fn (Client $c) => $c->analytics->export(['from' => '2026-08-01', 'to' => '2026-08-31']), '/v1/analytics/export?from=2026-08-01&to=2026-08-31'];
    }

    public function testNewslettersListUsesLimitOffsetAndReturnsTotal(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], (string) json_encode(['data' => [self::RECORD], 'total' => 41])));
        $client = new Client('sk_test', ['transport' => $transport]);

        $result = $client->newsletters->all(['limit' => 20, 'offset' => 40]);

        $this->assertSame('https://api.euromail.dev/v1/newsletters?limit=20&offset=40', $transport->getLastRequest()->url ?? null);
        $this->assertSame(['data' => [self::RECORD], 'total' => 41], $result);
    }

    public function testDeadLettersListPassesCountAndReturnsTotal(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], (string) json_encode(['data' => [self::RECORD], 'total' => 3])));
        $transport->queueResponse(new Response(200, [], (string) json_encode(['data' => [], 'total' => 0])));
        $client = new Client('sk_test', ['transport' => $transport]);

        $withCount = $client->deadLetters->all(5);
        $this->assertSame('https://api.euromail.dev/v1/dead-letters?count=5', $transport->getLastRequest()->url ?? null);
        $this->assertSame(['data' => [self::RECORD], 'total' => 3], $withCount);

        $client->deadLetters->all();
        $this->assertSame('https://api.euromail.dev/v1/dead-letters', $transport->getLastRequest()->url ?? null);
    }

    public function testDeadLetterRetryPostsToRetryPath(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], (string) json_encode(['data' => ['status' => 'queued']])));
        $client = new Client('sk_test', ['transport' => $transport]);

        $client->deadLetters->retry('id/with space');

        $request = $transport->getLastRequest();
        $this->assertSame('POST', $request->method ?? null);
        $this->assertSame('https://api.euromail.dev/v1/dead-letters/' . rawurlencode('id/with space') . '/retry', $request->url ?? null);
    }
}
