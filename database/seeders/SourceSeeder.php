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
                'enabled' => false,
                'crawl_policy' => ['rate_limit_per_minute' => 30],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'kaskus',
                'name' => 'KASKUS',
                'adapter' => 'kaskus',
                'source_type' => SourceType::Forum,
                // Disabled: confirmed live (2026-09-04) that kaskus.co.id's search page is a
                // Next.js app whose results load client-side only (SSR payload ships with an
                // empty `fallback` cache) — a plain HTML GET returns zero thread links every
                // time, robots.txt/preflight notwithstanding. Needs the underlying JSON API
                // (if one is public) or a JS-rendering fetcher before re-enabling.
                'enabled' => false,
                'crawl_policy' => ['rate_limit_per_minute' => 10],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'lowendtalk',
                'name' => 'LowEndTalk',
                'adapter' => 'lowendtalk',
                'source_type' => SourceType::Forum,
                'enabled' => false,
                'crawl_policy' => ['rate_limit_per_minute' => 10],
                'retention_policy' => ['raw_ttl_hours' => 72],
            ],
            [
                'key' => 'mediakonsumen',
                'name' => 'MediaKonsumen',
                'adapter' => 'mediakonsumen',
                'source_type' => SourceType::Rss,
                // Disabled pending a live operator check (same DoD as every other wave
                // before it is turned on for backfill). robots.txt allows crawling and
                // /feed works (confirmed 5 Sep 2026), but this adapter has not yet run
                // against real production traffic.
                'enabled' => false,
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
