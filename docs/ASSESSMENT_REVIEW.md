# Assessment acceptance review — 17 November 2026

Reviewed against the supplied 7P Software assessment. Functional requirements are implemented and local automated acceptance passed. Two submission qualifications remain: GitHub Actions cannot start because of the account billing lock, and retired local test credentials remain in historical Git commits. Candidate understanding must be established in the interview.

## Requirement coverage

| Requirement | Implementation / evidence |
| --- | --- |
| Story 1: customer listing/details, contact fields, status, notes, linked deals, seeded data | `CustomerController`, `CustomerResource`, customer React pages, database seeders; API and browser relationship tests |
| Story 2: deal listing/details, customer, value, six stages, expected close, description | `DealController`, `DealResource`, deal React pages, PostgreSQL foreign keys and stage/value constraints |
| Story 3: customer overview, relationship, deals, opportunities, risks | Customer `AiPanel`, `AiAssistant::customer`, minimized `AiContextBuilder`; live test validates five summary sections |
| Story 4: question interface, five assessment questions, relevant records, missing information | Assistant page, validated read-only tool catalog/executor, owner-scoped queries, source and numeric verification; all five questions passed live acceptance |
| Story 5: totals, open pipeline, won/lost, stage breakdown, top customers, narrative | `SalesReportService` calculates PostgreSQL aggregates; dashboard and AI reuse these facts; offline exact-decimal and live report tests |
| Laravel architecture | Separate controllers, Form Requests, Resources, models, services, provider interface/adapters, migrations and seeders |
| React architecture | Routes, feature query hooks, shared API/list-filter hooks, shared components, loading/error/empty states, JSON translations and style tokens |
| PostgreSQL relationships | User has many customers; customer has many deals; deal ownership follows its customer. ERD and decisions in `ARCHITECTURE.md` |
| Security/privacy | Sanctum session/CSRF, policies and owner scopes, session revocation/expiry, throttling, server-only AI keys, minimized context and allowlisted tools; see `SECURITY.md` |
| Testing | PostgreSQL API/security/relationship tests, provider contract and grounding tests, React integration tests, real browser journeys and live provider evaluation |
| Documentation | Both READMEs, `SETUP.md`, API, architecture/ERD, security, testing, AI usage, refactor, demo and Arabic review guides |
| Deliverables | Separate publicly accessible frontend/backend GitHub repositories, complete source and example environment files, recorded walkthrough in frontend `docs/demo.webm` |
| AI-assisted development explanation | Generation/review history and candidate checklist in `AI_USAGE.md`; candidate must independently explain and debug the result |

The assessment requires listing/details and seeded data; editing, registration and administration are not specified. Historical trends cannot be established from a current snapshot: the report explicitly states this rather than inventing growth figures. Suggested opportunities and qualitative recommendations still require human review.

## Fresh verification

Baseline code: backend `4a9fab2`, frontend `b9ad3fd`.

- Backend default suite: **51 passed / 247 assertions**, four live tests skipped in that run.
- Backend separate live Gemini suite: **4 passed / 22 assertions**; provider connectivity, five assessment questions, five-section customer summary and sales report against synthetic PostgreSQL fixtures.
- Frontend integration: **16 passed**.
- Frontend production build and repository formatting: passed.
- Real browser acceptance: **3 passed**. Covers desktop relationships, filters, CSRF, account isolation, logout, mobile navigation, and six routes at 320/390/768 pixels in both languages and themes; Arabic login also checked through 1440 pixels.
- Production dependency audits: npm and Composer reported no known advisories at review time. This does not establish the absence of all security flaws.
- Both repository pages returned HTTP 200 without authentication. Current source does not track `.env` or `.env.testing`; local environment files are ignored.
- Recorded walkthrough exists and covers the required stories. It predates the final interface localization polish; a fresh recording can be produced with `npm run demo`.

These are local results with the installed dependencies and configured environment. A clean-machine installation and production deployment were not performed during this review.

## Submission qualifications

1. **GitHub account billing:** latest frontend/backend workflow jobs were marked failed without starting any steps. GitHub's annotation states: “The job was not started because your account is locked due to a billing issue.” Resolve billing/account access, then rerun workflows. This review does not claim remote CI passed.
   - [Frontend workflow](https://github.com/tamourax/CRMfrontend/actions/runs/43962667567)
   - [Backend workflow](https://github.com/tamourax/CRMbackend/actions/runs/43962684857)
2. **Historical test environment file:** `.env.testing` existed in earlier commits with local test credentials/application keys. Those values were rotated and removed from tracking; no AI API key was in that file. Published history remains unchanged. This means the assessment's “never commit credentials” condition was not fully met historically. See `SECURITY.md`; do not reuse retired values or describe the entire history as secret-free.
3. **Candidate ownership:** code/tests cannot prove independent understanding or that architecture was explained before implementation. Use `REVIEW_GUIDE_AR.md`, `AI_USAGE.md` and `DEMO.md` to prepare truthful explanations, reproduce a test, trace an authorized request, and debug a small change independently. The 24-hour process/timeline is not certified by this audit.

Production HTTPS, infrastructure secrets, verified database TLS/backups and removal of public demo accounts remain deployment responsibilities, documented separately. A hosted demo is not required by the supplied task; the recorded working demonstration is available.
