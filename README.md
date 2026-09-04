# euromail-php

Official PHP SDK for the [euromail.dev](https://euromail.dev) transactional email API.

Requires PHP 7.4 or newer. Zero runtime dependencies beyond `ext-json`.
Covers emails, templates, domains, webhooks, suppressions, contact lists,
newsletters, signup forms, inbound mail and routes, sub-accounts, API keys,
analytics, audit logs, operations, dead letters and the account itself.
Not yet wrapped: agent mailboxes, GDPR export/erase, billing and insights.

## Install

```bash
composer require euromail/euromail
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

The API key can also come from the `EUROMAIL_API_KEY` environment variable:

```php
$client = new Client(); // reads EUROMAIL_API_KEY
```

A missing or blank key throws `InvalidArgumentException` from the
constructor rather than a 401 on the first request. The key is trimmed, so
a trailing newline from a secret file is harmless; a key with other control
characters is rejected. A blank `base_url` means the production API.

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

## Resources

Every resource hangs off the client as a property. Methods return plain
arrays shaped like the API's JSON (`emails` returns `SentEmail` /
`EmailDetails` objects), so new fields the API adds are available without an
SDK update. Path segments are URL-encoded for you.

| Property | Methods |
| --- | --- |
| `emails` | `send`, `sendBatch`, `broadcast`, `get`, `all`, `iterate`, `cancel`, `links`, `validate` |
| `templates` | `create`, `all`, `iterate`, `get`, `update`, `delete` |
| `domains` | `create`, `all`, `page`, `iterate`, `get`, `verify`, `delete`, `setSendingSubdomain`, `setTrackingDomain`, `verifyTrackingDomain`, `removeTrackingDomain` |
| `webhooks` | `create`, `all`, `iterate`, `get`, `update`, `test`, `delete` |
| `suppressions` | `create`, `all`, `iterate`, `delete`, `import`, `export` |
| `contactLists` | `create`, `all`, `get`, `update`, `delete`, `addContact`, `addContacts`, `contacts`, `iterateContacts`, `removeContact`, `getWelcomeEmail`, `configureWelcomeEmail` |
| `newsletters` | `create`, `all`, `get`, `update`, `delete`, `send` |
| `signupForms` | `create`, `all`, `get`, `update`, `delete`, `toggle` |
| `inbound` | `all`, `iterate`, `get`, `delete` |
| `inboundRoutes` | `create`, `all`, `iterate`, `get`, `update`, `delete` |
| `subAccounts` | `create`, `all`, `iterate`, `get`, `update`, `delete`, `analytics`, `createApiKey` |
| `apiKeys` | `create`, `all`, `delete` |
| `analytics` | `overview`, `timeseries`, `domains`, `tags`, `aggregate`, `export` |
| `auditLogs` | `all`, `iterate` |
| `operations` | `all`, `iterate`, `get` |
| `deadLetters` | `all`, `retry`, `delete` |
| `account` | `get`, `export`, `delete` |

```php
$domain = $client->domains->create('yourdomain.com');
// publish $domain['dns_records'], then:
$client->domains->verify($domain['id']);

$template = $client->templates->create([
    'alias' => 'welcome',
    'name' => 'Welcome',
    'subject' => 'Welcome, {{name}}',
    'html_body' => '<p>Hi {{name}}</p>',
]);

$webhook = $client->webhooks->create([
    'url' => 'https://example.com/hooks/euromail',
    'events' => ['delivered', 'bounced', 'complained'],
]);
$webhook['secret']; // shown once; store it for signature verification below
```

### Pagination

Paginated `all()` methods return one page as `['data' => [...],
'pagination' => ['page', 'per_page', 'total', 'total_pages']]` and accept
`page` / `per_page` in their filters. The matching `iterate()` methods walk
every page and yield items one at a time:

```php
foreach ($client->emails->iterate(['status' => 'bounced']) as $email) {
    echo $email->id, PHP_EOL;
}

foreach ($client->contactLists->iterateContacts($listId) as $contact) {
    // ...
}
```

`domains->all()` returns the bare list of domains, as it did in 1.x;
`domains->page()` returns the envelope. `newsletters->all()` pages with
`limit` / `offset` and returns `['data' => [...], 'total' => n]`, mirroring
that endpoint.

Analytics methods, `domains->create()`, `domains->setTrackingDomain()`,
`domains->verifyTrackingDomain()`, `newsletters->get()` and
`emails->validate()` return the full response body, because those endpoints
carry fields next to `data` (`period`, `warnings`, `cname_target`,
`tracking_check`, `stats`, `valid`).

`emails->broadcast()` is never retried automatically: the endpoint has no
idempotency key, so a retry after a timeout could mail the whole list twice.
Check the returned `operation_id` with `operations->get()` before resending.

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

A 2xx response whose body is not a JSON object (an HTML error page from a
proxy, a truncated response) is raised as a plain `EuroMailException`
carrying the status code and request id, never returned as an empty result.

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

## Development

```bash
composer install
composer check   # php -l, PHPStan level 8, PHPUnit
```

## License

MIT
