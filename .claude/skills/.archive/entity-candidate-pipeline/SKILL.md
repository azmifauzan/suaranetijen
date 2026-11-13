---
name: entity-candidate-pipeline
description: Use when auto-approving Wikidata entity candidates.
category: entities
---

# Entity candidate pipeline

A candidate becomes a real `Entity` without admin review only when every gate holds. Any doubt leaves it `pending`. Precision over recall: a wrong entity attracts opinions it should never get.

## Auto-approval gates (all required, `EntityCandidateAggregator::autoApprove`)

- Source type is in `config('entity_candidates.auto_approve.source_types')`.
- LLM verdict `is_relevant` and `sold_in_indonesia` are both explicitly `true`. Missing means `false`.
- LLM type is `product`, and the category slug is in `auto_approve.category_slugs`.
- The suggested name starts with an existing active brand's name or alias as a whole word. Longest match wins and becomes `parent_id`.
- No existing alias already equals the name or the normalized term.

## Behavior

- Entity creation goes through `EntityCandidateApprover`, shared with the admin approve action. Never duplicate creation logic.
- A failed approval (slug clash, etc.) is caught and logged. The candidate stays `pending` and the scan continues.
- Auto-approved rows keep `reviewed_by` null.
- Do not add a source to the trusted list unless its feed names the brand reliably.
- Wikidata: drop raw `Q\d+` labels, and request labels in `en,id,mul`.
- Global feeds include models never sold in Indonesia. Gate on `sold_in_indonesia` before creating anything.

Rule notes also live in `.ai/rules/services.md`. Keep both in sync when a gate changes.
