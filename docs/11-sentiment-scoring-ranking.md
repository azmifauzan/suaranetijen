# 11 - Sentiment Scoring & Ranking

## Public sentiment classes

- positive
- neutral
- negative

Tidak ada public aspect score, experience score, authenticity score, performance score, atau source weight.

## Score formula v1

Untuk periode tertentu:

```text
P = positive_count
N = neutral_count
G = negative_count
T = P + N + G

score = 100 * (P + 0.5 * N) / T
```

Formula ekuivalen dengan mapping positive=100, neutral=50, negative=0.

Contoh:
- 60 positive
- 20 neutral
- 20 negative

Score = `100 * (60 + 10) / 100 = 70`.

## Why simple

- mudah diaudit;
- mudah dijelaskan;
- tidak menyembunyikan source/reviewer weighting;
- sesuai product promise “apa kata netijen”.

## Minimum observations

Config MVP recommendation:
- score public: >= 30 opinions.
- ranking eligible: >= 100 opinions.

Nilai ini configuration, bukan hard-coded business truth. Opinion count selalu ditampilkan.

## Periods

- 30d
- 90d
- 365d
- all

Default public MVP: `365d` jika tersedia, fallback `all` untuk entity baru. Homepage/top-list menggunakan satu period konsisten per ranking page.

## Ranking

Sort:
1. score desc;
2. opinion_count desc sebagai tie breaker;
3. name asc deterministic fallback.

Tidak ada popularity bonus. Entity dengan volume lebih besar tidak otomatis mendapat score lebih tinggi.

## Rating Netijen

Manual star rating dihitung terpisah:

```text
rating_average = sum(active_user_rating) / rating_count
```

Tidak masuk formula Sentimen Netijen.

## Versioning

Simpan `sentiment_model_version` dan `score_formula_version` pada snapshot agar perubahan metodologi dapat dilacak.
