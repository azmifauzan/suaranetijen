<?php

namespace Database\Seeders;

use App\Domains\Entities\Models\Entity;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Enums\SponsorPeriodStatus;
use App\Domains\Sponsorships\Enums\SponsorshipOrderStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorPeriod;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Domains\Sponsorships\Services\SponsorLeaderboardService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

class DummySponsorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Demo User',
            'email' => 'user@suaranetijen.id',
        ]);

        $service = app(SponsorLeaderboardService::class);
        $activePeriod = $service->getActivePeriod();

        // Previous closed period so users can test the period switcher
        $pastPeriod = SponsorPeriod::firstOrCreate(
            ['key' => '2026-w38'],
            [
                'name' => 'Minggu 38 (Sep 2026)',
                'starts_at' => CarbonImmutable::parse('2026-09-14 00:00:00'),
                'ends_at' => CarbonImmutable::parse('2026-09-20 23:59:59'),
                'status' => SponsorPeriodStatus::Closed,
            ]
        );

        $demoEntries = [
            [
                'name' => 'Apple',
                'website_url' => 'https://www.apple.com/id/',
                'amount' => 500000,
                'clicks' => 142,
                'views' => 1890,
                'days_ago' => 3,
            ],
            [
                'name' => 'Samsung',
                'website_url' => 'https://www.samsung.com/id/',
                'amount' => 350000,
                'clicks' => 98,
                'views' => 1250,
                'days_ago' => 2,
            ],
            [
                'name' => 'Xiaomi',
                'website_url' => 'https://www.mi.co.id/',
                'amount' => 250000,
                'clicks' => 76,
                'views' => 940,
                'days_ago' => 2,
            ],
            [
                'name' => 'Asus',
                'website_url' => 'https://www.asus.com/id/',
                'amount' => 150000,
                'clicks' => 45,
                'views' => 620,
                'days_ago' => 1,
            ],
            [
                'name' => 'Oppo',
                'website_url' => 'https://www.oppo.com/id/',
                'amount' => 100000,
                'clicks' => 31,
                'views' => 480,
                'days_ago' => 1,
            ],
            [
                'name' => 'Vivo',
                'website_url' => 'https://www.vivo.com/id/',
                'amount' => 75000,
                'clicks' => 22,
                'views' => 310,
                'days_ago' => 1,
            ],
            [
                'name' => 'Realme',
                'website_url' => 'https://www.realme.com/id/',
                'amount' => 50000,
                'clicks' => 15,
                'views' => 210,
                'days_ago' => 0,
            ],
        ];

        foreach ($demoEntries as $data) {
            $entity = Entity::where('name', $data['name'])->first();
            if (! $entity) {
                continue;
            }

            if (! $entity->website_url) {
                $entity->update(['website_url' => $data['website_url']]);
            }

            $settledAt = CarbonImmutable::now()->subDays($data['days_ago']);

            $entry = SponsoredEntry::updateOrCreate(
                [
                    'period_id' => $activePeriod->id,
                    'entity_id' => $entity->id,
                ],
                [
                    'settled_total_amount' => $data['amount'],
                    'first_settled_at' => $settledAt,
                    'status' => SponsoredEntryStatus::Active,
                    'clicks_count' => $data['clicks'],
                    'views_count' => $data['views'],
                ]
            );

            SponsorshipOrder::firstOrCreate(
                [
                    'sponsored_entry_id' => $entry->id,
                    'provider_order_id' => 'DEMO-ORDER-'.$entry->id.'-'.$data['days_ago'],
                ],
                [
                    'user_id' => $user->id,
                    'provider' => 'sumopod',
                    'amount' => $data['amount'],
                    'status' => SponsorshipOrderStatus::Paid,
                    'paid_at' => $settledAt,
                ]
            );
        }

        // Add 2 entries for past period so switching periods has content
        $pastEntries = [
            ['name' => 'Samsung', 'amount' => 400000, 'clicks' => 110, 'views' => 1300],
            ['name' => 'Apple', 'amount' => 300000, 'clicks' => 85, 'views' => 1100],
        ];

        foreach ($pastEntries as $data) {
            $entity = Entity::where('name', $data['name'])->first();
            if (! $entity) {
                continue;
            }

            SponsoredEntry::updateOrCreate(
                [
                    'period_id' => $pastPeriod->id,
                    'entity_id' => $entity->id,
                ],
                [
                    'settled_total_amount' => $data['amount'],
                    'first_settled_at' => CarbonImmutable::parse('2026-09-15 10:00:00'),
                    'status' => SponsoredEntryStatus::Active,
                    'clicks_count' => $data['clicks'],
                    'views_count' => $data['views'],
                ]
            );
        }
    }
}
