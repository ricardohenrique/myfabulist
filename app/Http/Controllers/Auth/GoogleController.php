<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class GoogleController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            // First try to find an already-linked Google account.
            $user = User::where('google_id', $googleUser->getId())->first();

            if (! $user) {
                // Check whether this email already belongs to a Purplelist user.
                $user = User::where('email', $googleUser->getEmail())->first();

                if ($user) {
                    // Link Google to the existing Purplelist account.
                    $user->update([
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar(),
                    ]);
                } else {
                    // Completely new user.
                    $user = User::create([
                        'name' => $googleUser->getName(),
                        'email' => $googleUser->getEmail(),
                        'google_id' => $googleUser->getId(),
                        'avatar' => $googleUser->getAvatar(),
                        'email_verified_at' => now(),
                    ]);
                }
            }

            Auth::login($user, true);

            request()->session()->regenerate();

            return redirect()->intended('/dashboard');

        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('login')
                ->with('error', 'Unable to sign in with Google. Please try again.');
        }
    }
}
