---
paths:
  - 'app/Domains/Sentiment/**'
---

# Sentiment

## Root categories may hold no entities of their own
Automotive, Consumer Brands, Digital Services, Technology and Tokoh Publik have 0 direct entities; only their child categories do. Any per-category aggregate (ranking, listing, count) must include children (id OR parent_id), or a root-category page renders empty. SentimentRankingService::getRanking() and CategoryShowController do this; the sitemap lists only categories with an active entity (own or child).
