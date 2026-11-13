---
name: manual-threads-opinion-harvest
description: Use when harvesting Threads sentimen posts or komentar about tokoh, person, or brand entities into manual_threads.
category: sources
---

# Manual Threads opinion harvest

Claude Code collects public Threads posts in the user's local Chrome (claude-in-chrome) and submits them to the staging DB as `manual_threads` source items. The existing pipeline on the workers (EntityMatcher, SentimentClassifier) then decides entity and sentiment. No adapter, no repo code, no deploy: submit runs through tinker on staging. Never write a sentiment label and never insert `sentiment_observations` by hand.

## Decided, do not ask again (29 Oct 2026)

- The pipeline labels; the agent only collects and submits. Applies to politicians and other `person` entities too.
- Main posts and replies are both harvested.
- Use the cap, query list and report format below without asking. Ask only when blocked (login wall, staging key refused). Finish every query in the list (name plus each alias) and read the replies in the same run before reporting. One query at 99 of 100 is not a stop; do not end the turn with "want me to continue?".
- Prefer entities under the public threshold (30 opinions in 365d) or just under ranking (100 in 365d). Check the 365d snapshot, not the `all` count: Jokowi had 468 observations but only 90 in 365d.

## Why rows get wasted (Jokowi test, 29 Oct 2026: 36 submitted, 1 observation)

- 23 `not_an_evaluation`: SentimentClassifier found no evaluative word. Text had been cut to 100 chars and emoji stripped, which removed the opinion. Submit the full post text with emoji.
- 12 `entity_not_resolved`: the post named several entities. EntityMatcher takes the longest matching name and drops ties, so "Jokowi ... Prabowo" goes to Prabowo (longer) and "Jokowi ... Gibran" is a tie and dropped. Posts that name other registered tokoh do not count for the target.
- Pipeline rejection is correct behaviour (precision over recall). The fix is collecting better rows and pre-checking them, not loosening the pipeline.

## Collect

- One entity at a time: read its id, name and aliases from the staging DB. Queries: name and each alias; for brands add the live incident term (`flip error`, `bca diblokir`) and skip bare brand queries that return promo.
- `browser_batch`: navigate to `https://www.threads.com/search?q=<q>&serp_type=default&filter=recent`, wait ~4s, then scroll-capture. Close the tab at the end.
- Threads is infinite scroll and virtualized. Per step: last `a[href*="/post/"]` `.scrollIntoView({block:'end'})`, `window.scrollBy(0, 600)`, wait ~2.8s, capture every rendered post into `window.__rec` (Map by post code). Jumping to `document.body.scrollHeight` stalls at ~9 posts. About 8 steps per JS call: one call with 30 steps timed out, 8 steps finished in about 25s. Stop after 3 steps with nothing new, or 40 steps; report `end` or `cap` per query.
- Per post capture code, body and `published_at`. Body starts after the relative-time line (`23m`, `4h`, `1d`, `2w`) and ends at `Translate` or a bare counter. Drop the leading `Replying to <name>` and the username line. `published_at` = capture time minus the age, UTC ISO.
- Keep a post only when: it names the target (name or alias, word-bounded); it names no other tokoh or brand (Prabowo, Gibran, a party, another company); it is a personal opinion, not news, a survey, a meme or a brand account. Still open news and brand posts for their replies.
- Replies: open each kept post's permalink, scroll with the same loop, collect each reply's own `/post/<code>`, skip replies tagged `Author`, apply the same keep rules. A reply that only says "setuju" or "bener" without naming the target is dropped by the matcher; skip it. List posts whose replies were not read.
- Copy codes only from scrape output, never from memory.
- Pre-filter in the page JS before relaying: target regex (word-bounded name and aliases), a denylist of other tokoh, party and brand names, and a body of at least 25 chars. On 29 Oct, 275 captured posts gave 88 survivors.
- Also put the SentimentClassifier lexicon (its positive and negative words and emoji, copied from `SentimentClassifier.php`) into that page filter. A post with no evaluative word cannot become an observation, so drop it in the browser instead of relaying it. On 29 Oct, with the lexicon, 36 of 36 relayed rows passed the staging pre-check; without it, 8 of 66 had passed.

