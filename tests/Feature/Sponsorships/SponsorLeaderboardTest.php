<?php

use App\Domains\Entities\Enums\EntityStatus;
use App\Domains\Entities\Models\Entity;
use App\Domains\Sentiment\Enums\Period;
use App\Domains\Sentiment\Models\SentimentSnapshot;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Enums\SponsorPeriodStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorPeriod;
use App\Domains\Sponsorships\Services\SponsorLeaderboardService;
use Carbon\CarbonImmutable;

test('leaderboard sorts by settled_total_amount desc, then first_settled_at asc, then id asc', function () {
    $service = app(SponsorLeaderboardService::class);
    $period = $service->getActivePeriod();

    $entityA = Entity::factory()->create(['name' => 'Entity A', 'status' => EntityStatus::Active, 'searchable' => true]);
    $entityB = Entity::factory()->create(['name' => 'Entity B', 'status' => EntityStatus::Active, 'searchable' => true]);
    $entityC = Entity::factory()->create(['name' => 'Entity C', 'status' => EntityStatus::Active, 'searchable' => true]);
    $entityD = Entity::factory()->create(['name' => 'Entity D', 'status' => EntityStatus::Active, 'searchable' => true]);

    $t1 = CarbonImmutable::parse('2026-09-01 10:00:00');
    $t2 = CarbonImmutable::parse('2026-09-01 12:00:00');

    // Entry 1: 100k, t2 -> should be #2
    $entry1 = SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entityA->id,
        'settled_total_amount' => 100000,
        'first_settled_at' => $t2,
        'status' => SponsoredEntryStatus::Active,
    ]);

    // Entry 2: 250k, t2 -> should be #1 (highest amount)
    $entry2 = SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entityB->id,
        'settled_total_amount' => 250000,
        'first_settled_at' => $t2,
        'status' => SponsoredEntryStatus::Active,
    ]);

    // Entry 3: 100k, t1 -> should be #3 (same amount as Entry 1, but earlier settled date t1 < t2, so it actually beats Entry 1!)
    // Wait: t1 < t2 means Entry 3 is earlier, so Entry 3 (t1) is #2, and Entry 1 (t2) is #3!
    $entry3 = SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entityC->id,
        'settled_total_amount' => 100000,
        'first_settled_at' => $t1,
        'status' => SponsoredEntryStatus::Active,
    ]);

    // Entry 4: 50k -> should be #4
    $entry4 = SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entityD->id,
        'settled_total_amount' => 50000,
        'first_settled_at' => $t1,
        'status' => SponsoredEntryStatus::Active,
    ]);

    $leaderboard = $service->getLeaderboard($period);

    expect($leaderboard)->toHaveCount(4);
    expect($leaderboard[0]['entity_id'])->toBe($entityB->id); // 250k
    expect($leaderboard[0]['rank'])->toBe(1);

    expect($leaderboard[1]['entity_id'])->toBe($entityC->id); // 100k at t1 (earlier than t2)
    expect($leaderboard[1]['rank'])->toBe(2);

    expect($leaderboard[2]['entity_id'])->toBe($entityA->id); // 100k at t2
    expect($leaderboard[2]['rank'])->toBe(3);

    expect($leaderboard[3]['entity_id'])->toBe($entityD->id); // 50k
    expect($leaderboard[3]['rank'])->toBe(4);
});

test('leaderboard excludes zero settled amounts and paused or removed entries', function () {
    $service = app(SponsorLeaderboardService::class);
    $period = $service->getActivePeriod();

    $activeEntity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);
    $pausedEntity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);
    $zeroEntity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $activeEntity->id,
        'settled_total_amount' => 50000,
        'status' => SponsoredEntryStatus::Active,
    ]);

    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $pausedEntity->id,
        'settled_total_amount' => 100000,
        'status' => SponsoredEntryStatus::Paused,
    ]);

    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $zeroEntity->id,
        'settled_total_amount' => 0,
        'status' => SponsoredEntryStatus::Active,
    ]);

    $leaderboard = $service->getLeaderboard($period);

    expect($leaderboard)->toHaveCount(1);
    expect($leaderboard[0]['entity_id'])->toBe($activeEntity->id);
});

test('public page /sponsor returns 200 with inertia props', function () {
    $service = app(SponsorLeaderboardService::class);
    $period = $service->getActivePeriod();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 75000,
        'status' => SponsoredEntryStatus::Active,
    ]);

    $this->get(route('sponsor.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sponsor/Index')
            ->has('leaderboard', 1)
            ->has('periods')
            ->has('activePeriod')
            ->where('minAmount', 1000)
        );
});

