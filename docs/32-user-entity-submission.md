# 32 — User entity submission (Usulkan Brand/Produk)

Status: all phases implemented 28 October 2026, not deployed.

Decision 28 Oct 2026: no verified-owner badge or owner rights for now; `is_owner` stays admin-only context.

## Goal

A logged-in user can propose a brand, product, or service — typically their own business — from
the user panel. The proposal joins the existing entity candidate review queue
(`/admin/entity-candidates`, see CLAUDE.md "Entity candidate pipeline"). An admin approves or
rejects it there. The user sees the status of their own proposals.

Nothing about this changes the index model: a submitter never gets a score bonus, a rating bonus,
an edit right, or a public "owner" label. Sentimen Netijen stays crawler-derived (ADR-007/011).

## Scope

In:
- Form in the user panel: `/usulan` (own item in the main sidebar, not under settings) or
  a "Usulkan Brand/Produk" card on `/dashboard` linking to it.
- Fields: `name` (required), `entity_type` (`brand` | `product` | `service`, required),
  `category_id` (required, leaf categories only, Tokoh Publik excluded), `parent_entity_id`
  (optional, product/service only, picked via existing `/api/search`), `website_url` (optional,
  `http(s)` only), `description` (optional, max 500 chars), `is_owner` (checkbox
  "Saya pemilik/pengelola brand ini").
- List of the user's own submissions with status: Menunggu review / Disetujui (link to `/e/{slug}`)
  / Ditolak (with optional admin note).
- Duplicate guard at submit time: if the normalized name already matches an `entity_aliases` row,
  show "Sudah ada" with a link to that entity instead of creating a submission.
- Admin queue shows submitter count, `is_owner`, website, and description on the candidate card.

Out (deliberately):
- `person` entities. Tokoh Publik stays admin/feed-sourced only — users proposing individuals is a
  privacy and abuse surface (ADR-010 override covers crawling public figures, not crowd-adding them).
- Auto-approve. User submissions never go through `EntityCandidateAggregator::autoApprove()`;
  an admin always decides (precision over recall).
- Ownership verification, owner dashboard, owner-editable description/specs, replies to opinions.
- Paid fast-track. Papan Sponsor (`docs/26`) already auto-creates a disabled entity on payment;
  this flow is the free path and does not touch sponsorship tables.

## Data model

New table `entity_submissions`:

| column | type | note |
|---|---|---|
| `id` | id | |
| `user_id` | fk users, cascade | |
| `entity_candidate_id` | fk entity_candidates, null on delete | the queue row it joined |
| `name` | string(120) | as typed |
| `entity_type` | string | `brand`/`product`/`service` |
| `category_id` | fk categories | |
| `parent_entity_id` | fk entities, nullable | |
| `website_url` | string, nullable | |
| `description` | text, nullable | |
| `is_owner` | bool | self-declared, unverified, never public |
| `timestamps` | | |

Unique `(user_id, entity_candidate_id)` — one user counts once per candidate.

Status is read from the linked `entity_candidates.status` (+ `entity_id` for the link); no
duplicated status column. Admin rejection note: add nullable `review_note` to `entity_candidates`
(also useful for feed candidates).

Joining the queue: normalize `name` with `TextNormalizer::normalize()` and `createOrFirst` on
`entity_candidates.normalized_term` (same unique key the scanner uses). New row gets
`source_types = ['user_submission']`, `suggested_name/type/category/parent` from the form,
`frequency_score = 1`. Existing pending row: append `user_submission` to `source_types`, bump
`frequency_score` once per distinct submitter. Existing rejected row: still record the
submission, show the user "Ditolak" immediately (same no-resurfacing rule as feeds).

## Flows

1. User opens form → fills → `POST /usulan` (`StoreEntitySubmissionRequest`).
2. Request: `auth` + `verified` + `throttle:entity-submissions` (5/day per user), not banned
   (`User::isBanned()`, same as `StoreRatingRequest`), category not under Tokoh Publik, URL host
   not private (reuse sponsor's URL rules, no fetch).
3. Duplicate alias → 422 with link to the existing entity, nothing stored.
4. Otherwise transaction: candidate upsert + submission row → `Inertia::flash('toast', ...)`.
5. Admin approves/rejects in the existing queue (`EntityCandidateApprover` unchanged). Optional
   LLM enrichment is not run for user submissions — the user already supplied type/category.
6. User list reflects the new status on next visit. Email notification: skipped, add when users
   ask for it.

## Abuse / policy

- Copy guard: run `PublicCopyGuard` on `description` only if it is ever shown publicly (it is not
  in this plan — admin-only).
- An approved entity starts with zero opinions → below threshold → `noindex` and out of the
  sitemap automatically (Epic 10). No thin pages from submissions.
- Log `entity_submission.created` with user id and candidate id (same pattern as `rating.updated`).
- No third-party data enters here; submitter data is first-party and tied to their own account
  (deleted with the account via cascade).

## Phases

| Phase | Name | Depends On | Size |
|---|---|---|---|
| 1 | Migration (`entity_submissions`, `entity_candidates.review_note`) + model + factory | — | S |
| 2 | Submit endpoint: request, duplicate guard, candidate upsert, throttle, tests | 1 | M |
| 3 | User panel page: form + "Usulan saya" list (Wayfinder, settings layout) | 2 | M |
| 4 | Admin queue: show submitter count / owner flag / website / description, rejection note | 1 | S |
| 5 | Dashboard entry card + search zero-result CTA ("Brand kamu belum ada? Usulkan") | 3 | S |

Phases 3 and 4 run in parallel after 2/1.

Milestones:
- M1 (1–4): users can submit and track proposals; admins review them in the queue they already use.
- M2 (5): discovery — the zero-result search page is where most "my brand is missing" users land.

Tests (Pest feature): guest redirected; banned user 403; Tokoh Publik category rejected; existing
alias returns "sudah ada"; two users on same name → one candidate, `frequency_score` 2; same user
twice → one submission; throttle 429; approval flips the user's list to Disetujui with entity link;
submission never auto-approves.

## Open decision

- Should a self-declared owner get any rights later (verified owner badge, edit description,
  respond to opinions)? Default in this plan: **no** — `is_owner` is stored for admin context only.
  Changing this is a public/trust decision (owner influence on an independent index) and needs its
  own ADR.
