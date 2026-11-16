# Security notes for this delivery

See `ARCHITECTURE.md` for authentication, authorization, CSRF, AI privacy, and prompt-injection boundaries.

## Implemented controls

| Area | Implemented boundary |
| --- | --- |
| Browser authentication | Sanctum encrypted HttpOnly session cookies and CSRF; no JS-stored bearer tokens |
| Single-device login | New random secret, SHA-256 verifier on the user, deletion of older database sessions; middleware rejects obsolete/missing verifiers |
| Renewal | Authenticated CSRF-protected `/session/refresh`; ID and secret rotation, old-verifier replay rejection; five requests/minute/user |
| Expiry and logout | 30-minute idle default, 480-minute absolute default; renewal cannot extend absolute expiry; logout revokes verifier and invalidates session |
| Frontend cleanup | Check validity on focus/foreground interval; clear private query data on login/logout/`401` |
| Passwords and encryption | Laravel password hashing; encrypted/authenticated cookies and encrypted session payloads; verifier hidden from user JSON |
| Authorization | Owner-scoped queries, policies and foreign-ID `404` across customer/deal/report/AI paths |
| Input and abuse | Form Requests, bounded tools/page sizes, allowlisted sorts; login 5/min/account and 30/min/IP; AI 10/min/user |
| Browser/API responses | `no-store`, `nosniff`, frame denial, API CSP, referrer/permissions headers; HSTS on HTTPS API requests |
| Production defaults | Force Secure/HttpOnly cookies, session encryption and debug-off |
| Provider privacy | Backend-only keys; omitted contact fields, redacted notes/descriptions, bounded context, no arbitrary SQL/write tools |

`SessionService` owns start/validation/renewal/revocation; `EnforceActiveSession` applies it to stateful requests. In-flight requests already authorized before revocation may complete. An idle/hidden UI may display its last state until a validity check or request occurs; server-side checks control subsequent access.

## Deployment responsibilities and limits

Use HTTPS and an appropriate same-site domain/proxy setup. Configure exact CORS origins/stateful domains, private PostgreSQL networking/authentication, verified database TLS, encrypted disks/backups and secret storage. The code encrypts sessions, not every customer/deal column. Keep `APP_KEY` private and stable; do not rotate it casually on an existing environment.

Apply frontend security headers at the actual static host; Vite development/preview settings do not configure production hosting. Remove public demo accounts, disable local helper use in deployment, and separate test/development/production databases. The repositories do not provision these infrastructure controls.

Provider redaction is best-effort and is not complete data-loss prevention. Company/business data can leave the server. Prompt/source/number validation cannot prove every qualitative statement; review recommendations and provider privacy/retention settings. `store=false` on OpenAI is not a blanket zero-retention guarantee.

## Verification

`tests/Feature/SessionSecurityTest.php` covers revocation, expiry, refresh replay, logout, CSRF and headers. API/service tests cover ownership and input/tool boundaries. Test-only mocks do not establish live-provider correctness. See [testing](TESTING.md) and [setup](SETUP.md).

## Local environment tracking correction

During the final review, `.env.testing` was found in an earlier repository commit. It contained generated local application/test database credentials and a local Laravel encryption key. The local database password and Laravel keys were rotated, and `.env.testing` was removed from tracking. `.env.*` is now ignored with `.env.example` explicitly allowed. No AI API key was present in that historical test file. The subsequently supplied Gemini key has been stored only in the ignored local environment.

Existing Git history was retained, without force-pushing or changing previously published commits. The historical local values are retired; they must never be reused. For any redistribution requiring complete removal of historical values, coordinate a separate history-cleanup operation with the repository owner. The current source tree tracks no real environment file.

The demo users and password in the seeder/README are intentionally public fictional credentials. They must be removed before a production deployment.
