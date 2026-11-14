<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.guest')]
#[Title('Connexion')]
class Login extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login()
    {
        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = Str::transliterate(Str::lower($this->email).'|'.request()->ip());

        if (RateLimiter::tooManyAttempts($key, 5)) {
            event(new Lockout(request()));
            throw ValidationException::withMessages([
                'email' => 'Trop de tentatives. Réessayez dans '.RateLimiter::availableIn($key).' secondes.',
            ]);
        }

        $credentials = ['email' => $this->email, 'password' => $this->password];
        $provider = Auth::guard()->getProvider();
        // disabled_at = null : un compte désactivé ne peut pas se connecter (même message : pas d'énumération de comptes).
        $user = $provider->retrieveByCredentials(['email' => $this->email, 'disabled_at' => null]);
        if (! $user || ! $provider->validateCredentials($user, $credentials)) {
            RateLimiter::hit($key);
            throw ValidationException::withMessages(['email' => 'Identifiants incorrects, ou compte désactivé.']);
        }

        RateLimiter::clear($key);

        // Étape 9 : si la 2FA est active, la session n'est PAS ouverte avant le défi (code TOTP ou code de secours).
        if ($user->hasTwoFactorEnabled()) {
            session()->put(TwoFactorChallenge::SESSION_KEY, [
                'id' => $user->id,
                'remember' => $this->remember,
                'expires' => now()->addMinutes((int) config('security.totp.pending_login_minutes'))->timestamp,
            ]);

            return $this->redirectRoute('two-factor.challenge', navigate: false);
        }

        $provider->rehashPasswordIfRequired($user, $credentials);
        Auth::login($user, $this->remember);
        session()->regenerate();

        return $this->redirectIntended(route('dashboard'), navigate: false);
    }

    public function render()
    {
        return view('livewire.auth.login');
    }
}
