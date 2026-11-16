# Setup, environment and troubleshooting

This is the canonical setup guide for both repositories. Start PostgreSQL, configure Laravel, migrate/seed, then start React. AI configuration is optional for browsing the CRM and required for generated answers.

## Prerequisites

- PHP **8.5**; last verified runtime **8.5.12**. Enable `pdo_pgsql`, `pdo`, `openssl`, `curl`, `fileinfo`, `xml`, `ctype`, `tokenizer`, `dom`, and `mbstring` where available (Composer includes a multibyte polyfill).
- Composer **2.x** and PostgreSQL **17**.
- Node.js **24.x** and npm; last verified Node **24.15.0**, npm **11.12.1**.
- Git; Chromium installed through Playwright only if running browser tests.

```sh
php -v
php --ini
php -m
composer --version
node --version
npm --version
```

Check versions against `composer.lock` and `package-lock.json`; use `composer install` and `npm ci` rather than dependency updates for a reproducible checkout.

## Windows and PHP 8.5.12

Run `where.exe php` in PowerShell to identify which executable the terminal selects. An already-open terminal may keep an older PATH even after changing Laragon's selected PHP. Select PHP 8.5 in Laragon, restart the terminal, and check `php -v` again. Composer's Windows launcher uses the PHP selected by PATH too.

On the assessment workstation the explicit runtime is:

```powershell
& 'D:\laragon\bin\php\php-8.5.12-nts-Win32-vs17-x64\php.exe' -v
```

This path is machine-specific, not a portable project requirement. Enable `extension=pdo_pgsql` in the `php.ini` printed by `php --ini`, then restart the API process. Run `composer check-platform-reqs` to confirm the active runtime satisfies installed packages.

## 1. Clone and install

From a parent directory:

```sh
git clone https://github.com/tamourax/CRMbackend.git backend
git clone https://github.com/tamourax/CRMfrontend.git frontend
cd backend
composer install
```

Keep `backend/` and `frontend/` as siblings: the frontend's Playwright configuration resolves `../backend`.

## 2. Database and environment

Choose manual setup or the optional helper. Do not run both as if they were consecutive steps.

### Option A — Manual setup

Using a local PostgreSQL administrator in `psql`, create a restricted application role and two databases. Replace the illustrative password before executing:

```sql
CREATE ROLE orbit_crm_app LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE
  PASSWORD 'replace-with-your-local-password';
CREATE DATABASE orbit_crm OWNER orbit_crm_app;
CREATE DATABASE orbit_crm_test OWNER orbit_crm_app;
```

Run `CREATE DATABASE` outside a transaction. If these already exist, reuse their intended credentials instead of recreating/resetting them.

Copy the template:

```sh
# Bash
cp .env.example .env
```

```powershell
# PowerShell
Copy-Item .env.example .env
```

Edit backend `.env`:

```dotenv
APP_ENV=local
APP_DEBUG=false
APP_URL=http://localhost:8000
FRONTEND_URL=http://localhost:5173
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=orbit_crm
DB_USERNAME=orbit_crm_app
DB_PASSWORD=replace-with-your-local-password
SANCTUM_STATEFUL_DOMAINS=localhost:5173
```

Use your server's actual port; the assessment workstation uses **5433**, while `.env.example` uses the conventional **5432**. Generate the application key for this new environment:

```sh
php artisan key:generate
php artisan config:clear
php artisan migrate --seed
```

Keep `APP_KEY` stable after setup; replacing it invalidates encrypted cookies/sessions and any data encrypted with that key.

### Option B — Optional local helper

`scripts/setup-local.php` needs local PostgreSQL administrator access. It creates restricted `orbit_crm_app` credentials, `orbit_crm`/`orbit_crm_test`, an application key if missing, and ignored `.env`/`.env.testing`. It preserves recognized AI settings, but rebuilds environment files from the template: customize other settings afterward. It is intended for initial local setup, not deployment.

Its default connection is `127.0.0.1:5433`, administrator `postgres`. For a typical port-5432 installation:

```sh
# Bash; set PGHOST/PGUSER/PGPASSWORD locally if required
PGPORT=5432 php scripts/setup-local.php
php artisan config:clear
php artisan migrate --seed
```

```powershell
# PowerShell; set PGHOST/PGUSER/PGPASSWORD locally if required
$env:PGPORT = '5432'
php scripts/setup-local.php
Remove-Item Env:PGPORT
php artisan config:clear
php artisan migrate --seed
```

