<?php

namespace EuroMail\Tests;

use EuroMail\Client;
use EuroMail\Http\Response;
use PHPUnit\Framework\TestCase;

final class SuppressionsTest extends TestCase
{
    private function makeClient(MockTransport $transport): Client
    {
        return new Client('sk_test', ['transport' => $transport]);
    }

    public function testCreatePostsEmailAddressAndReason(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(201, [], json_encode([
            'data' => [
                'id' => 'sup_1',
                'email_address' => 'bounced@example.com',
                'reason' => 'hard_bounce',
                'created_at' => '2026-08-08T00:00:00Z',
            ],
        ])));

        $client = $this->makeClient($transport);
        $result = $client->suppressions->create('bounced@example.com', 'hard_bounce');

        $request = $transport->getLastRequest();
        $this->assertSame('POST', $request->method);
        $this->assertSame('https://api.euromail.dev/v1/suppressions', $request->url);
        $this->assertSame(
            ['email_address' => 'bounced@example.com', 'reason' => 'hard_bounce'],
            json_decode($request->body, true)
        );
        $this->assertSame('sup_1', $result['id']);
    }

    public function testCreateOmitsReasonWhenNotProvided(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(201, [], json_encode(['data' => ['id' => 'sup_1']])));

        $client = $this->makeClient($transport);
        $client->suppressions->create('manual@example.com');

        $body = json_decode($transport->getLastRequest()->body, true);
        $this->assertSame(['email_address' => 'manual@example.com'], $body);
        $this->assertArrayNotHasKey('reason', $body);
    }

    public function testAllBuildsQueryStringAndReturnsDataWithPagination(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], json_encode([
            'data' => [
                ['id' => 'sup_1', 'email_address' => 'a@example.com'],
                ['id' => 'sup_2', 'email_address' => 'b@example.com'],
            ],
            'pagination' => ['page' => 1, 'per_page' => 25, 'total' => 2, 'total_pages' => 1],
        ])));

        $client = $this->makeClient($transport);
        $result = $client->suppressions->all(['page' => 1, 'per_page' => 25]);

        $request = $transport->getLastRequest();
        $this->assertSame('GET', $request->method);
        $this->assertStringStartsWith('https://api.euromail.dev/v1/suppressions?', $request->url);
        $this->assertStringContainsString('page=1', $request->url);
        $this->assertStringContainsString('per_page=25', $request->url);
        $this->assertCount(2, $result['data']);
        $this->assertSame(['page' => 1, 'per_page' => 25, 'total' => 2, 'total_pages' => 1], $result['pagination']);
    }

    public function testAllWithoutFiltersOmitsQueryString(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], json_encode(['data' => [], 'pagination' => []])));

        $client = $this->makeClient($transport);
        $client->suppressions->all();

        $this->assertSame('https://api.euromail.dev/v1/suppressions', $transport->getLastRequest()->url);
    }

    public function testDeleteSendsDeleteToEncodedEmailPath(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(204, [], ''));

        $client = $this->makeClient($transport);
        $client->suppressions->delete('a+tag@example.com');

        $request = $transport->getLastRequest();
        $this->assertSame('DELETE', $request->method);
        $this->assertSame(
            'https://api.euromail.dev/v1/suppressions/' . rawurlencode('a+tag@example.com'),
            $request->url
        );
    }

    public function testImportPostsEmailsListAndReason(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], json_encode([
            'data' => [
                'inserted' => 2,
                'total_requested' => 3,
                'invalid_addresses' => ['not-an-email'],
            ],
        ])));

        $client = $this->makeClient($transport);
        $result = $client->suppressions->import(
            ['a@example.com', 'b@example.com', 'not-an-email'],
            'import'
        );

        $request = $transport->getLastRequest();
        $this->assertSame('POST', $request->method);
        $this->assertSame('https://api.euromail.dev/v1/suppressions/import', $request->url);
        $this->assertSame(
            ['emails' => ['a@example.com', 'b@example.com', 'not-an-email'], 'reason' => 'import'],
            json_decode($request->body, true)
        );
        $this->assertSame(2, $result['inserted']);
        $this->assertSame(3, $result['total_requested']);
        $this->assertSame(['not-an-email'], $result['invalid_addresses']);
    }

    public function testImportRejectsEmptyListWithoutMakingARequest(): void
    {
        $transport = new MockTransport();
        $client = $this->makeClient($transport);

        try {
            $client->suppressions->import([]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('empty', $exception->getMessage());
        }

        $this->assertSame(0, $transport->getRequestCount());
    }

    public function testImportRejectsMoreThanTenThousandAddressesWithoutMakingARequest(): void
    {
        $transport = new MockTransport();
        $client = $this->makeClient($transport);

        $emails = array_fill(0, 10_001, 'a@example.com');

        try {
            $client->suppressions->import($emails);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('10000', $exception->getMessage());
        }

        $this->assertSame(0, $transport->getRequestCount());
    }

    public function testImportAllowsExactlyTenThousandAddresses(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], json_encode([
            'data' => ['inserted' => 10_000, 'total_requested' => 10_000, 'invalid_addresses' => []],
        ])));

        $client = $this->makeClient($transport);
        $emails = array_fill(0, 10_000, 'a@example.com');

        $result = $client->suppressions->import($emails);

        $this->assertSame(10_000, $result['inserted']);
        $this->assertSame(1, $transport->getRequestCount());
    }

    public function testExportReturnsRawCsvBodyWithoutJsonDecoding(): void
    {
        $csv = "email_address,reason,created_at\n\"a@example.com\",\"hard_bounce\",\"2026-08-08T00:00:00Z\"\n";
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, ['Content-Type' => 'text/csv; charset=utf-8'], $csv));

        $client = $this->makeClient($transport);
        $result = $client->suppressions->export();

        $this->assertSame($csv, $result);

        $request = $transport->getLastRequest();
        $this->assertSame('GET', $request->method);
        $this->assertSame('https://api.euromail.dev/v1/suppressions/export', $request->url);
    }

    public function testExportThrowsMappedExceptionOnErrorResponse(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(401, [], json_encode([
            'error' => ['type' => 'auth_error', 'code' => 'AUTH_FAILED', 'message' => 'Invalid API key'],
        ])));

        $client = $this->makeClient($transport);

        $this->expectException(\EuroMail\Exceptions\AuthenticationException::class);
        $client->suppressions->export();
    }
}
