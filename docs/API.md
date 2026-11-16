# HTTP API

Base: `http://localhost:8000`. Send `Accept: application/json`. Browser flow: `GET /sanctum/csrf-cookie`, then `POST /login` with credentials and decoded `XSRF-TOKEN` as `X-XSRF-TOKEN`. Send credentials on all requests. Missing CSRF returns `419` on state-changing browser requests.

| Method | Path | Input / behavior |
|---|---|---|
| GET | `/up` | Health |
| GET | `/sanctum/csrf-cookie` | CSRF and session cookies |
| POST | `/login` | `{ "email": "...", "password": "..." }`; 5/minute per email+IP |
| POST | `/logout` | Invalidate session |
| GET | `/api/user` | `{ data: { id, name, email } }` |
| GET | `/api/status` | `{ data: { ai_configured: boolean } }`; key presence, not a live connectivity check |
| GET | `/api/customers` | `search`, `status`, `page`, `per_page` |
| GET | `/api/customers/{id}` | Customer, notes, associated deals |
| GET | `/api/deals` | `search`, `stage`, `customer_id`, `closing`, `close_from`, `close_to`, `sort`, `page`, `per_page` |
| GET | `/api/deals/{id}` | Deal and customer reference |
| GET | `/api/reports/sales` | Current aggregate snapshot |
| POST | `/api/customers/{id}/ai-summary` | No body |
| POST | `/api/assistant/messages` | `{ "question": "..." }`; 1–2000 characters |
| POST | `/api/reports/sales/ai-summary` | No body |

All `/api/*` routes require login. AI POST routes share a ten/minute per-user limit. No registration, CRUD write API, or admin override.

Statuses: `prospect`, `active`, `inactive`. Stages: `lead`, `qualified`, `proposal`, `negotiation`, `won`, `lost`; filtering also accepts `open`. `closing`: `this_month` or `overdue`. Explicit bounds: `YYYY-MM-DD`. Sorts: `value_desc` (default), `value_asc`, `close_asc` (nulls last), `newest`. Page size defaults to 10, maximum 100. Search is literal case-insensitive text, escaping `%` and `_`.

Resources use `{ data: ... }`; lists use Laravel `{ data: [...], links: {...}, meta: { current_page, last_page, per_page, total, ... } }`. Amounts are decimal strings. IDs/counts are integers; close dates can be null.

AI result example:

```json
{
  "data": {
    "sections": [{ "title": "Negotiation", "content": "..." }],
    "facts": { "deal_count": 2, "total_value": "1235.00" },
    "sources": [{ "type": "aggregate", "label": "Negotiation · all matching deals" }],
    "generated_at": "2026-11-16T12:00:00+02:00",
    "mode": "live",
    "limitations": []
  }
}
```

Sources can also be `customer`/`deal` with `id` and `label`. Facts vary by query and always originate from backend calculations. Application configuration has no fake mode.

Errors: `401` unauthenticated, `404` missing/inaccessible, `419` CSRF mismatch, `422` validation with Laravel `message`/`errors`, `429` rate limit, `503` unavailable provider/unverifiable output. Raw external errors and keys are never returned.
