# Orbit CRM · Backend

Laravel 13 REST API with PostgreSQL 17, session authentication, per-user ownership, deterministic sales reports, and read-only AI assistance.

Companion: [React frontend](https://github.com/tamourax/CRMfrontend).

## Requirements and setup

PHP 8.4+, Composer 2, PostgreSQL 17. Enable PHP `pdo_pgsql`, `pdo`, `mbstring`, `openssl`, `curl`, `fileinfo`, `xml`, `ctype`, `tokenizer`, and `dom`. Check `php --ini` and `php -m`. No Node installation is required for this API.

```sh
composer install
php scripts/setup-local.php
php artisan migrate --seed
php artisan serve --host=127.0.0.1 --port=8000
```

The local helper expects PostgreSQL administrator access on localhost. Defaults are `127.0.0.1:5433`, matching the assessment workstation; override `PGHOST`, `PGPORT`, `PGUSER`, `PGPASSWORD` for your installation. It creates a restricted `orbit_crm_app` login, the `orbit_crm` and `orbit_crm_test` databases, and ignored `.env`/`.env.testing` files. It preserves existing application credentials and the AI key. It does not change PostgreSQL authentication configuration or grant application superuser privileges.

Alternatively, copy `.env.example` to `.env`, configure `DB_*` for an application-owned database, run `php artisan key:generate`, then migrate and seed. The example uses conventional port `5432`. Create a separate test database and `.env.testing` when configuring manually.

Use browser URLs **http://localhost:5173** and **http://localhost:8000** consistently; mixing `localhost` and `127.0.0.1` breaks cookie sharing. `GET /up` is the health check.

## Synthetic demo accounts

| Account | Email | Demo-only password |
|---|---|---|
| Alex Morgan | `alex@orbit.example` | `OrbitDemo!2026` |
| Sarah Chen | `sarah@orbit.example` | `OrbitDemo!2026` |

These are intentionally public, fictional seed credentials, never production credentials. Each account owns 10 customers and 30 deals, including a customer with no deals, all sales stages, missing notes/loss reasons, and varied closing dates. Seeding is idempotent and limited to local/testing environments. Re-seeding refreshes dates relative to the current Cairo calendar.

## Environment variables

| Variables | Purpose |
|---|---|
| `APP_KEY`, `APP_ENV`, `APP_DEBUG`, `APP_URL` | Encryption, environment, safe error output, API URL |
| `FRONTEND_URL`, `SANCTUM_STATEFUL_DOMAINS` | Exact first-party origin and SPA hosts/ports |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | PostgreSQL connection |
| `SESSION_DRIVER`, `SESSION_ENCRYPT`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE`, `SESSION_SAME_SITE` | Encrypted server-side sessions and cookies |
| `CACHE_STORE` | Database during local use, array during tests |
| `AI_PROVIDER`, `OPENAI_API_KEY`, `OPENAI_MODEL` | OpenAI configuration |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Gemini configuration |
| `AI_CA_BUNDLE` | Optional trusted CA PEM file for local PHP HTTPS |

Only `.env.example` is tracked. Never commit actual keys, passwords, `.env.testing`, or session data. Production needs HTTPS, secure cookies, private PostgreSQL networking, strong authentication, individual credentials, and removal of demo users.

## AI configuration and delivery status

For Gemini, set `AI_PROVIDER=gemini`, `GEMINI_API_KEY`, and `GEMINI_MODEL=gemini-3.5-flash-lite` in backend `.env`. OpenAI remains supported through `AI_PROVIDER=openai`, `OPENAI_API_KEY`, and `OPENAI_MODEL=gpt-5.4-mini`. Run `php artisan config:clear` and restart the backend server after environment changes. Keep keys out of the frontend and Git.

On Windows PHP installations missing trusted CA configuration, set `AI_CA_BUNDLE` to an existing trusted PEM file. TLS verification remains enabled; never use verify=false. The assessment workstation uses Laragon's existing CA bundle.

A Gemini key was supplied after the initial delivery and is stored only in the ignored local `.env`. Gemini uses forced read-only function selection and structured summaries with server-validated fact placeholders; Laravel inserts the authoritative values and validates sources. OpenAI uses Responses with `store=false`. Fake providers remain test-only. Missing configuration and provider failures return honest `503` messages.

Gemini retries a received transient HTTP 500/502/503/504 once, never quota/authentication failures or network timeouts. The two-phase request shares a 55-second overall budget with a 25-second per-attempt limit. External model overload can still exhaust the bounded retry.

Run the five questions in [the demo guide](docs/DEMO.md), checking numbers and source links. Contact fields are omitted; notes/descriptions are minimized and redacted on a best-effort basis. Company and business information can still be sent externally. Review provider/privacy settings before real customer use. Stateless Gemini requests and OpenAI's `store=false` do not guarantee zero provider retention.

## Tests

```sh
php artisan test --compact
php vendor/bin/pint --format agent
composer audit
```

Tests use **PostgreSQL**, not SQLite. The helper creates `.env.testing` using `orbit_crm_test`. The base test case refuses a non-PostgreSQL connection or database not ending in `_test` before migrations. `RefreshDatabase` resets that disposable test database; never use a real database.

Coverage includes authentication, ownership/IDOR, relationships, decimal aggregates, filters, date boundaries, database constraints, missing information, tool validation, prompt-injection access boundaries, fact/source validation, rate limits, and provider failures. Frontend/browser tests live in the separate React repo.

## Documentation

- [Architecture, ERD, security, decisions](docs/ARCHITECTURE.md)
- [HTTP API](docs/API.md)
- [Testing and acceptance](docs/TESTING.md)
- [Demo and live evaluation](docs/DEMO.md)
- [AI-assisted development record](docs/AI_USAGE.md)
- [Arabic discussion guide](docs/REVIEW_GUIDE_AR.md)
- [Security notes and local environment correction](docs/SECURITY.md)

CRUD writes, roles, persisted conversations, historical revenue analytics, streaming, and hosted deployment are outside this assessment version.
