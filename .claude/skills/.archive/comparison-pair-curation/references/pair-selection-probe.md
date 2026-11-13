# Pair selection probe

Read-only checks. Connection details come from the staging host's app container env; never paste credentials into the repo or chat.

## Eligible entities per category (production, read-only)

```sql
select c.name as category, e.type, e.slug,
  max(s.opinion_count) filter (where s.period='365d') o365,
  max(s.opinion_count) filter (where s.period='all') oall
from entities e
join categories c on c.id = e.category_id
join sentiment_snapshots s on s.entity_id = e.id
where e.status='active' and e.searchable and s.period in ('365d','all')
group by c.name, e.type, e.slug
having max(s.opinion_count) >= 30
order by c.name, oall desc;
```

Run with `PGOPTIONS="-c default_transaction_read_only=on"` so an accidental write fails.

## Demand check

```bash
q='indihome vs myrepublic'
curl -s "https://suggestqueries.google.com/complete/search?client=firefox&hl=id&gl=id&q=$(python3 -c 'import sys,urllib.parse;print(urllib.parse.quote(sys.argv[1]))' "$q")"
```

A non-empty suggestion list counts as demand.

## Test guard

Tests in `tests/Feature/Seo/EntityComparisonTest.php` cover canonical ordering. Run it after editing `config/comparisons.php`.
