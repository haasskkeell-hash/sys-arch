<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Fortify\Events\TwoFactorAuthenticationChallenged;
use Laravel\Fortify\Fortify;
use Throwable;

class GoogleAuthController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        if ($this->isNotConfigured()) {
            return redirect()->route('login')->withErrors([
                'email' => __('Google sign-in is not configured yet.'),
            ]);
        }

        $state = Str::random(64);

        $request->session()->put('google_oauth_state', $state);

        $query = http_build_query([
            'client_id' => config('services.google.client_id'),
            'redirect_uri' => config('services.google.redirect'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'prompt' => 'select_account',
        ]);

        return redirect()->away('https://accounts.google.com/o/oauth2/v2/auth?'.$query);
    }

    public function callback(Request $request): RedirectResponse
    {
        if ($request->filled('error')) {
            $request->session()->forget('google_oauth_state');

            return redirect()->route('login')->withErrors([
                'email' => __('Google sign-in was cancelled.'),
            ]);
        }

        $expectedState = (string) $request->session()->pull('google_oauth_state');
        $receivedState = (string) $request->query('state');

        if ($expectedState === '' || $receivedState === '' || ! hash_equals($expectedState, $receivedState)) {
            return redirect()->route('login')->withErrors([
                'email' => __('Google sign-in expired. Please try again.'),
            ]);
        }

        if (! $request->filled('code') || $this->isNotConfigured()) {
            return redirect()->route('login')->withErrors([
                'email' => __('Google sign-in could not be completed.'),
            ]);
        }

        try {
            $tokenResponse = Http::asForm()
                ->connectTimeout(3)
                ->timeout(10)
                ->retry(2, 200, throw: false)
                ->post('https://oauth2.googleapis.com/token', [
                    'code' => $request->string('code')->toString(),
                    'client_id' => config('services.google.client_id'),
                    'client_secret' => config('services.google.client_secret'),
                    'redirect_uri' => config('services.google.redirect'),
                    'grant_type' => 'authorization_code',
                ]);

            $tokenResponse->throw();

            $accessToken = $tokenResponse->json('access_token');

            if (! is_string($accessToken) || $accessToken === '') {
                throw new \RuntimeException('Google did not return an access token.');
            }

            $profileResponse = Http::withToken($accessToken)
                ->connectTimeout(3)
                ->timeout(10)
                ->retry(2, 200, throw: false)
                ->get('https://openidconnect.googleapis.com/v1/userinfo');

            $profileResponse->throw();

            $profile = $profileResponse->json();
            $googleId = (string) data_get($profile, 'sub');
            $email = Str::lower((string) data_get($profile, 'email'));
            $emailVerified = filter_var(data_get($profile, 'email_verified'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($googleId === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || $emailVerified !== true) {
                throw new \RuntimeException('Google returned an incomplete or unverified profile.');
            }

            $user = User::query()->where('google_id', $googleId)->first();

            if (! $user) {
                $user = User::query()->where('email', $email)->first();
            }

            if (! $user) {
                return redirect()->route('register')->withInput([
                    'name' => data_get($profile, 'name'),
                    'email' => $email,
                ])->withErrors([
                    'email' => __('No FixTrack account was found for this Google email. Please register first.'),
                ]);
            }

            if ($user->google_id !== null && ! hash_equals((string) $user->google_id, $googleId)) {
                return redirect()->route('login')->withErrors([
                    'email' => __('This Google account is linked to a different FixTrack account.'),
                ]);
            }

            $user->forceFill([
                'google_id' => $googleId,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            if (Fortify::confirmsTwoFactorAuthentication() && $user->two_factor_secret && $user->two_factor_confirmed_at) {
                $request->session()->put([
                    'login.id' => $user->getKey(),
                    'login.remember' => true,
                ]);

                TwoFactorAuthenticationChallenged::dispatch($user);

                return redirect()->route('two-factor.login');
            }

            Auth::login($user, remember: true);
            $request->session()->regenerate();

            return redirect()->intended(route('dashboard'));
        } catch (Throwable $exception) {
            Log::warning('Google sign-in failed.', [
                'message' => $exception->getMessage(),
            ]);

            return redirect()->route('login')->withErrors([
                'email' => __('Google sign-in could not be completed. Please try again.'),
            ]);
        }
    }

    private function isNotConfigured(): bool
    {
        return blank(config('services.google.client_id'))
            || blank(config('services.google.client_secret'))
            || blank(config('services.google.redirect'));
    }
}
