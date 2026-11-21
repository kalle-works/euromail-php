<?php

namespace EuroMail\Tests;

use EuroMail\Client;
use EuroMail\Http\Response;
use PHPUnit\Framework\TestCase;

final class DomainsTest extends TestCase
{
    private function clientReturning(MockTransport $transport, bool $requireTls): Client
    {
        $transport->queueResponse(new Response(200, [], (string) json_encode([
            'data' => ['id' => 'dom_1', 'domain' => 'example.com', 'require_tls' => $requireTls],
        ])));

        return new Client('sk_test', ['transport' => $transport]);
    }

    public function testUpdateRequiringTlsPatchesTheDomainAndReturnsItWithRequireTls(): void
    {
        $transport = new MockTransport();
        $client = $this->clientReturning($transport, true);

        $domain = $client->domains->update('dom_1', ['require_tls' => true]);

        $request = $transport->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('PATCH', $request->method);
        $this->assertSame('https://api.euromail.dev/v1/domains/dom_1', $request->url);
        $this->assertSame(['require_tls' => true], json_decode((string) $request->body, true));
        $this->assertTrue($domain['require_tls']);
    }

    public function testUpdateSendsFalseInsteadOfDroppingIt(): void
    {
        $transport = new MockTransport();
        $client = $this->clientReturning($transport, false);

        $domain = $client->domains->update('dom_1', ['require_tls' => false]);

        $request = $transport->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('{"require_tls":false}', $request->body);
        $this->assertFalse($domain['require_tls']);
    }

    public function testUpdateWithoutFieldsChangesNothingAndDoesNotSendAnArrayBody(): void
    {
        $transport = new MockTransport();
        $client = $this->clientReturning($transport, true);

        $domain = $client->domains->update('dom_1', []);

        $request = $transport->getLastRequest();
        $this->assertNotNull($request);
        $this->assertSame('GET', $request->method);
        $this->assertNull($request->body);
        $this->assertSame('dom_1', $domain['id']);
    }
}
