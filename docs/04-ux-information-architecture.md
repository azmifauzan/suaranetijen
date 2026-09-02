# 04 - UX & Information Architecture

## Homepage

Above the fold mobile:

```text
SUARANETIJEN
Apa kata netijen?
[ Cari brand, produk, atau layanan... ]
```

Di bawahnya:
- Trending searches.
- Popular categories.
- Top sentiment entities.
- Recently updated entities.

## Search result card

- Entity name.
- Type/category.
- Sentimen Netijen.
- Opinion count.
- Rating Netijen jika ada.

Tidak menampilkan spec, harga, atau aspect score.

## Entity page

1. Name + category/type.
2. Sentimen Netijen `0-100`.
3. `N opini dianalisis`.
4. Positive / neutral / negative distribution.
5. Rating Netijen `1-5` + voter count.
6. Period selector (30d / 90d / 12m / all, bila data tersedia).
7. Trend chart sederhana.
8. Related entities.
9. CTA beri rating.
10. Methodology/source disclosure link.

## Category page

- Search/filter.
- Top Sentimen.
- Most Discussed.
- Recently Updated.

## Top list

Ranking by Sentimen Netijen, dengan minimum observation threshold. Tampilkan score dan opinion count. Jangan memakai editorial copy yang menyatakan “terbaik secara objektif”.

Copy yang benar:

> VPS dengan sentimen netijen tertinggi.

Bukan:

> VPS terbaik di Indonesia.

## Mobile requirements

- Search input min 48px height.
- Main score terlihat tanpa horizontal scroll.
- Cards satu kolom pada <=640px.
- Ranking table berubah menjadi stacked cards pada mobile.
- CLS rendah; reserve space untuk images.
