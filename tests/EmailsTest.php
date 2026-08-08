<?php

namespace EuroMail\Tests;

use EuroMail\Client;
use EuroMail\Exceptions\TransportException;
use EuroMail\Http\Response;
use EuroMail\Types\EmailDetails;
use EuroMail\Types\SentEmail;
use PHPUnit\Framework\TestCase;

final class EmailsTest extends TestCase
{
    private function makeClient(MockTransport $transport): Client
    {
        return new Client('sk_test', ['transport' => $transport]);
    }

    public function testSendPostsToEmailsEndpointWithJsonBody(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(202, [], json_encode([
            'data' => [
                'id' => 'em_1',
                'message_id' => 'msg_1',
                'status' => 'queued',
                'to' => ['a@example.com'],
                'sandbox' => false,
                'scheduled_at' => null,
                'created_at' => '2026-08-08T00:00:00Z',
            ],
        ])));

        $client = $this->makeClient($transport);
        $params = [
            'from' => 'sender@example.com',
            'to' => 'a@example.com',
            'subject' => 'Hello',
            'html_body' => '<p>Hi</p>',
            'idempotency_key' => 'idem-12345',
        ];

        $client->emails->send($params);

        $request = $transport->getLastRequest();
        $this->assertSame('POST', $request->method);
        $this->assertSame('https://api.euromail.dev/v1/emails', $request->url);
        $this->assertSame($params, json_decode($request->body, true));
    }

    public function testSendAutoGeneratesIdempotencyKeyWhenNotProvided(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(202, [], json_encode(['data' => ['id' => 'em_1']])));

        $client = $this->makeClient($transport);
        $client->emails->send(['from' => 'x@example.com', 'to' => 'a@example.com']);

        $body = json_decode($transport->getLastRequest()->body, true);
        $this->assertArrayHasKey('idempotency_key', $body);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $body['idempotency_key']
        );
    }

    public function testSendPreservesCallerProvidedIdempotencyKeyVerbatim(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(202, [], json_encode(['data' => ['id' => 'em_1']])));

        $client = $this->makeClient($transport);
        $client->emails->send([
            'from' => 'x@example.com',
            'to' => 'a@example.com',
            'idempotency_key' => 'caller-supplied-key',
        ]);

        $body = json_decode($transport->getLastRequest()->body, true);
        $this->assertSame('caller-supplied-key', $body['idempotency_key']);
    }

    public function testSendReusesSameIdempotencyKeyAcrossRetries(): void
    {
        $transport = new MockTransport();
        $transport->queueException(new TransportException('connection reset'));
        $transport->queueResponse(new Response(202, [], json_encode(['data' => ['id' => 'em_1']])));

        $client = new Client('sk_test', ['transport' => $transport, 'max_retries' => 1]);
        $client->emails->send(['from' => 'x@example.com', 'to' => 'a@example.com']);

        $this->assertSame(2, $transport->getRequestCount());

        $requests = $transport->getRequests();
        $firstKey = json_decode($requests[0]->body, true)['idempotency_key'];
        $secondKey = json_decode($requests[1]->body, true)['idempotency_key'];

        $this->assertSame($firstKey, $secondKey);
    }

    public function testSendBatchAddsIdempotencyKeyOnlyToItemsMissingOne(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(202, [], json_encode([
            'operation_id' => 'op_1',
            'data' => [['id' => 'em_1'], ['id' => 'em_2']],
            'errors' => [],
        ])));

        $client = $this->makeClient($transport);
        $client->emails->sendBatch([
            ['from' => 'x@example.com', 'to' => 'a@example.com'],
            ['from' => 'x@example.com', 'to' => 'b@example.com', 'idempotency_key' => 'caller-key'],
        ]);

        $body = json_decode($transport->getLastRequest()->body, true);
        $this->assertArrayHasKey('idempotency_key', $body['emails'][0]);
        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $body['emails'][0]['idempotency_key']
        );
        $this->assertSame('caller-key', $body['emails'][1]['idempotency_key']);
        $this->assertNotSame($body['emails'][0]['idempotency_key'], $body['emails'][1]['idempotency_key']);
    }

    public function testSendUnwrapsDataEnvelopeIntoSentEmail(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(202, [], json_encode([
            'data' => [
                'id' => 'em_1',
                'message_id' => 'msg_1',
                'status' => 'queued',
                'to' => ['a@example.com'],
                'sandbox' => true,
                'scheduled_at' => '2026-08-09T00:00:00Z',
                'created_at' => '2026-08-08T00:00:00Z',
            ],
        ])));

        $client = $this->makeClient($transport);
        $sentEmail = $client->emails->send(['from' => 'x@example.com', 'to' => 'a@example.com']);

        $this->assertInstanceOf(SentEmail::class, $sentEmail);
        $this->assertSame('em_1', $sentEmail->id);
        $this->assertSame('msg_1', $sentEmail->messageId);
        $this->assertSame('queued', $sentEmail->status);
        $this->assertSame(['a@example.com'], $sentEmail->to);
        $this->assertTrue($sentEmail->sandbox);
        $this->assertSame('2026-08-09T00:00:00Z', $sentEmail->scheduledAt);
        $this->assertSame('2026-08-08T00:00:00Z', $sentEmail->createdAt);
    }

    public function testToFieldAsStringIsNormalizedToArray(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(202, [], json_encode([
            'data' => ['id' => 'em_1', 'to' => 'single@example.com'],
        ])));

        $client = $this->makeClient($transport);
        $sentEmail = $client->emails->send(['from' => 'x@example.com', 'to' => 'single@example.com']);

        $this->assertSame(['single@example.com'], $sentEmail->to);
    }

    public function testToFieldAsArrayIsPreserved(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(202, [], json_encode([
            'data' => ['id' => 'em_1', 'to' => ['a@example.com', 'b@example.com']],
        ])));

        $client = $this->makeClient($transport);
        $sentEmail = $client->emails->send(['from' => 'x@example.com', 'to' => ['a@example.com', 'b@example.com']]);

        $this->assertSame(['a@example.com', 'b@example.com'], $sentEmail->to);
    }

    public function testSendBatchThrowsInvalidArgumentExceptionWhenExceeding500Items(): void
    {
        $transport = new MockTransport();
        $client = $this->makeClient($transport);

        $emails = array_fill(0, 501, ['from' => 'x@example.com', 'to' => 'a@example.com']);

        try {
            $client->emails->sendBatch($emails);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('500', $exception->getMessage());
        }

        $this->assertSame(0, $transport->getRequestCount());
    }

    public function testSendBatchAllowsExactly500Items(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(202, [], json_encode([
            'operation_id' => 'op_123',
            'data' => array_fill(0, 500, ['id' => 'em_1', 'to' => ['a@example.com']]),
            'errors' => [],
        ])));

        $client = $this->makeClient($transport);
        $emails = array_fill(0, 500, ['from' => 'x@example.com', 'to' => 'a@example.com']);

        $result = $client->emails->sendBatch($emails);

        $this->assertCount(500, $result['data']);
        $this->assertContainsOnlyInstancesOf(SentEmail::class, $result['data']);
        $this->assertSame('POST', $transport->getLastRequest()->method);
        $this->assertSame('https://api.euromail.dev/v1/emails/batch', $transport->getLastRequest()->url);
    }

    public function testSendBatchWrapsEmailsInEnvelopeAndReturnsPartialFailures(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(202, [], json_encode([
            'operation_id' => 'op_456',
            'data' => [['id' => 'em_1', 'to' => ['a@example.com']]],
            'errors' => [['index' => 1, 'error' => 'Invalid email address']],
        ])));

        $client = $this->makeClient($transport);
        $emails = [
            ['from' => 'x@example.com', 'to' => 'a@example.com'],
            ['from' => 'x@example.com', 'to' => 'not-an-email'],
        ];

        $result = $client->emails->sendBatch($emails);

        $body = json_decode($transport->getLastRequest()->body, true);
        $this->assertSame(['emails'], array_keys($body));
        $this->assertCount(2, $body['emails']);

        $this->assertSame('op_456', $result['operation_id']);
        $this->assertCount(1, $result['data']);
        $this->assertSame([['index' => 1, 'error' => 'Invalid email address']], $result['errors']);
    }

    public function testGetUnwrapsIntoEmailDetailsWithEvents(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], json_encode([
            'data' => [
                'id' => 'em_1',
                'to' => ['a@example.com'],
                'events' => [
                    ['type' => 'delivered', 'timestamp' => '2026-08-08T00:00:01Z'],
                ],
            ],
        ])));

        $client = $this->makeClient($transport);
        $details = $client->emails->get('em_1');

        $this->assertInstanceOf(EmailDetails::class, $details);
        $this->assertSame('em_1', $details->id);
        $this->assertCount(1, $details->events);
        $this->assertSame('delivered', $details->events[0]['type']);

        $request = $transport->getLastRequest();
        $this->assertSame('GET', $request->method);
        $this->assertSame('https://api.euromail.dev/v1/emails/em_1', $request->url);
    }

    public function testAllBuildsQueryStringAndReturnsDataWithPagination(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], json_encode([
            'data' => [
                ['id' => 'em_1', 'to' => ['a@example.com']],
                ['id' => 'em_2', 'to' => ['b@example.com']],
            ],
            'pagination' => ['page' => 1, 'per_page' => 20, 'total' => 2],
        ])));

        $client = $this->makeClient($transport);
        $result = $client->emails->all(['status' => 'delivered', 'tag' => 'welcome', 'page' => 1, 'per_page' => 20]);

        $request = $transport->getLastRequest();
        $this->assertSame('GET', $request->method);
        $this->assertStringStartsWith('https://api.euromail.dev/v1/emails?', $request->url);
        $this->assertStringContainsString('status=delivered', $request->url);
        $this->assertStringContainsString('tag=welcome', $request->url);
        $this->assertStringContainsString('page=1', $request->url);
        $this->assertStringContainsString('per_page=20', $request->url);

        $this->assertCount(2, $result['data']);
        $this->assertContainsOnlyInstancesOf(SentEmail::class, $result['data']);
        $this->assertSame(['page' => 1, 'per_page' => 20, 'total' => 2], $result['pagination']);
    }

    public function testCancelPostsToCancelEndpointAndUnwrapsSentEmail(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], json_encode([
            'data' => ['id' => 'em_1', 'status' => 'cancelled', 'to' => ['a@example.com']],
        ])));

        $client = $this->makeClient($transport);
        $sentEmail = $client->emails->cancel('em_1');

        $this->assertInstanceOf(SentEmail::class, $sentEmail);
        $this->assertSame('cancelled', $sentEmail->status);

        $request = $transport->getLastRequest();
        $this->assertSame('POST', $request->method);
        $this->assertSame('https://api.euromail.dev/v1/emails/em_1/cancel', $request->url);
    }
}
