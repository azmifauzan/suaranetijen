# 12 - First-party Rating & Moderation

## MVP rating

User login memberi 1-5 star untuk entity.

Rules:
- satu active rating per user/entity;
- update mengganti rating lama;
- delete menghapus contribution;
- rating average dan count dihitung ulang/asynchronously refreshed.

## Authentication

MVP options:
- Google OAuth;
- email magic link.

Tidak perlu KTP, invoice, bukti pembelian, verified owner, atau identitas nyata.

## Minimum anti-abuse

Tujuannya menjaga integritas endpoint, bukan menilai benar/salah opini:
- authenticated user;
- rate limit;
- CSRF;
- unique user/entity constraint;
- burst/anomaly logging;
- account ban/admin disable.

## Optional text review

Tidak perlu launch. Jika ditambahkan kemudian, ia memerlukan report/moderation/takedown workflow yang lebih besar. MVP lebih bersih dengan star rating saja.

## Public display

```text
Rating Netijen
4.3 / 5
318 rating
```

Jangan sebut `verified rating`.
