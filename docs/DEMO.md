# Demo walkthrough

The [frontend repository](https://github.com/tamourax/CRMfrontend) contains `docs/demo.webm`, screenshots, and `npm run demo`. The recording uses real PostgreSQL data and openly shows missing AI configuration. There are no simulated AI answers.

1. Sign in as Alex. Explain that SQL-backed services calculate the cards, pipeline excludes Won/Lost, and the report is a current snapshot.
2. Customers → Atlas Industries: show contact/company details, notes, associated deals, and a deal's customer link.
3. Filter Deals by Negotiation and sort by value. Show close-date filters and pagination.
4. Generate a customer summary. Without a key, show the explicit unavailable response. With a key, review overview, relationship, deals, opportunities, and risks, with source links.
5. Ask the five questions below. Compare facts and citations with the underlying records.
6. Generate a sales narrative. Explain why historical growth cannot be inferred.
7. Sign in as Sarah in an isolated browser. Alex's IDs return `404`; her lists/reports include only her records.
8. Show tests. Explain the browser-discovered logout cache issue and its regression test.

| Live question | Expected tool |
|---|---|
| What are the highest-value open deals? | `search_deals`, stage `open`, descending value |
| Which deals are expected to close this month? | `search_deals`, Cairo calendar month |
| What is the total value of deals in the Negotiation stage? | `stage_totals` |
| Which customers have the most active deals? | `top_customers`, metric `active_count` |
| What are the reasons behind the lost deals? | `lost_deal_reasons`, explicit/missing reasons |

Also ask for nonexistent history, other users' records, and an unknown deal. Expected: unsupported/empty results and no fabricated explanation. For missing `lost_reason`, the assistant must say it is not recorded.

Discussion: explain separate repos, scopes plus policies, inherited ownership, decimal strings, database calculations, tool-vs-aggregate limits, privacy boundaries, and what needs live validation.
