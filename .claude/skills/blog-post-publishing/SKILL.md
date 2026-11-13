---
name: blog-post-publishing
description: Use when publishing or editing admin blog posts.
category: content
---
# Blog post publishing

A published `BlogPost` must keep a non-null `published_at`. Public `/blog` routes filter on it through `live()`, so a save that nulls it turns a live post into a silent 404.

- `AdminBlogController::update()`: set `published_at` from the request only when non-empty; clear it only when the post is still a draft. Never write `published_at => $value ?: null` for every status.
- `resources/js/pages/Admin/Blog/Edit.vue`: `form.published_at` is seeded from props at setup. Inertia reuses the component after save, so re-seed it with a `watch` on `props.post.published_at`.
- Entity linking: the `input[type=search]` picker shows `div.absolute.z-20 button` results; links persist in the `blog_post_entity` pivot. Unlinking removes the entity cards on the public page.
- Regression proof: `tests/Feature/AdminBlogTest.php` saves a published post with an empty date and asserts `published_at` is unchanged and the public URL returns 200. Extend that test for any new status or date rule.
- Browser check after a save: read the datetime input value, then `curl -s -o /dev/null -w '%{http_code}'` the public slug before trusting a page selector.

## Data recap posts

- Numbers come from read-only SELECTs on staging. Put the arithmetic in a script that asserts the sums (positive + neutral + negative = opini; weekly split = month total) and recomputes each score from counts with the `docs/11` formula. Paste script output into tables; never retype them.
- Before saving, check every comparison, ratio, rank order, and "why" sentence against the computed numbers. A first draft said "hampir tiga kali lipat" for a 2.5x gap, listed the top 10 out of order, and gave a cause the sub-counts did not show. Only the recheck caught it.
- Name the month in each post. When a score is window-only, say it differs from the entity page's 365-day score.
- Cover: 1600×900 PNG rendered from the same numbers. Recipe in `references/covers-and-publish.md`. Write only under the `suaranetijen/` prefix; the bucket is shared with other apps.
- Create posts by upsert on `slug`, piping the PHP over ssh (`references/covers-and-publish.md`). Read the matched/expected entity counts; a shortfall means a slug typo.
- After publish, purge the edge (`cloudflare-edge-cache`), then check each URL: 200, one `<h1>` in server HTML, `og:image` is that post's own cover, entity cards present, every internal link 200, `/sitemap.xml` lists it, and no horizontal scroll at 390px in a logged-out context.
