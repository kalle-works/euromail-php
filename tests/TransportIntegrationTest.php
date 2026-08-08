<?php

namespace EuroMail\Tests;

use EuroMail\Http\CurlTransport;
use EuroMail\Http\Request;
use EuroMail\Http\StreamTransport;
use EuroMail\Http\TransportInterface;
use PHPUnit\Framework\TestCase;

/**
 * Exercises CurlTransport and StreamTransport against a real HTTP server
 * (PHP's built-in web server running tests/fixtures/router.php) instead of a
 * mock, so redirect-following, header casing and body delivery are verified
 * against actual socket/HTTP behavior rather than assumptions about it.
 */
final class TransportIntegrationTest extends TestCase
{
    /** @var resource|null */
    private static $process;
    private static int $port;
    private static string $baseUrl;

    public static function setUpBeforeClass(): void
    {
        self::$port = self::findFreePort();
        self::$baseUrl = 'http://127.0.0.1:' . self::$port;

        $router = __DIR__ . '/fixtures/router.php';
        $command = sprintf(
            '%s -S 127.0.0.1:%d %s',
            escapeshellarg(PHP_BINARY),
            self::$port,
            escapeshellarg($router)
        );

        $descriptors = [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']];
        $process = proc_open($command, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to start PHP built-in server for integration tests.');
        }

        self::$process = $process;

        self::waitForServer();
    }

    public static function tearDownAfterClass(): void
    {
        if (is_resource(self::$process)) {
            proc_terminate(self::$process);
            proc_close(self::$process);
            self::$process = null;
        }
    }

    private static function findFreePort(): int
    {
        $socket = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($socket === false) {
            throw new \RuntimeException("Could not reserve a free port: {$errstr}");
        }

        $name = stream_socket_get_name($socket, false);
        fclose($socket);

        return (int) substr($name, strrpos($name, ':') + 1);
    }

    private static function waitForServer(): void
    {
        $deadline = microtime(true) + 5.0;

        while (microtime(true) < $deadline) {
            $connection = @fsockopen('127.0.0.1', self::$port, $errno, $errstr, 0.2);
            if ($connection !== false) {
                fclose($connection);
                return;
            }
            usleep(50000);
        }

        throw new \RuntimeException('PHP built-in server did not start accepting connections in time.');
    }

    private function makeTransport(string $type): TransportInterface
    {
        if ($type === 'curl') {
            if (!extension_loaded('curl')) {
                $this->markTestSkipped('ext-curl not loaded.');
            }

            return new CurlTransport();
        }

        return new StreamTransport();
    }

    public function transportTypeProvider(): array
    {
        return [
            'curl' => ['curl'],
            'stream' => ['stream'],
        ];
    }

    /**
     * @dataProvider transportTypeProvider
     */
    public function testOkEndpointReturnsStatusCodeAndLowercasedHeaders(string $type): void
    {
        $transport = $this->makeTransport($type);

        $response = $transport->send(new Request('GET', self::$baseUrl . '/ok'));

        $this->assertSame(200, $response->statusCode);
        $this->assertSame('custom-value', $response->getHeader('X-Custom-Header'));
        $this->assertArrayHasKey('x-custom-header', $response->headers);

        $decoded = json_decode($response->body, true);
        $this->assertSame(['status' => 'ok'], $decoded['data']);
    }

    /**
     * @dataProvider transportTypeProvider
     */
    public function testErrorEndpointReturnsStatusCodeAndErrorEnvelopeBody(string $type): void
    {
        $transport = $this->makeTransport($type);

        $response = $transport->send(new Request('GET', self::$baseUrl . '/error'));

        $this->assertSame(422, $response->statusCode);

        $decoded = json_decode($response->body, true);
        $this->assertSame('validation_error', $decoded['error']['type']);
        $this->assertSame('bad_input', $decoded['error']['code']);
    }

    /**
     * @dataProvider transportTypeProvider
     */
    public function testPostBodyIsDeliveredToServer(string $type): void
    {
        $transport = $this->makeTransport($type);
        $payload = json_encode(['hello' => 'world']);

        $response = $transport->send(new Request(
            'POST',
            self::$baseUrl . '/echo',
            ['Content-Type' => 'application/json'],
            $payload
        ));

        $this->assertSame(200, $response->statusCode);

        $decoded = json_decode($response->body, true);
        $this->assertSame('POST', $decoded['method']);
        $this->assertSame($payload, $decoded['body']);
    }

    /**
     * @dataProvider transportTypeProvider
     */
    public function testCustomRequestHeadersReachTheServer(string $type): void
    {
        $transport = $this->makeTransport($type);

        $response = $transport->send(new Request(
            'GET',
            self::$baseUrl . '/echo',
            ['X-Test-Header' => 'sdk-value']
        ));

        $decoded = json_decode($response->body, true);
        $this->assertSame('sdk-value', $decoded['headers']['X-TEST-HEADER'] ?? $decoded['headers']['X-Test-Header'] ?? null);
    }

    /**
     * @dataProvider transportTypeProvider
     */
    public function testRedirectIsFollowedToFinalOkStatusAndBody(string $type): void
    {
        $transport = $this->makeTransport($type);

        $response = $transport->send(new Request('GET', self::$baseUrl . '/redirect'));

        // Both transports must follow the 302 and report the FINAL hop's status,
        // not the redirect's 302 — this is what fix 2 (final-hop header/status
        // parsing) makes true for StreamTransport, and what CurlTransport's
        // CURLOPT_FOLLOWLOCATION + per-hop header reset makes true for curl.
        $this->assertSame(200, $response->statusCode);

        $decoded = json_decode($response->body, true);
        $this->assertSame(['status' => 'ok'], $decoded['data']);

        // The final hop's own header must be present...
        $this->assertSame('custom-value', $response->getHeader('X-Custom-Header'));
        // ...and the redirect hop's Location header must NOT leak through.
        $this->assertNull($response->getHeader('Location'));
    }
}
