---
paths:
  - app/Domains/Entities/Services/HomepageCategoryBlockService.php
  - 'app/Domains/Entities/Services/EntityCandidate*.php'
---

# Services

## Homepage caches are cleared by model events, not by TTL alone
homepage:category_blocks and homepage:popular_topics are cached 15 min. Entity (status/searchable/category_id), SearchLandingPage (status/category_id/title/keyword/candidate_signal, delete) call HomepageCategoryBlockService::clearCache() on save. Without this, an unpublished topic keeps a homepage link to a 404 for up to 15 min. New fields that change what the homepage shows must be added to those wasChanged() lists.

## Wikidata products of a known brand are auto-approved
EntityCandidateAggregator auto-approves a candidate only when all hold: source_types includes one of config entity_candidates.auto_approve.source_types (wikidata), LLM type = product, LLM sold_in_indonesia = true (missing = false), category slug in auto_approve.category_slugs (smartphone/mobil/motor), suggested name starts with an existing active brand's name/alias as a whole word (longest wins -> parent_id), and no entity alias already equals the name. Anything else stays pending (precision over recall). Entity creation lives in EntityCandidateApprover, shared with the admin approve action; auto-approved rows have reviewed_by = null and get only the primary alias (LLM-suggested aliases are never applied unreviewed). Do not widen the trusted sources without a feed that names the brand reliably.
