<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirectResponse;
use Throwable;

class GoogleAuthController extends Controller
{
    /**
     * Redirect the user to Google's OAuth consent screen.
     */
    public function redirect(): SymfonyRedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle the callback from Google and log the user in.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            // Covers an expired/invalid OAuth state and a denied consent grant alike —
            // both surface here as a failed token/user exchange, not a distinguishable error.
            return redirect()->route('login')->withErrors([
                'email' => 'Gagal masuk dengan Google. Silakan coba lagi.',
            ]);
        }

        $user = User::query()->where('google_id', $googleUser->getId())->first()
            ?? User::query()->where('email', $googleUser->getEmail())->first();

        if ($user === null) {
            $user = User::create([
                'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: $googleUser->getEmail(),
                'email' => $googleUser->getEmail(),
                'google_id' => $googleUser->getId(),
                'email_verified_at' => now(),
                'password' => Str::password(32),
            ]);
        } elseif ($user->google_id === null) {
            $user->forceFill(['google_id' => $googleUser->getId()])->save();
        }

        Auth::login($user, remember: true);

        request()->session()->regenerate();

        return redirect()->intended(config('fortify.home'));
    }
}
