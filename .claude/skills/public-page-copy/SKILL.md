---
name: public-page-copy
description: Use when writing Indonesian copy, SEO text, or hub pages.
category: seo
---
# Public page copy

Apply antislop and antislop-copywriting while writing public copy, including UI labels, button text, and form helper text. Do not ask the user whether antislop applies: the user settled that and does not want the question repeated, so asking it stalls the work. Ask only when the copy needs a fact the data cannot back.

Hold every line below:

- **Name the actor.** Write "SuaraNetijen mengambil opini dari forum...", not "Opini dikumpulkan dari forum... Yang dinilai adalah entitasnya". The user rejected passive text on `/top/{kategori}`. Apply antislop-copywriting's Actorless Passive and Filler Phrases rules.
- **Gate every internal link.** `/top/{slug}` is `noindex` when its ranking is empty (`TopRankingController`). Link to it only when the controller passes a prop that says the ranking exists (`has_ranking`, same 365d query as `/top`). Check robots on any target before linking from new copy.
- **Keep product rules.** Score: positive counts full, neutral half, negative zero; 0-100; shown only from 30 opinions. Sentimen Netijen and Rating Netijen stay separate. Judge the entity, never the author or usernames. Ranking copy says "sentimen netijen tertinggi", never "terbaik". No per-aspect scores, prices, specs or benchmarks in copy.
- **Theme labels are copy too.** Show only labels that pass `PublicCopyGuard::publishableThemes()`. Drop generic phrasing ("Kualitas ... bagus/jelek", "Ponsel bagus", "Rekomendasi beli bagus") and never show a positive and a negative label for the same entity; they contradict each other. Keep the filter in that one place so the entity page and `/banding` agree.
- **Thin pages need page-specific facts.** Pasting the same explainer block into every category hub adds words, not substance. With no new facts for a page, leave it short rather than pad.
- **Every opinion count names its window.** Write "Dalam 30 hari terakhir", "Dalam 90 hari terakhir", "Dalam setahun terakhir", or "Sejak pencatatan dimulai" for all-time. Round percentages once in the service and reuse the same numbers in the meta description, FAQ and cards. Answer "masih worth it?" with the window, the positive/negative split and the score, plus the line that the score reflects public opinion, not a price or spec verdict.
- **Ranges and separators.** Write ranges in Indonesian as words: "skor 0 sampai 100", "bintang 1 sampai 5". Never an en dash ("0–100"). Do not join facts with a middle dot ("Smartphone · 246 opini"); render each part as its own element so it reads as a fact, not a decoration. Before reporting done, scan the rendered text for `–`, `—` (em dash), and `·`.
- **Test the change.** CLAUDE.md requires a test for every code change. A prop, controller or link change gets a Pest assertion (for example `has_ranking`, or the `X-Robots-Tag` header). For pure text, run `npx vue-tsc --noEmit` and the page's feature test, and say the copy itself is untested.
- **Rename a public label, not its key.** Change `display_label` only and keep the slug and aliases so matching still works. The slug stays in the page's embedded JSON, so a raw grep for the old term still hits. Verify against visible text and the `<h1>`/title, not raw HTML.
- **Measure rendered HTML, not source.** Word counts and links come from SSR output. Procedure: `references/ssr-render-check.md`.
