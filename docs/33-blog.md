# 33 — Blog (admin-authored articles)

Status: implemented and reviewed 4 November 2026 (phases 1-4). Not yet deployed; staging main host needs the four `S3_STORAGE_*` keys in `.env` first.

## Goal

Admins write and publish articles at `/blog` (methodology explainers, monthly sentiment recaps,
category round-ups). Each article is an indexable page that links into entity, category, and topic
pages. "Blog" is a primary navbar item; "Cara kerja" and "Tentang kami" leave the navbar because the
footer already links them ("Metodologi", "Tentang SuaraNetijen").

A blog post is editorial copy, not a metric. It never writes to `sentiment_*`, `rating_*`, or
`theme_*` tables and never shows a score that is not the live public score of a linked entity.

## Pattern source

Copy the Topik module (`docs/28`), which already has the same shape: admin CRUD with
draft/publish, slug locked after publish, model events that clear `SitemapController`,
`HomepageCategoryBlockService` and `CachePublicPages` caches, sitemap entries, and SSR public pages.

| Topik (existing) | Blog (new) |
|---|---|
| `SearchLandingPage` model, `search_landing_pages` | `BlogPost` model, `blog_posts` |
| `AdminTopicsController` + `Admin/Topics/{Index,Create,Edit}.vue` | `AdminBlogController` + `Admin/Blog/{Index,Create,Edit}.vue` |
| `TopicIndexController` / `TopicShowController` + `Topics/{Index,Show}.vue` | `BlogIndexController` / `BlogShowController` + `Blog/{Index,Show}.vue` |
| `SearchLandingPageStatus` enum | `BlogPostStatus` enum (`Draft`, `Published`) |
| `SitemapController` topic block | same, blog block |

Module location: `app/Domains/Blog/` (Controllers, Models, Enums), new domain folder approved
3 November 2026.

## Data model

`blog_posts`:

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `slug` | string unique | `a-z0-9-`, generated from title, editable until publish, locked after (same rule as Topik) |
| `title` | string(160) | `<h1>` and `<title>` |
| `excerpt` | string(300) nullable | meta description + list card; falls back to first paragraph |
| `body` | text | Markdown |
| `cover_image_path` | string nullable | object key on the `media` disk, e.g. `blog/covers/{uuid}.webp` (see "Image storage") |
| `status` | string | `draft` / `published` |
| `published_at` | timestamp nullable | set on first publish, admin may backdate; future date = scheduled |
| `author_id` | FK users nullable, `nullOnDelete` | internal only |
| timestamps | | |

`blog_post_entity` pivot (`blog_post_id`, `entity_id`, unique pair): optional linked entities,
rendered as cards under the article with their live Sentimen Netijen (only when publicly eligible,
same `ScoreCalculator::isPublicScoreEligible()` check as search).

Public byline: "Tim SuaraNetijen", not the admin's name. **Asumsi.**

## Rendering

- Body stored as Markdown, rendered server-side with Laravel's built-in `Str::markdown()`
  (league/commonmark, already installed) using `html_input => 'strip'` and
  `allow_unsafe_links => false`. Output passed to Vue as `body_html` and shown with `v-html`.
  No WYSIWYG editor, no new npm/composer dependency.
- Admin editor: plain `<textarea>` + "Pratinjau" tab that requests the server-rendered HTML, so
  preview and public output are the same renderer.
- `PublicCopyGuard` runs on `title` and `excerpt` on every save (no "terbaik", no percentages,
  handles, URLs). The body is **not** guarded: articles legitimately cite numbers and links.
  Ranking copy rule still applies by editorial review ("sentimen netijen tertinggi", never
  "terbaik di Indonesia").

## Public pages

- `GET /blog` (`blog.index`): published posts with `published_at <= now()`, newest first,
  paginated 12. Empty state copy when there is none.
- `GET /blog/{slug}` (`blog.show`): 404 for draft or future posts. Breadcrumb Beranda > Blog >
  title, cover, body, linked entity cards, "Artikel lain" (3 latest, excluding self).
- SEO via `PublicSeo.vue`: canonical, `og:type=article`, `og:image` = cover or site default,
  JSON-LD `BlogPosting` (`headline`, `datePublished`, `dateModified`, `author` = Organization
  SuaraNetijen). Never `AggregateRating`. Page prop name must not be `seo`
  (`.ai/rules/controllers.md`).
- Sitemap: `/blog` plus every published post (`lastmod` = `updated_at`).
- Cache: `BlogPost::booted()` saved/deleted clears `SitemapController` and `CachePublicPages`
  exactly like `SearchLandingPage`. Cloudflare edge needs the usual post-deploy purge
  (`.ai/rules/middleware.md`); publishing a post does not purge the edge, so a new post can take up
  to 5 minutes (`s-maxage=300`) to appear on `/blog` for cookie-less visitors. Acceptable.
- Verify `<h1>` in real SSR output, not only Inertia props.

## Navbar

`resources/js/layouts/PublicLayout.vue` `navItems` (one array drives desktop and mobile menus):

