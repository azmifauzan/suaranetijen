<?php

namespace App\Domains\Sponsorships\Services;

use App\Models\User;
use App\Notifications\SponsorGuestAccessNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * Guest sponsor checkout (docs/26): no account is required to sponsor an entity. The order is
 * still tied to a real User row (not a separate guest table) so everything downstream — order
 * ownership checks, ban checks, the post-payment status page — works unchanged. The guest is
 * logged into the current session immediately (session survives the Sumopod checkout redirect),
 * and also emailed a signed link so they can get back in from another device or after the
 * session expires.
 */
class ResolveGuestSponsorUser
{
    public function handle(string $email, Request $request): User
    {
        $user = User::query()->where('email', $email)->first();
        $isNewUser = $user === null;

        if ($user === null) {
            $user = User::create([
                'name' => Str::before($email, '@'),
                'email' => $email,
                'password' => Str::password(32),
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($isNewUser) {
            $user->notify(new SponsorGuestAccessNotification($isNewUser));
        }

        return $user;
    }
}
