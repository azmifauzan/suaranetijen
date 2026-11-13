# Topic Landing Page Publishing Rules

## Slug Format

- Pattern: `^[a-z0-9]+(?:-[a-z0-9]+)*$` (lowercase, alphanumeric, hyphens)
- Example: `vps-cepat`, `hp-baterai-awet`
- Once published, slug is locked and cannot be modified
- Unique per landing page (unique constraint in `search_landing_pages.slug`)

## Copy Guard Limits

Applied on every save (create, update, publish). No superlatives, percentages, or clickable links.

- **Title**: max 60 chars, no "terbaik", "termurah", "paling", etc.
- **Meta Description**: max 155 chars (meta tag display limit), no superlatives
- **Intro**: max 1000 chars, no "terbaik", "termurah", "paling", percentage claims, or links

Violations throw `ValidationException::withMessages()` with a readable `[field]` error.

## State Transitions

```
Candidate  ---[publish]--->  Published
   ^                              |
   |                            [unpublish]
  Draft  ---[publish]--->    (back to Draft)
   ^
   |---[save/generate]---|
        (stays Draft)     |
                       Rejected
                       (terminal)
```

**Terminal states:** A Rejected topic never transitions back. A Published topic cannot be published again (save the first `published_at` timestamp). Only Candidate or Draft topics can move to Published.

**Unpublish:** Reverts from Published to Draft, nulling `published_at`. The topic becomes draft-editable again and drops off `/topik` hub and sitemap until re-published.

**Regenerate:** Allowed only in Draft or Candidate states. Rejects if status is Published or Rejected.

## Preset Theme Ranking

When `TopicDraftWriter` resolves theme candidates for LLM drafting:

1. Load all themes already attached to the topic (`$topic->themes()->get()`) — these are **always included first**
2. Extract keyword tokens from `$topic->keyword` (2+ chars per token)
3. Query Theme table for matches on `display_label` or `canonical_key`, **ranked by total mention count** (`withSum('snapshots as mention_total', 'observation_count')`)
4. Include matched themes up to `MAX_CANDIDATE_THEMES - $preset->count()` (at least 1 if room remains)
5. If no preset and no matches, fall back to top-N themes by mention count

**Why:** Admin-curated (preset) themes must never be buried by LLM noise. Ranking by actual netizen mention frequency ensures the LLM sees the most-discussed themes first, not just the ones alphabetically first.
