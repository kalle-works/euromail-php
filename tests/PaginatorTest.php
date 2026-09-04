<?php

namespace EuroMail\Tests;

use EuroMail\Client;
use EuroMail\Http\Response;
use EuroMail\Paginator;
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

    public function testCallerSuppliedPageIsOverriddenSoIterationStartsFromOne(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(self::page([['id' => 't1']], 1, 1));
        $client = new Client('sk_test', ['transport' => $transport]);

        iterator_to_array($client->templates->iterate(['page' => 7]));

        $this->assertStringContainsString('page=1', $transport->getLastRequest()->url ?? '');
        $this->assertStringNotContainsString('page=7', $transport->getLastRequest()->url ?? '');
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