The helper does not change PostgreSQL authentication rules. If its role already exists and it cannot recover matching credentials from `.env`, it stops instead of resetting that role's password; use manual setup.

## 3. AI configuration

In backend `.env`, use **one** of these blocks, replacing the placeholder key:

```dotenv
AI_PROVIDER=openai
OPENAI_API_KEY=replace-with-your-private-openai-key
OPENAI_MODEL=gpt-5.4-mini
```

```dotenv
AI_PROVIDER=gemini
GEMINI_API_KEY=replace-with-your-private-gemini-key
GEMINI_MODEL=gemini-3.5-flash-lite
```

These identifiers match repository defaults; model availability and quota depend on your provider account. The unused key may remain blank. Real keys must stay in the ignored backend environment or a production secret manager. Never put keys in React's environment; `VITE_*` values are bundled into public assets.

Run `php artisan config:clear` and restart the API after changes. A configured `/api/status` response only means the selected key is non-empty. Generate an AI summary to verify access, quota and the model. Provider failures return a visible error without replacing the answer with fictional data.

## 4. Start both applications

Terminal 1, inside `backend/`:

```sh
php artisan serve --host=127.0.0.1 --port=8000
```

Terminal 2, inside `frontend/`:

```sh
npm ci
# Optional: copy .env.example to .env to set VITE_API_URL explicitly.
npm run dev
```

Frontend environment:

```dotenv
VITE_API_URL=http://localhost:8000
```

