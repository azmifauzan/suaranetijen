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
