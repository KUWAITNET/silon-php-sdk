# Changelog

## Unreleased

- Default retry budget raised from 2 retries to **6**, and the per-attempt
  backoff cap from 8s to 30s — about 31s of total coverage
  (0.5+1+2+4+8+16 seconds). A Silon install can be a single host, so an
  upgrade or a reboot takes the API away for tens of seconds and answers
  `503` with `Retry-After` while it does. The old default covered ~1.5s, so a
  routine restart reached callers as a hard failure. Retry preconditions are
  unchanged: POSTs are still only replayed when they carry an
  `Idempotency-Key`, so this can never double-send. Set `maxRetries` lower to fail
  fast instead.

## 0.3.0

**BREAKING**: removed deprecated `Auth::login` / `LoginResult` / `getAuthToken` (`POST /api/v1/login/` retired). Use an `sk_live_`/`sk_test_` API key.

## 0.2.0

Initial release of the Silon PHP SDK. Ships at `0.2.0` to enter lock-step with
the other official SDKs (Python, Node, Go, Java, .NET, Flutter) against the same
[cross-language contract](../SPEC.md) — all 81 `/api/v1/` operations, both API
error shapes normalized onto a typed exception hierarchy, safe automatic retries
(idempotent methods + `Idempotency-Key`'d sends only), cursor pagination with
proxy-safe auto-paging, multipart CSV upload, and offline `Silon-Signature`
webhook verification.

Highlights:

- **PHP 8.1+, zero runtime dependencies** — the default transport is native
  `curl`; any `Silon\Http\HttpClientInterface` can be injected for custom TLS,
  proxies, or tests.
- **Typed models** for every operation, tolerant of unknown response fields
  (forward compatibility) and carrying `livemode` on every affected envelope.
- **Cursor-paginated CRM** (C2 grammar): `clients` and `client_groups` target
  the canonical plural routes and return a `Silon\CursorPage`.
- **Scheduling + cancellation** (`send_at`, `messages->cancel`,
  `broadcasts->cancel`), **suppressions** with the additive `skipped`
  breakdown, **templates** with the immutable version spine, and the
  **webhook endpoint** test/attempts endpoints.
