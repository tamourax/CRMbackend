# AI-assisted development record

This implementation was produced with an AI coding assistant. The candidate must review and understand it before presenting it; passing tests do not establish independent understanding.

## Generation requests

Generate a bounded read-only CRM; Laravel schema/models/factories/validation/policies/API Resources/scoped reports; interchangeable AI integration with strict read-only tools and minimized context; responsive React routes and reusable components; deterministic PostgreSQL and HTTP contract tests; frontend/browser tests; documentation, ERD, and a demo.

## Review and improvements during implementation

- Tested real PostgreSQL sums with exact cents and a large foreign-account record that must not affect them.
- Applied ownership scopes to every AI/report path, independent of model wording.
- Added explicit nullable loss reasons rather than inventing explanations.
- Added tool validation, permitted source keys, server-filled fact placeholders, and rejection of unknown facts and literal numeric output.
- Preserved honest missing-data/provider states; fakes are test-only.
- Fixed browser-discovered logout observer/cache behavior and mobile overflow; added regression coverage.
- Replaced arbitrary decorative bars with actual stage values and self-hosted fonts.
- Audited packages and upgraded the test runner after the initial version had advisories.
- Removed unused Laravel frontend scaffolding.

## Candidate review checklist

Trace a request end-to-end. Explain session/CSRF vs authorization. Reproduce a foreign-account `404`. Change a filter with its test. Explain report definitions and missing data. Follow a model tool call into the scoped query. Explain why numeric/source checks cannot prove qualitative meaning. Debug provider failures without logging secrets. Independently run the live question set once a key is available.

Gemini support was added after the initial delivery: provider adapter, shared structured summary contracts, complete report fact references, bounded transient-error retry, trusted-CA configuration and opt-in live tests. Developer credentials were confined to ignored local settings and are not part of a checkout. Reviewers must configure their own provider key/model/quota. Mocked tests must not be described as live validation; report actual live results and provider failures separately.

Later review separated reusable frontend buttons/panels/covers/statistics, centralized typography tokens and ordered stylesheets, and extracted backend session/translation behavior with domain controllers. Existing API, security, integration and desktop/mobile browser tests were rerun during that refactor. See [refactor decisions](REFACTOR.md), [current test record](TESTING.md) and the [review walkthrough](DEMO.md).
