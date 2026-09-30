---
name: topic-landing-page-publishing
description: Use when validating topic pages; state, slug, copy rules.
category: search
---

## State Guards

Published topics cannot be re-published or regenerated; unpublish first. Rejected topics are terminal (never transition back). Only candidate or draft topics can move to published status.

## Slug Validation

Lowercase alphanumeric with hyphens only (`/^[a-z0-9]+(?:-[a-z0-9]+)*$/`). Lock after publish — cannot be modified.

## Copy Guard on Every Save

Title ≤60, meta description ≤155, intro ≤1000 chars. No superlatives, percentages, or links. Applied on create, update, and publish — a published topic cannot be edited into a violation.

## Preset Theme Prioritization

When the LLM drafts themes, admin-attached themes come first (ranked by netizen mention count via `withSum('snapshots as mention_total', 'observation_count')`), then keyword matches. Guarantees admin curation never buried by LLM noise.

See `references/topic-publishing-rules.md` for state transition diagram, copy-guard details, and theme ranking algorithm.
