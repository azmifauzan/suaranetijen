<?php

namespace Database\Seeders;

use App\Domains\Sources\Enums\SourceHealthState;
use App\Domains\Sources\Enums\SourceType;
use App\Domains\Sources\Models\Source;
use Illuminate\Database\Seeder;

class SourceSeeder extends Seeder
{
    /**
     * Seed the source registry.
     */
    public function run(): void
    {
        $sources = [
            [
                'key' => 'diskusiwebhosting',
                'name' => 'DiskusiWebHosting',
                'adapter' => 'diskusiwebhosting',
                'source_type' => SourceType::Rss,
                'crawl_policy' => ['rate_limit_per_minute' => 30],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'serayamotor',
                'name' => 'SerayaMotor',
                'adapter' => 'serayamotor',
                'source_type' => SourceType::Forum,
                'crawl_policy' => ['rate_limit_per_minute' => 30, 'forum_ids' => [19, 64, 63]],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'indoforum',
                'name' => 'IndoForum',
                'adapter' => 'indoforum',
                'source_type' => SourceType::Forum,
                'crawl_policy' => ['rate_limit_per_minute' => 20, 'forum_ids' => [139, 107, 93]],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'bluesky',
                'name' => 'Bluesky',
                'adapter' => 'bluesky',
                'source_type' => SourceType::Social,
                // Disabled: Jetstream (jetstream2.us-east.bsky.network/subscribe) is a
                // WebSocket-only firehose. BlueskyAdapter::discover() issues a plain HTTP
                // GET, which the server rejects with 400 Bad Request — confirmed against
                // the live endpoint (2026-09-04). Adapter tests only covered fixture data,
                // never the real WebSocket handshake, so this never surfaced before. Needs
                // a proper WebSocket client / persistent listener before re-enabling.
                'enabled' => false,
                'crawl_policy' => ['rate_limit_per_minute' => 30],
                'retention_policy' => ['raw_ttl_hours' => 24],
            ],
            [
                'key' => 'youtube',
                'name' => 'YouTube',
                'adapter' => 'youtube',
                'source_type' => SourceType::VideoComments,
                // Enabled (5 Sep 2026): verified live with YOUTUBE_API_KEY configured.
                'crawl_policy' => ['rate_limit_per_minute' => 30],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'kaskus',
                'name' => 'KASKUS',
                'adapter' => 'kaskus',
                'source_type' => SourceType::Forum,
                // Was disabled: kaskus.co.id's search page is a Next.js app whose results load
                // client-side only (empty SSR fallback), so a plain HTML GET always returned zero
                // thread links. Re-enabled (5 Sep 2026) now that KaskusAdapter routes through
                // FlareSolverr (a real browser, so it executes the client-side render) —
                // confirmed live that thread links come back once FLARESOLVERR_URL is configured.
                'crawl_policy' => ['rate_limit_per_minute' => 10],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'kaskus_politik',
                'name' => 'KASKUS Berita dan Politik',
                'adapter' => 'kaskus',
                'source_type' => SourceType::Forum,
                // Added for the Tokoh Publik category (5 Sep 2026): scoped to a single
                // subforum via 'listing_url', same pattern as LowEndTalk's category_urls
                // scoping — reuses KaskusAdapter unchanged, no new adapter class. Seeded
                // disabled pending a live operator check, same DoD as every other source.
                'enabled' => false,
                'crawl_policy' => [
                    'rate_limit_per_minute' => 10,
                    'listing_url' => 'https://www.kaskus.co.id/komunitas/1167/berita-dan-politik-indonesia',
                ],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'kaskus_otomotif',
                'name' => 'KASKUS Otomotif',
                'adapter' => 'kaskus',
                'source_type' => SourceType::Forum,
                // Added for the Motor category (6 Sep 2026): Otomotifnet/Oto.com were
                // researched as replacements but both rejected — Otomotifnet's comments
                // load from apis.kompas.com (robots.txt: Disallow: / for all agents, same
                // Kompas Gramedia network already blocked on kompas.com itself), and
                // Oto.com's own robots.txt explicitly disallows *userReviews* paths. Same
                // scoped-subforum pattern as kaskus_politik — reuses KaskusAdapter
                // unchanged. Seeded disabled pending a live operator check.
                'enabled' => false,
                'crawl_policy' => [
                    'rate_limit_per_minute' => 10,
                    'listing_url' => 'https://www.kaskus.co.id/komunitas/28/otomotif',
                ],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'kaskus_fashion',
                'name' => 'KASKUS Fashion',
                'adapter' => 'kaskus',
                'source_type' => SourceType::Forum,
                // Added for the Brand Umum category (6 Sep 2026): fashion/beauty brands
                // (Wardah, Erigo, Scarlett Whitening, etc.) have near-zero coverage from
                // the generic YouTube/Kaskus name-search sources. Same scoped-subforum
                // pattern as kaskus_politik/kaskus_otomotif — reuses KaskusAdapter
                // unchanged. Seeded disabled pending a live operator check.
                'enabled' => false,
                'crawl_policy' => [
                    'rate_limit_per_minute' => 10,
                    'listing_url' => 'https://www.kaskus.co.id/komunitas/306/fashion',
                ],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'lowendtalk',
                'name' => 'LowEndTalk',
                'adapter' => 'lowendtalk',
                'source_type' => SourceType::Forum,
                // Enabled (5 Sep 2026): verified live, scoped to Reviews/Providers/Outages.
                'crawl_policy' => ['rate_limit_per_minute' => 10],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'mediakonsumen',
                'name' => 'MediaKonsumen',
                'adapter' => 'mediakonsumen',
                'source_type' => SourceType::Rss,
                // Enabled for live operator check (5 Sep 2026): robots.txt allows
                // crawling, /feed works, fixture tests pass. Same DoD gate as
                // YouTube/LowEndTalk before them — watch the first backfill cycle.
                'crawl_policy' => ['rate_limit_per_minute' => 20],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'mojok',
                'name' => 'Mojok.co',
                'adapter' => 'mojok',
                'source_type' => SourceType::Rss,
                // Enabled for live operator check (5 Sep 2026): robots.txt allows /esai/
                // and its feed/pagination (the blanket "Disallow: /page/" rule only
                // matches root-level /page/, not /esai/page/N/); confirmed the esai
                // category feed and article pages are server-rendered, not the
                // client-side-only widget on the homepage.
                'crawl_policy' => ['rate_limit_per_minute' => 20],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
        ];

        foreach ($sources as $attributes) {
            Source::updateOrCreate(
                ['key' => $attributes['key']],
                [
                    ...$attributes,
                    'enabled' => $attributes['enabled'] ?? true,
                    'priority' => 100,
                    'health_state' => SourceHealthState::Healthy,
                ]
            );
        }
    }
}
