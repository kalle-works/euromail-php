<?php

namespace EuroMail\Tests;

use EuroMail\Client;
use EuroMail\Exceptions\EuroMailException;
use EuroMail\Exceptions\TransportException;
use EuroMail\Http\Response;
use PHPUnit\Framework\TestCase;

final class ClientConfigTest extends TestCase
{
    protected function tearDown(): void
    {
        putenv(Client::API_KEY_ENV);
    }

    public function testApiKeyFallsBackToEnvironmentVariable(): void
    {
        putenv(Client::API_KEY_ENV . '=sk_from_env');
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], '{"data":{}}'));

        $client = new Client(null, ['transport' => $transport]);
        $client->account->get();

        $this->assertSame('Bearer sk_from_env', $transport->getLastRequest()->headers['Authorization'] ?? null);
    }

    public function testExplicitApiKeyWinsOverEnvironmentVariable(): void
    {
        putenv(Client::API_KEY_ENV . '=sk_from_env');
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], '{"data":{}}'));

        $client = new Client('sk_explicit', ['transport' => $transport]);
        $client->account->get();

        $this->assertSame('Bearer sk_explicit', $transport->getLastRequest()->headers['Authorization'] ?? null);
    }

    /**
     * @dataProvider missingKeyProvider
     */
    public function testMissingOrBlankApiKeyIsRejectedAtConstruction(?string $apiKey): void
    {
        putenv(Client::API_KEY_ENV);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage(Client::API_KEY_ENV);
        new Client($apiKey, ['transport' => new MockTransport()]);
    }

    /**
     * @return array<string, array{?string}>
     */
    public function missingKeyProvider(): array
    {
        return [
            'null and no env' => [null],
            'empty string' => [''],
            'whitespace' => ['   '],
        ];
    }

    public function testApiKeyIsTrimmedBeforeUse(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], '{"data":{}}'));

        $client = new Client("  sk_test\n", ['transport' => $transport]);
        $client->account->get();

        $this->assertSame('Bearer sk_test', $transport->getLastRequest()->headers['Authorization'] ?? null);
    }

    public function testApiKeyWithEmbeddedControlCharactersIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('control characters');
        new Client("sk_test\r\nX-Injected: 1", ['transport' => new MockTransport()]);
    }

    /**
     * @dataProvider badBaseUrlProvider
     */
    public function testBaseUrlMustBeHttp(string $baseUrl): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('base_url');
        new Client('sk_test', ['base_url' => $baseUrl, 'transport' => new MockTransport()]);
    }

    /**
     * @return array<string, array{string}>
     */
    public function badBaseUrlProvider(): array
    {
        return [
            'file scheme' => ['file:///etc'],
            'no scheme' => ['api.euromail.dev'],
        ];
    }

    /**
     * A cleared config field arrives as '' (the WordPress plugin does this);
     * it must behave like an absent option, as it did in 1.x.
     *
     * @dataProvider blankBaseUrlProvider
     */
    public function testBlankBaseUrlMeansTheDefault(string $baseUrl): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], '{"data":{}}'));

        $client = new Client('sk_test', ['base_url' => $baseUrl, 'transport' => $transport]);
        $client->account->get();

        $this->assertSame(Client::DEFAULT_BASE_URL . '/v1/account', $transport->getLastRequest()->url ?? null);
    }

    /**
     * @return array<string, array{string}>
     */
    public function blankBaseUrlProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['  '],
        ];
    }

    public function testBaseUrlTrailingSlashIsDropped(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], '{"data":{}}'));

        $client = new Client('sk_test', ['base_url' => 'http://localhost:8080/', 'transport' => $transport]);
        $client->account->get();

        $this->assertSame('http://localhost:8080/v1/account', $transport->getLastRequest()->url ?? null);
    }

    public function testMalformedJsonOnSuccessStatusIsAnErrorNotAnEmptyResult(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, ['X-Request-Id' => 'req_9'], '<html>proxy error</html>'));
        $client = new Client('sk_test', ['transport' => $transport]);

        try {
            $client->account->get();
            $this->fail('Expected EuroMailException was not thrown.');
        } catch (EuroMailException $exception) {
            $this->assertSame(200, $exception->getStatusCode());
            $this->assertSame('req_9', $exception->getRequestId());
            $this->assertStringContainsString('GET /v1/account', $exception->getMessage());
            $this->assertFalse($exception->isRetryable());
        }
    }

    public function testJsonScalarOnSuccessStatusIsAnError(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], 'true'));
        $client = new Client('sk_test', ['transport' => $transport]);

        $this->expectException(EuroMailException::class);
        $this->expectExceptionMessage('not a JSON object');
        $client->account->get();
    }

    /**
     * @dataProvider emptyBodyProvider
     */
    public function testEmptyOrWhitespaceBodyDecodesToEmptyArray(string $body): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(204, [], $body));
        $client = new Client('sk_test', ['transport' => $transport]);

        $this->assertSame([], $client->request('DELETE', '/v1/templates/t_1'));
    }

    /**
     * @return array<string, array{string}>
     */
    public function emptyBodyProvider(): array
    {
        return [
            'empty' => [''],
            'newline' => ["\n"],
            'crlf and spaces' => ["  \r\n"],
        ];
    }

    /**
     * @dataProvider envelopeWithoutDataProvider
     */
    public function testSuccessEnvelopeWithoutDataObjectIsAnErrorNotAnEmptyRecord(string $body): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(200, [], $body));
        $client = new Client('sk_test', ['transport' => $transport]);

        $this->expectException(EuroMailException::class);
        $this->expectExceptionMessage('"data"');
        $client->templates->get('t_1');
    }

    /**
     * @return array<string, array{string}>
     */
    public function envelopeWithoutDataProvider(): array
    {
        return [
            'no data key' => ['{}'],
            'data is null' => ['{"data":null}'],
            'data is a string' => ['{"data":"oops"}'],
        ];
    }

    public function testExtraRequestHeadersAreSentAlongsideTheDefaults(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(204, [], ''));
        $client = new Client('sk_test', ['transport' => $transport]);

        $client->request('DELETE', '/v1/x', null, ['headers' => ['X-Confirm-Delete' => 'DELETE']]);

        $headers = $transport->getLastRequest()->headers ?? [];
        $this->assertSame('DELETE', $headers['X-Confirm-Delete'] ?? null);
        $this->assertSame('Bearer sk_test', $headers['Authorization'] ?? null);
    }

    public function testRetryOptionFalseSendsExactlyOnceEvenWhenRetriesAreConfigured(): void
    {
        $transport = new MockTransport();
        $transport->queueException(new TransportException('timeout'));
        $transport->queueResponse(new Response(200, [], '{"data":{}}'));
        $client = new Client('sk_test', ['transport' => $transport, 'max_retries' => 2, 'max_retry_delay' => 0]);

        try {
            $client->request('POST', '/v1/x', ['a' => 1], ['retry' => false]);
            $this->fail('Expected TransportException was not thrown.');
        } catch (TransportException $exception) {
            $this->assertSame(1, $transport->getRequestCount());
        }

        $client->request('POST', '/v1/x', ['a' => 1]);
        $this->assertSame(2, $transport->getRequestCount(), 'the default still retries and consumes the queued success');
    }
}
