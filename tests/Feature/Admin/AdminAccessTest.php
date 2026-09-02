<?php

use App\Models\User;
use Illuminate\Support\Facades\Gate;

test('unauthenticated users cannot access admin dashboard', function () {
    $this->get('/admin')
        ->assertRedirect('/login');
});

test('non-admin users receive 403 forbidden on admin dashboard', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/admin')
        ->assertForbidden();
});

test('admin privileges cannot be mass assigned', function () {
    $user = User::factory()->make();

    $user->fill(['is_admin' => true]);

    expect($user->isAdmin())->toBeFalse();
});

test('only admin users may view Horizon', function () {
    $admin = User::factory()->admin()->make();
    $user = User::factory()->make();

    expect(Gate::forUser($admin)->allows('viewHorizon'))->toBeTrue()
        ->and(Gate::forUser($user)->allows('viewHorizon'))->toBeFalse();
});

test('admin users can access admin dashboard', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Admin/Dashboard')
            ->has('stats.total_entities')
            ->has('stats.total_categories')
            ->has('stats.total_aliases')
        );
});