```text
before: Jelajahi · Ranking · Papan Sponsor · Cara kerja · Tentang kami
after:  Jelajahi · Ranking · Papan Sponsor · Blog
```

Footer unchanged. Admin sidebar (`AppSidebar.vue`, admin group) gets "Blog" → `/admin/blog`.

## Admin

- `/admin/blog`: list with status filter (Semua / Draft / Terbit), title search, published date.
- Create/Edit: title, slug (read-only once published), excerpt, body + preview, cover, linked
  entities (picker over existing `/api/search`, Tokoh Publik allowed since it is only a link),
  `published_at`.
- Actions: Simpan draft, Terbitkan, Tarik (unpublish → draft), Hapus (drafts only; published
  posts must be unpublished first, so a live URL never disappears by one click).
- Feedback via `Inertia::flash('toast', ...)`, never `->with('success')`.
- Routes under existing `routes/admin.php` group (`access-admin` Gate).

## Image storage

Decided 3 November 2026: S3-compatible object storage the operator already holds
(IDCloudHost, `is3.cloudhost.id`, bucket `yahoot`).

- The bucket is **shared with another app** (`question-images/` at its root). Everything
  SuaraNetijen writes lives under the `suaranetijen/` prefix, created 3 Nov 2026 (`.keep` object).
  Never write, list-and-delete, or lifecycle-rule outside that prefix.
- Probe 3 Nov 2026: objects are private by default (existing `question-images/*` returns 403);
  an object uploaded with ACL `public-read` returns 200 on both
  `https://is3.cloudhost.id/yahoot/...` and `https://yahoot.is3.cloudhost.id/...`. So covers are
  uploaded with `public` visibility and served directly from the bucket, no app proxy or
  temporary URL.
- New disk `media` in `config/filesystems.php` reading the existing `.env` keys
  (`S3_STORAGE_SERVER`, `S3_STORAGE_BUCKET`, `S3_STORAGE_ACCESS_KEY`, `S3_STORAGE_SECRET_KEY`),
  `endpoint` = `https://{server}`, `root` = `suaranetijen`, `visibility` = `public`,
  `url` = `https://{bucket}.{server}/suaranetijen`. The existing `AWS_*`/`s3` disk is untouched.
  `FILESYSTEM_DISK` stays `local`.
- Requires `league/flysystem-aws-s3-v3` (new Composer dependency, approved by this decision).
- Upload rules: `image` mimes jpg/png/webp, max 2 MB, min width 1200 (og:image size). Key
  `blog/covers/{uuid}.{ext}`; replacing or removing a cover deletes the old object; deleting a
  draft post deletes its cover.
- Staging `.env` (main host only; workers never touch media) needs the four `S3_STORAGE_*` keys.
- Tests use `Storage::fake('media')`, never the real bucket.
- Not covered by `backup:database`; the bucket is the copy of record for covers.

## Out of scope (v1)

Categories/tags, comments, RSS feed, homepage "artikel terbaru" block, multiple authors with
public profiles, LLM-drafted posts, scheduled-publish notifications. Add when there are enough
posts for them to matter (tags/RSS after ~20 posts).

## Phases

1. **Navbar** — swap "Cara kerja"/"Tentang kami" for "Blog" (link can ship once phase 3 lands;
   ship together with phase 3 to avoid a 404). Size S.
2. **Model + admin CRUD** — migration, `BlogPost`, enum, factory, `AdminBlogController`,
   admin pages, sidebar link, Markdown preview, copy guard, tests (create, slug lock, publish
   validation, guard rejection, non-admin 403, delete-only-draft). Size M.
3. **Public pages + SEO** — `/blog`, `/blog/{slug}`, `BlogPosting` JSON-LD, sitemap,
   cache invalidation, linked entity cards, tests (draft/future 404, sitemap excludes drafts,
   stripped raw HTML / `javascript:` link in body, SSR `<h1>`). Size M.
4. **Cover image** — `media` disk + dependency, upload/replace/delete in admin form, cover on
   card/article, `og:image` = cover URL; tests with `Storage::fake('media')`. Size S.

| Phase | Name | Depends On | Size |
|---|---|---|---|
| 1 | Navbar swap | 3 (release together) | S |
| 2 | Model + admin CRUD | — | M |
| 3 | Public pages + SEO | 2 | M |
| 4 | Cover image upload (S3 `media` disk) | 2 | S |

Milestones:
- **M1 (phases 2, 3, 1):** admin can write and publish text articles; Blog in navbar; indexed in
  sitemap. Posts use the site default `og:image`.
- **M2 (phase 4):** posts carry their own cover and share image.

## Open decisions

None. Resolved 3 November 2026:
- Image storage → S3 `media` disk, `suaranetijen/` prefix (see "Image storage").
- Module folder → `app/Domains/Blog/`.

Remaining assumptions (change before phase 2 if wrong): public byline "Tim SuaraNetijen";
v1 excludes tags, RSS, homepage block.
