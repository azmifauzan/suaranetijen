Read-only tinker dry-run output: "{\"orphans\":3100,\"planned\":1462,\"per_brand\":{\"vivo\":292,\"samsung\":246,\"oppo\":235,...}}"

Dry-run logic: brands ordered by `mb_strlen($b->name)` desc; match when `$b->category_id===$p->category_id && mb_stripos($p->name, $b->name.' ')===0`; `break` on first match.

docs/31: "Backfill ini syarat hub dan daftar produk berisi; dijalankan bersama deploy."