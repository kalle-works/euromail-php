<?php

namespace EuroMail\Webhooks;

final class WebhookSignature
{
    public static function verify(string $payload, string $signatureHeader, string $secret, int $tolerance = 300): bool
    {
        $timestamp = null;
        $signatures = [];

        foreach (explode(',', $signatureHeader) as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $pieces = explode('=', $part, 2);
            if (count($pieces) !== 2) {
                continue;
            }

            [$key, $value] = $pieces;
            if ($key === 't') {
                $timestamp = $value;
            } elseif ($key === 'v1') {
                $signatures[] = $value;
            }
        }

        if ($timestamp === null || $signatures === [] || !ctype_digit($timestamp)) {
            return false;
        }

        $timestamp = (int) $timestamp;
        if (abs(time() - $timestamp) > $tolerance) {
            return false;
        }

        $expected = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);

        foreach ($signatures as $signature) {
            if (hash_equals($expected, $signature)) {
                return true;
            }
        }

        return false;
    }
}