test('api /api/sponsor/leaderboard returns ranked list in json', function () {
    $service = app(SponsorLeaderboardService::class);
    $period = $service->getActivePeriod();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 120000,
        'status' => SponsoredEntryStatus::Active,
    ]);

    $this->getJson(route('api.sponsor.leaderboard'))
        ->assertOk()
        ->assertJsonPath('period.key', $period->key)
        ->assertJsonPath('data.0.name', $entity->name)
        ->assertJsonPath('data.0.settled_total_amount', 120000)
        ->assertJsonPath('data.0.rank', 1);
});

test('homepage includes sponsor teaser with active board data', function () {
    $service = app(SponsorLeaderboardService::class);
    $period = $service->getActivePeriod();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 80000,
        'status' => SponsoredEntryStatus::Active,
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Welcome')
            ->has('sponsorTeaser')
            ->where('sponsorTeaser.top_entry.name', $entity->name)
            ->where('sponsorTeaser.total_settled_amount', 80000)
        );
});

test('all-time leaderboard sums an entity\'s settled amount across multiple periods', function () {
    $service = app(SponsorLeaderboardService::class);
    $currentPeriod = $service->getActivePeriod();
    $pastPeriod = SponsorPeriod::factory()->create([
        'key' => 'past-period',
        'status' => SponsorPeriodStatus::Closed,
        'starts_at' => CarbonImmutable::parse('2026-08-01'),
        'ends_at' => CarbonImmutable::parse('2026-08-07'),
    ]);

    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);
    $otherEntity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    SponsoredEntry::factory()->create([
        'period_id' => $pastPeriod->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 200000,
        'first_settled_at' => CarbonImmutable::parse('2026-08-02'),
        'status' => SponsoredEntryStatus::Active,
    ]);
    SponsoredEntry::factory()->create([
        'period_id' => $currentPeriod->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 150000,
        'first_settled_at' => CarbonImmutable::now(),
        'status' => SponsoredEntryStatus::Active,
    ]);

    // Only ever sponsored once, in the current period — should rank below the two-period total.
    SponsoredEntry::factory()->create([
        'period_id' => $currentPeriod->id,
        'entity_id' => $otherEntity->id,
        'settled_total_amount' => 300000,
        'first_settled_at' => CarbonImmutable::now(),
        'status' => SponsoredEntryStatus::Paused, // paused -> excluded even from all-time
    ]);

    $allTime = $service->getAllTimeLeaderboard();

    expect($allTime)->toHaveCount(1);
    expect($allTime[0]['entity_id'])->toBe($entity->id);
    expect($allTime[0]['settled_total_amount'])->toBe(350000);
    expect($allTime[0]['first_settled_at'])->toStartWith('2026-08-02');
});

test('api /api/sponsor/leaderboard?period=all returns the all-time archive board', function () {
    $service = app(SponsorLeaderboardService::class);
    $period = $service->getActivePeriod();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 90000,
        'status' => SponsoredEntryStatus::Active,
    ]);

    $this->getJson(route('api.sponsor.leaderboard', ['period' => 'all']))
        ->assertOk()
        ->assertJsonPath('period.key', 'all')
        ->assertJsonPath('period.is_active', false)
        ->assertJsonPath('data.0.entity_id', $entity->id)
        ->assertJsonPath('data.0.settled_total_amount', 90000);
});

test('sponsor board page period selector includes the all-time entry', function () {
    $this->get(route('sponsor.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sponsor/Index')
            ->where('periods.0.key', 'all')
            ->where('periods.0.name', 'Semua Waktu')
        );
});

test('sponsor board page ?period=all renders the archive board without crashing', function () {
    $service = app(SponsorLeaderboardService::class);
    $period = $service->getActivePeriod();
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 60000,
        'status' => SponsoredEntryStatus::Active,
    ]);

    $this->get(route('sponsor.index', ['period' => 'all']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Sponsor/Index')
            ->where('selectedPeriod.key', 'all')
            ->has('leaderboard', 1)
        );
});

test('sponsorship contributions never alter sentiment snapshots or rating snapshots', function () {
    $entity = Entity::factory()->create(['status' => EntityStatus::Active, 'searchable' => true]);

    // Organic snapshot with score 65
    SentimentSnapshot::create([
        'entity_id' => $entity->id,
        'period' => Period::OneYear->value,
        'score' => 65.0,
        'positive_count' => 13,
        'neutral_count' => 0,
        'negative_count' => 7,
        'opinion_count' => 20,
        'sentiment_model_version' => 'test-v1',
        'score_formula_version' => 'v1',
        'calculated_at' => now(),
    ]);

    $service = app(SponsorLeaderboardService::class);
    $period = $service->getActivePeriod();

    // Large sponsor payment
    SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 50000000, // Rp50.000.000
        'status' => SponsoredEntryStatus::Active,
    ]);

    $snapshot = SentimentSnapshot::where('entity_id', $entity->id)->first();
    expect((float) $snapshot->score)->toBe(65.0);
    expect($snapshot->opinion_count)->toBe(20);
});
