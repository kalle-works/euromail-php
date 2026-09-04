<?php

namespace EuroMail\Tests;

use EuroMail\Client;
use EuroMail\Http\Response;
use EuroMail\Paginator;
use EuroMail\Types\SentEmail;
use PHPUnit\Framework\TestCase;

final class PaginatorTest extends TestCase
{
    /**
     * @param array<int, mixed> $items
     */
    private static function page(array $items, int $page, int $totalPages): Response
    {
        return new Response(200, [], (string) json_encode([
            'data' => $items,
            'pagination' => ['page' => $page, 'per_page' => 2, 'total' => 5, 'total_pages' => $totalPages],
        ]));
    }

    public function testWalksEveryPageOnceAndStopsAtTotalPages(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(self::page([['id' => 't1'], ['id' => 't2']], 1, 3));
        $transport->queueResponse(self::page([['id' => 't3'], ['id' => 't4']], 2, 3));
        $transport->queueResponse(self::page([['id' => 't5']], 3, 3));

        $client = new Client('sk_test', ['transport' => $transport]);

        $ids = [];
        foreach ($client->templates->iterate(['per_page' => 2]) as $template) {
            $ids[] = $template['id'];
        }

        $this->assertSame(['t1', 't2', 't3', 't4', 't5'], $ids);
        $this->assertSame(3, $transport->getRequestCount(), 'one request per page, none past the last');

        $urls = array_map(static fn ($request) => $request->url, $transport->getRequests());
        $this->assertStringContainsString('page=1', $urls[0]);
        $this->assertStringContainsString('page=2', $urls[1]);
        $this->assertStringContainsString('page=3', $urls[2]);
        $this->assertStringContainsString('per_page=2', $urls[2], 'caller filters survive on every page');
    }

    /**
     * Every iterate() entry point must start at page 1 (overriding a
     * caller-supplied page), keep the other filters, and hit its own path.
     *
     * @dataProvider iterateProvider
     * @param callable(Client): \Generator<int, mixed> $call
     */
    public function testEveryIterateStartsFromPageOneAndKeepsFilters(callable $call, string $expectedPath): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(self::page([['id' => 'x']], 1, 1));
        $client = new Client('sk_test', ['transport' => $transport]);

        $items = iterator_to_array($call($client), false);

        $this->assertCount(1, $items);
        $url = $transport->getLastRequest()->url ?? '';
        $this->assertStringStartsWith('https://api.euromail.dev' . $expectedPath . '?', $url);
        $this->assertStringContainsString('page=1', $url);
        $this->assertStringNotContainsString('page=7', $url);
        $this->assertStringContainsString('per_page=5', $url);
    }

    /**
     * @return iterable<string, array{callable(Client): \Generator<int, mixed>, string}>
     */
    public function iterateProvider(): iterable
    {
        $f = ['page' => 7, 'per_page' => 5];

        yield 'emails' => [fn (Client $c) => $c->emails->iterate($f), '/v1/emails'];
        yield 'templates' => [fn (Client $c) => $c->templates->iterate($f), '/v1/templates'];
        yield 'domains' => [fn (Client $c) => $c->domains->iterate($f), '/v1/domains'];
        yield 'webhooks' => [fn (Client $c) => $c->webhooks->iterate($f), '/v1/webhooks'];
        yield 'suppressions' => [fn (Client $c) => $c->suppressions->iterate($f), '/v1/suppressions'];
        yield 'contacts' => [fn (Client $c) => $c->contactLists->iterateContacts('l1', $f), '/v1/contact-lists/l1/contacts'];
        yield 'inbound' => [fn (Client $c) => $c->inbound->iterate($f), '/v1/inbound'];
        yield 'inboundRoutes' => [fn (Client $c) => $c->inboundRoutes->iterate($f), '/v1/inbound-routes'];
        yield 'subAccounts' => [fn (Client $c) => $c->subAccounts->iterate($f), '/v1/accounts'];
        yield 'auditLogs' => [fn (Client $c) => $c->auditLogs->iterate($f), '/v1/audit-logs'];
        yield 'operations' => [fn (Client $c) => $c->operations->iterate($f), '/v1/operations'];
    }

    public function testEmailsIterateYieldsSentEmailObjects(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(self::page([['id' => 'em_1', 'status' => 'queued']], 1, 1));
        $client = new Client('sk_test', ['transport' => $transport]);

        $items = iterator_to_array($client->emails->iterate(), false);

        $this->assertContainsOnlyInstancesOf(SentEmail::class, $items);
        $this->assertSame('em_1', $items[0]->id);
    }

    public function testStopsWhenTheServerEchoesADifferentPageThanRequested(): void
    {
        $calls = 0;
        $items = iterator_to_array(Paginator::iterate(function (int $page) use (&$calls): array {
            $calls++;
            // A broken server that ignores ?page= and always serves page 1.
            return ['data' => [['id' => 'same']], 'pagination' => ['page' => 1, 'total_pages' => 3]];
        }), false);

        $this->assertSame([['id' => 'same']], $items, 'the echoed page is yielded once, never duplicated');
        $this->assertSame(2, $calls, 'page 2 is requested once, recognised as an echo, and dropped');
    }

    public function testStopsAfterOnePageWhenTheEnvelopeCarriesNoPagination(): void
    {
        $items = [];
        $calls = 0;
        foreach (Paginator::iterate(function (int $page) use (&$calls): array {
            $calls++;
            return ['data' => [['id' => $page]], 'pagination' => []];
        }) as $item) {
            $items[] = $item;
        }

        $this->assertSame([['id' => 1]], $items);
        $this->assertSame(1, $calls);
    }

    public function testStopsOnAnEmptyPageEvenIfTotalPagesClaimsMore(): void
    {
        $calls = 0;
        $items = iterator_to_array(Paginator::iterate(function (int $page) use (&$calls): array {
            $calls++;
            return ['data' => [], 'pagination' => ['total_pages' => 99]];
        }), false);

        $this->assertSame([], $items);
        $this->assertSame(1, $calls, 'an empty page ends iteration; total_pages alone cannot keep it going');
    }
}
