<?php

namespace EuroMail;

/**
 * Walks every page of a paginated list endpoint and yields the items one by
 * one, so callers can `foreach` over a whole collection without tracking
 * page numbers themselves.
 */
final class Paginator
{
    /**
     * `$fetchPage` receives a 1-based page number and must return the
     * `{data, pagination}` envelope that every `all()` list method returns.
     * Iteration stops after the page whose `pagination.total_pages` has been
     * reached, after the first page when the envelope carries no pagination
     * at all, as soon as a page comes back empty, and when the server
     * answers with a different `pagination.page` than was asked for, so a
     * server that keeps echoing the same page cannot loop or duplicate.
     *
     * @param callable(int): array{data: array<int, mixed>, pagination: array<string, mixed>} $fetchPage
     * @return \Generator<int, mixed>
     */
    public static function iterate(callable $fetchPage): \Generator
    {
        $page = 1;

        while (true) {
            $envelope = $fetchPage($page);
            $items = $envelope['data'] ?? [];

            $pagination = $envelope['pagination'] ?? [];
            $servedPage = $pagination['page'] ?? $page;

            if (!is_array($items) || $items === [] || $servedPage !== $page) {
                return;
            }

            foreach ($items as $item) {
                yield $item;
            }

            $totalPages = $pagination['total_pages'] ?? null;
            if (!is_int($totalPages) || $page >= $totalPages) {
                return;
            }

            $page++;
        }
    }
}
