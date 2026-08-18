<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Exceptions\GoogleAccountAlreadyLinkedException;
use App\Http\Controllers\Controller;
use App\Services\GoogleAccountService;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class GoogleController extends Controller
{
    public function __construct(
        private readonly GoogleAccountService $accounts,
    ) {}

    public function redirect(): RedirectResponse
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            if (! $googleUser instanceof AbstractUser) {
                throw new \UnexpectedValueException('Google returned an unsupported user payload.');
            }

            if (! $this->hasVerifiedEmail($googleUser)) {
                return redirect()->route('login')->with(
                    'error',
                    'Google did not provide a verified email address. Please use another sign-in method.',
                );
            }

            $user = $this->accounts->resolve(
                (string) $googleUser->getId(),
                (string) $googleUser->getEmail(),
                (string) $googleUser->getName(),
                $googleUser->getAvatar(),
            );

            Auth::login($user);

            request()->session()->regenerate();

            request()->session()->flash('analytics_event', [
                'name' => $user->wasRecentlyCreated ? 'sign_up' : 'login',
                'method' => 'google',
            ]);

            return redirect()->intended(route('inbox', absolute: false));
        } catch (GoogleAccountAlreadyLinkedException) {
            return redirect()
                ->route('login')
                ->with('error', 'This Purplelist account is already linked to another Google account.');
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('login')
                ->with('error', 'Unable to sign in with Google. Please try again.');
        }
    }

    private function hasVerifiedEmail(AbstractUser $googleUser): bool
    {
        $raw = $googleUser->getRaw();

        return filled($googleUser->getEmail())
            && filter_var($raw['email_verified'] ?? $raw['verified_email'] ?? false, FILTER_VALIDATE_BOOL);
    }
}
