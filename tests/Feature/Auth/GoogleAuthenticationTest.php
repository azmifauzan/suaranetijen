<?php

use App\Models\User;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeGoogleUser(string $id, string $email, string $name): SocialiteUser
{
    $socialiteUser = new SocialiteUser;
    $socialiteUser->id = $id;
    $socialiteUser->email = $email;
    $socialiteUser->name = $name;

    return $socialiteUser;
}

test('redirect route sends the user to google', function () {
    Socialite::shouldReceive('driver->redirect')->once()->andReturn(redirect('https://accounts.google.com/o/oauth2/auth'));

    $response = $this->get(route('auth.google.redirect'));

    $response->assertRedirect('https://accounts.google.com/o/oauth2/auth');
});

test('callback creates a new user and logs them in', function () {
    Socialite::shouldReceive('driver->user')->andReturn(
        fakeGoogleUser('google-123', 'jane@example.com', 'Jane Doe')
    );

    $response = $this->get(route('auth.google.callback'));

    $this->assertAuthenticated();
    $response->assertRedirect(route('dashboard', absolute: false));

    $user = User::where('email', 'jane@example.com')->firstOrFail();
    expect($user->google_id)->toBe('google-123')
        ->and($user->name)->toBe('Jane Doe')
        ->and($user->email_verified_at)->not->toBeNull();
});

test('callback links an existing password account by email instead of duplicating it', function () {
    $existing = User::factory()->create(['email' => 'jane@example.com', 'google_id' => null]);

    Socialite::shouldReceive('driver->user')->andReturn(
        fakeGoogleUser('google-123', 'jane@example.com', 'Jane Doe')
    );

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($existing);
    expect(User::count())->toBe(1);
    expect($existing->fresh()->google_id)->toBe('google-123');
});

test('callback logs in an existing google user without creating a duplicate', function () {
    $existing = User::factory()->create(['google_id' => 'google-123']);

    Socialite::shouldReceive('driver->user')->andReturn(
        fakeGoogleUser('google-123', $existing->email, $existing->name)
    );

    $this->get(route('auth.google.callback'));

    $this->assertAuthenticatedAs($existing);
    expect(User::count())->toBe(1);
});

test('callback redirects back to login on an invalid state error', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new InvalidStateException);

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('callback redirects back to login when the user denies consent', function () {
    Socialite::shouldReceive('driver->user')->andThrow(new Exception('access_denied'));

    $response = $this->get(route('auth.google.callback'));

    $response->assertRedirect(route('login'));
    $response->assertSessionHasErrors('email');
    $this->assertGuest();
});

test('authenticated users cannot access the google auth routes', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('auth.google.redirect'))
        ->assertRedirect(route('dashboard', absolute: false));
});
