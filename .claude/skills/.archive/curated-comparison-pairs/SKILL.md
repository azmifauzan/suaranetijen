---
name: curated-comparison-pairs
description: Use when editing comparison pairs or banding chips.
category: seo
---
# Curated comparison pairs

Pairs are hand-picked in `config/comparisons.php`. The entity page shows them as chips through `EntityComparison::forEntity()`, which caps the list at 10. A cap drops items silently: a pair past the cap never appears on its entity page, even though the pair URL works.

## Checks after adding or changing a pair
- For each side of the pair, fetch the entity page with a browser user agent (`curl -A 'Mozilla/5.0'`) and grep for the chip href. Check both entities, not only the pair URL.
- Fetch the pair URL `/banding/{a}-vs-{b}`: expect 200, `robots` content `index, follow`, and the pair listed in `sitemap.xml`.
- The cap and the pair list are code. Commit and deploy through the normal staging path before judging the live result.

The six-chip cap hid `infinix-vs-vivo` on `/e/infinix` until it was raised to ten. That is why the per-entity chip check comes first.