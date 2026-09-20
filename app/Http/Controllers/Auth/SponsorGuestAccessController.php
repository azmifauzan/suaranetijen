<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SponsorGuestAccessController extends Controller
{
    /**
     * Log a guest sponsor back in via the signed link from SponsorGuestAccessNotification.
     * Signature validity (including expiry) is enforced by the `signed` route middleware.
     */
    public function login(Request $request, User $user): RedirectResponse
    {
        Auth::login($user, remember: true);
        $request->session()->regenerate();

        if ($user->email_verified_at === null) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        return redirect()->intended(route('sponsor.index'));
    }
}
