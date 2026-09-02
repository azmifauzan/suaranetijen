# 20 - Risk Register

| Risk | Impact | Mitigation |
|---|---|---|
| Entity mismatch | Wrong score | aliases, ambiguity queue, conservative matching |
| Sarcasm/slang error | Sentiment noise | Indonesian model evaluation, confidence threshold/QA sample |
| Source parser breaks | Coverage drop | adapters isolated, health checks, kill switch |
| One source dominates | Biased coverage perception | disclose source counts; expand source diversity; no hidden source weighting |
| Spam/duplicate content | Inflated counts | exact/near duplicate suppression, exclude obvious promos |
| Low data entity | Unstable score | minimum public/ranking thresholds |
| YouTube quota/policy issue | Coverage loss | other sources remain operational; adapter feature flag |
| KASKUS/source access changes | Coverage loss | preflight + source disable; no core dependency |
| Database growth | Cost/latency | raw TTL, aggregate tables, indexes/partitioning later |
| Search thin pages | SEO quality | noindex under-data entities, quality thresholds |
| Rating brigading | Manual rating distortion | account constraint, rate limiting, anomaly logs |
| Model change alters history | Comparability | model/formula versioning |
| Legal/privacy exposure | Operational | data minimization, no author profiling, source guardrails, clear methodology |
