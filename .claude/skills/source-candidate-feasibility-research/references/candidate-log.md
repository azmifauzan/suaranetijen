# Source candidate log

## 2026-11-03: Repliz (repliz.com) and Tebar (tebar.app)

Question: can either API feed SuaraNetijen sentiment from social accounts?

- **Tebar: no.** REST `api.tebar.app/v1` plus MCP. Reads are limited to comments on the user's own posts (`GET /comments`, IG/TikTok comments, Threads replies). No keyword search over public posts, no mentions endpoint. Verdict: not usable for opinion ingestion.
- **Repliz: conditional.** Research group `GET /public/research/threads/content/search` (keyword or tag, `sort` TOP|RECENT, `since`/`until`, `nextToken` pagination), tier Gold+, Basic Auth (access key:secret key). Search runs under the connected Threads account's access.
- **Open question:** whether results include other users' public posts. Meta's `threads_keyword_search` without app review returns only the authenticated user's own posts. Repliz docs say "search public content" but this is unverified. Test: `search?search=biznet&sort=RECENT` on a connected account, then check whether results contain posts by others.
- **Meta limits underneath:** 2,200 queries per rolling 24h per user, shared across all apps. Queries with empty results do not count. Sensitive keywords return an empty array. `username` is returned, so the adapter must drop it.
- **Pricing:** unconfirmed. The only figure found was an unofficial GitBook (Standard Rp18.000/month). The pricing page is client-rendered.
- **Gap:** no Repliz or Tebar API calls were made. Verdicts rest on docs only.

Go/no-go: pending the single test call above.
