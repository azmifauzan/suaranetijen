# 15 - Security, Privacy & Governance

## Data minimization

SuaraNetijen menilai entity, bukan author. Jangan membangun profile third-party user.

Core index tidak memerlukan:
- username;
- avatar;
- follower graph;
- email/phone;
- inferred demographic attributes.

Simpan source external item ID/hash hanya untuk dedupe/audit sesuai kebutuhan adapter.

## Raw content

Temporary, access restricted, TTL-driven. Encrypted at rest jika disimpan di object storage.

## User data

First-party:
- account identity minimum;
- authentication metadata;
- ratings.

Privacy policy harus menjelaskan penggunaan data dan deletion request.

## Security controls

- CSRF/session security.
- rate limiting.
- passwordless/OAuth preferred.
- admin authorization.
- secrets via environment/secret manager.
- database backups encrypted.
- no crawler credentials in logs.

## Source guardrails

Adapter preflight memeriksa:
- public accessibility;
- current robots/path behavior;
- explicit automated-access restriction yang diketahui;
- login/CAPTCHA/bypass requirement;
- parser/source health.

Jika source berubah, disable adapter; core product tetap berjalan.

## Methodology transparency

Public methodology menjelaskan bahwa score adalah aggregate sentiment dari source yang dipantau, bukan representasi statistik seluruh masyarakat dan bukan ukuran kebenaran objektif.
