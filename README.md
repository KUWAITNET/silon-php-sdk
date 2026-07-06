# Silon PHP SDK

PHP client for the [Silon](https://silon.tech) messaging platform API — send
messages on any channel (WhatsApp, SMS, email, push, web push, voice), manage
CRM contacts and groups, run bulk campaigns, consume events, and verify
webhooks. PHP 8.1+, **zero runtime dependencies** (native `curl`), PSR-4.

## Installation

```bash
composer require silon/silon-sdk
```

Requires PHP >= 8.1 with the `curl` and `json` extensions. The SDK pulls in no
runtime packages; the default transport is native curl. You can swap in your
own HTTP client (Guzzle, a PSR-18 adapter, a mock) — see
[Custom HTTP client](#custom-http-client).

## Quickstart

```php
<?php

use Silon\Client;

$client = new Client([
    'apiKey' => 'sk_live_...',   // Settings → API keys; or set SILON_API_KEY
    'workspace' => 'acme',       // => https://acme.silon.tech; or SILON_WORKSPACE / SILON_BASE_URL
]);

$sent = $client->messages->send([
    'channel' => 'whatsapp',
    'to' => ['client_id' => 'cust_001'],
    'content' => ['body' => 'Your order has shipped 📦'],
]);

echo $sent->id, ' ', $sent->status;   // e.g. "9f3e..." "queued"
```

Configuration resolves in this order and **fails fast** at construction (a
`Silon\Exception\SilonException`) if a required value is missing:

- **API key** — `apiKey`, else `SILON_API_KEY`. Required.
- **Base URL** — `baseUrl`, else `SILON_BASE_URL`, else `workspace` →
  `https://<workspace>.silon.tech`, else `SILON_WORKSPACE`. Required; a
  trailing slash is stripped.
- `timeout` (seconds, default `30`), `maxRetries` (default `2`), `headers`
  (added to every request).

Every operation takes an **associative array** of the API's own field names
(snake_case) and returns a **typed model** whose properties mirror the API.
Unknown response fields are preserved and readable via `$model->new_field`, so
an older SDK keeps working as the API grows.

## Sending

Three entry points, every channel: `messages->send` targets a single recipient
via `to`; `messages->sendBatch` sends many independent, personalised messages
in one call — inline rows via `messages` (max 500) or an uploaded CSV via
`file`; `broadcasts->create` fans one piece of content out to an `audience`
(a client group, explicit client ids, or an inline `recipients` list). On
`send`, exactly one of `to` / `audience` is required, and on `sendBatch`
exactly one of `messages` / `file` — the SDK validates client-side and throws
`SilonException` before any network call.

```php
// Approved WhatsApp template to a raw number
$client->messages->send([
    'channel' => 'whatsapp',
    'to' => ['phone_number' => '+12025550123'],
    'whatsapp_template' => [
        'name' => 'order_confirmed',
        'language' => 'en',
        'variables' => ['body_1' => 'Sara', 'body_2' => 'ORD-42'],
    ],
    'provider' => 'meta_cloud',
]);

// Personalised batch — every row is its own message (same shape as a send()
// body minus `audience`); the top-level channel is the default, a row's own
// channel wins. Max 500 rows, all-or-nothing: any invalid row 422s the whole
// batch (`messages[3].to.phone_number`) and nothing is queued.
$batch = $client->messages->sendBatch([
    'channel' => 'sms',
    'messages' => [
        ['to' => ['phone_number' => '+96550001234'], 'content' => ['body' => 'Sara, table for 2 at 7pm.']],
        ['to' => ['phone_number' => '+96550001235'], 'content' => ['body' => 'Omar, table for 4 at 9pm.']],
        [
            'channel' => 'email',
            'to' => ['email' => 'lulu@example.com'],
            'content' => ['subject' => 'Booking confirmed', 'body' => 'Lulu, table for 6 at 8pm.'],
        ],
    ],
]);
// Per-row envelopes come back in request order; poll each id individually.
// Inline batches have no GET endpoint — the per-row ids are the tracking key.
foreach ($batch->messages ?? [] as $row) {
    $status = $client->messages->retrieve($row->id);
    echo $row->id, ' ', $status->status, PHP_EOL;
}

// Batch from an uploaded CSV — request-level fields are row defaults, CSV
// columns override per row ({{name}} renders from the row). The rows expand
// asynchronously: the 202 is an aggregate (no per-row `messages`) and the
// returned id IS the bulk batch id.
$uploaded = $client->bulk->files->upload("phone_number,name\n+96550001234,Sara\n");
$fileBatch = $client->messages->sendBatch([
    'file' => $uploaded->name,
    'channel' => 'sms',
    'content' => ['body' => 'Hello {{name}}'],
]);
echo $fileBatch->status, ' ', $fileBatch->row_count; // "queued" 1
$detail = $client->bulk->retrieve((int) $fileBatch->id); // per-row status

// Email broadcast to a client group
$result = $client->broadcasts->create([
    'channel' => 'email',
    'audience' => ['type' => 'client_group', 'slug' => 'vip'],
    'content' => ['subject' => 'We saved you a seat', 'body' => '<h1>Hello</h1>'],
]);
echo $result->target_count, ' ', $result->skipped_count;
// Why rows were skipped, itemised (skipped_count stays the sum):
echo $result->skipped?->suppressed;  // e.g. 2

// Track it
$broadcast = $client->broadcasts->retrieve($result->id);
foreach ($client->broadcasts->deliveries($result->id, ['limit' => 100])->autoPaging() as $delivery) {
    echo $delivery->client_id, ' ', $delivery->status, PHP_EOL;
}
```

Every `messages->send` / `messages->sendBatch` / `broadcasts->create` /
`otp->send` call carries an `Idempotency-Key` header (auto-generated UUIDv4
unless you pass `idempotency_key`), so automatic retries can never double-send.
The key is a request header, never part of the JSON body. Channel-specific
fields not covered by the documented keys can go in `extra_body`, which is
merged into the JSON body last.

## Scheduling and cancellation

Pass `send_at` — a `DateTimeInterface` **with a UTC offset**, or an ISO-8601
string (e.g. `"2026-07-15T09:00:00+03:00"`) — on `messages->send`,
`broadcasts->create`, or the **file** form of `messages->sendBatch` to schedule
the send. It must be strictly in the future and at most 90 days ahead; naive
date-times are rejected — otherwise the server answers `422 send-at-invalid`.
The response is the normal `202` envelope with `status: "scheduled"`, and its
`id` is stable across the lifecycle: `messages->retrieve` / `broadcasts->
retrieve` resolve it before dispatch (`"scheduled"`) and after (the normal
queued/sent lifecycle).

```php
$scheduled = $client->messages->send([
    'channel' => 'sms',
    'to' => ['phone_number' => '+96550001234'],
    'content' => ['body' => 'Doors open in an hour'],
    'send_at' => new DateTimeImmutable('+1 hour'),
]);
echo $scheduled->status;                       // "scheduled"

// Changed your mind? Allowed while still "scheduled":
$canceled = $client->messages->cancel($scheduled->id);
echo $canceled->status;                        // "canceled" — never dispatches
```

`messages->cancel($id)` and `broadcasts->cancel($id)` return the same envelope
types as their create calls (now showing `status: "canceled"`) and emit a
`message.canceled` / `broadcast.canceled` event. Cancel is idempotent by
nature — repeating it answers `200` with the canceled envelope again (no
`Idempotency-Key` is sent, and none is needed). Once the send has dispatched
(or for an immediate send's id) the server answers `409 not-cancellable`
(`ConflictException`); an unknown id is a `404`. `send_at` on `sendBatch` works
with the **file** form only; with inline `messages` it is rejected
`422 batch-invalid` (schedule those individually via `messages->send`).

## Suppressions

`$client->suppressions` manages the workspace do-not-contact list. A row is an
address (E.164 phone or email, stored normalized so any formatting matches)
optionally scoped to one channel — omit `channel` to suppress the address
everywhere. It is enforced automatically on **every** send path:

- **Single-recipient sends** (`messages->send` with `to`, `otp->send`) to a
  suppressed address reject `422 recipient-suppressed`
  (`UnprocessableEntityException`).
- **Fan-outs** (`broadcasts->create`, `messages->sendBatch`, legacy bulk)
  silently **skip** suppressed recipients — never an error. The `202` envelope
  itemises why rows were skipped in an additive `skipped` object
  (`suppressed` / `wrong_channel` / `duplicate`), with `skipped_count` staying
  the sum. On broadcasts `skipped` is `null` exactly when `target_count` is
  `null` (a scheduled audience resolving at dispatch).

```php
$row = $client->suppressions->create([
    'address' => '+96550001234',
    'reason' => 'stop',   // "manual" (default) | "unsubscribe" | "hard_bounce" | "stop"
]);                       // omit `channel` => suppressed everywhere

foreach ($client->suppressions->list(['channel' => 'sms'])->autoPaging() as $s) {
    echo $s->address, ' ', $s->reason, PHP_EOL;
}
$client->suppressions->delete($row->id);
```

Transactional/legal sends may bypass suppression with
`'override_suppression' => true` — single-recipient sends only, and the key
must carry the `suppressions:override` scope (else `403 missing-scope`).

## CRM: clients and groups

```php
$client->clients->create([
    'client_id' => 'cust_001',
    'first_name' => 'Sara',
    'phone_number' => '+96512345678',
    'default_channel' => 'whatsapp',
]);

// Lists are cursor-paginated (newest first); iterate one page or drain all:
foreach ($client->clients->list(['limit' => 100])->autoPaging() as $contact) {
    echo $contact->client_id, PHP_EOL;
}

$client->clients->update('cust_001', ['notes' => 'VIP']);   // PATCH (partial)
$client->clients->delete('cust_001');

$group = $client->clientGroups->create([
    'name' => 'VIP',
    'slug' => 'vip',
    'client_ids' => ['cust_001', 'cust_002'],   // membership (write-only)
]);
```

## Templates

Slug-keyed message templates with an immutable version spine — the same rows
sends render for `'template' => ['slug' => ...]`.

```php
$tmpl = $client->templates->create([
    'slug' => 'order-shipped',
    'subject' => 'Your order is on its way',
    'body_md' => 'Hi {{ client_name }}, order **{{ order_id }}** has shipped.',
]);                                                    // version 1

$client->templates->update('order-shipped', ['body_md' => '...new copy...']);   // mints v2

// Pin a revision on a send (omit `version` for the latest):
$client->messages->send([
    'channel' => 'email',
    'to' => ['email' => 'sara@example.com'],
    'template' => ['slug' => 'order-shipped', 'version' => 1, 'variables' => ['client_name' => 'Sara']],
]);
```

## Webhooks

Verify the `Silon-Signature` header on incoming deliveries — no HTTP client
needed. Always verify against the **raw** request body, not re-encoded JSON.

```php
use Silon\Webhooks;
use Silon\Exception\WebhookSignatureVerificationException;

$payload = file_get_contents('php://input');
$signature = $_SERVER['HTTP_SILON_SIGNATURE'] ?? '';

try {
    $event = Webhooks::constructEvent($payload, $signature, $_ENV['SILON_WEBHOOK_SECRET']);
} catch (WebhookSignatureVerificationException $e) {
    http_response_code(400);
    exit;
}

if ($event->type === 'message.failed') {
    error_log($event->data->recipient . ' failed: ' . $event->data->error);
}
```

`Webhooks::verifySignature($payload, $header, $secret, $tolerance = 300)`
returns a `bool` (constant-time compare; `$tolerance <= 0` skips the freshness
check), and `Webhooks::sign($secret, $payload, $timestamp = null)` produces a
valid header for tests. You can also read the same event stream over HTTP with
`$client->events->list()` / `$client->events->retrieve($id)`, and manage
subscriptions with `$client->webhookEndpoints` (create returns a one-time
`secret`; `->test($id)` sends a signed ping; `->listAttempts($id)` is the
delivery ledger).

## Pagination

Cursor endpoints return a `Silon\CursorPage`, which is iterable, `count()`-able,
and indexable for the current page, and offers `hasNextPage()`, `nextPage()`,
and a lazy `autoPaging()` generator that walks every page. `nextPage()` never
follows the opaque `next` URL directly — it extracts only its query params and
re-requests the original path against your configured base URL (proxy safety).

```php
$page = $client->events->list(['type' => 'message.delivered', 'limit' => 50]);
foreach ($page as $event) { /* this page only */ }
foreach ($page->autoPaging() as $event) { /* every page, fetched lazily */ }
```

## Errors

Every non-2xx response raises a typed exception under `Silon\Exception\`,
selected by status code, all extending `ApiStatusException` (itself a
`SilonException`):

| Status | Exception |
| --- | --- |
| 400 | `BadRequestException` |
| 401 | `AuthenticationException` |
| 403 | `PermissionDeniedException` |
| 404 | `NotFoundException` |
| 409 | `ConflictException` |
| 410 | `GoneException` |
| 422 | `UnprocessableEntityException` |
| 429 | `RateLimitException` (adds `->retryAfter` seconds) |
| ≥500 | `InternalServerException` |

Each carries `->statusCode`, `->requestId` (from `X-Request-Id`), `->errorType`,
`->errors` (a list of `ErrorDetail` with `code` / `detail` / `attr`),
`->retryable` (the body's verbatim `retryable` flag, `null` when absent —
`true` iff retrying the same request could ever succeed), and `->body` (the
parsed JSON, useful for shapes like the OTP-verify failure's
`remaining_attempts`). Transport failures raise `ApiConnectionException`
(timeouts: `ApiTimeoutException`, a subtype).

```php
use Silon\Exception\UnprocessableEntityException;
use Silon\Exception\RateLimitException;
use Silon\Exception\ApiStatusException;

try {
    $client->messages->send([...]);
} catch (UnprocessableEntityException $e) {
    echo $e->errors[0]->attr, ': ', $e->errors[0]->detail;
} catch (RateLimitException $e) {
    sleep((int) ceil($e->retryAfter ?? 1));
} catch (ApiStatusException $e) {
    error_log("HTTP {$e->statusCode} ({$e->requestId}): {$e->getMessage()}");
}
```

## Retries

The SDK retries automatically (default `maxRetries = 2`) with exponential
backoff and jitter, honouring the server's `Retry-After` / `RateLimit-Reset`
hint. A request is retried only when it is safe: the method is
GET/HEAD/OPTIONS/PUT/DELETE **or** it carries an `Idempotency-Key` (so every
`send` / `sendBatch` / `broadcasts->create` / `otp->send` is retry-safe), and
the failure is a connection error/timeout or HTTP 429/500/502/503/504. Other
POST/PATCH requests are never retried. The same `Idempotency-Key` is replayed
on every attempt, so a retried send cannot double-fire.

## Test mode

`sk_test_` API keys traverse the full pipeline (validation, scopes, throttles,
idempotency, delivery rows, events) but never reach a provider and never bill;
every affected envelope carries `livemode: false`. Delivery status is simulated
a few seconds after the `202`, so polling and webhooks behave realistically.

Magic recipients (test mode only, deterministic):

| Recipient | Result |
| --- | --- |
| `+15005550001` / `delivered@silon.test` | delivered |
| `+15005550002` / `bounce@silon.test` | failed (simulated provider error) |
| `+15005550009` / `suppressed@silon.test` | always suppressed (single send → `422 recipient-suppressed`; fan-out → skipped into `skipped.suppressed`) |
| any other | delivered |

In **live** mode a magic recipient is rejected `422 test-recipient-in-live`.
Test-mode OTPs are never dispatched; the magic code `000000` always verifies
(and only it). Webhook endpoints carry a create-time `livemode` flag (default
`true`): test events deliver only to `livemode: false` endpoints, live events
only to `livemode: true` ones.

## Custom HTTP client

The default transport is native curl. Inject any
`Silon\Http\HttpClientInterface` to control TLS (private CAs, client certs),
route through a proxy, or drive the SDK against a mock in tests:

```php
use Silon\Client;
use Silon\Http\HttpClientInterface;
use Silon\Http\Request;
use Silon\Http\Response;

$client = new Client([
    'apiKey' => 'sk_live_...',
    'workspace' => 'acme',
    'httpClient' => new class implements HttpClientInterface {
        public function send(Request $request, float $timeout): Response
        {
            // ... adapt to Guzzle, a PSR-18 client, an on-prem CA, etc.
            // Return a Response for any HTTP status; throw a
            // Silon\Http\TransportException only when no response is produced.
        }
    },
]);
```

## Async

This SDK is synchronous, matching the platform idiom for PHP. Every call blocks
until the response is ready and returns a typed model. (The Python SDK
additionally ships an async client.)

## Development

```bash
composer install
composer test        # or: ./vendor/bin/phpunit
```

## License

MIT
