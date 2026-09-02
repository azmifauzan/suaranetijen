# 08 - Source Adapter Specifications

Semua adapters implement interface konseptual:

```php
interface SourceAdapter {
    public function preflight(): SourceHealth;
    public function discover(CrawlCursor $cursor): DiscoveryBatch;
    public function fetch(SourceDocumentRef $ref): FetchedDocument;
    public function extract(FetchedDocument $doc): iterable; // CandidateOpinion
}
```

## Common CandidateOpinion

```text
source_key
external_item_id
external_document_id
canonical_url? 
published_at?
text (temporary)
content_hash
metadata (temporary/minimal)
```

## YouTubeAdapter

Discovery:
- search tracked entity aliases periodically;
- maintain known video IDs;
- avoid repeated search for stable entities.

Fetch:
- comment threads via official API;
- pagination;
- replies optional after launch if quota allows.

Rules:
- raw comment text follows YouTube retention/refresh requirements;
- sentiment aggregate provenance must identify YouTube as a source group when disclosed.

## KaskusAdapter

Discovery:
- category/search/thread index pages yang public;
- tracked thread URLs;
- optional external discovery from search engine only as URL discovery, not source data.

Fetch:
- public thread pages;
- pagination.

Extract:
- strip quotes/signatures/navigation;
- one forum post = candidate opinion unit.

## DiskusiWebHostingAdapter

Discovery:
- RSS + forum listing.
- priority forums: testimonial, complaints, shared/cloud hosting, VPS/cloud server, ISP/network.

Exclude:
- offers/WTS/promo signatures.

## SerayaMotorAdapter

Discovery:
- Review Corner;
- Suggestion Corner;
- Our Voices;
- selected Common Topics.

Extract:
- one post = candidate opinion.
- quoted previous posts removed before hashing.

## IndoForumAdapter

Use allowlisted forum IDs only. Aggressive spam prefilter allowed only for obvious promotional/duplicated content; do not judge opinion correctness.

## BlueskyAdapter

Consume Jetstream JSON. Filter records to public post events; candidate filter by normalized entity aliases. Store URI/hash, timestamp, text temporary.

## LowEndTalkAdapter

Target categories Reviews, Providers, Outages and targeted discovered threads. Low crawl rate, cache thread cursors, skip Offers unless a reply clearly appears in a tracked discussion flow and is needed.

## Source health states

- healthy
- degraded
- blocked
- policy_disabled
- parser_broken
- quota_limited

Adapter failure never blocks other sources.
