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

    /**
     * A fixed, independently-computed HMAC-SHA256 vector for the signature
     * algorithm the worker actually uses to sign deliveries
     * (crates/euromail-worker/src/processors/fire_webhook.rs): sign
     * "{timestamp}.{payload}" with the webhook secret and hex-encode it.
     *
     * Unlike the tests above, the expected signature here is a literal that
     * was computed independently with both Python's hmac/hashlib and PHP's
     * hash_hmac (outside of, and prior to, this SDK's implementation) rather
     * than produced by calling the same `sign()` helper the assertion checks
     * against. A `sign()`-based test can drift in lockstep with a broken
     * implementation and still pass; this cannot.
     */
    public function testKnownVectorMatchesTheWorkersSigningAlgorithm(): void
    {
        $secret = 'whsec_test_vector_secret';
        $timestamp = 1700000000;
        $payload = '{"event_type":"email.delivered","data":{"id":"em_test123","to":"recipient@example.com"}}';
        $expectedSignature = '7100ccaee1671c17870749fa31402bf471442409c753f037f148694f73162156';

        $header = 't=' . $timestamp . ',v1=' . $expectedSignature;

        // A tolerance large enough to not be time-sensitive keeps this test
        // about the signature algorithm, not about wall-clock timing (which
        // testExpiredTimestampFails and friends already cover).
        $this->assertTrue(WebhookSignature::verify($payload, $header, $secret, PHP_INT_MAX));

        // The vector is also a negative control: flipping a single character
        // of the signature must fail, proving verify() is not accepting the
        // header on faith.
        $tamperedHeader = 't=' . $timestamp . ',v1=' . substr($expectedSignature, 0, -1)
            . (substr($expectedSignature, -1) === '0' ? '1' : '0');
        $this->assertFalse(WebhookSignature::verify($payload, $tamperedHeader, $secret, PHP_INT_MAX));
    }

    /**
     * The exact test vector specified for this cross-SDK effort (the same
     * one asserted by the Python, TypeScript, Go, and Rust SDKs), so all
     * five verify identically against one shared, independently-computed
     * HMAC — not just internally consistent with this package's own
     * signing code.
     */
    public function testSharedCrossSdkContractVector(): void
    {
        $secret = 'whsec_test_secret_do_not_use';
        $timestamp = 1735689600;
        $payload = '{"event":"delivered","email_id":"018f2c3a-7b1e-7c3e-8b1a-2f6e9d4c5a01",'
            . '"account_id":"018f2c3a-7b1e-7c3e-8b1a-2f6e9d4c5a02","timestamp":"2025-01-01T00:00:00Z"}';
        $expectedSignature = 'd571fbef13b9e524d460f6f2c88f8d8dc7df3c50ff7aabdedd8a3656abb96dd0';

        $header = 't=' . $timestamp . ',v1=' . $expectedSignature;

        $this->assertTrue(WebhookSignature::verify($payload, $header, $secret, PHP_INT_MAX));
    }
}
