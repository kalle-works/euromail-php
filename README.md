# euromail-php

Official PHP SDK for the [euromail.dev](https://euromail.dev) transactional email API.

Requires PHP 7.4 or newer. Zero runtime dependencies beyond `ext-json`.

## Install

```bash
composer require euromail/euromail-php
```

## Quickstart

```php
use EuroMail\Client;

$client = new Client('sk_live_...');

$email = $client->emails->send([
    'from' => 'sender@yourdomain.com',
    'to' => 'recipient@example.com',
    'subject' => 'Welcome',
    'html_body' => '<p>Thanks for signing up.</p>',
]);

echo $email->id;
echo $email->status; // "queued"
```

### Client options

```php
$client = new Client('sk_live_...', [
    'base_url' => 'https://api.euromail.dev', // default
    'timeout' => 15,                          // seconds, default 15
    'max_retries' => 3,                       // default 0 (no retries)
    'max_retry_delay' => 30,                  // seconds, default 30
]);
```

When `max_retries` is greater than zero, requests that fail with a `429`, a `5xx`
status, or a transport-level failure (DNS, TLS, connection timeout) are retried
automatically. The `retry-after` response header is honored when present on
either a `429` or a `5xx` response; otherwise the SDK backs off exponentially
(1s, 2s, 4s, ...). Either way, the wait between attempts is capped at
`max_retry_delay` seconds, so a very large `retry-after` value from the server
can't stall a request for longer than that.

### Idempotency

`emails->send()` and `emails->sendBatch()` automatically attach an
`idempotency_key` (a UUIDv4, via `Idempotency::generate()`) to any email that
doesn't already have one, generated once before the request is sent. If a
request is retried (per `max_retries` above), every attempt reuses that same
key, so retrying a timed-out send can't result in a duplicate email. Pass your
own `idempotency_key` in the params to override it.

## Error handling

Every non-2xx response and every transport failure is raised as an exception
under `EuroMail\Exceptions`, all extending `EuroMailException`:

- `TransportException` — network/DNS/TLS/timeout failure, no HTTP status
- `AuthenticationException` — 401 or 403
- `NotFoundException` — 404
- `ConflictException` — 409
- `ValidationException` — 422, or any response with error type `validation_error`
- `RateLimitException` — 429
- `ServerException` — 5xx

Every `EuroMailException` exposes `getRetryAfter(): ?int`, parsed from the
`retry-after` response header on both `429` and `5xx` responses (not just
`RateLimitException`), used internally to size the wait between automatic
retries.

```php
use EuroMail\Exceptions\EuroMailException;
use EuroMail\Exceptions\RateLimitException;
use EuroMail\Exceptions\ValidationException;

try {
    $client->emails->send([
        'from' => 'sender@yourdomain.com',
        'to' => 'recipient@example.com',
        'subject' => 'Welcome',
        'html_body' => '<p>Thanks for signing up.</p>',
    ]);
} catch (RateLimitException $e) {
    sleep($e->getRetryAfter() ?? 5);
} catch (ValidationException $e) {
    // $e->getErrorCode(), $e->getErrorType(), $e->getMessage()
} catch (EuroMailException $e) {
    if ($e->isRetryable()) {
        // safe to retry: transport failure, 429, or 5xx
    }
    error_log(sprintf(
        '[euromail] %s (status=%s request_id=%s)',
        $e->getMessage(),
        $e->getStatusCode(),
        $e->getRequestId()
    ));
}
```

## Custom transport

The SDK ships a `CurlTransport` (used automatically when `ext-curl` is loaded)
and a `StreamTransport` fallback. Inject your own by implementing
`EuroMail\Http\TransportInterface`:

```php
use EuroMail\Http\Request;
use EuroMail\Http\Response;
use EuroMail\Http\TransportInterface;

class LoggingTransport implements TransportInterface
{
    private TransportInterface $inner;

    public function __construct(TransportInterface $inner)
    {
        $this->inner = $inner;
    }

    public function send(Request $request): Response
    {
        error_log("euromail: {$request->method} {$request->url}");
        return $this->inner->send($request);
    }
}

$client = new Client('sk_live_...', [
    'transport' => new LoggingTransport(new \EuroMail\Http\CurlTransport()),
]);
```

## Webhook signature verification

```php
use EuroMail\Webhooks\WebhookSignature;

$payload = file_get_contents('php://input');
$signatureHeader = $_SERVER['HTTP_EUROMAIL_SIGNATURE'] ?? '';
$secret = getenv('EUROMAIL_WEBHOOK_SECRET');

if (!WebhookSignature::verify($payload, $signatureHeader, $secret)) {
    http_response_code(400);
    exit;
}

$event = json_decode($payload, true);
// handle $event
```

`WebhookSignature::verify()` never throws — a malformed header, wrong secret,
or timestamp outside the tolerance window (default 300 seconds) simply
returns `false`. During secret rotation the signature header may carry
multiple `v1=` entries; verification succeeds if any of them match.

## License

MIT
