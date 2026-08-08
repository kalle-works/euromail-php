<?php

namespace EuroMail\Tests;

use EuroMail\Client;
use EuroMail\Exceptions\ValidationException;
use EuroMail\Http\Response;
use PHPUnit\Framework\TestCase;

final class RetryTest extends TestCase
{
    public function testMaxRetriesHonorsRetryAfterHeaderThenSucceeds(): void
    {
        $transport = new MockTransport();
        // retry-after: 0 keeps the test fast while still exercising the honored-header path.
        $transport->queueResponse(new Response(429, ['Retry-After' => '0'], json_encode([
            'error' => ['type' => 'rate_limit_exceeded', 'code' => 'rate_limited', 'message' => 'Too many requests'],
        ])));
        $transport->queueResponse(new Response(200, [], json_encode(['data' => ['status' => 'active']])));

        $client = new Client('sk_test', ['transport' => $transport, 'max_retries' => 2]);
        $start = microtime(true);
        $result = $client->account->get();
        $elapsed = microtime(true) - $start;

        $this->assertSame(['status' => 'active'], $result);
        $this->assertSame(2, $transport->getRequestCount());
        // A retry-after of 0 must be honored rather than falling back to the 1s/2s/4s
        // exponential backoff; anything approaching a full second means the header was ignored.
        $this->assertLessThan(0.5, $elapsed);
    }

    public function testPermanent422IsNeverRetried(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(422, [], json_encode([
            'error' => ['type' => 'validation_error', 'code' => 'invalid_field', 'message' => 'Invalid field'],
        ])));

        $client = new Client('sk_test', ['transport' => $transport, 'max_retries' => 3]);

        try {
            $client->account->get();
            $this->fail('Expected ValidationException was not thrown.');
        } catch (ValidationException $exception) {
            $this->assertSame('invalid_field', $exception->getErrorCode());
        }

        $this->assertSame(1, $transport->getRequestCount());
    }

    public function testExhaustingRetriesOnPersistentServerErrorThrowsFinalException(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(503, ['Retry-After' => '0'], '{}'));
        $transport->queueResponse(new Response(503, ['Retry-After' => '0'], '{}'));

        $client = new Client('sk_test', ['transport' => $transport, 'max_retries' => 1]);

        $this->expectException(\EuroMail\Exceptions\ServerException::class);
        $client->account->get();
    }

    public function testZeroMaxRetriesDoesNotRetryOnRetryableError(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(500, [], '{}'));

        $client = new Client('sk_test', ['transport' => $transport]);

        try {
            $client->account->get();
            $this->fail('Expected ServerException was not thrown.');
        } catch (\EuroMail\Exceptions\ServerException $exception) {
            // expected
        }

        $this->assertSame(1, $transport->getRequestCount());
    }

    public function testTransportFailureIsRetriedWhenMaxRetriesGreaterThanZero(): void
    {
        $transport = new MockTransport();
        $transport->queueException(new \EuroMail\Exceptions\TransportException('DNS resolution failed'));
        $transport->queueResponse(new Response(200, [], json_encode(['data' => ['status' => 'active']])));

        $client = new Client('sk_test', ['transport' => $transport, 'max_retries' => 1]);
        $result = $client->account->get();

        $this->assertSame(['status' => 'active'], $result);
        $this->assertSame(2, $transport->getRequestCount());
    }

    public function testRetryAfterIsHonoredOnServerErrorsNotJustRateLimit(): void
    {
        $transport = new MockTransport();
        // retry-after: 3 is deliberately larger than the 1s the exponential
        // backoff fallback would use for the first attempt, so honoring the
        // header (vs. ignoring it and falling back to backoff) is observable.
        $transport->queueResponse(new Response(503, ['Retry-After' => '3'], '{}'));
        $transport->queueResponse(new Response(200, [], json_encode(['data' => ['status' => 'active']])));

        $client = new Client('sk_test', ['transport' => $transport, 'max_retries' => 1]);

        $start = microtime(true);
        $result = $client->account->get();
        $elapsed = microtime(true) - $start;

        $this->assertSame(['status' => 'active'], $result);
        $this->assertSame(2, $transport->getRequestCount());
        $this->assertGreaterThanOrEqual(2.9, $elapsed);
        $this->assertLessThan(4.5, $elapsed);
    }

    public function testMaxRetryDelayCapsAnOversizedRetryAfterHeader(): void
    {
        $transport = new MockTransport();
        // A retry-after value this large would sleep for hours if honored verbatim.
        $transport->queueResponse(new Response(503, ['Retry-After' => '9999'], '{}'));
        $transport->queueResponse(new Response(200, [], json_encode(['data' => ['status' => 'active']])));

        $client = new Client('sk_test', [
            'transport' => $transport,
            'max_retries' => 1,
            'max_retry_delay' => 1,
        ]);

        $start = microtime(true);
        $result = $client->account->get();
        $elapsed = microtime(true) - $start;

        $this->assertSame(['status' => 'active'], $result);
        $this->assertSame(2, $transport->getRequestCount());
        // The wait must be bounded by max_retry_delay (1s), not the 9999s header.
        $this->assertGreaterThanOrEqual(0.9, $elapsed);
        $this->assertLessThan(3.0, $elapsed);
    }
}
