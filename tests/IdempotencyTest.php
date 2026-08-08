<?php

namespace EuroMail\Tests;

use EuroMail\Idempotency;
use PHPUnit\Framework\TestCase;

final class IdempotencyTest extends TestCase
{
    public function testGenerateReturnsValidUuidV4Format(): void
    {
        $uuid = Idempotency::generate();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid
        );
    }

    public function testGenerateProducesUniqueValues(): void
    {
        $seen = [];
        for ($i = 0; $i < 1000; $i++) {
            $seen[Idempotency::generate()] = true;
        }

        $this->assertCount(1000, $seen);
    }
}
