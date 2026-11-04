---
name: blog-post-publishing
description: Use when editing, publishing, or linking admin blog posts.
category: content
---
# Blog post publishing

A published `BlogPost` must keep a non-null `published_at`. Public `/blog` routes filter on it through `live()`, so a save that nulls it turns a live post into a silent 404.

- `AdminBlogController::update()`: set `published_at` from the request only when non-empty; clear it only when the post is still a draft. Never write `published_at => $value ?: null` for every status.
- `resources/js/pages/Admin/Blog/Edit.vue`: `form.published_at` is seeded from props at setup. Inertia reuses the component after save, so re-seed it with a `watch` on `props.post.published_at`.
- Entity linking: the `input[type=search]` picker shows `div.absolute.z-20 button` results; links persist in the `blog_post_entity` pivot. Unlinking removes the entity cards on the public page.
- Regression proof: `tests/Feature/AdminBlogTest.php` saves a published post with an empty date and asserts `published_at` is unchanged and the public URL returns 200. Extend that test for any new status or date rule.
- Browser check after a save: read the datetime input value, then `curl -s -o /dev/null -w '%{http_code}'` the public slug before trusting a page selector.