<?php

use App\Domains\Entities\Models\Entity;
use App\Domains\Sponsorships\Enums\SponsoredEntryStatus;
use App\Domains\Sponsorships\Models\SponsoredEntry;
use App\Domains\Sponsorships\Models\SponsorPeriod;
use App\Domains\Sponsorships\Models\SponsorshipOrder;
use App\Models\User;

test('guest or non-admin cannot access admin sponsorship page', function () {
    $this->get(route('admin.sponsorship.index'))
        ->assertRedirect(route('login'));

    $user = User::factory()->create(['is_admin' => false]);
    $this->actingAs($user)
        ->get(route('admin.sponsorship.index'))
        ->assertForbidden();
});

test('admin can view sponsorship moderation dashboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $period = SponsorPeriod::factory()->create();
    $entity = Entity::factory()->create();
    $entry = SponsoredEntry::factory()->create([
        'period_id' => $period->id,
        'entity_id' => $entity->id,
        'settled_total_amount' => 50000,
    ]);
    SponsorshipOrder::factory()->create([
        'sponsored_entry_id' => $entry->id,
        'user_id' => $admin->id,
        'amount' => 50000,
    ]);

    $this->actingAs($admin)
        ->get(route('admin.sponsorship.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Sponsorship/Index')
            ->has('periods')
            ->has('entries.data', 1)
            ->has('recentOrders', 1)
        );
});

test('admin can toggle entry status between active and paused', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $entry = SponsoredEntry::factory()->create(['status' => SponsoredEntryStatus::Active]);

    $this->actingAs($admin)
        ->from('/admin/sponsorship')
        ->post(route('admin.sponsorship.entries.toggle-status', $entry))
        ->assertRedirect('/admin/sponsorship');

    $entry->refresh();
    expect($entry->status)->toBe(SponsoredEntryStatus::Paused);

    $this->actingAs($admin)
        ->from('/admin/sponsorship')
        ->post(route('admin.sponsorship.entries.toggle-status', $entry))
        ->assertRedirect('/admin/sponsorship');

    $entry->refresh();
    expect($entry->status)->toBe(SponsoredEntryStatus::Active);
});

test('admin can remove entry from leaderboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $entry = SponsoredEntry::factory()->create(['status' => SponsoredEntryStatus::Active]);

    $this->actingAs($admin)
        ->from('/admin/sponsorship')
        ->post(route('admin.sponsorship.entries.remove', $entry))
        ->assertRedirect('/admin/sponsorship');

    $entry->refresh();
    expect($entry->status)->toBe(SponsoredEntryStatus::Removed);
});
