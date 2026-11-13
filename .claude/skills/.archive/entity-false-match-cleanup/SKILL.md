---
name: entity-false-match-cleanup
description: Use when opinions hit the wrong entity, or a brand's opinions are mostly about another product line: purge, split, rebuild.
category: database
---

Wrong attribution in prod is a data bug. Fix the alias or matcher first, then purge, then rebuild aggregates, then regenerate summaries. Verify every leak, not just the top ones.

When the user has already said the data is wrong, run the cleanup directly. Do not pause for per-row confirmation. Report the counts afterward.

Order of operations:
1. Block the source. Put a common-word alias in `blocked_aliases` in `config/entity_matching.php` and remove it from `database/data/seed_entities.csv` in the same commit. If the word is also a real brand term (e.g. "jago" for a bank), do not block it: add it to `context_required_aliases` with the words that mark the brand (bank, rekening, aplikasi, ...). `AliasPolicy::hasRequiredContext()` then skips the match unless one context word appears in the text. Deploy the code fix before cleanup; cleanup on the old image re-leaks.
2. Dry-run the purge with the exact flags you will run: `php artisan entities:purge-mismatched-opinions --entity=<slug> --dry-run`. A dry-run without `--purge-unverifiable` under-reports: Jago's dry-run said `to delete 40`, the real run with the flag deleted 170. Compare the real run's counts, not the dry-run's, when reporting. The real run prints `verified N, unverifiable M, to delete K` before deleting, then "Purged K opinion(s) ... and rebuilt its aggregates": an alias purge rebuilds that entity's aggregates itself, so the separate `themes:rebuild-aggregates` in step 4 is only for theme-observation cleanup. Opinions with an expired raw payload count as "unverifiable" and are deleted only with `--purge-unverifiable=<slug>`. Read both counts before adding that flag.
3. After removing an alias in the DB, wait about 60s for the matcher cache to expire before purging. Otherwise the purge still sees the alias.
4. Delete theme observations whose theme domain does not fit the entity's root category. Then run `php artisan themes:rebuild-aggregates`, then `php artisan themes:summarize`.
5. `themes:summarize` silently skips unless `THEMES_EXTRACTOR=llm`. It queues one LLM job per entity on the `aggregate` queue, so wait for the queue to drain before checking the page.
6. Verify across all rows. Scan `entity_theme_summaries` and theme observations for off-domain terms. A rare-theme leak slipped past a top-N check in one run.
7. Re-judging stored opinions for relevance (`themes:recheck-relevance`, LLM `about_entity` check, deletes off-topic opinions and their aggregates): run `--ambiguous --dry-run` first. It prints batch and opinion counts and dispatches nothing. Then run it for real. Batches are paced on the `themes` queue. Watch the drain with `zcard('queues:themes:delayed')`, `llen('queues:themes')`, and `failed_jobs` from the last 30 minutes. Run `themes:summarize` after the drain if the summaries need refreshing.

Merging duplicate entity rows (two rows for one brand, e.g. "BCA" stub + "Bank Central Asia"):
- Check for an existing row by name and alias before inserting any manual data. A stub with the same alias text makes `EntityMatcher` reject the match as ambiguous, so opinions land in `unmatched_mentions`.
- Keep the row with the richer alias set. Move the source's alias to the target when the normalized alias is not already there. Repoint `sentiment_observations` and `theme_observations` (drop theme rows whose `(theme_id, source_item_id)` already exists on the target first). Delete the source's `sentiment_daily`, `sentiment_snapshots`, `entity_theme_daily`, `entity_theme_snapshots`, `entity_search_documents`, and leftover `entity_aliases`, then delete the source entity. Do it in one transaction.
- Recompute: `SentimentAggregator::aggregateDaily()` for each distinct observed date of the target, then `refreshAllSnapshots()`. Then `search:rebuild-documents`. `themes:rebuild-aggregates` rebuilds every entity (882 here) and takes minutes, so run it in the background. No scheduled command covers the sentiment aggregates after a merge.
- When the user has already picked the merge, run it and the recompute without asking again.

Brand whose opinions are mostly about one product line filed under another category (Polytron: electric scooters and an SUV under an electronics brand):
- Do not purge. Make the line a sub-brand (`Polytron EV`, category of the line) with the products as children, and give it line aliases (`motor listrik Polytron`) gated by `context_required_aliases`.
- Move opinions with a dry matcher run: match each opinion's theme context against the sub-brand family first. Move on a hit, keep on the parent otherwise. Plain brand mentions stay on the parent.
- Regex proposals miss things (LG's Antutu, V30, tablet lines). Review the full candidate list by hand before applying.
- Forum-signature-only opinions ("dikirim pakai LG-D855") are not opinions about the brand. Purge them.
- Rebuild aggregates for the parent, the sub-brand, and each touched product, then `search:rebuild-documents`.

Pitfalls:
- Brand name equals the alias (e.g. "Jago" for a bank, which is also a common word): the entity's own name matches any text containing the word, so blocking does not work. Gate it with `context_required_aliases` (step 1), deploy to every host including workers, then purge; the purge re-runs the matcher and removes opinions that no longer match. Manual row deletion is a last resort.
- Auto-mode classifier can block prod ssh or docker commands and blocks the whole write chain. Do not retry around it, and do not stage the same write another way (`scp` the script, `docker cp` it into the container, pipe it over ssh). Hand the user an exact `!` command and wait. A plain "lanjut" after a summary that asks for an explicit confirmation is not that confirmation: ask for the exact phrase, quoting the row counts to be deleted.
- Multi-line PHP on prod: pipe a script file over ssh into `docker exec -i <app> php artisan tinker`. Use the fully qualified `\Illuminate\Support\Facades\Redis::llen(...)`. Bare `Redis::connection()` fails in tinker.
