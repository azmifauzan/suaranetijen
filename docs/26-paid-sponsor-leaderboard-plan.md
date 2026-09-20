# 26 - Paid Sponsor Leaderboard Plan

**Status:** Proposed product and architecture plan  
**Date:** 20 September 2026  
**Scope:** Homepage discovery and monetisation for existing entities

This document records a proposal for an Outbid/Pamerin-style paid leaderboard. It is not yet a
replacement for the frozen product decisions in the earlier documents. Until this proposal is
approved, Sentimen Netijen, Rating Netijen, Top Suara Netijen, search relevance, and organic
rankings keep their existing definitions.

## Decision summary

The feature is feasible and should share the existing `entities` table, but it must use a separate
ranking and payment domain:

```text
entities -> sentiment observations/snapshots -> organic sentiment rankings
    |
    `-> sponsored entries/orders -> paid sponsor leaderboard
```

The entity is shared; the provenance and ordering rules are not. A payment must never alter an
entity's sentiment score, rating, theme frequency, search relevance, or eligibility for an organic
`/top/{slug}` ranking. This preserves the entity-centric model and the no-hidden-mixing principle
from [docs/01](01-product-vision.md), [docs/02](02-prd-mvp.md), and [docs/21](21-architecture-decisions.md).

## Product concept

Use the Indonesian label **Papan Sponsor** in the public UI. Avoid names such as “Top Brand” or
“Top Sentimen”, which could imply that payment is evidence of quality.

The board ranks existing active entities by verified sponsor amount. It is an exposure product,
not a review, endorsement, or guarantee of traffic, sales, followers, or sentiment.

Recommended MVP constraints:

- Sponsor only an existing active/searchable brand, product, or service.
- Do not accept arbitrary external URLs as listings in the first version.
- Do not auto-create an entity from a submitted URL; missing entities go through the existing
  admin/entity workflow.
- Exclude public-figure/political sponsorship from the first release until a separate policy review
  is complete.
- Display the board publicly, but require authentication for creating or managing a payment order.
- Show a configurable minimum and increment; do not hard-code pricing in PHP. Default minimum
  Rp1.000, default increment Rp1.000 — both read from config, not constants.
- Weekly season for the active board, with an all-time archive. A lifetime-only board can lock the
  top positions and reduce repeat participation.

## Ranking rules

The proposed active-board order is:

1. `settled_total_amount` descending.
2. `first_settled_at` ascending for equal totals.
3. A stable unique ID as the final deterministic tie-breaker.

Only confirmed `capture`/`settlement` payments count. Pending, expired, denied, and cancelled
orders do not affect a position.

**Decision: community model.** Any authenticated user may top up any entity's sponsor total; a
re-bid is a target total charged only for the difference, and multiple sponsors can accumulate on
the same entity. This avoids building an entity-claim/verification flow before launch. The
disclosure copy states this explicitly (see below) so users understand a total is a sum of
contributions, not a single sponsor's exclusive spend. A payment buys a position for the active
period, not a permanent #1 guarantee.

## Homepage UX

The homepage search remains the primary action. Directly below the search form, attach a compact,
visually distinct sponsor notice and a top-three preview:

```text
[ Cari brand, produk, atau layanan...                         Cari ]
[ 🏆 Papan Sponsor · #1 Entity A · Rp250.000 · Lihat papan -> ]
  Posisi berdasarkan nominal sponsor; tidak memengaruhi skor atau hasil pencarian.

[#1 Entity A]  [#2 Entity B]  [#3 Entity C]
                                      Lihat semua · Sponsori entitas
```

Implementation placement is immediately after `EntitySearch` in `Welcome.vue`, before the
“Coba cari” suggestions. The existing autocomplete remains textual and relevance-driven; paid
entries must not be injected into its result list or used as a search tie-breaker.

Each sponsor card should show:

- Position and entity name.
- Entity type/category.
- Verified sponsor total and active-period label.
- Optional click count, clearly labelled as traffic from the sponsor board.
- A separate, non-promotional Sentimen Netijen summary only if it helps users identify the entity.

The sponsor total and sentiment metrics must be visually and textually separated. Use a distinct
amber/gold sponsor treatment while retaining the existing organic green treatment for sentiment.
Cards become a single-column stack on mobile.

Suggested disclosure copy:

> **Sponsor.** Urutan papan ini ditentukan nominal pembayaran terkonfirmasi, bukan Sentimen
> Netijen, Rating Netijen, atau penilaian editorial.

## Proposed domain model

Add a dedicated sponsorship bounded context (the final module name is still open; `Sponsorships`
is the working name). Do not overload sentiment or rating models.

### `sponsor_periods`

- `id`
- `key` / `slug`
- `starts_at`, `ends_at`
- `status` (`draft`, `active`, `closed`)
- timestamps

### `sponsored_entries`

- `id`
- `period_id` foreign key
- `entity_id` foreign key
- `settled_total_amount` integer in IDR minor units
- `first_settled_at` nullable timestamp
- `status` (`pending`, `active`, `paused`, `removed`)
- timestamps

Unique `(period_id, entity_id)`. The row is the board projection for one entity and period; the
payment ledger remains the source of truth.

### `sponsorship_orders`

- `id`
- `sponsored_entry_id` foreign key
- `user_id` foreign key
- `provider` (fixed value `sumopod` for MVP) and unique `provider_order_id`
  (`SNT-SPN-{sponsorship_orders.id}`)
- `provider_payment_id` nullable (Sumopod's `payment_id`, filled once the payment is created)
- requested target total (contribution amount) and charged amount, integer IDR minor units
- status (`pending`, `paid`, `expired`, `failed`, `refunded`)
- `expires_at`, `paid_at`
- a minimal audit payload (the last webhook `event_type` and relay `X-Webhook-Id`, not the raw
  Sumopod payload)
- timestamps

Do not store card, bank, or wallet credentials. Retain only the provider identifiers and fields
needed for reconciliation.

### Optional `sponsorship_relay_events`

Mirrors satsetui's `sumopod_webhook_events` inbox pattern (`D:\dev\satsetui\app\Models\SumopodWebhookEvent.php`):
one row per relayed `X-Webhook-Id`, unique on that ID, with `status`
(`received`, `queued`, `delivered`, `ignored`, `failed`) and `last_error`. This is what makes
webhook processing idempotent against satsetui's retrying forwarder — see the payment flow below.

### Optional `sponsor_click_daily`

Store daily aggregate counts per entry, not raw third-party visitor identity. If click-throughs
eventually point to an external destination, record the redirect separately and qualify the link as
`rel="sponsored"` according to [Google Search Central's paid-link guidance](https://developers.google.com/search/docs/crawling-indexing/qualify-outbound-links).

## Payment provider: Sumopod QRIS via the satsetui relay

**Decision: Sumopod Managed Payment (QRIS), not Midtrans.** Sumopod is already the payment
provider for the operator's other apps (`satsetui`, `satsetops`, `fabriku`), and `satsetui`
already runs a central Sumopod webhook ingress that verifies Svix signatures once and relays to
each downstream app by `order_id` prefix
(`D:\dev\satsetui\app\Http\Controllers\SumopodWebhookController.php`,
`App\Jobs\ProcessSumopodWebhookJob::forwardDownstream()`). SuaraNetijen reuses that hub instead of
registering its own Sumopod webhook endpoint — one fewer public endpoint to secure, and payment
config for the whole operator stays in one place.

This is a cross-repo integration. The design below states the full contract; wiring the
`satsetui`-side half (new destination constant, `resolveDestination()` prefix match, config/env
entries) is a separate task against the `satsetui` repo, not part of this SuaraNetijen build.

### Order creation (SuaraNetijen side)

Mirrors `App\Services\Payment\SumopodPaymentDriver`/`App\Services\SumopodService` in `satsetui`:

1. User selects an existing entity and a target sponsor contribution amount (>= Rp1.000, the
   configured minimum).
2. The server recalculates the amount and predicted position; client-supplied totals are not
   trusted.
3. Create a `sponsorship_orders` row, `status = pending`, `expires_at` set, and a deterministic
   `provider_order_id` = `SNT-SPN-{order.id}`.
4. Call Sumopod's `POST /api/v1/payments` (`X-Api-Key` header, `payment_method_type_code: QRIS`,
   `currency: IDR`, `success_return_url`/`cancel_return_url` pointing back at the order status
   page) and store the returned `payment_id`/`payment_link_url`.
5. Redirect the user to `payment_link_url`. Sumopod's own checkout page already shows and charges
   its gateway fee to the customer (0.7% + Rp300) — SuaraNetijen records and charges only the
   plain contribution amount, same convention as `satsetui`.
6. Show the position only after a confirmed webhook; the browser return URL is not proof of
   payment.

### Webhook delivery: relayed, not direct

SuaraNetijen never receives a Sumopod-signed webhook body directly. Instead:

1. Sumopod calls `satsetui`'s existing `POST /webhooks/sumopod`, which verifies the Svix
   signature/token once.
2. `satsetui` resolves the destination from the `order_id` prefix (`SNT-SPN-` -> a new
   `DESTINATION_SUARANETIJEN` case) and forwards the original raw body to a SuaraNetijen-owned
   internal endpoint, `POST /api/sponsor/webhooks/sumopod-relay`, with headers:
   `X-Webhook-Id` (satsetui's `svix_id`, the idempotency key), `X-Webhook-Timestamp`,
   `X-Webhook-Environment` (`live`/`sandbox`), `X-Webhook-Signature: v1,<base64 HMAC-SHA256>` —
   computed over `{id}.{timestamp}.{rawBody}` with a secret shared out-of-band between the two
   apps (`App\Services\Payment\SvixWebhookVerifier::generateSignatureHeader()` on the satsetui
   side; SuaraNetijen implements the matching `verify()` half against the same secret).
3. SuaraNetijen verifies the signature and timestamp tolerance (5 minutes, same as
   `SvixWebhookVerifier`), rejects on mismatch, and upserts a `sponsorship_relay_events` row keyed
   on `X-Webhook-Id` — a duplicate relay (satsetui retries on non-2xx) is a no-op 200, never a
   double-count.
4. Only `payment.completed`, `payment.failed`, `payment.expired` are handled; anything else
   (including `payment.test`) is acknowledged and ignored.
5. In a database transaction: lock the `sponsorship_orders` row by `provider_order_id`, re-verify
   `provider_payment_id` and `amount` against what was recorded at order-creation time (never
   trust the relayed amount alone), then write the payment result and update the
   `sponsored_entries.settled_total_amount` projection used for ranking.
6. Return 2xx only once fully processed, so satsetui's retry-with-backoff (`$tries = 5`,
   `[10, 30, 60, 180, 300]`) is the sole redelivery mechanism SuaraNetijen needs to rely on.

`SumopodService`'s "charge fee to customer" note and the exact webhook `event_type`/`data` shape
(`payment_id`, `order_id`, `amount` as integer minor units, `currency`) are the same for every app
on this Sumopod account, so no separate confirmation from Sumopod's docs is needed beyond what
`satsetui`'s working integration already proves.

## API and page surface (proposed)

- `GET /sponsor` — full public board and rules.
- `GET /api/sponsor/leaderboard` — public active-period preview with an explicit period/filter.
- `POST /api/sponsor/orders` — authenticated order creation.
- `GET /api/sponsor/orders/{order}` — authenticated order status.
- `POST /api/sponsor/webhooks/sumopod-relay` — internal endpoint, called only by the `satsetui`
  relay, never by Sumopod directly; excluded from normal CSRF handling but requires the
  `X-Webhook-Signature` HMAC check described above. Not `{provider}`-parameterised — Sumopod is
  the only provider reachable this way for MVP.
- Admin resources for periods, entries, orders, moderation, refunds/pauses, and reconciliation.

All frontend route calls should use the project's Wayfinder-generated route functions when the
feature is implemented.

## Trust, moderation, and privacy guardrails

- Label every paid placement as `Sponsor` before the user clicks it.
- Keep sponsor order out of search, organic ranking, sitemap priority, and sentiment calculations.
- Allow admins to pause or remove policy-violating entries, with the decision and refund policy
  stated in the terms.
- Block illegal gambling, illegal lending, adult content, impersonation, malware, deceptive
  redirects, shorteners, and other prohibited destinations if external links are introduced later.
- Do not publish sponsor email, real name, IP address, payment method, or other payer identity by
  default.
- Rate-limit order creation, webhook endpoints, and click tracking; use idempotency keys and unique
  provider order IDs.
- State clearly that the purchase is for board exposure and position only, not guaranteed outcomes.

## Testing requirements

Before public launch, cover at least:

- Amount-descending ranking and deterministic tie handling.
- Period boundaries and closed-period exclusion.
- Pending/failed/expired payment exclusion.
- Duplicate and out-of-order relay delivery (same `X-Webhook-Id` redelivered by satsetui).
- Relay signature/timestamp mismatch rejection (`X-Webhook-Signature`, 5-minute tolerance).
- Amount mismatch between the order-creation record and the relayed webhook payload.
- Concurrent bids around the current #1 position.
- Re-bid/community top-up accumulation on the same entity.
- Entity status/category moderation rules.
- Homepage preview visibility and mobile rendering.
- Proof that paid data cannot change sentiment snapshots, ratings, themes, search ordering, or
  organic category rankings.

## Rollout sequence

### Phase 0 — Product decision (settled)

Board period: weekly with all-time archive. Sponsorship model: community (any authenticated user
tops up any entity, contributions accumulate). Minimum contribution: Rp1.000, increment
configurable, default Rp1.000. Provider: Sumopod (QRIS) via the existing `satsetui` webhook relay.
Refunds and eligible entity types stay as scoped in this document. Record these in the relevant
product and architecture documents once Phase 1 begins.

### Phase 1 — Read-only board

Build the entity-linked projection, public board, homepage teaser, rules page, and fixture-backed
ranking tests without accepting real payments.

### Phase 2 — Payments

Add authenticated checkout against Sumopod, the `sumopod-relay` internal webhook endpoint and its
signature verification, idempotent order processing keyed on `X-Webhook-Id`, reconciliation, and
admin pause/refund controls. Before this phase can go live end-to-end, coordinate with the
`satsetui` repo to add SuaraNetijen as a fourth relay destination (`DESTINATION_SUARANETIJEN`,
`SNT-SPN-` prefix in `resolveDestination()`, `internal_endpoints`/`internal_secrets` config+env) —
that change ships independently and isn't part of this repo's implementation.

### Phase 3 — Measurement and iteration

Measure impressions, outbound clicks, conversion to paid placement, re-bid rate, repeat sponsors,
and trust signals such as search completion. Do not optimise the feature by changing organic
sentiment metrics.

## External references

- [Outbid FAQ](https://outbid.lol/faq) — paid rank, minimum bid, tie handling, and rolling activity.
  Primary product-concept reference for this plan.
- [Pamerin rules](https://pamerin.id/aturan) — Indonesian currency/payment example, confirmed
  payment requirement, and sponsored-link rules. Primary product-concept reference for this plan.
- [Pamerin terms](https://pamerin.id/syarat) — exposure is not a guarantee of clicks, sales, or
  virality.

## Internal references

- `D:\dev\satsetui\app\Http\Controllers\SumopodWebhookController.php` — the central Sumopod
  webhook ingress and relay-by-`order_id`-prefix pattern SuaraNetijen plugs into.
- `D:\dev\satsetui\app\Jobs\ProcessSumopodWebhookJob.php` — `forwardDownstream()` is the exact
  signing/retry contract the `sumopod-relay` endpoint above must satisfy.
- `D:\dev\satsetui\app\Services\Payment\SvixWebhookVerifier.php` — signature algorithm
  (HMAC-SHA256 over `{id}.{timestamp}.{rawBody}`, `v1,<base64>` header) to reimplement on the
  verify side.
- `D:\dev\satsetui\app\Services\Payment\SumopodPaymentDriver.php` and
  `App\Services\SumopodService.php` — order creation shape (`order_id` prefix convention, QRIS
  payment method, customer-charged gateway fee) to mirror for order creation.
