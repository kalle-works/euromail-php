<?php

namespace EuroMail\Tests;

use EuroMail\Client;
use EuroMail\Http\CurlTransport;
use EuroMail\Http\Response;
use EuroMail\Resources\Account;
use EuroMail\Resources\Domains;
use EuroMail\Resources\Emails;
use EuroMail\Version;
use PHPUnit\Framework\TestCase;

final class ClientTest extends TestCase
{
    private function readPrivateProperty(object $object, string $property)
    {
        $reflection = new \ReflectionProperty(get_class($object), $property);
        $reflection->setAccessible(true);

        return $reflection->getValue($object);
    }

    public function testDefaultBaseUrlIsUsedWhenNotProvided(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], '{"data":{}}'));

        $client = new Client('sk_test', ['transport' => $transport]);
        $client->account->get();

        $this->assertSame('https://api.euromail.dev/v1/account', $transport->getLastRequest()->url);
    }

    public function testBaseUrlOptionOverridesDefault(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], '{"data":{}}'));

        $client = new Client('sk_test', [
            'transport' => $transport,
            'base_url' => 'https://staging.euromail.dev/',
        ]);
        $client->account->get();

        $this->assertSame('https://staging.euromail.dev/v1/account', $transport->getLastRequest()->url);
    }

    public function testRequestHeadersIncludeAuthorizationContentTypeAndUserAgent(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], '{"data":{}}'));

        $client = new Client('sk_live_abc123', ['transport' => $transport]);
        $client->account->get();

        $headers = $transport->getLastRequest()->headers;

        $this->assertSame('Bearer sk_live_abc123', $headers['Authorization']);
        $this->assertSame('application/json', $headers['Content-Type']);
        $this->assertSame(
            'euromail-php/' . Version::SDK_VERSION . ' PHP/' . PHP_VERSION,
            $headers['User-Agent']
        );
    }

    public function testDefaultTimeoutIsFifteenSeconds(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('ext-curl not loaded.');
        }

        $client = new Client('sk_test');
        $transport = $this->readPrivateProperty($client, 'transport');

        $this->assertInstanceOf(CurlTransport::class, $transport);
        $this->assertSame(15, $this->readPrivateProperty($transport, 'timeout'));
    }

    public function testTimeoutOptionIsPassedToDefaultTransport(): void
    {
        if (!extension_loaded('curl')) {
            $this->markTestSkipped('ext-curl not loaded.');
        }

        $client = new Client('sk_test', ['timeout' => 42]);
        $transport = $this->readPrivateProperty($client, 'transport');

        $this->assertSame(42, $this->readPrivateProperty($transport, 'timeout'));
    }

    public function testCustomTransportIsUsedWhenProvided(): void
    {
        $transport = new MockTransport();
        $client = new Client('sk_test', ['transport' => $transport]);

        $this->assertSame($transport, $this->readPrivateProperty($client, 'transport'));
    }

    public function testMaxRetriesDefaultsToZero(): void
    {
        $client = new Client('sk_test');

        $this->assertSame(0, $this->readPrivateProperty($client, 'maxRetries'));
    }

    public function testMaxRetryDelayDefaultsToThirtySeconds(): void
    {
        $client = new Client('sk_test');

        $this->assertSame(30, $this->readPrivateProperty($client, 'maxRetryDelay'));
    }

    public function testResourcesArePubliclyAccessibleAndCorrectlyTyped(): void
    {
        $client = new Client('sk_test', ['transport' => new MockTransport()]);

        $this->assertInstanceOf(Emails::class, $client->emails);
        $this->assertInstanceOf(Account::class, $client->account);
        $this->assertInstanceOf(Domains::class, $client->domains);
    }

    public function testInvalidTransportOptionThrowsInvalidArgumentException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client('sk_test', ['transport' => new \stdClass()]);
    }

    public function testInvalidTransportOptionAsStringAlsoThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client('sk_test', ['transport' => 'not-a-transport']);
    }

    public function testRequestBodyThatFailsJsonEncodingThrowsInvalidArgumentExceptionWithoutSendingRequest(): void
    {
        $transport = new MockTransport();
        $client = new Client('sk_test', ['transport' => $transport]);

        try {
            // "\xC3\x28" is invalid UTF-8, which json_encode() cannot represent.
            $client->emails->send(['from' => 'x@example.com', 'to' => 'a@example.com', 'subject' => "\xC3\x28"]);
            $this->fail('Expected InvalidArgumentException was not thrown.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertStringContainsString('JSON', $exception->getMessage());
        }

        $this->assertSame(0, $transport->getRequestCount());
    }
}
