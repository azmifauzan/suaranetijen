---
paths:
  - app/Domains/Entities/Services/HomepageCategoryBlockService.php
---

# Services

## Homepage caches are cleared by model events, not by TTL alone
homepage:category_blocks and homepage:popular_topics are cached 15 min. Entity (status/searchable/category_id), SearchLandingPage (status/category_id/title/keyword/candidate_signal, delete) call HomepageCategoryBlockService::clearCache() on save. Without this, an unpublished topic keeps a homepage link to a 404 for up to 15 min. New fields that change what the homepage shows must be added to those wasChanged() lists.
