---
name: search-relevance-verification
description: Use when changing search ranking or match tiers.
category: search
---
# Search relevance verification

Name-based tiers only for suggestions. A weak tier (`category_context`, descriptor hits) returns unrelated entities for a specific query. Filter with `priority_rank <= 4` (exact, alias, prefix, trigram) for any "did you mean" or auto-match list.

- Exclude `EntityType::Person` (Tokoh Publik) from brand or sponsor suggestions, as the existing filter does.
- Verify ranking changes on live PostgreSQL with `pg_trgm`. The SQLite trigram shim in tests does not prove ranking.
- If local Postgres is down, start it and rerun before reporting results. Do not report SQLite output as verified ranking; say explicitly what DB ran if you must fall back.
- Add a regression case per tier decision: a query whose only hit is a weak tier must return no suggestion. Prove it with `regression-proof-mutation-check`.
- Check the rendered result at phone width (390x844) when the match feeds a form or picker.
