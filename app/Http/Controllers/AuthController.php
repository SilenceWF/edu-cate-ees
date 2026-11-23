<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Audit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Throwable;

class AuthController extends Controller
{
    public function show()
    {
        return view('auth.login', [
            'localAuth' => config('portal.local_auth'),
            'microsoft' => filled(config('services.microsoft.client_id')),
        ]);
    }

    public function login(Request $request)
    {
        abort_unless(config('portal.local_auth'), 404);

        $data = $request->validate(['email' => 'required|email', 'password' => 'required|string']);
        $key = 'login:'.Str::lower($data['email']).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages(['email' => __('ui.auth.throttled', ['seconds' => RateLimiter::availableIn($key)])]);
        }

        if (! Auth::attempt(['email' => Str::lower($data['email']), 'password' => $data['password'], 'is_active' => true], true)) {
            RateLimiter::hit($key, 300);
            Audit::log('auth.login_failed', null, ['email' => Str::lower($data['email'])]);
            throw ValidationException::withMessages(['email' => __('ui.auth.failed')]);
        }

        RateLimiter::clear($key);

        return $this->signedIn($request, 'local');
    }

    public function redirectToMicrosoft()
    {
        abort_unless(filled(config('services.microsoft.client_id')), 404);

        return Socialite::driver('microsoft')->redirect();
    }

    public function microsoftCallback(Request $request)
    {
        abort_unless(filled(config('services.microsoft.client_id')), 404);

        try {
            $ms = Socialite::driver('microsoft')->user();
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')->withErrors(['email' => __('ui.auth.sso_failed')]);
        }

        $email = Str::lower((string) ($ms->getEmail() ?: ($ms->user['userPrincipalName'] ?? '')));
        $user = User::where('microsoft_id', $ms->getId())->first()
            ?? ($email ? User::where('email', $email)->first() : null);

        if (! $user || ! $user->is_active) {
            Audit::log('auth.sso_rejected', null, ['email' => $email]);

            return redirect()->route('login')->withErrors(['email' => __('ui.auth.not_in_roster', ['email' => $email])]);
        }

        $user->forceFill(['microsoft_id' => $ms->getId()])->save();
        Auth::login($user, true);

        return $this->signedIn($request, 'microsoft');
    }

    public function logout(Request $request)
    {
        Audit::log('auth.logout', $request->user());
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    public function locale(Request $request)
    {
        $data = $request->validate(['locale' => 'required|string|max:5']);
        if (in_array($data['locale'], ui_locales(), true)) {
            $request->session()->put('locale', $data['locale']);
            $request->user()?->forceFill(['locale' => $data['locale']])->save();
        }

        return back();
    }

    private function signedIn(Request $request, string $method)
    {
        $request->session()->regenerate();
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();
        if ($user->locale) {
            $request->session()->put('locale', $user->locale);
        }
        Audit::log('auth.login', $user, ['method' => $method]);

        return redirect()->intended(route('home'));
    }
}