Open [http://localhost:5173](http://localhost:5173). Check [API health](http://localhost:8000/up). The dev processes bind to `127.0.0.1`, but use **localhost consistently in browser URLs and API configuration**. Same host with different ports supports the local session-cookie setup; mixing hostnames creates cookie/CORS problems.

Sign in as `ahmed@7bahmedtamer.example` or `nour@7bahmedtamer.example`, password `OrbitDemo!2026`. Follow the [user journey](https://github.com/tamourax/CRMfrontend/blob/main/docs/USER_GUIDE.md).

## Environment reference

Backend `.env.example` is safe to commit; actual `.env` and `.env.testing` are ignored.

| Variable(s) | What to set / meaning |
| --- | --- |
| `APP_NAME` | Display/application name; template: `7B AHMED TAMER` |
| `APP_ENV` | `local` for demo development; `production` for deployment; `testing` for tests |
| `APP_KEY` | Generated by `key:generate` or helper; secret, stable per environment |
| `APP_DEBUG` | Keep `false`; application forces it off in production |
| `APP_URL` | Backend URL, including scheme and port |
| `FRONTEND_URL` | Exact frontend origin allowed by credentialed CORS |
| `APP_LOCALE`, `APP_FALLBACK_LOCALE` | Application defaults (`en`); supported request locales are `ar`/`en` |
| `APP_FAKER_LOCALE` | Test/factory generation locale; template `en_US` |
| `DB_CONNECTION` | `pgsql`; CRM search/schema depend on PostgreSQL |
| `DB_HOST`, `DB_PORT` | Actual PostgreSQL host/port; template `127.0.0.1:5432` |
| `DB_DATABASE` | Development `orbit_crm`; disposable tests `orbit_crm_test` |
| `DB_USERNAME`, `DB_PASSWORD` | Restricted role and its private password; enter your own password |
| `DB_SSLMODE` | Optional libpq SSL mode; template/default `prefer`; configure verified TLS for deployment |
| `SESSION_DRIVER` | `database` for local/application sessions; tests use `array` |
| `SESSION_LIFETIME` | Idle minutes, default `30`; foreground session checks keep an actively open workspace alive |
| `SESSION_ABSOLUTE_LIFETIME` | Maximum authenticated lifetime in minutes, default `480`; refresh does not extend it |
| `SESSION_ENCRYPT`, `SESSION_HTTP_ONLY` | Template documents `true`; code enforces these protections even if changed to `false` |
| `SESSION_DOMAIN` | `null` locally means a host-only cookie; configure carefully if using subdomains |
| `SESSION_SECURE_COOKIE` | `false` only for local HTTP; production code forces `true` |
| `SESSION_SAME_SITE` | `lax` for the documented same-site frontend/API deployment |
| `SANCTUM_STATEFUL_DOMAINS` | Frontend host:port without scheme; local `localhost:5173`; comma-separated when needed |
| `CACHE_STORE` | `database` locally; test suite uses `array` |
| `QUEUE_CONNECTION` | `sync`; current AI requests are synchronous, no queue worker required |
| `BROADCAST_CONNECTION`, `MAIL_MAILER` | `log` locally; application does not need external mail/broadcast services |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_LEVEL` | Template `stack`, `single`, `warning`; logs live under `storage/logs` |
| `AI_PROVIDER` | Exactly `openai` or `gemini` |
| `OPENAI_API_KEY`, `OPENAI_MODEL` | Selected OpenAI account key and accessible model identifier |
| `GEMINI_API_KEY`, `GEMINI_MODEL` | Selected Gemini account key and accessible model identifier |
| `AI_CA_BUNDLE` | Optional absolute path to a trusted PEM CA bundle for provider TLS; otherwise blank |
| Frontend `VITE_API_URL` | Public backend URL only; no AI key or database password belongs in frontend env |

Local-only helper variables `PGHOST`, `PGPORT`, `PGUSER`, and `PGPASSWORD` configure its administrator connection; they are distinct from Laravel's restricted `DB_*` settings. `RUN_LIVE_AI=1` enables network/quota-dependent tests; it is not required for normal app startup.

## Test environment

For manual setup, copy backend `.env` to `.env.testing` locally. Change `APP_ENV=testing`, `DB_DATABASE=orbit_crm_test`, and clear provider keys. Keep a valid application key and the correct database connection/password. The helper prepares a separate test file automatically.

The test runner enforces a PostgreSQL database name ending `_test` before migrations; test migrations reset that database. Keep it separate from development/production data. `phpunit.xml` additionally sets disposable session/cache drivers and test database defaults.

```sh
php artisan test --compact
```

Frontend integration: `npm test`. Browser prerequisites: migrated/seeded backend, both sibling checkouts, `npx playwright install chromium`. Run `npm run test:e2e -- e2e/workspace.spec.ts` in `frontend/`. [Coverage and opt-in live checks](TESTING.md).

## Subsequent starts and updates

Restart PostgreSQL and both dev processes. Do not recreate the application key or rerun the helper on every start. After pulling migrations, run `php artisan migrate`; run `php artisan db:seed` only when you want to refresh/reset the demo records. After dependency changes, use `composer install` / `npm ci`. Reload Vite after editing frontend environment values.

## Troubleshooting

| Symptom | Check |
| --- | --- |
| Composer says PHP version is incompatible | `php -v`, `where.exe php` / `which php`; select 8.5 and restart the terminal |
| `could not find driver` | Enable `pdo_pgsql` in the loaded `php.ini`; verify with `php -m` |
| PostgreSQL connection refused | Service status, actual port `5432` vs `5433`, `DB_HOST`, `DB_PORT` |
| Database authentication fails | Restricted role/password and PostgreSQL authentication rules; do not disable authentication to bypass it |
| Missing table/column | Run `php artisan migrate` against the intended environment |
| Application encryption/key error | Generate a key only for a new env; retain the original key for an existing environment |
| Login `419` / CORS / cookie problems | Use `localhost` consistently; verify frontend origin, stateful domain, credentials and CSRF; keep local Secure cookie off for HTTP |
| Login rejected | Seed first; use current Ahmed/Nour emails and password; re-seeding resets demo passwords |
| Another device is logged out | Expected for a second login to the same account; use different demo accounts for isolation testing |
| `429` | Login/AI/refresh rate limit reached; wait for the limit window |
| AI `503` | Selected provider/key/model/quota, TLS configuration, and safe server logs; no fake fallback exists |
| `ai_configured=true` but generation fails | Key presence is not a provider connectivity or entitlement check |
| Test database safety error | Set a dedicated PostgreSQL database ending `_test` in ignored `.env.testing` |
| UI looks older than current code | Confirm checkout/branch, restart Vite after env edits, and reload the browser |

## Production handoff

Use HTTPS for frontend/API and a same-site domain layout or same-origin proxy. Set `APP_ENV=production`, `APP_DEBUG=false`, production URLs/origins/stateful domains, private DB credentials and a stable secret `APP_KEY`. Configure verified DB TLS, backups, storage permissions, and static-host security headers. Build frontend assets with the production `VITE_API_URL`; configure SPA fallback to `index.html`. Never seed public demo accounts in production.

`php artisan serve` and Vite's dev/preview servers are local tools, not the production deployment architecture. The repositories do not provision hosting, TLS certificates or database encryption. [Security boundaries](SECURITY.md).
