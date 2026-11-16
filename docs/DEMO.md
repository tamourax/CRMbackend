# Demo walkthrough

Start with the [complete setup](SETUP.md) and [application journey](https://github.com/tamourax/CRMfrontend/blob/main/docs/USER_GUIDE.md). The frontend repository contains an earlier `docs/demo.webm`, screenshots, and `npm run demo`. Earlier media may show older accounts, branding or currency; the current seeded application is authoritative. The recorder uses real PostgreSQL data and captures actual generated results or visible provider errors. Without a key it shows the missing configuration state. There are no simulated AI answers.

1. Sign in as Ahmed (`ahmed@7bahmedtamer.example`, password `OrbitDemo!2026`). Explain that SQL-backed services calculate EGP cards, pipeline excludes Won/Lost, and the report is a current snapshot.
2. Customers → NileBridge Technologies: show the Egyptian contact, Cairo location, company details, notes, associated deals, and a deal's customer link.
3. Filter Deals by Negotiation and sort by value. Show close-date filters and pagination.
4. Generate a customer summary. Without a key, show the explicit unavailable response. With a key, review overview, relationship, deals, opportunities, and risks, with source links.
5. Ask the five questions below. Compare facts and citations with the underlying records.
6. Generate a sales narrative. Explain why historical growth cannot be inferred.
7. Sign in as Nour in an isolated browser. Ahmed's IDs return `404`; her lists/reports include only her records.
8. Show tests. Explain the browser-discovered logout cache issue and its regression test.

Switch Arabic/English to show RTL navigation and localized seeded records. Show theme changes and mobile navigation. Then demonstrate a second login to the same account: the prior session becomes unauthorized. Use Ahmed and Nour, rather than two Ahmed sessions, when demonstrating separate account ownership.

## Story-to-code review map

| Story | Frontend | Backend |
| --- | --- | --- |
| Customer list/detail | Customer pages, `customerQueries.ts` | `CustomerController`, `CustomerResource`, owner-scoped queries |
| Deal list/detail | Deal pages, `dealQueries.ts` | `DealController`, `DealResource`, Customer→Deal relationship |
| Customer summary | `AiPanel`, `useAiSummary` | `AiController`, `AiAssistant`, `AiContextBuilder` |
| Deal questions | `AssistantPage`, `useDealAssistant` | Tool catalog/executor, query service, provider, formatter |
| Report/narrative | `ReportPage`, `reportQueries.ts` | `SalesReportController`, `SalesReportService`, AI report context |
| Security | Session gate and cache clearing | `SessionService`, middleware, Policies, CSRF and rate limits |
| Shared UI / localization | Shared components, styles/tokens, locale/theme providers | `HasTranslations`, locale middleware, `lang/ar` and `lang/en` |

| Live question | Expected tool |
|---|---|
| What are the highest-value open deals? | `search_deals`, stage `open`, descending value |
| Which deals are expected to close this month? | `search_deals`, Cairo calendar month |
| What is the total value of deals in the Negotiation stage? | `stage_totals` |
| Which customers have the most active deals? | `top_customers`, metric `active_count` |
| What are the reasons behind the lost deals? | `lost_deal_reasons`, explicit/missing reasons |

Also ask for nonexistent history, other users' records, and an unknown deal. Expected: unsupported/empty results and no fabricated explanation. For missing `lost_reason`, the assistant must say it is not recorded.

Discussion: explain separate repos, scopes plus policies, inherited ownership, decimal strings, database calculations, tool-vs-aggregate limits, privacy boundaries, and what needs live validation.
