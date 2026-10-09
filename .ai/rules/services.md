---
paths:
  - app/Domains/Entities/Services/HomepageCategoryBlockService.php
  - 'app/Domains/Entities/Services/EntityCandidate*.php'
  - app/Domains/Entities/Services/EntityReviewVideoFinder.php
---

# Services

## Homepage caches are cleared by model events, not by TTL alone
homepage:category_blocks and homepage:popular_topics are cached 15 min. Entity (status/searchable/category_id), SearchLandingPage (status/category_id/title/keyword/candidate_signal, delete) call HomepageCategoryBlockService::clearCache() on save. Without this, an unpublished topic keeps a homepage link to a 404 for up to 15 min. New fields that change what the homepage shows must be added to those wasChanged() lists.

## Wikidata products of a known brand are auto-approved
EntityCandidateAggregator auto-approves a candidate only when all hold: source_types includes one of config entity_candidates.auto_approve.source_types (wikidata), LLM type = product, LLM sold_in_indonesia = true (missing = false), category slug in auto_approve.category_slugs (smartphone/mobil/motor), suggested name starts with an existing active brand's name/alias as a whole word (longest wins -> parent_id), and no entity alias already equals the name. Anything else stays pending (precision over recall). Entity creation lives in EntityCandidateApprover, shared with the admin approve action; auto-approved rows have reviewed_by = null and get only the primary alias (LLM-suggested aliases are never applied unreviewed). Do not widen the trusted sources without a feed that names the brand reliably.

## Review videos: exact-model titles only, stored as id + title, never scored
entities:fetch-review-videos (daily 06:00, YOUTUBE_REVIEW_VIDEO_DAILY_LIMIT searches, 100 quota units each, shared with the crawler's key) stores up to 3 videos per product in entity_review_videos. A title is kept only if it reads as a review and contains the name's first word plus every model-code token; a variant word (pro, ultra, fe...) after the code must also be in the entity name. Only youtube_id and title are stored, never the channel. Shown on /e/{slug} (max 5 visible, manual first) as reference material, copy states it is not part of Sentimen Netijen (ADR-007/011). Products with no result are not searched again for 30 days (cache key review-videos:checked:{id}). Admins manage videos on the product's admin edit page (AdminEntityReviewVideoController): add by YouTube link (validated through the public oEmbed endpoint, no quota), edit title, delete a manual video, or hide an automatic one. An automatic video is only hidden (hidden_at), never deleted, so the daily search cannot re-add it; adding the same link as a manual video converts it. Manual videos do not stop the automatic search (it only skips products that already have an automatic row, hidden or not), and the search never overwrites a manual row. The public page shows visible videos, manual first, max 5.
