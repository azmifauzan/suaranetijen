---
paths:
  - app/Domains/Search/Services/SearchService.php
---

# Search Services

## Anchor tokens are word-bounded; trigram fuzzy only for 4+ characters
Classifying a query word as an anchor (name/alias/category) must match whole words (3 chars or fewer) or word starts (4+), never a substring, and only use similarity() from 4 characters. A substring/fuzzy anchor made "hp" anchor on the alias "hpm" and returned Honda for "hp snapdragon". Anchors and aliases must belong to active searchable entities. The SQLite shim hides this: verify ranking changes against the live Postgres with real queries (samsng a57, vps biznet, hp snapdragon). The sentiment tie-break counts only publicly eligible scores (>= scoring.public_min_opinions).
