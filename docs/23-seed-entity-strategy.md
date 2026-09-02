# 23 - Seed Entity Strategy

## Target

Approximately 200 entities, curated for:
- high search interest;
- high likelihood of public discussion;
- coverage by MVP sources;
- clear naming/aliases;
- category diversity.

## Suggested distribution

| Category | Count |
|---|---:|
| Smartphone | 45 |
| Mobil | 30 |
| Motor | 25 |
| Cloud & Hosting | 30 |
| ISP & Telco | 20 |
| E-commerce / Ride Hailing / Logistics | 30 |
| General consumer brands | 20 |
| Total | 200 |

## Cloud example strategy

Use brand + generic service entities when people commonly discuss both:

```text
Biznet Gio
VPS Biznet Gio
Hosting/Cloud Hosting Biznet Gio
IDCloudHost
VPS IDCloudHost
Hosting IDCloudHost
Dewaweb
VPS Dewaweb
Hosting Dewaweb
```

Do not create every pricing plan.

## Selection workflow

1. Draft candidate list.
2. Run source discovery sample.
3. Estimate opinion availability.
4. Remove entities with almost no public discussion.
5. Add aliases.
6. Seed entity/category/parent relationships.
7. Begin backfill.

## Growth

Log zero-result and unresolved search queries. High-frequency queries become primary input for new entity creation.
