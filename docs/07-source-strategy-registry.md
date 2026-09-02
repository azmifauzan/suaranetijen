# 07 - Source Strategy & Registry

## Selection criteria

Source dipilih berdasarkan:
- density opini nyata;
- relevansi terhadap kategori seed;
- freshness;
- kemampuan incremental ingestion;
- kestabilan akses;
- biaya;
- noise/spam level;
- operational preflight.

## MVP sources

### YouTube - Core broad source
Use case: komentar video tentang brand/product/service. Gunakan official Data API. Derived sentiment adalah use case analytics yang harus mengikuti kebijakan derived metrics YouTube dan provenance/retention requirements.

### KASKUS - Core broad Indonesia
Use case: thread publik lintas gadget, telco, layanan digital, otomotif, consumer brands. Adapter hanya untuk halaman publik; runtime preflight harus memeriksa robots dan response behavior. Jangan bergantung pada login/API privat.

### DiskusiWebHosting - Core cloud/hosting
High-density source untuk hosting, VPS, cloud, ISP, data center. Prioritaskan forum testimonial, complaints, shared/cloud hosting, VPS/cloud server, ISP/network. Exclude offers/WTS/iklan dari opinion ingestion.

### SerayaMotor - Core automotive
Prioritaskan Review Corner, Suggestion Corner, Our Voices, dan selected Common Topics. Exclude business/promotional areas.

### IndoForum - Supporting broad/historical
Gunakan selective allowlist karena noise/spam. Prioritaskan forum komplain, ponsel, komputer/internet, otomotif. Jangan crawl seluruh forum tanpa filter.

### Bluesky / AT Protocol - Supporting realtime
Gunakan Jetstream/firehose public infrastructure untuk realtime public posts. Filter candidate entities/language sebelum NLP.

### LowEndTalk - Supporting cloud international
Targeted crawl pada Reviews/Providers/Outages dan thread yang mention entity relevan. Jangan mirror seluruh forum.

### SuaraNetijen - Core first-party
Star ratings adalah data first-party dan selalu tersedia untuk semua entity.

## Source roles

- `broad`: YouTube, KASKUS.
- `niche_high_density`: DWH, SerayaMotor.
- `supporting`: IndoForum, Bluesky, LowEndTalk.
- `first_party`: SuaraNetijen.

Role tidak memberi bobot pada sentiment score. Role hanya memengaruhi crawl priority/resource allocation.

## Roadmap sources

Priority 1:
- X API ketika economics masuk akal.
- Additional general web/forum adapters.

Priority 2 / partnership or licensed:
- MediaKonsumen.
- Female Daily.
- Try & Review.
- Trustpilot Data Solutions.
- Google Play review access through sustainable mechanism.

Later licensed/commercial access:
- TikTok.
- Instagram/Facebook.
- Marketplace reviews (Shopee/Tokopedia/Lazada).

## Source governance rule

ToS/robots bukan product logic; ia adalah **adapter guardrail**. Setiap adapter mempunyai `preflight()` dan feature flag. Jika automated access secara eksplisit dilarang, robots path melarang, login/CAPTCHA/bypass diperlukan, atau source menjadi unstable, adapter dihentikan tanpa memengaruhi core index architecture.