## Get the rows out of the browser

- No browser-to-disk channel exists (verified 29 Oct 2026: blob download, fetch/sendBeacon/Image to 127.0.0.1 and clipboard all fail). The only channel is the JS tool output, cut at ~1000 chars per call; some outputs are blocked as "Cookie/query string data".
- Send 3 rows per JS item as `code|ISO|text`, full text, many JS items in one `browser_batch`, then write the JSON with the Write tool. Strip only URLs and `@handles` in JS. If an item is blocked, re-send that slice with `[^\p{L}\p{N}\p{Emoji} .,!?'-]` replaced by a space; keep emoji.

## Pre-check on staging, then submit

- Dry run first, no writes: one script does both checks. For each row it runs `app(App\Domains\Entities\Services\EntityMatcher::class)->matchWithTerm($text)` and `app(App\Domains\Sentiment\Services\SentimentClassifier::class)->classify($text)`, and submits only when `getenv('SUBMIT') === '1'`. The default run prints the counts; read them, then rerun with `SUBMIT=1`. Expect most rows to fail `no_class`: 29 Oct, 8 of 62 passed and 49 had no evaluative word. With the in-page lexicon filter above, few should fail that way; if many still do, the page filter is wrong, not the rows.
- Submit only those rows. For rows that fail, re-collect the full text once if it looks cut; otherwise drop them. Report the counts per reason.
- Submit script (one transaction per row): `SourceItem::firstOrCreate(['source_id' => manual_threads id, 'external_id' => code], ['content_hash' => sha256('threads:'.code), 'processing_state' => pending, 'published_at' => UTC])`. Skip when a `RawPayload` already exists for that item (no double count). Otherwise create `RawPayload` (`payload_ref` = `payload-<uuid>`, text, `text/plain`, `expires_at` = now + `retention_policy.raw_ttl_hours` or 72), set the item's `raw_payload_ref`/`expires_at`, and dispatch `App\Domains\Ingestion\Jobs\MatchEntitiesJob`. Print `RESULT rows= queued= skipped=`.
- Run it as a file inside the container. Piping PHP into `tinker` on stdin printed nothing and wrote nothing. Use: `(echo '<?php'; cat submit.php) | ssh ... 'docker exec -i suaranetijen-app sh -c "cat > /tmp/s.php && php artisan tinker /tmp/s.php; rm -f /tmp/s.php"'`. SSH with the key from `.env` (`SSH_KEY_PATH`), `-o BatchMode=yes -o IdentitiesOnly=yes`; if refused, stop and ask.
- Leave `manual_threads.enabled` as it is; the submit path does not check it.
- Scratch rows (text and codes only, no handles) stay in the session scratchpad under `/tmp`, never in the repo. Delete them after submit.

## Verify and report

- After the analysis queue drains (about a minute for tens of rows), count per row state (`processed`, `skipped`), observations by entity and sentiment, and `unmatched_mentions` by reason for the submitted codes. Column names: `sentiment_observations.sentiment` (not `sentiment_class`); `sentiment_snapshots` has `period`, `score`, `opinion_count`, `calculated_at`. Read them with `DB::table(...)` in tinker; a wrong column in an Eloquent `selectRaw` throws.
- Snapshots refresh on their own. On 29 Oct, 8 rows submitted and every period's `calculated_at` moved within about a minute, with no aggregate command run. If a snapshot does not move after the queue drains, report the score as stale; do not invent a rebuild step.
- Report: queries with stop reason, posts collected, rows passing the pre-check, rows submitted, observations by sentiment, rejected rows by reason, posts whose replies were not read.
