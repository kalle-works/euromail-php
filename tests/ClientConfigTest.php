<?php

namespace EuroMail\Tests;

use EuroMail\Client;
use EuroMail\Exceptions\EuroMailException;
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
            'empty' => [''],
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

    public function testEmptyBodyDecodesToEmptyArray(): void
    {
        $transport = new MockTransport();
        $transport->queueResponse(new Response(204, [], ''));
        $client = new Client('sk_test', ['transport' => $transport]);

        $this->assertSame([], $client->request('DELETE', '/v1/account'));
    }
}
