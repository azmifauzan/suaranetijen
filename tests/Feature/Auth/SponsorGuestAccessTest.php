<?php

use App\Models\User;
use Illuminate\Support\Facades\URL;

test('a valid signed link logs the user in and marks their email verified', function () {
    $user = User::factory()->create(['email_verified_at' => null]);

    $url = URL::temporarySignedRoute('sponsor.access.login', now()->addDays(7), ['user' => $user->id]);

    $this->get($url)->assertRedirect(route('sponsor.index'));

    $this->assertAuthenticatedAs($user);
    expect($user->fresh()->email_verified_at)->not->toBeNull();
});

test('an expired or tampered signed link is rejected', function () {
    $user = User::factory()->create();

    $url = URL::temporarySignedRoute('sponsor.access.login', now()->subMinute(), ['user' => $user->id]);

    $this->get($url)->assertForbidden();
    $this->assertGuest();
});
