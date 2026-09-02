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
| Theme over-fragmentation | Diluted/noisy Top Suara list | canonical theme dictionary, normalization/clustering before ranking (`docs/25`) |
| Poor theme normalization | Garbage or misleading themes | Indonesian synonym dictionary, LLM fallback only for ambiguous cases, manual QA sample |
| Templated/spam text inflates theme frequency | Misleading "most-said" ranking | shares the same dedup layer as sentiment (`docs/09`), not a separate weaker one |
| Theme score misread as objective quality score | Drifts toward aspect-scoring/benchmarking positioning | never render a numeric per-theme score in UI or API; frequency and count only (`docs/25`, ADR-008) |
