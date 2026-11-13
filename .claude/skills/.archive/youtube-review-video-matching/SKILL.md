---
name: youtube-review-video-matching
category: sources
description: Use when attaching YouTube review videos to products.
---

A review video attaches to a product only when its title names that exact model. Implementation lives in `app/Domains/Entities/Services/EntityReviewVideoFinder.php`.

- Require the model code in the title. Reject variant suffixes (Ultra, Pro, FE) the entity name does not carry, so an "S24 Ultra" review never attaches to "Galaxy S24".
- Store only the YouTube id and title. Do not store channel names (data minimization).
- Each product search costs 100 YouTube Data API units. The daily cap is per product (default 20, about 2,000 units) and shares the API key with the crawler. Override with `YOUTUBE_REVIEW_VIDEO_DAILY_LIMIT`. Compute the budget before large backfills.
- Show at most 5 visible videos per product (manual first; the search itself stores at most 3 automatic ones). Hide the card when a product has none. The card copy must say videos are not counted in Sentimen Netijen.
- Admins add, edit, delete (manual) or hide (automatic) videos on the product's admin edit page. Never delete an automatic row to remove it: the daily search would add it back. Hide it (`hidden_at`).
- Product pages are cached about 5 minutes, so new videos may take a moment to appear.
