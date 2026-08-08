<?php

namespace EuroMail\Tests;

use EuroMail\Webhooks\WebhookSignature;
use PHPUnit\Framework\TestCase;

final class WebhookSignatureTest extends TestCase
{
    private function sign(string $payload, string $secret, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    public function testValidSignaturePasses(): void
    {
        $payload = '{"event":"email.delivered"}';
        $secret = 'whsec_test_secret';
        $timestamp = time();
        $header = 't=' . $timestamp . ',v1=' . $this->sign($payload, $secret, $timestamp);

        $this->assertTrue(WebhookSignature::verify($payload, $header, $secret));
    }

    public function testWrongSecretFails(): void
    {
        $payload = '{"event":"email.delivered"}';
        $timestamp = time();
        $header = 't=' . $timestamp . ',v1=' . $this->sign($payload, 'correct_secret', $timestamp);

        $this->assertFalse(WebhookSignature::verify($payload, $header, 'wrong_secret'));
    }

    public function testExpiredTimestampFails(): void
    {
        $payload = '{"event":"email.delivered"}';
        $secret = 'whsec_test_secret';
        $timestamp = time() - 600;
        $header = 't=' . $timestamp . ',v1=' . $this->sign($payload, $secret, $timestamp);

        $this->assertFalse(WebhookSignature::verify($payload, $header, $secret, 300));
    }

    public function testFutureTimestampBeyondToleranceFails(): void
    {
        $payload = '{"event":"email.delivered"}';
        $secret = 'whsec_test_secret';
        $timestamp = time() + 600;
        $header = 't=' . $timestamp . ',v1=' . $this->sign($payload, $secret, $timestamp);

        $this->assertFalse(WebhookSignature::verify($payload, $header, $secret, 300));
    }

    public function testTimestampWithinToleranceOfFuturePasses(): void
    {
        $payload = '{"event":"email.delivered"}';
        $secret = 'whsec_test_secret';
        $timestamp = time() + 60;
        $header = 't=' . $timestamp . ',v1=' . $this->sign($payload, $secret, $timestamp);

        $this->assertTrue(WebhookSignature::verify($payload, $header, $secret, 300));
    }

    public function testMalformedHeaderReturnsFalseWithoutThrowing(): void
    {
        $payload = '{"event":"email.delivered"}';
        $secret = 'whsec_test_secret';

        $this->assertFalse(WebhookSignature::verify($payload, 'not-a-valid-header', $secret));
        $this->assertFalse(WebhookSignature::verify($payload, '', $secret));
        $this->assertFalse(WebhookSignature::verify($payload, 't=abc,v1=deadbeef', $secret));
        $this->assertFalse(WebhookSignature::verify($payload, 'v1=deadbeef', $secret));
    }

    public function testMultipleV1EntriesAcceptedIfAnyMatchesForSecretRotation(): void
    {
        $payload = '{"event":"email.delivered"}';
        $oldSecret = 'whsec_old';
        $newSecret = 'whsec_new';
        $timestamp = time();

        $header = sprintf(
            't=%d,v1=%s,v1=%s',
            $timestamp,
            $this->sign($payload, 'whsec_totally_wrong', $timestamp),
            $this->sign($payload, $newSecret, $timestamp)
        );

        // Verifying against the new secret should succeed even though the first v1 entry
        // (signed with a different, wrong secret) does not match.
        $this->assertTrue(WebhookSignature::verify($payload, $header, $newSecret));

        // And it should also work when checking against the old secret pattern used during rotation.
        $rotationHeader = sprintf(
            't=%d,v1=%s,v1=%s',
            $timestamp,
            $this->sign($payload, $oldSecret, $timestamp),
            $this->sign($payload, $newSecret, $timestamp)
        );
        $this->assertTrue(WebhookSignature::verify($payload, $rotationHeader, $oldSecret));
        $this->assertTrue(WebhookSignature::verify($payload, $rotationHeader, $newSecret));
    }
}
