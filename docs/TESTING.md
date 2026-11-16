# Testing and acceptance

## Automated layers

- **Laravel on PostgreSQL:** login/logout, throttling, protected endpoints, ownership/IDOR, customer/deal relations, exact decimal sums, all/empty stages, sorting/pagination, literal wildcard search, calendar boundaries, invalid/reversed filters, database constraints, idempotent seeding, and configured-origin CORS.
- **AI orchestration:** deterministic test-only provider; authorized facts, verified source keys, server-filled numeric templates, foreign-ID isolation, owner injection rejection, bounded tools, top-N disclosure, missing loss reasons, PII minimization, unsupported questions, and unavailable configuration.
- **Provider contracts:** mocked Laravel HTTP requests verify Responses payloads, strict schemas, `store=false`, structured parsing, malformed/incomplete output, network failure, and provider rate limiting. No live keys or external calls.
- **React integration:** Vitest + Testing Library + MSW exercise login, API errors, dashboard numbers, relationships, filters, empty/loading states, summary citations, missing AI, chat, and logout cache behavior.
- **Real browser:** Playwright connects both local servers to seeded PostgreSQL. Tests sign-in, reports, detail links, stage filtering, missing-AI state, CSRF rejection, foreign-account denial in a separate browser context, persistent logout, mobile navigation, and viewport overflow. Screenshots/videos are produced.

Run backend: `php artisan test --compact`. A disposable PostgreSQL database ending `_test` is mandatory; the test base class checks this before migrations. Frontend: `npm test`, `npm run build`, `npm run test:e2e`. Browser tests require sibling frontend/backend checkouts, migrated/seeded development data, and `npx playwright install chromium`. They never reset the development database. Repeated demo logins within a minute can intentionally hit rate limits.

## Known ground truth

Fixture values: Lead `1000.10`, Negotiation `2000.20`, Proposal `3000.30`, Won `500.40`, Lost `700.50`. Expected total `7201.50`, pipeline `6000.60`, won `500.40`, lost `700.50`. A foreign user's large deal must not alter them. Another test verifies `1234.56 + 0.44 = 1235.00` and exact server interpolation.

Calendar tests freeze Cairo time and include both boundaries of the current month, adjacent months, and terminal-stage exclusions for the open-closing metric. Schema tests recover from PostgreSQL constraint failures through savepoints.

## Live AI acceptance: pending key

Mocked tests prove application boundaries and deterministic calculations, not a real model's reasoning. Run the five assessment questions with a valid key. Compare every amount/count/source with database facts; check paraphrases, missing reasons, nonexistent records, top-N truncation, hostile notes, and cross-user requests. Verify opportunities are suggestions and unsupported historical claims are refused.

Source allowlists and numeric placeholders reduce errors, but cannot prove natural-language semantics. The missing key remains an explicit live-acceptance gap. The recorded demo is not evidence of successful live AI generation.

## CI

Backend CI provisions PostgreSQL 17, validates Composer, checks formatting, runs tests, and audits dependencies. Frontend CI uses locked dependencies, tests, production build, and audit. Local browser integration is documented because a combined workflow over two private repositories would need an additional cross-repository credential.
